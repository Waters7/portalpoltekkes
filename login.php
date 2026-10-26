<?php
session_start();

// Jika sudah login, langsung lempar ke halaman admin
if (isset($_SESSION['is_admin_logged_in']) && $_SESSION['is_admin_logged_in'] === true) {
    header('Location: admin.php');
    exit;
}

// Konfigurasi Kredensial Admin (Silakan ubah sesuai kebutuhan)
$adminUser = 'admin';
$adminPass = 'admin123';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = (is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '');
    $password = (is_string($_POST['password'] ?? null) ? $_POST['password'] : '');

    if ($username === $adminUser && $password === $adminPass) {
        session_regenerate_id(true);
        $_SESSION['is_admin_logged_in'] = true;
        $_SESSION['admin_user'] = $username;
        header('Location: admin.php');
        exit;
    } else {
        $error = 'Username atau Password salah!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin — Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">

    <div class="card border-0 shadow-lg rounded-4 p-4" style="width: 100%; max-width: 400px;">
        <div class="card-body text-center">
            <div class="mb-3 text-success">
                <i class="bi bi-shield-lock-fill display-4"></i>
            </div>
            <h4 class="fw-bold mb-1">Login Admin</h4>
            <p class="text-muted small mb-4">Masuk untuk mengelola portal layanan</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 small rounded-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-floating mb-3 text-start">
                    <input type="text" class="form-control rounded-3" id="username" name="username" placeholder="Username" required>
                    <label for="username"><i class="bi bi-person me-1"></i> Username</label>
                </div>
                <div class="form-floating mb-3 text-start">
                    <input type="password" class="form-control rounded-3" id="password" name="password" placeholder="Password" required>
                    <label for="password"><i class="bi bi-key me-1"></i> Password</label>
                </div>
                <button type="submit" class="btn btn-success w-100 py-2 rounded-3 fw-bold shadow-sm">
                    Masuk ke Admin
                </button>
            </form>

            <div class="mt-4 border-top pt-3">
                <a href="index.php" class="text-decoration-none small text-muted">
                    <i class="bi bi-arrow-left me-1"></i> Kembali ke Halaman Utama
                </a>
            </div>
        </div>
    </div>

</body>
</html>