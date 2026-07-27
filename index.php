<?php
require_once 'config.php';

$loggedIn = is_logged_in();

// Ganti kantor/gudang aktif kalau user memilih dari dropdown, lalu redirect
// supaya URL tetap bersih (tanpa param kantor) dan search/page tidak hilang.
// Hanya untuk user yang login - umum tidak boleh mengganti kantor, jadi
// parameter ?kantor= diabaikan begitu saja kalau belum login.
if ($loggedIn && isset($_GET['kantor'])) {
    $requested = trim($_GET['kantor']);
    $validKantor = get_kantor_list($pdo);
    if (in_array($requested, $validKantor, true)) {
        set_current_kantor($requested);
    }
    $redirectParams = $_GET;
    unset($redirectParams['kantor']);
    $qs = http_build_query($redirectParams);
    header('Location: index.php' . ($qs !== '' ? '?' . $qs : ''));
    exit;
}

$kantorList = get_kantor_list($pdo);
// Umum selalu memakai kantor default yang diatur admin (tidak bisa ganti).
// User & admin yang login boleh pilih kantor sendiri lewat dropdown, tersimpan di session.
$kantor = $loggedIn ? current_kantor($pdo) : get_setting('default_kantor', 'UTM');

// Pengaturan tampilan (tipe item, stok kosong) hanya berlaku untuk pengunjung
// umum (tanpa login). User & admin yang login selalu melihat katalog lengkap
// tanpa batasan ini.
$displayJenis   = $loggedIn ? [] : get_display_jenis();
$showStokKosong = $loggedIn ? true : get_show_stok_kosong();

$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['p'] ?? 1));
$limit  = 50;
$offset = ($page - 1) * $limit;

// Dibanding versi sebelumnya: barcode dicek lewat EXISTS, bukan LEFT JOIN + DISTINCT.
// Ini menghindari baris duplikat di sumbernya (bukan membersihkannya belakangan),
// dan memungkinkan COUNT(*) OVER() menghitung total baris secara akurat dalam satu query
// -> tidak perlu query count terpisah untuk pagination.
//
// Harga yang ditampilkan di katalog harus konsisten dengan halaman detail:
// - sistem 'O' (harga tetap)  -> hargajual1 langsung
// - sistem 'S'/'L'/'J'        -> harga dari tbl_itemhj pada SATUAN DASAR saja,
//   diambil tingkatan terendah (level 1 untuk 'L', jmlsampai terkecil untuk 'J')
// LATERAL join dipakai supaya per-item cukup ambil 1 baris harga yang relevan,
// tanpa N+1 query dan tanpa mengganggu COUNT(*) OVER() untuk pagination.
$sql = "SELECT i.kodeitem, i.namaitem, i.merek, i.satuan AS satuandasar, s.stok,
               CASE WHEN UPPER(i.sistemhargajual) = 'O' THEN i.hargajual1 ELSE hj.hargajual END AS harga_raw,
               COUNT(*) OVER() AS total_rows
        FROM tbl_item i
        JOIN tbl_itemstok s ON i.kodeitem = s.kodeitem
        LEFT JOIN LATERAL (
            SELECT h.hargajual
            FROM tbl_itemhj h
            WHERE h.kodeitem = i.kodeitem
              AND h.satuan = i.satuan
              AND (
                    UPPER(i.sistemhargajual) = 'S'
                 OR (UPPER(i.sistemhargajual) = 'L' AND h.level = 1)
                 OR (UPPER(i.sistemhargajual) = 'J' AND h.jmlsampai >= 1)
              )
            ORDER BY
              CASE WHEN UPPER(i.sistemhargajual) = 'J' THEN h.jmlsampai END ASC NULLS LAST,
              CASE WHEN UPPER(i.sistemhargajual) = 'L' THEN h.level END ASC NULLS LAST
            LIMIT 1
        ) hj ON TRUE
        WHERE s.kantor = ?";

$params = [$kantor];

if (!$showStokKosong) {
    $sql .= " AND s.stok > 0";
}

if (!empty($displayJenis)) {
    $placeholders = implode(',', array_fill(0, count($displayJenis), '?'));
    $sql .= " AND i.jenis IN ($placeholders)";
    array_push($params, ...$displayJenis);
}

if ($search !== '') {
    $sql .= " AND (i.namaitem ILIKE ? OR i.merek ILIKE ? OR i.keterangan ILIKE ? OR i.jenis ILIKE ? OR i.kodeitem ILIKE ?
               OR EXISTS (
                    SELECT 1 FROM tbl_itemsatuanjml b
                    WHERE b.kodeitem = i.kodeitem AND b.kodebarcode ILIKE ?
               ))";
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like, $like, $like);
}

$sql .= " ORDER BY i.kodeitem ASC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

$totalRows  = $items[0]['total_rows'] ?? 0;
$totalPages = $totalRows > 0 ? (int)ceil($totalRows / $limit) : 1;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katalog Harga</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="favicon.ico">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a href="index.php" class="brand">
            <span class="brand-mark">SB</span>
            <span class="brand-name">Katalog Harga</span>
        </a>
        <form method="GET" action="index.php" class="search-form" id="searchForm">
            <input type="text" name="q" id="searchInput" class="search-box"
                   placeholder="Cari atau scan kode item…"
                   value="<?= htmlspecialchars($search) ?>" autofocus autocomplete="off">
        </form>
        <?php if ($loggedIn && !empty($kantorList)): ?>
        <form method="GET" action="index.php" class="kantor-form" id="kantorForm">
            <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
            <select name="kantor" id="kantorSelect" class="kantor-select" onchange="this.form.submit()">
                <?php foreach ($kantorList as $k): ?>
                <option value="<?= htmlspecialchars($k) ?>" <?= $k === $kantor ? 'selected' : '' ?>><?= htmlspecialchars($k) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
        <div class="auth-block">
            <?php if ($loggedIn): ?>
                <span class="auth-whoami">Halo, <?= htmlspecialchars($_SESSION['username']) ?></span>
                <?php if (current_user_role() === 'admin'): ?>
                    <a href="admin/index.php" class="auth-link">Admin</a>
                <?php endif; ?>
                <a href="admin/logout.php" class="auth-link">Keluar</a>
            <?php else: ?>
                <a href="admin/login.php" class="auth-link">Login</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="container">
    <?php if ($search !== ''): ?>
        <p class="result-meta">
            <?php if ($loggedIn): ?>
                <?= $totalRows ?> item ditemukan untuk “<?= htmlspecialchars($search) ?>” di kantor <?= htmlspecialchars($kantor) ?>
            <?php else: ?>
                <?= $totalRows ?> item ditemukan untuk “<?= htmlspecialchars($search) ?>”
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <div class="catalog-list">
        <div class="catalog-head" role="row">
            <span>Item</span>
            <span>Merek</span>
            <span>Satuan</span>
            <span class="col-harga">Harga</span>
        </div>

        <?php if (empty($items)): ?>
            <div class="empty-state">
                <span class="empty-icon">🔍</span>
                <p>Tidak ada item yang cocok.</p>
                <?php if ($search !== ''): ?>
                    <a href="index.php" class="empty-clear">Hapus pencarian</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($items as $row):
                // Bulatkan ke ATAS ke kelipatan 500 terdekat.
                $harga = ceil(((float)$row['harga_raw']) / 500) * 500;
                $kosong = (float)$row['stok'] <= 0;
            ?>
            <a class="catalog-row" href="detail.php?id=<?= urlencode($row['kodeitem']) ?>">
                <span class="col-item">
                    <img class="thumb" src="image.php?id=<?= urlencode($row['kodeitem']) ?>" alt="" loading="lazy" width="40" height="40">
                    <span class="item-name"><?= htmlspecialchars($row['namaitem']) ?></span>
                    <?php if ($kosong): ?><span class="badge badge-out badge-inline">Stok kosong</span><?php endif; ?>
                </span>
                <span class="col-merek" data-label="Merek"><?= htmlspecialchars($row['merek']) ?></span>
                <span class="col-satuan" data-label="Satuan"><?= htmlspecialchars($row['satuandasar']) ?></span>
                <span class="col-harga price-tag">Rp <?= number_format($harga, 0, ',', '.') ?></span>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if (!empty($items)): ?>
    <nav class="pagination">
        <a class="page-btn <?= $page <= 1 ? 'is-disabled' : '' ?>"
           href="?q=<?= urlencode($search) ?>&p=<?= max(1, $page - 1) ?>" aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>">&lsaquo;</a>
        <span class="page-info">Halaman <?= $page ?> dari <?= $totalPages ?></span>
        <a class="page-btn <?= $page >= $totalPages ? 'is-disabled' : '' ?>"
           href="?q=<?= urlencode($search) ?>&p=<?= min($totalPages, $page + 1) ?>" aria-disabled="<?= $page >= $totalPages ? 'true' : 'false' ?>">&rsaquo;</a>
    </nav>
    <?php endif; ?>
</main>

<script>
// Pencarian otomatis (debounced) tanpa perlu tekan Enter, tetap fallback ke submit form biasa.
const input = document.getElementById('searchInput');
const form = document.getElementById('searchForm');
let debounceTimer;
input.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => form.submit(), 1450);
});
</script>
</body>
</html>
