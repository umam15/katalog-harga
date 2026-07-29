<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
require_admin();

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $message = 'Sesi tidak valid, silakan coba lagi.';
        $messageType = 'danger';
    } elseif ($action === 'backup') {
        // Unduh backup sebagai file JSON. Header dikirim langsung dari sini,
        // jadi tidak lanjut ke rendering halaman HTML di bawah.
        $backup = build_settings_backup();
        $json = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $filename = 'katalog-harga-settings-' . date('Y-m-d_His') . '.json';

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($json));
        echo $json;
        exit;
    } elseif ($action === 'restore') {
        if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            $message = 'Pilih file backup (.json) untuk di-restore.';
            $messageType = 'danger';
        } else {
            $raw = file_get_contents($_FILES['backup_file']['tmp_name']);
            $data = json_decode((string) $raw, true);

            if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
                $message = 'File tidak bisa dibaca, pastikan file JSON hasil backup yang benar.';
                $messageType = 'danger';
            } else {
                try {
                    $count = restore_settings_backup($data);
                    $message = "Restore berhasil, $count pengaturan diperbarui.";
                    $messageType = 'success';
                } catch (InvalidArgumentException $e) {
                    $message = $e->getMessage();
                    $messageType = 'danger';
                }
            }
        }
    }
}

$currentSettingsCount = count(get_all_settings());
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup &amp; Restore · Admin</title>
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
    <h1 class="section-title" style="margin-top:0;">Backup &amp; Restore Pengaturan</h1>
    <p class="muted-text">
        Ekspor semua pengaturan aplikasi (kredensial database &amp; pengaturan tampilan) saat ini sebagai
        <?= $currentSettingsCount ?> baris, atau pulihkan dari file backup sebelumnya.
        Akun login (admin/user) tidak termasuk di backup ini, kelola lewat
        <a href="users.php">Manajemen Pengguna</a>.
    </p>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <section>
        <h2 class="section-title" style="font-size:16px;">Backup</h2>
        <p class="muted-text">
            File yang diunduh berisi kredensial database dalam bentuk teks biasa (tidak dienkripsi).
            Simpan di tempat yang aman dan jangan dibagikan sembarangan.
        </p>
        <form method="POST" class="stack-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="backup">
            <div class="btn-row">
                <button type="submit" class="btn btn-primary">Unduh Backup (.json)</button>
            </div>
        </form>
    </section>

    <hr style="border:none; border-top:1px solid var(--border); margin:28px 0;">

    <section>
        <h2 class="section-title" style="font-size:16px;">Restore</h2>
        <p class="muted-text">
            Mengunggah file backup akan <strong>menimpa</strong> pengaturan yang cocok saat ini (termasuk
            kredensial database jika ada di file). Pengaturan lain yang tidak ada di file tidak akan diubah.
        </p>
        <form method="POST" enctype="multipart/form-data" class="stack-form"
              onsubmit="return confirm('Yakin ingin menimpa pengaturan saat ini dengan isi file backup ini?');">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="restore">
            <label class="form-label">File backup (.json)
                <input type="file" name="backup_file" class="form-input" accept="application/json,.json" required>
            </label>
            <div class="btn-row">
                <button type="submit" class="btn btn-danger">Restore Pengaturan</button>
            </div>
        </form>
    </section>
</main>
</body>
</html>
