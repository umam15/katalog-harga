<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
require_admin();

$fields = ['db_host' => 'Host', 'db_port' => 'Port', 'db_name' => 'Nama Database', 'db_user' => 'User'];
$message = '';
$messageType = 'success';

// Nilai form: mulai dari pengaturan tersimpan, lalu ditimpa input POST kalau ada (biar sticky saat error)
$current = [
    'db_host' => get_setting('db_host'),
    'db_port' => get_setting('db_port'),
    'db_name' => get_setting('db_name'),
    'db_user' => get_setting('db_user'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $message = 'Sesi tidak valid, silakan coba lagi.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        $input = [
            'host'   => trim($_POST['db_host'] ?? ''),
            'port'   => trim($_POST['db_port'] ?? ''),
            'dbname' => trim($_POST['db_name'] ?? ''),
            'user'   => trim($_POST['db_user'] ?? ''),
            'pass'   => $_POST['db_pass'] ?? '',
        ];
        // Field password dikosongkan di form kalau tidak ingin diubah
        if ($input['pass'] === '') {
            $input['pass'] = get_setting('db_pass');
        }

        $current['db_host'] = $input['host'];
        $current['db_port'] = $input['port'];
        $current['db_name'] = $input['dbname'];
        $current['db_user'] = $input['user'];

        if ($action === 'test' || $action === 'save') {
            try {
                get_pgsql_pdo($input);
                $message = 'Koneksi berhasil.';
                $messageType = 'success';

                if ($action === 'save') {
                    set_setting('db_host', $input['host']);
                    set_setting('db_port', $input['port']);
                    set_setting('db_name', $input['dbname']);
                    set_setting('db_user', $input['user']);
                    set_setting('db_pass', $input['pass']);
                    $message = 'Pengaturan disimpan dan koneksi berhasil diverifikasi.';
                }
            } catch (PDOException $e) {
                $message = 'Koneksi gagal: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Database · Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
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
    <h1 class="section-title" style="margin-top:0;">Pengaturan Database</h1>
    <p class="muted-text">Kredensial koneksi PostgreSQL untuk katalog. Disimpan di <code>data/settings.sqlite</code>, tidak lagi di file kode.</p>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST" class="stack-form">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <label class="form-label">Host
            <input type="text" name="db_host" class="form-input" required value="<?= htmlspecialchars($current['db_host']) ?>">
        </label>
        <label class="form-label">Port
            <input type="text" name="db_port" class="form-input" required value="<?= htmlspecialchars($current['db_port']) ?>">
        </label>
        <label class="form-label">Nama Database
            <input type="text" name="db_name" class="form-input" required value="<?= htmlspecialchars($current['db_name']) ?>">
        </label>
        <label class="form-label">User
            <input type="text" name="db_user" class="form-input" required value="<?= htmlspecialchars($current['db_user']) ?>">
        </label>
        <label class="form-label">Password <span class="muted-text">(kosongkan jika tidak ingin diubah)</span>
            <input type="password" name="db_pass" class="form-input" autocomplete="new-password">
        </label>
        <div class="btn-row">
            <button type="submit" name="action" value="test" class="btn btn-secondary">Tes Koneksi</button>
            <button type="submit" name="action" value="save" class="btn btn-primary">Simpan</button>
        </div>
    </form>
</main>
</body>
</html>
