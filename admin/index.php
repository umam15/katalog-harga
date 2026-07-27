<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
require_admin();

$dbHost = get_setting('db_host');
$dbName = get_setting('db_name');
$adminTotal = admin_count();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin · Katalog Harga</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin.css">
    <link rel="icon" href="../favicon.ico">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a href="../index.php" class="brand">
            <span class="brand-mark">SB</span>
            <span class="brand-name">Admin · Katalog Harga</span>
        </a>
        <div class="admin-topbar-right">
            <span class="admin-whoami">Halo, <?= htmlspecialchars($_SESSION['username']) ?></span>
            <a href="logout.php" class="btn-back" style="color:#fff;">Keluar</a>
        </div>
    </div>
</header>

<main class="container">
    <h1 class="section-title" style="margin-top:0;">Dashboard</h1>
    <p class="muted-text">Kelola pengaturan koneksi database dan akun admin dari sini.</p>

    <div class="admin-card-grid">
        <a href="database.php" class="admin-card">
            <span class="admin-card-icon">🗄️</span>
            <span class="admin-card-title">Pengaturan Database</span>
            <span class="admin-card-desc"><?= htmlspecialchars($dbHost) ?> / <?= htmlspecialchars($dbName) ?></span>
        </a>
        <a href="users.php" class="admin-card">
            <span class="admin-card-icon">👤</span>
            <span class="admin-card-title">Manajemen Pengguna</span>
            <span class="admin-card-desc"><?= $adminTotal ?> akun admin terdaftar</span>
        </a>
        <a href="display.php" class="admin-card">
            <span class="admin-card-icon">🖥️</span>
            <span class="admin-card-title">Pengaturan Tampilan</span>
            <span class="admin-card-desc">Kantor, tipe item &amp; stok kosong untuk umum</span>
        </a>
        <a href="../index.php" class="admin-card">
            <span class="admin-card-icon">🛒</span>
            <span class="admin-card-title">Lihat Katalog</span>
            <span class="admin-card-desc">Kembali ke tampilan publik</span>
        </a>
    </div>
</main>
</body>
</html>
