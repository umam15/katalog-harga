<?php
require_once 'config.php';

// Sama seperti index.php: umum selalu memakai kantor default (tidak bisa ganti),
// user & admin yang login pakai kantor pilihan mereka di session.
$kantor = is_logged_in() ? current_kantor($pdo) : get_setting('default_kantor', 'UTM');

$id = $_GET['id'] ?? '';
if ($id === '') { header('Location: index.php'); exit; }

$sqlItem = "SELECT i.kodeitem, i.namaitem, i.satuan AS satuandasar, i.jenis, i.merek, i.keterangan, i.sistemhargajual, i.hargajual1, s.stok
            FROM tbl_item i
            JOIN tbl_itemstok s ON i.kodeitem = s.kodeitem
            WHERE i.kodeitem = ? AND s.kantor = ?";
$stmt = $pdo->prepare($sqlItem);
$stmt->execute([$id, $kantor]);
$item = $stmt->fetch();

if (!$item) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Item tidak ditemukan · Katalog Harga</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <main class="container">
            <div class="empty-state" style="margin-top:40px;">
                <span class="empty-icon">📦</span>
                <p>Item tidak ditemukan atau stok kosong<?= is_logged_in() ? ' di kantor ' . htmlspecialchars($kantor) : '' ?>.</p>
                <a href="index.php" class="empty-clear">&lsaquo; Kembali ke katalog</a>
            </div>
        </main>
    </body>
    </html>
    <?php
    exit;
}

$hargaList = get_item_harga_list($pdo, $id, $item['sistemhargajual'], (float) $item['hargajual1'], $item['satuandasar']);

$stokKosong = (float)$item['stok'] <= 0;

// Pembulatan harga hanya diterapkan di sini kalau admin mengaktifkan opsi
// "bulatkan juga di detail" - defaultnya detail menampilkan harga asli.
if (get_bulatkan_harga_detail()) {
    $pembulatan = get_harga_pembulatan();
    foreach ($hargaList as &$hl) {
        $hl['harga'] = bulatkan_harga((float)$hl['harga'], $pembulatan);
    }
    unset($hl);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($item['namaitem']) ?> · Katalog Harga</title>
    <link rel="stylesheet" href="fonts/fonts.css">
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="favicon.ico">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#1F3A5F">
    <link rel="apple-touch-icon" href="icons/icon-192.png">
</head>
<body>
<header class="topbar topbar-simple">
    <div class="topbar-inner">
        <a href="javascript:history.back()" class="btn-back">&lsaquo; Kembali</a>
    </div>
</header>

<main class="container">
    <div class="detail-grid">
        <div class="img-container">
            <img src="image.php?id=<?= urlencode($item['kodeitem']) ?>" alt="Gambar <?= htmlspecialchars($item['namaitem']) ?>" loading="lazy">
        </div>
        <div class="info-container">
            <h1 class="item-title"><?= htmlspecialchars($item['namaitem']) ?></h1>

            <div class="badge-row">
                <span class="badge badge-mono">#<?= htmlspecialchars($item['kodeitem']) ?></span>
                <?php if ($item['merek']): ?><span class="badge"><?= htmlspecialchars($item['merek']) ?></span><?php endif; ?>
                <?php if ($item['jenis']): ?><span class="badge"><?= htmlspecialchars($item['jenis']) ?></span><?php endif; ?>
                <span class="badge <?= $stokKosong ? 'badge-out' : 'badge-stock' ?>">
                    Stok: <?= number_format((float)$item['stok'], 2, ',', '.') ?> <?= htmlspecialchars($item['satuandasar']) ?>
                </span>
            </div>

            <?php if (trim((string)$item['keterangan']) !== ''): ?>
            <div class="keterangan-block">
                <p class="label-sm">Keterangan</p>
                <p class="word-wrap"><?= nl2br(htmlspecialchars($item['keterangan'])) ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <h2 class="section-title">Daftar Harga &amp; Satuan</h2>
    <div class="catalog-list">
        <div class="catalog-head price-head" role="row">
            <span>Satuan</span>
            <span>Barcode</span>
            <span class="col-harga">Harga</span>
            <span>Info</span>
        </div>
        <?php if (empty($hargaList)): ?>
            <div class="empty-state">
                <span class="empty-icon">🏷️</span>
                <p>Tidak ada data harga yang sesuai kriteria.</p>
            </div>
        <?php else: ?>
            <?php foreach ($hargaList as $hl): ?>
            <div class="catalog-row price-row" role="row">
                <span class="unit-chip"><?= htmlspecialchars($hl['satuan']) ?></span>
                <span class="barcode-txt word-wrap"><?= htmlspecialchars($hl['barcode']) ?></span>
                <span class="col-harga price-tag">Rp <?= number_format((float)$hl['harga'], 0, ',', '.') ?></span>
                <span class="info-txt"><?= htmlspecialchars($hl['info']) ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>
<script>
// Sama seperti di index.php: daftarkan service worker di sini juga, untuk
// pengguna yang landing langsung ke halaman detail (mis. scan barcode/QR)
// tanpa lewat index.php dulu. Registrasi idempotent - kalau SW sudah
// terdaftar dari halaman lain, ini tidak mendaftar ulang dari nol.
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.php').catch(() => {});
    });
}
</script>
</body>
</html>
