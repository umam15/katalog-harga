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

$sistem = strtoupper($item['sistemhargajual']);
$hargaList = [];

// Ambil SEMUA barcode item ini dalam satu query, lalu kelompokkan per satuan di PHP.
// Sebelumnya query barcode dijalankan ulang di dalam loop untuk tiap satuan (N+1 query) -
// sekarang cukup satu round-trip ke database berapa pun jumlah satuannya.
$sqlB = "SELECT satuan, kodebarcode FROM tbl_itemsatuanjml WHERE kodeitem = ?";
$stmtB = $pdo->prepare($sqlB);
$stmtB->execute([$id]);
$barcodeMap = [];
foreach ($stmtB->fetchAll() as $b) {
    $barcodeMap[$b['satuan']][] = $b['kodebarcode'];
}

if ($sistem === 'O') {
    $barcodes = $barcodeMap[$item['satuandasar']] ?? [];
    $hargaList[] = [
        'satuan'  => $item['satuandasar'],
        'barcode' => !empty($barcodes) ? implode(', ', $barcodes) : '-',
        'harga'   => $item['hargajual1'],
        'info'    => '',
    ];
} elseif (in_array($sistem, ['S', 'L', 'J'], true)) {
    $sqlHj = "SELECT hj.satuan, hj.hargajual, hj.level, hj.jmlsampai
              FROM tbl_itemhj hj WHERE hj.kodeitem = ?";
    $stmtHj = $pdo->prepare($sqlHj);
    $stmtHj->execute([$id]);

    foreach ($stmtHj->fetchAll() as $hj) {
        if ($sistem === 'L' && (int)$hj['level'] !== 1) continue;
        if ($sistem === 'J' && (float)$hj['jmlsampai'] < 1) continue;

        $barcodes = $barcodeMap[$hj['satuan']] ?? [];

        $infoEkstra = '';
        if ($sistem === 'L') $infoEkstra = "(Level: {$hj['level']})";
        if ($sistem === 'J') $infoEkstra = "(Sampai: " . round((float)$hj['jmlsampai']) . ")";

        $hargaList[] = [
            'satuan'  => $hj['satuan'],
            'barcode' => !empty($barcodes) ? implode(', ', $barcodes) : '-',
            'harga'   => $hj['hargajual'],
            'info'    => $infoEkstra,
        ];
    }
}

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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="favicon.ico">
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
</body>
</html>
