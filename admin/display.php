<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
require_admin();

$message = '';
$messageType = 'success';
$dbError = null;

try {
    $pdo = get_pgsql_pdo();
    $kantorList = get_kantor_list($pdo);
    $jenisList  = get_jenis_list($pdo);
} catch (PDOException $e) {
    // Database belum di-setting atau tidak bisa diakses - jangan fatal error,
    // tampilkan pesan dan arahkan ke halaman Pengaturan Database.
    $dbError = $e->getMessage();
    $kantorList = [];
    $jenisList  = [];
}

$current = [
    'default_kantor'         => get_setting('default_kantor', ''),
    'display_jenis'          => get_display_jenis(),
    'show_stok_kosong'       => get_show_stok_kosong(),
    'harga_pembulatan'       => get_harga_pembulatan(),
    'bulatkan_harga_detail'  => get_bulatkan_harga_detail(),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $message = 'Sesi tidak valid, silakan coba lagi.';
        $messageType = 'danger';
    } elseif (($_POST['action'] ?? '') === 'clear_img_cache') {
        $cleared = clear_img_cache();
        $message = "Cache gambar dibersihkan ($cleared file dihapus). Gambar akan diambil ulang dari database saat pertama kali diminta lagi.";
    } else {
        $defaultKantor         = trim($_POST['default_kantor'] ?? '');
        $selectedJenis         = $_POST['display_jenis'] ?? [];
        $showStokKosong        = isset($_POST['show_stok_kosong']);
        $hargaPembulatan       = (int) ($_POST['harga_pembulatan'] ?? 0);
        $bulatkanHargaDetail   = isset($_POST['bulatkan_harga_detail']);

        if (!is_array($selectedJenis)) $selectedJenis = [];
        $selectedJenis = array_values(array_intersect($jenisList, $selectedJenis));

        if ($defaultKantor === '' || (!empty($kantorList) && !in_array($defaultKantor, $kantorList, true))) {
            $message = 'Kantor default tidak valid.';
            $messageType = 'danger';
        } elseif ($hargaPembulatan < 0) {
            $message = 'Nilai pembulatan harga tidak boleh negatif.';
            $messageType = 'danger';
        } else {
            set_setting('default_kantor', $defaultKantor);
            set_display_jenis($selectedJenis);
            set_setting('show_stok_kosong', $showStokKosong ? '1' : '0');
            set_setting('harga_pembulatan', (string) $hargaPembulatan);
            set_setting('bulatkan_harga_detail', $bulatkanHargaDetail ? '1' : '0');

            $current = [
                'default_kantor'        => $defaultKantor,
                'display_jenis'         => $selectedJenis,
                'show_stok_kosong'      => $showStokKosong,
                'harga_pembulatan'      => $hargaPembulatan,
                'bulatkan_harga_detail' => $bulatkanHargaDetail,
            ];
            $message = 'Pengaturan tampilan berhasil disimpan.';
        }
    }
}

$csrf = csrf_token();
$imgCacheStats = img_cache_stats();
function format_bytes(int $bytes): string {
    if ($bytes < 1024) return "$bytes B";
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / (1024 * 1024), 1) . ' MB';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Tampilan · Admin</title>
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
    <h1 class="section-title" style="margin-top:0;">Pengaturan Tampilan</h1>
    <p class="muted-text">Mengatur apa yang dilihat pengunjung <strong>umum</strong> (tanpa login) di katalog. User dan admin yang login selalu melihat katalog lengkap tanpa batasan ini.</p>

    <?php if ($dbError !== null): ?>
        <div class="alert alert-danger">
            Database tidak terhubung, daftar kantor &amp; tipe item tidak bisa dimuat (isi manual di bawah kalau perlu). Pengaturan lain di halaman ini tetap bisa disimpan.
            Cek <a href="database.php">Pengaturan Database</a> untuk mengisi atau memperbaiki kredensial koneksi.
        </div>
    <?php endif; ?>

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

        <label class="form-label">Pembulatan harga (Rp)
            <span class="muted-text" style="font-weight:400;">Harga di katalog dibulatkan ke ATAS ke kelipatan angka ini, mis. 500. Isi 0 untuk menonaktifkan pembulatan (harga ditampilkan apa adanya).</span>
            <input type="number" name="harga_pembulatan" class="form-input" min="0" step="1" required
                   value="<?= htmlspecialchars((string) $current['harga_pembulatan']) ?>">
        </label>

        <label class="checkbox-item">
            <input type="checkbox" name="bulatkan_harga_detail" <?= $current['bulatkan_harga_detail'] ? 'checked' : '' ?>>
            Bulatkan juga harga di halaman detail
        </label>

        <div class="btn-row">
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
    </form>

    <h2 class="section-title">Cache Gambar</h2>
    <p class="muted-text">
        Gambar produk (termasuk thumbnail katalog) disimpan di <code>data/img-cache/</code>
        setelah pertama kali diambil dari database, supaya request berikutnya tidak perlu
        buka koneksi database lagi. Bersihkan cache ini kalau ada foto produk yang diganti
        tapi kodeitem-nya sama (perubahannya tidak otomatis muncul selama cache masih ada).
    </p>
    <p><strong><?= $imgCacheStats['count'] ?></strong> file cache, total <strong><?= format_bytes($imgCacheStats['bytes']) ?></strong>.</p>
    <form method="POST" class="stack-form" onsubmit="return confirm('Bersihkan semua cache gambar? Gambar akan diambil ulang dari database saat pertama kali diminta lagi.');">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="clear_img_cache">
        <div class="btn-row">
            <button type="submit" class="btn btn-secondary">Bersihkan cache gambar</button>
        </div>
    </form>
</main>
</body>
</html>
