<?php
date_default_timezone_set('Asia/Makassar');

function portalDefaults() {
    return [
        'address' => 'Jl. R.W. Monginsidi Malalayang II, Kota Manado, Sulawesi Utara 95263',
        'phone' => '62 895-0425-2514',
        'hero_pill' => 'POLKESDO Health Portal',
        'hero_title' => 'Portal Poltekkes Kemenkes Manado',
        'hero_desc' => 'Portal layanan digital terpadu untuk mahasiswa, dosen, dan staf poltekkes kesehatan di manado.',
        'wa_number' => '6289504252514',
        'wa_default_msg' => 'Halo Admin Portal Poltekkes Manado, saya butuh bantuan.',
        'logo_poltekkes' => 'images/Logo Poltekkes.jpeg',
        'logo_blu' => 'images/Logo Blu.png',
        'logo_smart' => 'images/Logo Smart.png',
        'bg_hero' => '',
        'hero_height' => '450',
    ];
}

function readPortalData($filePath) {
    if (!is_file($filePath)) {
        throw new RuntimeException('File data portal tidak ditemukan. Pulihkan data/portal_data.json dari cadangan.');
    }
    $json = @file_get_contents($filePath);
    $data = $json === false ? null : json_decode($json, true);
    if (!is_array($data) || !isset($data['kategori_layanan'], $data['pengumuman_list'])
        || !is_array($data['kategori_layanan']) || !is_array($data['pengumuman_list'])
        || (isset($data['settings']) && !is_array($data['settings']))) {
        throw new RuntimeException('Data portal tidak dapat dibaca atau format JSON rusak. Pulihkan file dari cadangan; data tidak ditimpa.');
    }
    $data['settings'] = array_replace(portalDefaults(), $data['settings'] ?? []);
    foreach ($data['kategori_layanan'] as &$category) {
        if (!is_array($category) || !isset($category['aplikasi']) || !is_array($category['aplikasi'])) {
            throw new RuntimeException('Format kategori layanan tidak valid. Data tidak ditimpa.');
        }
        $category['deskripsi'] = $category['deskripsi'] ?? $category['deskripsi_kategori'] ?? '';
        foreach ($category['aplikasi'] as &$app) {
            if (!is_array($app)) throw new RuntimeException('Format aplikasi tidak valid. Data tidak ditimpa.');
            $app['link'] = $app['link'] ?? $app['url'] ?? '';
            if ($app['link'] === '#') $app['link'] = '';
        }
        unset($app);
    }
    unset($category);
    return $data;
}

// Semua penulis memegang kunci ini sejak membaca hingga selesai menyimpan.
function lockPortalData($filePath) {
    $handle = @fopen($filePath . '.lock', 'c');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) fclose($handle);
        throw new RuntimeException('Data tidak dapat dikunci. Periksa izin tulis folder data.');
    }
    return $handle;
}

function unlockPortalData($handle) {
    if (is_resource($handle)) {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

// Panggil hanya saat memegang lockPortalData(). File lama tetap utuh jika gagal.
function saveJSON($filePath, $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) throw new RuntimeException('Data mengandung teks yang tidak valid dan belum disimpan.');
    $temp = @tempnam(dirname($filePath), 'portal_');
    if ($temp === false) throw new RuntimeException('Tidak dapat membuat file sementara. Periksa izin folder data.');
    try {
        if (@file_put_contents($temp, $json) !== strlen($json) || !@rename($temp, $filePath)) {
            throw new RuntimeException('Penyimpanan gagal. Periksa izin tulis dan ruang penyimpanan.');
        }
    } finally {
        if (is_file($temp)) @unlink($temp);
    }
    return true;
}

function validPortalUrl($url) {
    return is_string($url) && !preg_match('/[\x00-\x20\x7f]/', $url)
        && filter_var($url, FILTER_VALIDATE_URL)
        && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true);
}

function uploadPortalImage($key, $uploadDir) {
    if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $file = $_FILES[$key];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Unggah gambar gagal. Periksa ukuran berkas dan batas unggahan PHP.');
    }
    $info = @getimagesize($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!$info || !isset($extensions[$info['mime']]) || $file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Gunakan gambar JPG, PNG, GIF, atau WebP berukuran maksimal 5 MB.');
    }
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true)) {
        throw new RuntimeException('Folder images tidak dapat dibuat.');
    }
    $name = $key . '_' . bin2hex(random_bytes(8)) . '.' . $extensions[$info['mime']];
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $name)) {
        throw new RuntimeException('Gambar gagal disimpan. Periksa izin tulis folder images.');
    }
    return 'images/' . $name;
}
