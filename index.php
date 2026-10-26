<?php
require_once __DIR__ . '/storage.php';
$dataFile = __DIR__ . '/data/portal_data.json';
try {
    $data = readPortalData($dataFile);
} catch (RuntimeException $e) {
    http_response_code(503);
    exit('Data portal sementara tidak tersedia. Hubungi administrator.');
}
$settings = $data['settings'];

$kategoriList = $data['kategori_layanan'] ?? [];
$pengumumanList = $data['pengumuman_list'] ?? [];

// Penanganan style background hero & height secara dinamis
$heroHeightVal = !empty($settings['hero_height']) ? (int)$settings['hero_height'] . 'px' : '450px';
$stylesArr = ["min-height: {$heroHeightVal}"];

if (!empty($settings['bg_hero']) && file_exists(__DIR__ . '/' . $settings['bg_hero'])) {
    $stylesArr[] = "background: linear-gradient(rgba(0, 0, 0, 0.65), rgba(0, 0, 0, 0.65)), url('" . htmlspecialchars($settings['bg_hero']) . "') center/cover no-repeat";
}

$heroStyleAttr = 'style="' . implode('; ', $stylesArr) . ';"';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($settings['hero_title']); ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- Top Address Bar -->
    <div class="top-bar py-2 border-bottom border-secondary border-opacity-25">
        <div class="container d-flex justify-content-between align-items-center">
            <div class="small">
                <i class="bi bi-geo-alt-fill text-warning me-1"></i> <?php echo htmlspecialchars($settings['address']); ?>
            </div>
            <div class="d-flex align-items-center gap-3 small">
                <span><i class="bi bi-telephone-fill me-1"></i> <?php echo htmlspecialchars($settings['phone']); ?></span>
                <a href="admin.php" class="btn btn-warning btn-sm py-0 px-2 text-dark fw-bold" style="font-size: 0.75rem;"><i class="bi bi-lock-fill"></i> Panel Admin</a>
            </div>
        </div>
    </div>

    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top shadow-sm py-1">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2 p-0" href="#">
                <?php if (!empty($settings['logo_poltekkes'])): ?>
                    <img src="<?php echo htmlspecialchars($settings['logo_poltekkes']); ?>" alt="Poltekkes Manado" class="brand-logo-full">
                <?php endif; ?>
                <div class="logo-divider"></div>
                <?php if (!empty($settings['logo_blu'])): ?>
                    <img src="<?php echo htmlspecialchars($settings['logo_blu']); ?>" alt="BLU SPEED" class="brand-logo-blu">
                <?php endif; ?>
                <?php if (!empty($settings['logo_smart'])): ?>
                    <img src="<?php echo htmlspecialchars($settings['logo_smart']); ?>" alt="Smart Campus" class="brand-logo-smart">
                <?php endif; ?>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto fw-semibold">
                    <li class="nav-item"><a class="nav-link active" href="#">Beranda</a></li>
                    <li class="nav-item"><a class="nav-link" href="#layanan">Layanan Digital</a></li>
                    <li class="nav-item"><a class="nav-link" href="#pengumuman">Pengumuman</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section (dengan Dynamic Background & Height) -->
    <section class="hero-banner text-center py-5 bg-dark text-white d-flex align-items-center justify-content-center" <?php echo $heroStyleAttr; ?>>
        <div class="container">
            <span class="pill-tag fw-semibold mb-3 d-inline-block"><i class="bi bi-check-circle-fill text-info me-1"></i> <?php echo htmlspecialchars($settings['hero_pill']); ?></span>
            <h1 class="display-5 fw-bold mb-3"><?php echo htmlspecialchars($settings['hero_title']); ?></h1>
            <p class="lead text-white fw-semibold mx-auto mb-4 text-center" style="max-width: 680px; text-shadow: 0 1px 3px rgba(0,0,0,0.5);">
                <?php echo htmlspecialchars($settings['hero_desc']); ?>
            </p>
            <div class="d-flex justify-content-center gap-2">
                <a href="#layanan" class="btn btn-warning fw-bold px-4 py-2 text-dark shadow-sm">Akses Layanan</a>
                <a href="#pengumuman" class="btn btn-outline-light px-4 py-2 fw-semibold">Info & Pengumuman</a>
            </div>
        </div>
    </section>

    <!-- Section Pengumuman & Acara -->
    <section id="pengumuman" class="py-5 bg-white border-bottom">
        <div class="container" style="max-width: 1140px;">
            <div class="d-flex justify-content-between align-items-end mb-4">
                <div>
                    <h4 class="fw-bold text-dark d-flex align-items-center gap-2 mb-1">
                        <i class="bi bi-megaphone-fill text-warning"></i> Pengumuman & Agenda Acara
                    </h4>
                    <p class="text-muted small mb-0">Informasi terbaru seputar kegiatan akademik dan berita kampus.</p>
                </div>
            </div>

            <?php if (empty($pengumumanList)): ?>
                <div class="alert alert-light border text-center text-muted py-4">
                    Belum ada pengumuman atau informasi acara terbaru.
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($pengumumanList as $info): ?>
                        <div class="col-md-6">
                            <div class="card-pengumuman p-4 border rounded-4 bg-white h-100 d-flex flex-column justify-content-between shadow-sm">
                                <div>
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="badge bg-warning bg-opacity-25 text-dark fw-bold px-2 py-1" style="font-size: 0.725rem;">
                                            <i class="bi bi-tag-fill me-1"></i><?php echo htmlspecialchars($info['kategori'] ?? 'Umum'); ?>
                                        </span>
                                        <small class="text-muted" style="font-size: 0.75rem;">
                                            <i class="bi bi-calendar3 me-1"></i><?php echo date('d M Y', strtotime($info['tanggal'] ?? date('Y-m-d'))); ?>
                                        </small>
                                    </div>
                                    <h5 class="fw-bold text-dark fs-6 mb-2">
                                        <?php echo htmlspecialchars($info['judul'] ?? ''); ?>
                                    </h5>
                                    <p class="text-secondary small mb-3">
                                        <?php echo htmlspecialchars($info['isi'] ?? ''); ?>
                                    </p>
                                </div>
                                <?php if (!empty($info['link']) && $info['link'] !== '#'): ?>
                                    <div>
                                        <a href="<?php echo htmlspecialchars($info['link']); ?>" target="_blank" class="text-decoration-none fw-bold small text-success">
                                            Baca Selengkapnya <i class="bi bi-arrow-right"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Section Layanan Digital Terpadu -->
    <section id="layanan" class="py-5 bg-light">
        <div class="container" style="max-width: 1140px;">

            <div class="mb-5 text-center">
                <h3 class="fw-bold text-dark mb-1">
                    <i class="bi bi-grid-fill text-success me-2"></i>Layanan Digital Terpadu
                </h3>
                <p class="text-muted small mb-0">Pilih kategori layanan di bawah ini untuk melihat daftar aplikasi yang tersedia.</p>
            </div>

            <?php if (!empty($kategoriList)): ?>
                <?php foreach ($kategoriList as $catIndex => $kategori): ?>

                    <div class="category-header-bar rounded-3 p-3 mb-4 shadow-sm d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="category-icon-box rounded-2 d-flex align-items-center justify-content-center" style="background-color: <?php echo htmlspecialchars($kategori['icon_color'] ?? '#198754'); ?>;">
                                <i class="bi <?php echo htmlspecialchars($kategori['icon_kategori'] ?? 'bi-folder-fill'); ?> fs-4 text-white"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold text-white mb-0"><?php echo htmlspecialchars($kategori['nama_kategori'] ?? 'Kategori'); ?></h5>
                                <small class="text-white-50"><?php echo htmlspecialchars($kategori['deskripsi'] ?? ''); ?></small>
                            </div>
                        </div>
                        <span class="badge bg-white text-dark fw-bold px-3 py-2 rounded-pill d-none d-md-inline-block">
                            <?php echo count($kategori['aplikasi'] ?? []); ?> Aplikasi
                        </span>
                    </div>

                    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-3 mb-5">
                        <?php if (!empty($kategori['aplikasi'])): ?>
                            <?php foreach ($kategori['aplikasi'] as $appIndex => $item): ?>
                                <?php
                                    $appAvailable = validPortalUrl($item['link'] ?? '');
                                    $rawViews = isset($item['views']) ? (int)$item['views'] : 0;
                                    $viewsCount = number_format($rawViews, 0, ',', '.');
                                ?>
                                <div class="col">
                                    <a <?php if ($appAvailable): ?>href="click.php?cat=<?php echo $catIndex; ?>&app=<?php echo $appIndex; ?>"<?php else: ?>aria-disabled="true"<?php endif; ?>
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="app-card-link text-decoration-none">

                                        <div class="app-card h-100 p-3 rounded-3 bg-white border d-flex flex-column justify-content-between shadow-sm">
                                            <div>
                                                <div class="d-flex align-items-center justify-content-between mb-3">
                                                    <div class="app-icon-wrapper rounded-3 d-flex align-items-center justify-content-center"
                                                         style="background-color: <?php echo htmlspecialchars($item['icon_color'] ?? '#198754'); ?>15;">
                                                        <i class="bi <?php echo htmlspecialchars($item['icon'] ?? 'bi-link-45deg'); ?> fs-4"
                                                           style="color: <?php echo htmlspecialchars($item['icon_color'] ?? '#198754'); ?>;"></i>
                                                    </div>
                                                    <?php if (!empty($item['badge'])): ?>
                                                        <span class="badge bg-light text-secondary border small fw-normal">
                                                            <?php echo htmlspecialchars($item['badge']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>

                                                <h6 class="fw-bold text-dark mb-2 app-title">
                                                    <?php echo htmlspecialchars($item['nama'] ?? ''); ?>
                                                </h6>

                                                <p class="text-secondary small mb-0 app-desc" style="font-size: 0.8rem;">
                                                    <?php echo htmlspecialchars($item['deskripsi'] ?? ''); ?>
                                                </p>
                                            </div>

                                            <div class="pt-3 mt-3 border-top border-light d-flex align-items-center justify-content-between text-success fw-semibold small app-action">
                                                <span class="text-muted fw-normal" style="font-size: 0.75rem;">
                                                    <i class="bi bi-eye-fill me-1 text-primary"></i><?php echo $viewsCount; ?>
                                                </span>

                                                <div style="font-size: 0.8rem;">
                                                    <span><?php echo $appAvailable ? 'Buka' : 'Belum tersedia'; ?></span>
                                                    <i class="bi bi-box-arrow-up-right ms-1 icon-arrow"></i>
                                                </div>
                                            </div>
                                        </div>

                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <p class="text-muted small fst-italic">Belum ada aplikasi pada kategori ini.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-warning text-center rounded-4 py-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-3 d-block mb-2"></i>
                    Data layanan belum tersedia.
                </div>
            <?php endif; ?>

        </div>
    </section>

    <!-- FLOATING WHATSAPP BUTTON -->
    <div class="wa-floating-container">
        <button type="button" class="btn btn-whatsapp rounded-circle shadow-lg d-flex align-items-center justify-content-center" data-bs-toggle="modal" data-bs-target="#waModal" title="Hubungi Admin via WhatsApp">
            <i class="bi bi-whatsapp fs-2 text-white"></i>
        </button>
    </div>

    <!-- MODAL POP-UP WHATSAPP -->
    <div class="modal fade" id="waModal" tabindex="-1" aria-labelledby="waModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header bg-success text-white rounded-top-4">
                    <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="waModalLabel">
                        <i class="bi bi-whatsapp"></i> Hubungi Admin Portal
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-secondary small mb-3">
                        Silakan pilih layanan bantuan atau klik tombol di bawah untuk langsung terhubung dengan Admin via WhatsApp.
                    </p>

                    <div class="bg-light p-3 rounded-3 mb-3 border">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-success">Online</span>
                            <strong class="small text-dark">Helpdesk Layanan Terpadu</strong>
                        </div>
                        <small class="text-muted d-block" style="font-size: 0.8rem;">Jam Operasional: Senin - Jumat (08:00 - 16:00 WITA)</small>
                    </div>

                    <?php
                        $waUrl = "https://wa.me/" . htmlspecialchars($settings['wa_number']) . "?text=" . urlencode($settings['wa_default_msg']);
                    ?>
                    <a href="<?php echo $waUrl; ?>"
                       target="_blank"
                       class="btn btn-success w-100 fw-bold py-2 rounded-3 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-chat-dots-fill"></i> Mulai Chat WhatsApp
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JavaScript Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>