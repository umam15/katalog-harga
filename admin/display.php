<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
require_admin();

$pdo = get_pgsql_pdo();

$message = '';
$messageType = 'success';

$kantorList = get_kantor_list($pdo);
$jenisList  = get_jenis_list($pdo);

$current = [
    'default_kantor'   => get_setting('default_kantor', ''),
    'display_jenis'    => get_display_jenis(),
    'show_stok_kosong' => get_show_stok_kosong(),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $message = 'Sesi tidak valid, silakan coba lagi.';
        $messageType = 'danger';
    } else {
        $defaultKantor  = trim($_POST['default_kantor'] ?? '');
        $selectedJenis  = $_POST['display_jenis'] ?? [];
        $showStokKosong = isset($_POST['show_stok_kosong']);

        if (!is_array($selectedJenis)) $selectedJenis = [];
        $selectedJenis = array_values(array_intersect($jenisList, $selectedJenis));

        if ($defaultKantor === '' || (!empty($kantorList) && !in_array($defaultKantor, $kantorList, true))) {
            $message = 'Kantor default tidak valid.';
            $messageType = 'danger';
        } else {
            set_setting('default_kantor', $defaultKantor);
            set_display_jenis($selectedJenis);
            set_setting('show_stok_kosong', $showStokKosong ? '1' : '0');

            $current = [
                'default_kantor'   => $defaultKantor,
                'display_jenis'    => $selectedJenis,
                'show_stok_kosong' => $showStokKosong,
            ];
            $message = 'Pengaturan tampilan berhasil disimpan.';
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
    <title>Pengaturan Tampilan · Admin</title>
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
    <h1 class="section-title" style="margin-top:0;">Pengaturan Tampilan</h1>
    <p class="muted-text">Mengatur apa yang dilihat pengunjung <strong>umum</strong> (tanpa login) di katalog. User dan admin yang login selalu melihat katalog lengkap tanpa batasan ini.</p>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST" class="stack-form">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

        <label class="form-label">Kantor default untuk umum
            <?php if (!empty($kantorList)): ?>
            <select name="default_kantor" class="form-input">
                <?php foreach ($kantorList as $k): ?>
                <option value="<?= htmlspecialchars($k) ?>" <?= $k === $current['default_kantor'] ? 'selected' : '' ?>><?= htmlspecialchars($k) ?></option>
                <?php endforeach; ?>
            </select>
            <?php else: ?>
                <input type="text" name="default_kantor" class="form-input" value="<?= htmlspecialchars($current['default_kantor']) ?>">
                <span class="muted-text">Tidak bisa membaca daftar kantor dari database, isi manual.</span>
            <?php endif; ?>
        </label>

        <label class="form-label">Tipe item yang ditampilkan
            <span class="muted-text" style="font-weight:400;">Kosongkan semua untuk menampilkan semua tipe.</span>
        </label>
        <?php if (empty($jenisList)): ?>
            <p class="muted-text">Tidak ada data tipe item di database.</p>
        <?php else: ?>
        <div class="checkbox-list">
            <?php foreach ($jenisList as $j): ?>
            <label class="checkbox-item">
                <input type="checkbox" name="display_jenis[]" value="<?= htmlspecialchars($j) ?>" <?= in_array($j, $current['display_jenis'], true) ? 'checked' : '' ?>>
                <?= htmlspecialchars($j) ?>
            </label>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <label class="checkbox-item">
            <input type="checkbox" name="show_stok_kosong" <?= $current['show_stok_kosong'] ? 'checked' : '' ?>>
            Tampilkan item dengan stok kosong
        </label>

        <div class="btn-row">
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
    </form>
</main>
</body>
</html>
