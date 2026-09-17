<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
require_admin();

$logs = list_api_log(200);

$eventLabel = [
    'request'     => 'Request',
    'key_created' => 'Key dibuat',
    'key_deleted' => 'Key dihapus',
];
$eventColor = [
    'request'     => '#374151',
    'key_created' => '#1a7f37',
    'key_deleted' => '#b42318',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Akses API · Admin</title>
    <link rel="stylesheet" href="../fonts/fonts.css">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin.css">
    <link rel="icon" href="../favicon.ico">
</head>
<body>
<header class="topbar topbar-simple">
    <div class="topbar-inner">
        <a href="api-settings.php" class="btn-back">&lsaquo; Pengaturan API</a>
    </div>
</header>

<main class="container container-narrow">
    <h1 class="section-title" style="margin-top:0;">Log Akses API</h1>
    <p class="muted-text">
        200 event terbaru: request yang berhasil diautentikasi, serta key yang
        dibuat/dihapus. Riwayat tetap tersimpan meski key-nya sendiri sudah
        dihapus. Log ini otomatis disusutkan, cuma menyimpan
        <?= number_format(API_LOG_MAX_ROWS, 0, ',', '.') ?> baris terbaru.
    </p>

    <?php if (empty($logs)): ?>
        <p class="muted-text">Belum ada aktivitas API yang tercatat.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Event</th>
                    <th>Key</th>
                    <th>Endpoint</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= htmlspecialchars(str_replace('T', ' ', substr($log['created_at'], 0, 16))) ?></td>
                    <td><span style="color:<?= $eventColor[$log['event_type']] ?? '#374151' ?>;">
                        <?= htmlspecialchars($eventLabel[$log['event_type']] ?? $log['event_type']) ?>
                    </span></td>
                    <td><?= htmlspecialchars($log['key_label']) ?></td>
                    <td><code><?= htmlspecialchars($log['endpoint'] ?? '-') ?></code></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
