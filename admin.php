<?php
require_once __DIR__ . '/storage.php';
session_start();

// Check status login admin
if (!isset($_SESSION['is_admin_logged_in']) || $_SESSION['is_admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Fitur Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: login.php');
    exit;
}

$dataFile = __DIR__ . '/data/portal_data.json';
$uploadDir = __DIR__ . '/images/';

$dataLock = null;
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') $dataLock = lockPortalData($dataFile);
    $data = readPortalData($dataFile);
} catch (RuntimeException $e) {
    unlockPortalData($dataLock);
    http_response_code(503);
    exit(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$message = $_SESSION['portal_flash']['message'] ?? '';
$messageType = $_SESSION['portal_flash']['type'] ?? '';
unset($_SESSION['portal_flash']);
$uploadedImages = [];

$availableIcons = [
    'bi-laptop' => 'Laptop',
    'bi-book-half' => 'Buku',
    'bi-journal-bookmark-fill' => 'Jurnal',
    'bi-person-badge' => 'Profil',
    'bi-person-vcard' => 'Identitas',
    'bi-mortarboard-fill' => 'Wisuda',
    'bi-building' => 'Gedung',
    'bi-clipboard-data-fill' => 'Data',
    'bi-card-checklist' => 'Absensi',
    'bi-display' => 'Monitor',
    'bi-globe' => 'Website',
    'bi-envelope-at-fill' => 'Email',
    'bi-cash-coin' => 'Keuangan',
    'bi-file-earmark-text' => 'Berkas',
    'bi-heart-pulse-fill' => 'Kesehatan',
    'bi-calendar-event' => 'Agenda',
    'bi-chat-dots-fill' => 'Chat',
    'bi-link-45deg' => 'Link'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        foreach ($_POST as $value) {
            if (!is_string($value)) throw new RuntimeException('Isian formulir tidak valid.');
        }
        $action = $_POST['action'] ?? '';

        $allowedActions = ['save_settings', 'add_layanan', 'edit_layanan', 'delete_layanan', 'add_aplikasi', 'edit_aplikasi', 'delete_aplikasi', 'add_pengumuman', 'edit_pengumuman', 'delete_pengumuman'];
        if (!in_array($action, $allowedActions, true)) throw new RuntimeException('Formulir tidak lengkap atau ukuran unggahan melebihi batas server.');
        foreach (['index_layanan', 'index_aplikasi', 'index_pengumuman'] as $key) {
            if (isset($_POST[$key]) && filter_var($_POST[$key], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
                throw new RuntimeException('Indeks data tidak valid. Muat ulang halaman admin.');
            }
        }
        $requiredIndices = [];
        if (in_array($action, ['edit_layanan', 'delete_layanan', 'add_aplikasi', 'edit_aplikasi', 'delete_aplikasi'], true)) $requiredIndices[] = 'index_layanan';
        if (in_array($action, ['edit_aplikasi', 'delete_aplikasi'], true)) $requiredIndices[] = 'index_aplikasi';
        if (in_array($action, ['edit_pengumuman', 'delete_pengumuman'], true)) $requiredIndices[] = 'index_pengumuman';
        foreach ($requiredIndices as $key) {
            if (!isset($_POST[$key])) throw new RuntimeException('Indeks data tidak tersedia. Muat ulang halaman admin.');
        }
        if (isset($_POST['index_layanan']) && !isset($data['kategori_layanan'][(int)$_POST['index_layanan']])) throw new RuntimeException('Kategori tidak ditemukan.');
        if (isset($_POST['index_aplikasi']) && !isset($data['kategori_layanan'][(int)($_POST['index_layanan'] ?? -1)]['aplikasi'][(int)$_POST['index_aplikasi']])) throw new RuntimeException('Aplikasi tidak ditemukan.');
        if (isset($_POST['index_pengumuman']) && !isset($data['pengumuman_list'][(int)$_POST['index_pengumuman']])) throw new RuntimeException('Pengumuman tidak ditemukan.');
        foreach (['nama_kategori', 'nama', 'judul', 'isi'] as $field) {
            $needed = ($field === 'nama_kategori' && in_array($action, ['add_layanan', 'edit_layanan'], true))
                || ($field === 'nama' && in_array($action, ['add_aplikasi', 'edit_aplikasi'], true))
                || (in_array($field, ['judul', 'isi'], true) && in_array($action, ['add_pengumuman', 'edit_pengumuman'], true));
            if ($needed && trim($_POST[$field] ?? '') === '') throw new RuntimeException('Isian wajib belum lengkap.');
        }
        if (isset($_POST['link'])) {
            $link = trim($_POST['link']);
            if ($link === '#') $link = '';
            if ($link !== '' && !validPortalUrl($link)) throw new RuntimeException('Tautan harus berupa URL http:// atau https:// yang valid.');
            $_POST['link'] = $link;
        }
        if (in_array($action, ['add_pengumuman', 'edit_pengumuman'], true)) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $_POST['tanggal'] ?? '');
            if (!$date || $date->format('Y-m-d') !== $_POST['tanggal']) throw new RuntimeException('Tanggal pengumuman tidak valid.');
        }

        // --- KELOLA PENGATURAN UMUM & LOGO & BACKGROUND ---
        if ($action === 'save_settings') {
            $data['settings']['address'] = trim($_POST['address'] ?? '');
            $data['settings']['phone'] = trim($_POST['phone'] ?? '');
            $data['settings']['hero_pill'] = trim($_POST['hero_pill'] ?? '');
            $data['settings']['hero_title'] = trim($_POST['hero_title'] ?? '');
            $data['settings']['hero_desc'] = trim($_POST['hero_desc'] ?? '');
            $data['settings']['wa_number'] = preg_replace('/[^0-9]/', '', $_POST['wa_number'] ?? '');
            $data['settings']['wa_default_msg'] = trim($_POST['wa_default_msg'] ?? '');
            $data['settings']['hero_height'] = trim($_POST['hero_height'] ?? '450');

            $height = filter_var($data['settings']['hero_height'], FILTER_VALIDATE_INT);
            if ($height === false || $height < 200 || $height > 800) throw new RuntimeException('Tinggi banner harus 200 sampai 800 piksel.');
            foreach (['logo_poltekkes', 'logo_blu', 'logo_smart', 'bg_hero'] as $key) {
                $imagePath = uploadPortalImage($key, $uploadDir);
                if ($imagePath !== null) {
                    $uploadedImages[] = __DIR__ . '/' . $imagePath;
                    $data['settings'][$key] = $imagePath;
                }
            }

            if (saveJSON($dataFile, $data)) {
                $message = 'Pengaturan umum, logo, & background berhasil diperbarui!';
                $messageType = 'success';
            }
        }

        // --- KELOLA LAYANAN ---
        if ($action === 'add_layanan') {
            $data['kategori_layanan'][] = [
                'nama_kategori' => trim($_POST['nama_kategori'] ?? ''),
                'deskripsi'     => trim($_POST['deskripsi'] ?? ''),
                'icon_kategori' => trim($_POST['icon_kategori'] ?? 'bi-folder-fill'),
                'icon_color'    => $_POST['icon_color'] ?? '#198754',
                'aplikasi'      => []
            ];
            if (saveJSON($dataFile, $data)) {
                $message = 'Layanan baru berhasil dibuat!';
                $messageType = 'success';
            }
        }

        if ($action === 'edit_layanan') {
            $idxLayanan = (int)$_POST['index_layanan'];
            if (isset($data['kategori_layanan'][$idxLayanan])) {
                $data['kategori_layanan'][$idxLayanan]['nama_kategori'] = trim($_POST['nama_kategori'] ?? '');
                $data['kategori_layanan'][$idxLayanan]['deskripsi']     = trim($_POST['deskripsi'] ?? '');
                $data['kategori_layanan'][$idxLayanan]['icon_kategori'] = trim($_POST['icon_kategori'] ?? 'bi-folder-fill');
                $data['kategori_layanan'][$idxLayanan]['icon_color']    = $_POST['icon_color'] ?? '#198754';

                if (saveJSON($dataFile, $data)) {
                    $message = 'Data Layanan berhasil diperbarui!';
                    $messageType = 'success';
                }
            }
        }

        if ($action === 'delete_layanan') {
            $idxLayanan = (int)$_POST['index_layanan'];
            if (isset($data['kategori_layanan'][$idxLayanan])) {
                array_splice($data['kategori_layanan'], $idxLayanan, 1);
                if (saveJSON($dataFile, $data)) {
                    $message = 'Layanan berhasil dihapus!';
                    $messageType = 'warning';
                }
            }
        }

        // --- KELOLA APLIKASI ---
        if ($action === 'add_aplikasi') {
            $idxLayanan = (int)$_POST['index_layanan'];
            if (isset($data['kategori_layanan'][$idxLayanan])) {
                $data['kategori_layanan'][$idxLayanan]['aplikasi'][] = [
                    'nama'       => trim($_POST['nama'] ?? ''),
                    'deskripsi'  => trim($_POST['deskripsi'] ?? ''),
                    'link'       => trim($_POST['link'] ?? '#'),
                    'icon'       => trim($_POST['icon'] ?? 'bi-link-45deg'),
                    'icon_color' => $_POST['icon_color'] ?? '#198754',
                    'badge'      => trim($_POST['badge'] ?? ''),
                    'views'      => 0
                ];

                if (saveJSON($dataFile, $data)) {
                    $message = 'Aplikasi baru berhasil ditambahkan!';
                    $messageType = 'success';
                }
            }
        }

        if ($action === 'edit_aplikasi') {
            $idxLayanan  = (int)$_POST['index_layanan'];
            $idxAplikasi = (int)$_POST['index_aplikasi'];

            if (isset($data['kategori_layanan'][$idxLayanan]['aplikasi'][$idxAplikasi])) {
                $views = $data['kategori_layanan'][$idxLayanan]['aplikasi'][$idxAplikasi]['views'] ?? 0;
                $data['kategori_layanan'][$idxLayanan]['aplikasi'][$idxAplikasi] = [
                    'nama'       => trim($_POST['nama'] ?? ''),
                    'deskripsi'  => trim($_POST['deskripsi'] ?? ''),
                    'link'       => trim($_POST['link'] ?? '#'),
                    'icon'       => trim($_POST['icon'] ?? 'bi-link-45deg'),
                    'icon_color' => $_POST['icon_color'] ?? '#198754',
                    'badge'      => trim($_POST['badge'] ?? ''),
                    'views'      => $views
                ];

                if (saveJSON($dataFile, $data)) {
                    $message = 'Data aplikasi berhasil diperbarui!';
                    $messageType = 'success';
                }
            }
        }

        if ($action === 'delete_aplikasi') {
            $idxLayanan  = (int)$_POST['index_layanan'];
            $idxAplikasi = (int)$_POST['index_aplikasi'];

            if (isset($data['kategori_layanan'][$idxLayanan]['aplikasi'][$idxAplikasi])) {
                array_splice($data['kategori_layanan'][$idxLayanan]['aplikasi'], $idxAplikasi, 1);
                if (saveJSON($dataFile, $data)) {
                    $message = 'Aplikasi berhasil dihapus!';
                    $messageType = 'warning';
                }
            }
        }

        // --- KELOLA PENGUMUMAN ---
        if ($action === 'add_pengumuman') {
            $newPengumuman = [
                'judul'    => trim($_POST['judul'] ?? ''),
                'kategori' => trim($_POST['kategori'] ?? 'Umum'),
                'tanggal'  => $_POST['tanggal'] ?? date('Y-m-d'),
                'isi'      => trim($_POST['isi'] ?? ''),
                'link'     => trim($_POST['link'] ?? '#')
            ];

            array_unshift($data['pengumuman_list'], $newPengumuman);
            if (saveJSON($dataFile, $data)) {
                $message = 'Pengumuman berhasil dipublikasikan!';
                $messageType = 'success';
            }
        }

        if ($action === 'edit_pengumuman') {
            $index = (int)$_POST['index_pengumuman'];
            if (isset($data['pengumuman_list'][$index])) {
                $data['pengumuman_list'][$index] = [
                    'judul'    => trim($_POST['judul'] ?? ''),
                    'kategori' => trim($_POST['kategori'] ?? 'Umum'),
                    'tanggal'  => $_POST['tanggal'] ?? date('Y-m-d'),
                    'isi'      => trim($_POST['isi'] ?? ''),
                    'link'     => trim($_POST['link'] ?? '#')
                ];

                if (saveJSON($dataFile, $data)) {
                    $message = 'Pengumuman berhasil diperbarui!';
                    $messageType = 'success';
                }
            }
        }

        if ($action === 'delete_pengumuman') {
            $index = (int)$_POST['index_pengumuman'];
            if (isset($data['pengumuman_list'][$index])) {
                array_splice($data['pengumuman_list'], $index, 1);
                if (saveJSON($dataFile, $data)) {
                    $message = 'Pengumuman berhasil dihapus!';
                    $messageType = 'warning';
                }
            }
        }
        if ($message === '') throw new RuntimeException('Perubahan tidak dapat diproses. Muat ulang halaman admin.');
        $_SESSION['portal_flash'] = ['message' => $message, 'type' => $messageType];
    } catch (RuntimeException $e) {
        foreach ($uploadedImages as $path) { if (is_file($path)) @unlink($path); }
        $_SESSION['portal_flash'] = ['message' => $e->getMessage(), 'type' => 'danger'];
    } finally {
        unlockPortalData($dataLock);
    }
    header('Location: admin.php', true, 303);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Admin — Kelola Layanan & Konten</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg bg-white border-bottom border-4 border-warning shadow-sm py-2">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center gap-2 text-dark" href="admin.php">
                <span class="text-success fw-bold fs-5">Admin Panel Portal</span>
            </a>
            <div class="d-flex gap-2">
                <a href="index.php" class="btn btn-outline-success btn-sm rounded-pill px-3 fw-semibold">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Lihat Halaman Depan
                </a>
                <a href="admin.php?action=logout" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-semibold" onclick="return confirm('Yakin ingin keluar?');">
                    <i class="bi bi-box-arrow-right me-1"></i> Keluar
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4" style="max-width: 1100px;">

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show rounded-3 shadow-sm" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- KONTROL PENGATURAN UMUM & LOGO -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-gear-fill text-primary me-2"></i>Pengaturan Halaman Depan & Logo</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_settings">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Alamat Kantor (Top Bar)</label>
                            <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($data['settings']['address'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Telepon Kontak / Tampilan Top Bar</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($data['settings']['phone'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Label Pill Banner</label>
                            <input type="text" name="hero_pill" class="form-control" value="<?php echo htmlspecialchars($data['settings']['hero_pill'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Judul Banner Utama (Hero)</label>
                            <input type="text" name="hero_title" class="form-control" value="<?php echo htmlspecialchars($data['settings']['hero_title'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Tinggi Banner Hero (Pixel)</label>
                            <div class="input-group">
                                <input type="number" name="hero_height" class="form-control" value="<?php echo htmlspecialchars($data['settings']['hero_height'] ?? '450'); ?>" min="200" max="800" placeholder="450" required>
                                <span class="input-group-text">px</span>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Deskripsi Banner Utama</label>
                            <textarea name="hero_desc" class="form-control" rows="2" required><?php echo htmlspecialchars($data['settings']['hero_desc'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nomor WhatsApp Admin (Format: 628xxx)</label>
                            <input type="text" name="wa_number" class="form-control" value="<?php echo htmlspecialchars($data['settings']['wa_number'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Pesan Default WhatsApp</label>
                            <input type="text" name="wa_default_msg" class="form-control" value="<?php echo htmlspecialchars($data['settings']['wa_default_msg'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <hr>
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-image me-2"></i>Kelola Foto / Logo Header & Background</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold d-block">Logo Utama (Poltekkes)</label>
                            <?php if (!empty($data['settings']['logo_poltekkes'])): ?>
                                <img src="<?php echo htmlspecialchars($data['settings']['logo_poltekkes']); ?>" class="img-thumbnail mb-2 d-block" style="height: 50px;">
                            <?php endif; ?>
                            <input type="file" name="logo_poltekkes" class="form-control form-control-sm" accept="image/*">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold d-block">Logo Pendukung 1 (BLU SPEED)</label>
                            <?php if (!empty($data['settings']['logo_blu'])): ?>
                                <img src="<?php echo htmlspecialchars($data['settings']['logo_blu']); ?>" class="img-thumbnail mb-2 d-block" style="height: 50px;">
                            <?php endif; ?>
                            <input type="file" name="logo_blu" class="form-control form-control-sm" accept="image/*">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold d-block">Logo Pendukung 2 (Smart Campus)</label>
                            <?php if (!empty($data['settings']['logo_smart'])): ?>
                                <img src="<?php echo htmlspecialchars($data['settings']['logo_smart']); ?>" class="img-thumbnail mb-2 d-block" style="height: 50px;">
                            <?php endif; ?>
                            <input type="file" name="logo_smart" class="form-control form-control-sm" accept="image/*">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold d-block">Foto Background Kampus (Hero)</label>
                            <?php if (!empty($data['settings']['bg_hero'])): ?>
                                <img src="<?php echo htmlspecialchars($data['settings']['bg_hero']); ?>" class="img-thumbnail mb-2 d-block" style="height: 50px; object-fit: cover;">
                            <?php endif; ?>
                            <input type="file" name="bg_hero" class="form-control form-control-sm" accept="image/*">
                        </div>
                    </div>

                    <div class="text-end mt-3">
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold"><i class="bi bi-save me-1"></i> Simpan Pengaturan & Logo</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MANAJEMEN LAYANAN & APLIKASI -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold text-dark mb-0"><i class="bi bi-grid-fill text-success me-2"></i>Kelola Layanan & Aplikasi</h4>
                <small class="text-muted">Buat layanan utama, lalu tambahkan aplikasi-aplikasi di dalamnya.</small>
            </div>
            <button class="btn btn-success rounded-pill px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalTambahLayanan">
                <i class="bi bi-plus-lg me-1"></i> Buat Layanan Baru
            </button>
        </div>

        <?php if (empty($data['kategori_layanan'])): ?>
            <div class="card border-0 shadow-sm p-5 text-center my-4">
                <i class="bi bi-folder-plus text-muted fs-1 mb-2"></i>
                <h5 class="fw-bold text-secondary">Belum Ada Layanan</h5>
                <p class="text-muted small">Klik tombol <strong>"Buat Layanan Baru"</strong> di atas untuk memulai membuat grup layanan.</p>
            </div>
        <?php else: ?>
            <?php foreach ($data['kategori_layanan'] as $lIdx => $layanan): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 rounded-3 d-flex align-items-center justify-content-center" style="background-color: <?php echo htmlspecialchars($layanan['icon_color'] ?? '#198754'); ?>20;">
                                <i class="bi <?php echo htmlspecialchars($layanan['icon_kategori'] ?? 'bi-folder-fill'); ?> fs-5" style="color: <?php echo htmlspecialchars($layanan['icon_color'] ?? '#198754'); ?>;"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($layanan['nama_kategori']); ?></h5>
                                <small class="text-muted"><?php echo htmlspecialchars($layanan['deskripsi'] ?? 'Tidak ada deskripsi'); ?></small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-outline-success btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalTambahAplikasi<?php echo $lIdx; ?>">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Aplikasi
                            </button>
                            <button class="btn btn-outline-primary btn-sm rounded-circle" data-bs-toggle="modal" data-bs-target="#modalEditLayanan<?php echo $lIdx; ?>" title="Edit Layanan">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus layanan ini beserta seluruh aplikasi di dalamnya?');">
                                <input type="hidden" name="action" value="delete_layanan">
                                <input type="hidden" name="index_layanan" value="<?php echo $lIdx; ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm rounded-circle" title="Hapus Layanan">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small text-secondary">
                                    <tr>
                                        <th class="ps-4" style="width: 30%;">Ikon & Nama Aplikasi</th>
                                        <th style="width: 30%;">Deskripsi</th>
                                        <th style="width: 25%;">Link Tautan</th>
                                        <th class="text-end pe-4" style="width: 15%;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($layanan['aplikasi'])): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3 small">
                                                Belum ada aplikasi. Klik <strong>+ Tambah Aplikasi</strong> di atas.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($layanan['aplikasi'] as $aIdx => $app): ?>
                                            <tr>
                                                <td class="ps-4">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="icon-preview-box" style="background-color: <?php echo htmlspecialchars($app['icon_color'] ?? '#198754'); ?>18;">
                                                            <i class="bi <?php echo htmlspecialchars($app['icon'] ?? 'bi-link-45deg'); ?> fs-5" style="color: <?php echo htmlspecialchars($app['icon_color'] ?? '#198754'); ?>;"></i>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold text-dark small"><?php echo htmlspecialchars($app['nama']); ?></div>
                                                            <?php if (!empty($app['badge'])): ?>
                                                                <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 10px;"><?php echo htmlspecialchars($app['badge']); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><small class="text-secondary"><?php echo htmlspecialchars($app['deskripsi']); ?></small></td>
                                                <td>
                                                    <a href="<?php echo htmlspecialchars($app['link']); ?>" target="_blank" class="small text-decoration-none text-truncate d-inline-block" style="max-width: 200px;">
                                                        <i class="bi bi-box-arrow-up-right me-1"></i><?php echo htmlspecialchars($app['link']); ?>
                                                    </a>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <div class="d-flex align-items-center justify-content-end gap-1">
                                                        <button class="btn btn-outline-primary btn-sm rounded-2 py-0 px-2" data-bs-toggle="modal" data-bs-target="#modalEditAplikasi_<?php echo $lIdx; ?>_<?php echo $aIdx; ?>">
                                                            <i class="bi bi-pencil"></i> Edit
                                                        </button>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Hapus aplikasi ini?');">
                                                            <input type="hidden" name="action" value="delete_aplikasi">
                                                            <input type="hidden" name="index_layanan" value="<?php echo $lIdx; ?>">
                                                            <input type="hidden" name="index_aplikasi" value="<?php echo $aIdx; ?>">
                                                            <button type="submit" class="btn btn-outline-danger btn-sm rounded-2 py-0 px-2">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>

                                                    <!-- MODAL EDIT APLIKASI -->
                                                    <div class="modal fade text-start" id="modalEditAplikasi_<?php echo $lIdx; ?>_<?php echo $aIdx; ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog modal-dialog-centered modal-lg">
                                                            <div class="modal-content border-0 rounded-4 shadow">
                                                                <form method="POST">
                                                                    <input type="hidden" name="action" value="edit_aplikasi">
                                                                    <input type="hidden" name="index_layanan" value="<?php echo $lIdx; ?>">
                                                                    <input type="hidden" name="index_aplikasi" value="<?php echo $aIdx; ?>">
                                                                    <div class="modal-header border-bottom py-3">
                                                                        <h5 class="modal-title fw-bold">Edit Aplikasi: <?php echo htmlspecialchars($app['nama']); ?></h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body p-4">
                                                                        <div class="mb-3">
                                                                            <label class="form-label small fw-semibold">Nama Aplikasi</label>
                                                                            <input type="text" name="nama" class="form-control" value="<?php echo htmlspecialchars($app['nama']); ?>" required>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label small fw-semibold">Deskripsi</label>
                                                                            <textarea name="deskripsi" class="form-control" rows="2"><?php echo htmlspecialchars($app['deskripsi']); ?></textarea>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label small fw-semibold text-success">URL Tautan Aplikasi (Opsional)</label>
                                                                            <input type="url" name="link" class="form-control" value="<?php echo htmlspecialchars($app['link']); ?>" placeholder="https://... (kosongkan jika belum tersedia)">
                                                                        </div>
                                                                        <div class="row g-3 mb-3">
                                                                            <div class="col-md-4">
                                                                                <label class="form-label small fw-semibold">Warna Ikon</label>
                                                                                <input type="color" id="iconColorEdit_<?php echo $lIdx; ?>_<?php echo $aIdx; ?>" name="icon_color" class="form-control form-control-color w-100" value="<?php echo htmlspecialchars($app['icon_color'] ?? '#198754'); ?>" onchange="updateIconPreview('iconInputEdit_<?php echo $lIdx; ?>_<?php echo $aIdx; ?>', 'previewEdit_<?php echo $lIdx; ?>_<?php echo $aIdx; ?>', this.value)">
                                                                            </div>
                                                                            <div class="col-md-8">
                                                                                <label class="form-label small fw-semibold mb-1">Kode Class Ikon Bootstrap</label>
                                                                                <div class="input-group">
                                                                                    <span class="input-group-text bg-white" id="previewEdit_<?php echo $lIdx; ?>_<?php echo $aIdx; ?>">
                                                                                        <i class="bi <?php echo htmlspecialchars($app['icon'] ?? 'bi-link-45deg'); ?> fs-5" style="color: <?php echo htmlspecialchars($app['icon_color'] ?? '#198754'); ?>;"></i>
                                                                                    </span>
                                                                                    <input type="text" id="iconInputEdit_<?php echo $lIdx; ?>_<?php echo $aIdx; ?>" name="icon" class="form-control" value="<?php echo htmlspecialchars($app['icon'] ?? 'bi-link-45deg'); ?>" oninput="updateIconPreview('iconInputEdit_<?php echo $lIdx; ?>_<?php echo $aIdx; ?>', 'previewEdit_<?php echo $lIdx; ?>_<?php echo $aIdx; ?>', document.getElementById('iconColorEdit_<?php echo $lIdx; ?>_<?php echo $aIdx; ?>').value)" required>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label small fw-semibold">Label Badge (Opsional)</label>
                                                                            <input type="text" name="badge" class="form-control" value="<?php echo htmlspecialchars($app['badge'] ?? ''); ?>">
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer border-top py-2">
                                                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                                                        <button type="submit" class="btn btn-success px-4">Simpan Perubahan</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>

                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- MODAL EDIT LAYANAN -->
                <div class="modal fade" id="modalEditLayanan<?php echo $lIdx; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content border-0 rounded-4 shadow">
                            <form method="POST">
                                <input type="hidden" name="action" value="edit_layanan">
                                <input type="hidden" name="index_layanan" value="<?php echo $lIdx; ?>">
                                <div class="modal-header border-bottom py-3">
                                    <h5 class="modal-title fw-bold">Edit Layanan: <?php echo htmlspecialchars($layanan['nama_kategori']); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-4">
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Nama Layanan / Kategori</label>
                                        <input type="text" name="nama_kategori" class="form-control" value="<?php echo htmlspecialchars($layanan['nama_kategori']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Deskripsi Layanan</label>
                                        <textarea name="deskripsi" class="form-control" rows="2"><?php echo htmlspecialchars($layanan['deskripsi'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label small fw-semibold">Warna Ikon Layanan</label>
                                            <input type="color" id="iconColorEditLayanan_<?php echo $lIdx; ?>" name="icon_color" class="form-control form-control-color w-100" value="<?php echo htmlspecialchars($layanan['icon_color'] ?? '#198754'); ?>" onchange="updateIconPreview('iconInputEditLayanan_<?php echo $lIdx; ?>', 'previewEditLayanan_<?php echo $lIdx; ?>', this.value)">
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label small fw-semibold mb-1">Kode Class Ikon Bootstrap</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-white" id="previewEditLayanan_<?php echo $lIdx; ?>">
                                                    <i class="bi <?php echo htmlspecialchars($layanan['icon_kategori'] ?? 'bi-folder-fill'); ?> fs-5" style="color: <?php echo htmlspecialchars($layanan['icon_color'] ?? '#198754'); ?>;"></i>
                                                </span>
                                                <input type="text" id="iconInputEditLayanan_<?php echo $lIdx; ?>" name="icon_kategori" class="form-control" value="<?php echo htmlspecialchars($layanan['icon_kategori'] ?? 'bi-folder-fill'); ?>" oninput="updateIconPreview('iconInputEditLayanan_<?php echo $lIdx; ?>', 'previewEditLayanan_<?php echo $lIdx; ?>', document.getElementById('iconColorEditLayanan_<?php echo $lIdx; ?>').value)" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer border-top py-2">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-success px-4">Simpan Perubahan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- MODAL TAMBAH APLIKASI -->
                <div class="modal fade" id="modalTambahAplikasi<?php echo $lIdx; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content border-0 rounded-4 shadow">
                            <form method="POST">
                                <input type="hidden" name="action" value="add_aplikasi">
                                <input type="hidden" name="index_layanan" value="<?php echo $lIdx; ?>">
                                <div class="modal-header border-bottom py-3">
                                    <h5 class="modal-title fw-bold">Tambah Aplikasi ke: <span class="text-success"><?php echo htmlspecialchars($layanan['nama_kategori']); ?></span></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-4">
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Nama Aplikasi</label>
                                        <input type="text" name="nama" class="form-control" placeholder="Contoh: SIAKAD Mahasiswa" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Deskripsi Singkat</label>
                                        <textarea name="deskripsi" class="form-control" rows="2" placeholder="Layanan entri KRS dan lihat KHS"></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold text-success">URL / Link Tautan Aplikasi</label>
                                        <input type="url" name="link" class="form-control" placeholder="https://siakad.domain.id (opsional)">
                                    </div>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label small fw-semibold">Warna Ikon</label>
                                            <input type="color" id="iconColorAddApp_<?php echo $lIdx; ?>" name="icon_color" class="form-control form-control-color w-100" value="#198754" onchange="updateIconPreview('iconInputAddApp_<?php echo $lIdx; ?>', 'previewAddApp_<?php echo $lIdx; ?>', this.value)">
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label small fw-semibold mb-1">Kode Class Ikon Bootstrap</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-white" id="previewAddApp_<?php echo $lIdx; ?>">
                                                    <i class="bi bi-link-45deg fs-5" style="color: #198754;"></i>
                                                </span>
                                                <input type="text" id="iconInputAddApp_<?php echo $lIdx; ?>" name="icon" class="form-control" value="bi-link-45deg" oninput="updateIconPreview('iconInputAddApp_<?php echo $lIdx; ?>', 'previewAddApp_<?php echo $lIdx; ?>', document.getElementById('iconColorAddApp_<?php echo $lIdx; ?>').value)" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Label Badge (Opsional)</label>
                                        <input type="text" name="badge" class="form-control" placeholder="Baru / Penting">
                                    </div>
                                </div>
                                <div class="modal-footer border-top py-2">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-success px-4">Simpan Aplikasi</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>
        <?php endif; ?>

        <!-- MANAJEMEN PENGUMUMAN -->
        <div class="card border-0 shadow-sm mt-5">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-megaphone-fill text-warning"></i> Kelola Pengumuman & Berita
                </h5>
                <button class="btn btn-warning btn-sm rounded-pill px-3 fw-semibold text-dark" data-bs-toggle="modal" data-bs-target="#modalTambahPengumuman">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Pengumuman Baru
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-secondary">
                            <tr>
                                <th class="ps-4">Judul & Isi Informasi</th>
                                <th>Kategori</th>
                                <th>Tanggal Publikasi</th>
                                <th class="text-end pe-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($data['pengumuman_list'])): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">Belum ada pengumuman publik.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($data['pengumuman_list'] as $pIdx => $pItem): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($pItem['judul']); ?></div>
                                            <small class="text-muted text-truncate d-block" style="max-width: 450px;"><?php echo htmlspecialchars($pItem['isi']); ?></small>
                                        </td>
                                        <td><span class="badge bg-warning-subtle text-dark border border-warning-subtle"><?php echo htmlspecialchars($pItem['kategori'] ?? 'Umum'); ?></span></td>
                                        <td><small class="text-muted"><?php echo htmlspecialchars($pItem['tanggal'] ?? '-'); ?></small></td>
                                        <td class="text-end pe-4">
                                            <div class="d-flex align-items-center justify-content-end gap-1">
                                                <button class="btn btn-outline-primary btn-sm rounded-2 py-1 px-2" data-bs-toggle="modal" data-bs-target="#modalEditPengumuman<?php echo $pIdx; ?>">
                                                    <i class="bi bi-pencil-square me-1"></i> Ubah
                                                </button>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Hapus pengumuman ini?');">
                                                    <input type="hidden" name="action" value="delete_pengumuman">
                                                    <input type="hidden" name="index_pengumuman" value="<?php echo $pIdx; ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm rounded-2 py-1 px-2">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>

                                            <!-- MODAL EDIT PENGUMUMAN -->
                                            <div class="modal fade text-start" id="modalEditPengumuman<?php echo $pIdx; ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                                    <div class="modal-content border-0 rounded-4 shadow">
                                                        <form method="POST">
                                                            <input type="hidden" name="action" value="edit_pengumuman">
                                                            <input type="hidden" name="index_pengumuman" value="<?php echo $pIdx; ?>">
                                                            <div class="modal-header border-bottom py-3">
                                                                <h5 class="modal-title fw-bold">Edit Berita: <?php echo htmlspecialchars($pItem['judul']); ?></h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body p-4">
                                                                <div class="mb-3">
                                                                    <label class="form-label small fw-semibold">Judul Pengumuman / Berita</label>
                                                                    <input type="text" name="judul" class="form-control" value="<?php echo htmlspecialchars($pItem['judul']); ?>" required>
                                                                </div>
                                                                <div class="row g-2 mb-3">
                                                                    <div class="col-md-6">
                                                                        <label class="form-label small fw-semibold">Kategori</label>
                                                                        <input type="text" name="kategori" class="form-control" value="<?php echo htmlspecialchars($pItem['kategori'] ?? 'Umum'); ?>">
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label class="form-label small fw-semibold">Tanggal Publikasi</label>
                                                                        <input type="date" name="tanggal" class="form-control" value="<?php echo htmlspecialchars($pItem['tanggal'] ?? date('Y-m-d')); ?>" required>
                                                                    </div>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label small fw-semibold">Isi Informasi</label>
                                                                    <textarea name="isi" class="form-control" rows="4" required><?php echo htmlspecialchars($pItem['isi']); ?></textarea>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label small fw-semibold">Link Lampiran / Detail (Opsional)</label>
                                                                    <input type="url" name="link" class="form-control" value="<?php echo htmlspecialchars(($pItem['link'] ?? '') === '#' ? '' : ($pItem['link'] ?? '')); ?>">
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer border-top py-2">
                                                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                                                <button type="submit" class="btn btn-warning px-4 text-dark fw-semibold">Simpan Perubahan</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>

                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- MODAL BUAT LAYANAN BARU -->
    <div class="modal fade" id="modalTambahLayanan" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 rounded-4 shadow">
                <form method="POST">
                    <input type="hidden" name="action" value="add_layanan">
                    <div class="modal-header border-bottom py-3">
                        <h5 class="modal-title fw-bold">Buat Layanan / Grup Baru</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Nama Layanan / Kategori</label>
                            <input type="text" name="nama_kategori" class="form-control" placeholder="Contoh: Layanan Akademik" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Deskripsi Layanan</label>
                            <textarea name="deskripsi" class="form-control" rows="2" placeholder="Kumpulan aplikasi pendukung akademik civitas akademika"></textarea>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Warna Ikon Layanan</label>
                                <input type="color" id="iconColorAddLayanan" name="icon_color" class="form-control form-control-color w-100" value="#198754" onchange="updateIconPreview('iconInputAddLayanan', 'previewAddLayanan', this.value)">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small fw-semibold mb-1">Kode Class Ikon Bootstrap</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white" id="previewAddLayanan">
                                        <i class="bi bi-folder-fill fs-5" style="color: #198754;"></i>
                                    </span>
                                    <input type="text" id="iconInputAddLayanan" name="icon_kategori" class="form-control" value="bi-folder-fill" oninput="updateIconPreview('iconInputAddLayanan', 'previewAddLayanan', document.getElementById('iconColorAddLayanan').value)" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top py-2">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success px-4">Buat Layanan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL TAMBAH PENGUMUMAN -->
    <div class="modal fade" id="modalTambahPengumuman" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 rounded-4 shadow">
                <form method="POST">
                    <input type="hidden" name="action" value="add_pengumuman">
                    <div class="modal-header border-bottom py-3">
                        <h5 class="modal-title fw-bold">Tambah Pengumuman / Berita Baru</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Judul Pengumuman / Berita</label>
                            <input type="text" name="judul" class="form-control" placeholder="Judul Pengumuman" required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Kategori</label>
                                <input type="text" name="kategori" class="form-control" value="Umum">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Tanggal Publikasi</label>
                                <input type="date" name="tanggal" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Isi Informasi</label>
                            <textarea name="isi" class="form-control" rows="4" placeholder="Tulis isi pengumuman..." required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Link Lampiran / Detail (Opsional)</label>
                            <input type="url" name="link" class="form-control" value="" placeholder="https://... (opsional)">
                        </div>
                    </div>
                    <div class="modal-footer border-top py-2">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning px-4 text-dark fw-semibold">Publikasikan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Modal di dalam tabel responsif harus berada di body agar tidak terpotong.
        document.querySelectorAll('.modal').forEach(modal => document.body.appendChild(modal));
        function updateIconPreview(inputId, previewId, colorValue) {
            const iconClass = document.getElementById(inputId).value || 'bi-link-45deg';
            const previewBox = document.getElementById(previewId);
            const color = colorValue || '#198754';
            const icon = document.createElement('i');
            icon.className = 'bi ' + iconClass + ' fs-5';
            icon.style.color = color;
            previewBox.replaceChildren(icon);
        }
    </script>
</body>
</html>
