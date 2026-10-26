<?php
require_once __DIR__ . '/storage.php';
$dataFile = __DIR__ . '/data/portal_data.json';
$catIndex = filter_var($_GET['cat'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
$appIndex = filter_var($_GET['app'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
$targetUrl = 'index.php';
$lock = null;

if ($catIndex !== false && $appIndex !== false) {
    try {
        $lock = lockPortalData($dataFile);
        $data = readPortalData($dataFile);
        if (isset($data['kategori_layanan'][$catIndex]['aplikasi'][$appIndex])) {
            $app = &$data['kategori_layanan'][$catIndex]['aplikasi'][$appIndex];
            if (validPortalUrl($app['link'] ?? '')) {
                $targetUrl = $app['link'];
                $app['views'] = max(0, (int)($app['views'] ?? 0)) + 1;
                saveJSON($dataFile, $data);
            }
        }
    } catch (RuntimeException $e) {
        error_log('Portal click: ' . $e->getMessage());
        // Kegagalan pencatatan statistik tidak memblokir tautan layanan.
        if ($targetUrl === 'index.php') {
            try {
                $data = readPortalData($dataFile);
                $url = $data['kategori_layanan'][$catIndex]['aplikasi'][$appIndex]['link'] ?? '';
                if (validPortalUrl($url)) $targetUrl = $url;
            } catch (RuntimeException $ignored) {
                // Data rusak/tidak tersedia: kembali ke halaman utama.
            }
        }
    } finally {
        unlockPortalData($lock);
    }
}

header('Location: ' . $targetUrl);
exit;
