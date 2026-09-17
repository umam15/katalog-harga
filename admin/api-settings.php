<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
require_admin();

$message = '';
$messageType = 'success';

$current = ['api_enabled' => get_api_enabled()];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $message = 'Sesi tidak valid, silakan coba lagi.';
        $messageType = 'danger';
    } else {
        $enabled = isset($_POST['api_enabled']);
        set_api_enabled($enabled);
        $current['api_enabled'] = $enabled;
        $message = $enabled ? 'API diaktifkan.' : 'API dinonaktifkan.';
    }
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan API · Admin</title>
    <link rel="stylesheet" href="../fonts/fonts.css">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin.css">
    <link rel="icon" href="../favicon.ico">
</head>
<body>
<header class="topbar topbar-simple">
    <div class="topbar-inner">
        <a href="index.php" class="btn-back">&lsaquo; Dashboard</a>
    </div>
</header>

<main class="container container-narrow">
    <h1 class="section-title" style="margin-top:0;">Pengaturan API</h1>
    <p class="muted-text">
        Akses baca-saja ke katalog lewat <code>/api/v1/*</code> untuk integrasi program
        atau agent/LLM eksternal. API ini terpisah dari katalog publik dan defaultnya
        nonaktif sampai sengaja diaktifkan di sini.
    </p>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST" class="stack-form">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

        <label class="checkbox-item">
            <input type="checkbox" name="api_enabled" <?= $current['api_enabled'] ? 'checked' : '' ?>>
            Aktifkan API
        </label>

        <div class="btn-row">
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
    </form>

    <h2 class="section-title">Status</h2>
    <p>
        API saat ini:
        <?php if ($current['api_enabled']): ?>
            <strong style="color:#1a7f37;">Aktif</strong>
        <?php else: ?>
            <strong style="color:#b42318;">Nonaktif</strong>
        <?php endif; ?>
    </p>
    <p class="muted-text">
        Manajemen API key dan endpoint belum tersedia — menyusul di rilis
        berikutnya (lihat <code>TODO.md</code>). Mengaktifkan toggle ini di
        tahap sekarang belum membuka akses apa pun, karena belum ada
        endpoint/key yang bisa dipakai.
    </p>
</main>
</body>
</html>
