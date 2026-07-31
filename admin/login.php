<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
ensure_session();

if (is_logged_in()) {
    header('Location: ' . (current_user_role() === 'admin' ? 'index.php' : '../index.php'));
    exit;
}

$isSetup = admin_count() === 0;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $error = 'Sesi tidak valid, silakan coba lagi.';
    } elseif ($isSetup) {
        // Belum ada admin sama sekali -> buat akun admin pertama
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Username dan password wajib diisi.';
        } elseif (strlen($password) < 8) {
            $error = 'Password minimal 8 karakter.';
        } elseif ($password !== $confirm) {
            $error = 'Konfirmasi password tidak cocok.';
        } else {
            create_admin($username, $password, 'admin');
            $account = find_admin_by_username($username);
            $_SESSION['user_id']  = $account['id'];
            $_SESSION['username'] = $account['username'];
            $_SESSION['role']     = $account['role'];
            session_regenerate_id(true);
            header('Location: index.php');
            exit;
        }
    } else {
        // Login biasa (admin maupun user)
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $account = find_admin_by_username($username);

        if ($account && password_verify($password, $account['password_hash'])) {
            $_SESSION['user_id']  = $account['id'];
            $_SESSION['username'] = $account['username'];
            $_SESSION['role']     = $account['role'];
            session_regenerate_id(true);
            header('Location: ' . ($account['role'] === 'admin' ? 'index.php' : '../index.php'));
            exit;
        }
        $error = 'Username atau password salah.';
    }
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isSetup ? 'Buat Akun Admin' : 'Login' ?> · Katalog Harga</title>
    <link rel="stylesheet" href="../fonts/fonts.css">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin.css">
    <link rel="icon" href="../favicon.ico">
</head>
<body>
<header class="topbar topbar-simple">
    <div class="topbar-inner">
        <a href="../index.php" class="btn-back">&lsaquo; Kembali ke katalog</a>
    </div>
</header>
<main class="container container-narrow">
    <div class="auth-card">
        <h1 class="section-title" style="margin-top:0;">
            <?= $isSetup ? 'Buat Akun Admin Pertama' : 'Login' ?>
        </h1>
        <?php if ($isSetup): ?>
            <p class="muted-text">Belum ada akun admin. Buat akun pertama untuk mengelola pengaturan.</p>
        <?php else: ?>
            <p class="muted-text">Login sebagai admin atau user untuk akses katalog yang lebih lengkap.</p>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="stack-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <label class="form-label">Username
                <input type="text" name="username" class="form-input" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </label>
            <label class="form-label">Password
                <input type="password" name="password" class="form-input" required minlength="<?= $isSetup ? 8 : 1 ?>">
            </label>
            <?php if ($isSetup): ?>
            <label class="form-label">Konfirmasi Password
                <input type="password" name="confirm" class="form-input" required minlength="8">
            </label>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary"><?= $isSetup ? 'Buat Akun & Masuk' : 'Masuk' ?></button>
        </form>
    </div>

    <p class="muted-text" style="margin-top:2rem; text-align:center; font-size:0.85rem;">
        Katalog Harga v<?= htmlspecialchars(APP_VERSION) ?>
    </p>
</main>
</body>
</html>
