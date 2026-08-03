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
// Dinaikkan dari 50 -> 80: browser tanpa JS sekarang pindah halaman lewat
// link Sebelumnya/Berikutnya (lihat fallback <noscript> di bawah), jadi
// batch lebih besar berarti lebih sedikit klik. Query katalog sudah
// dioptimalkan (LATERAL join, tanpa N+1) dan gambar dimuat lazy + di-cache,
// jadi menaikkan limit tidak menambah beban signifikan per halaman.
$limit  = 80;
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

$hargaPembulatan = get_harga_pembulatan();

// Mode fragment: dipakai JS infinite scroll untuk minta baris tambahan
// tanpa reload seluruh halaman (tanpa <head>, header, dsb - cuma baris item).
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

/** Render baris katalog (dipakai baik di halaman penuh maupun fragment ajax). */
function render_item_rows(array $items, int $hargaPembulatan): string {
    ob_start();
    foreach ($items as $row):
        // Bulatkan ke ATAS ke kelipatan sesuai pengaturan admin (default 0 = tanpa pembulatan).
        $harga = bulatkan_harga((float)$row['harga_raw'], $hargaPembulatan);
        $kosong = (float)$row['stok'] <= 0;
        ?>
        <a class="catalog-row" href="detail.php?id=<?= urlencode($row['kodeitem']) ?>">
            <span class="col-item">
                <img class="thumb" src="image.php?id=<?= urlencode($row['kodeitem']) ?>&thumb=1" alt="" loading="lazy" width="40" height="40">
                <span class="item-name"><?= htmlspecialchars($row['namaitem']) ?></span>
                <?php if ($kosong): ?><span class="badge badge-out badge-inline">Stok kosong</span><?php endif; ?>
            </span>
            <span class="col-merek" data-label="Merek"><?= htmlspecialchars($row['merek']) ?></span>
            <span class="col-satuan" data-label="Satuan"><?= htmlspecialchars($row['satuandasar']) ?></span>
            <span class="col-harga price-tag">Rp <?= number_format($harga, 0, ',', '.') ?></span>
        </a>
        <?php
    endforeach;
    return ob_get_clean();
}

$rowsHtml = render_item_rows($items, $hargaPembulatan);

if ($isAjax) {
    header('Content-Type: text/html; charset=utf-8');
    echo $rowsHtml;
    exit;
}

// Query string dasar untuk link "muat lebih banyak" (tanpa p= dan ajax=,
// itu ditambahkan oleh JS sendiri per-request).
$ajaxParams = $_GET;
unset($ajaxParams['p'], $ajaxParams['ajax'], $ajaxParams['kantor']);
$ajaxBaseQs = http_build_query($ajaxParams);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katalog Harga</title>
    <link rel="stylesheet" href="fonts/fonts.css">
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="favicon.ico">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#1F3A5F">
    <link rel="apple-touch-icon" href="icons/icon-192.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Katalog Harga">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a href="index.php" class="brand">
            <span class="brand-mark">SB</span>
            <span class="brand-name">Katalog Harga</span>
        </a>
        <form method="GET" action="index.php" class="search-form" id="searchForm">
            <div class="search-box-wrap">
                <input type="search" name="q" id="searchInput" class="search-box"
                       placeholder="Cari atau scan kode item…"
                       value="<?= htmlspecialchars($search) ?>" autocomplete="off"
                       enterkeyhint="search" inputmode="search">
                <kbd class="search-shortcut-hint" id="searchShortcutHint" aria-hidden="true">/</kbd>
            </div>
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
            <?= $rowsHtml ?>
        <?php endif; ?>
    </div>

    <?php if (!empty($items) && $totalPages > 1): ?>
    <div class="load-more-wrap" id="loadMoreWrap"
         data-page="<?= $page ?>" data-total-pages="<?= $totalPages ?>"
         data-qs="<?= htmlspecialchars($ajaxBaseQs) ?>">
        <button type="button" id="loadMoreBtn" class="btn-load-more">Muat lebih banyak</button>
        <p id="loadMoreStatus" class="load-more-status" aria-live="polite"></p>
    </div>
    <?php
    // Fallback tanpa JavaScript: infinite scroll di atas butuh JS, jadi
    // browser/pembaca tanpa JS perlu cara lain untuk pindah halaman.
    // Sengaja dibuat minimal (cuma Sebelumnya/Berikutnya, bukan daftar
    // nomor halaman lengkap) supaya tetap ringan walau katalog punya
    // ratusan halaman. <noscript> juga menyembunyikan tombol "Muat lebih
    // banyak" di atas karena tombol itu tidak berfungsi tanpa JS.
    $prevQs = $ajaxBaseQs . ($ajaxBaseQs !== '' ? '&' : '') . 'p=' . ($page - 1);
    $nextQs = $ajaxBaseQs . ($ajaxBaseQs !== '' ? '&' : '') . 'p=' . ($page + 1);
    ?>
    <noscript>
        <style>.load-more-wrap { display: none; }</style>
        <nav class="pager-fallback" aria-label="Navigasi halaman">
            <?php if ($page > 1): ?>
                <a class="pager-link" href="index.php?<?= htmlspecialchars($prevQs) ?>">&laquo; Sebelumnya</a>
            <?php endif; ?>
            <span class="pager-info">Halaman <?= $page ?> dari <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a class="pager-link" href="index.php?<?= htmlspecialchars($nextQs) ?>">Berikutnya &raquo;</a>
            <?php endif; ?>
        </nav>
    </noscript>
    <?php endif; ?>
</main>

<script>
// Pencarian otomatis (debounced) tanpa perlu tekan Enter - HANYA di layar
// desktop/tablet. Di mobile ini dimatikan: submit di sini artinya reload
// halaman penuh (bukan cuma AJAX), dan kalau dipicu tiap kali jeda ngetik
// di keyboard virtual, hasilnya reload berkali-kali yang terasa berat +
// boros kuota. Di mobile pengguna cukup tekan Enter atau tombol cari
// (form tetap submit normal seperti biasa, cuma auto-submitnya yang mati).
// Breakpoint 720px sama dengan @media di style.css.
const input = document.getElementById('searchInput');
const form = document.getElementById('searchForm');
const isMobileViewport = window.matchMedia('(max-width: 720px)').matches;

if (!isMobileViewport) {
    let debounceTimer;
    let lastQuery = input.value;
    // 600ms: cukup singkat untuk terasa responsif setelah pengguna
    // berhenti ngetik, tapi tetap ngasih jeda supaya tidak submit di
    // tengah pengguna masih mengetik kata yang sama (dibanding 1450ms
    // sebelumnya yang terasa lambat/telat).
    const DEBOUNCE_MS = 600;

    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const value = input.value;
        if (value === lastQuery) return; // tidak ada perubahan nyata, skip
        debounceTimer = setTimeout(() => {
            lastQuery = value;
            form.submit();
        }, DEBOUNCE_MS);
    });

    // Kalau form disubmit manual (Enter / tombol cari), batalkan timer
    // yang masih pending supaya tidak ada submit ganda / navigasi dobel.
    form.addEventListener('submit', () => clearTimeout(debounceTimer));
}

// Shortcut keyboard "/" untuk langsung fokus ke kolom pencarian, mirip
// GitHub/Slack. Diabaikan kalau fokus sedang ada di form field lain
// (input/textarea/select/contenteditable) supaya tidak mengganggu saat
// pengguna mengetik karakter "/" di tempat lain, dan diabaikan juga kalau
// ada modifier key (Ctrl/Alt/Meta) supaya tidak bentrok dengan shortcut
// browser bawaan.
document.addEventListener('keydown', (e) => {
    if (e.key !== '/' || e.ctrlKey || e.altKey || e.metaKey) return;
    const active = document.activeElement;
    const tag = active ? active.tagName : '';
    const isEditable = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT'
        || (active && active.isContentEditable);
    if (isEditable) return;
    e.preventDefault();
    input.focus();
    input.select();
});

// Daftarkan service worker supaya katalog bisa di-"Add to Home Screen" /
// di-install sebagai app (PWA). SW ini SENGAJA cuma cache aset statis
// (CSS/font/ikon) - lihat komentar di sw.php untuk alasannya (harga & stok
// tidak boleh disajikan dari cache basi).
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.php').catch(() => {
            // Gagal daftar SW (mis. browser lama) - abaikan saja, situs
            // tetap berfungsi normal tanpa fitur install/offline.
        });
    });
}

// Infinite scroll: load-more-wrap sekarang satu-satunya navigasi (pagination
// klasik sudah dihapus). Perlu JavaScript aktif untuk melihat item di luar
// batch pertama.
(() => {
    const loadMoreWrap = document.getElementById('loadMoreWrap');
    if (!loadMoreWrap || !window.fetch) return;

    const catalogList = document.querySelector('.catalog-list');
    const loadMoreBtn = document.getElementById('loadMoreBtn');
    const statusEl     = document.getElementById('loadMoreStatus');

    let currentPage = parseInt(loadMoreWrap.dataset.page, 10) || 1;
    const totalPages = parseInt(loadMoreWrap.dataset.totalPages, 10) || 1;
    const baseQs = loadMoreWrap.dataset.qs || '';
    let loading = false;

    function updateVisibility() {
        loadMoreWrap.hidden = currentPage >= totalPages;
    }
    updateVisibility();

    async function loadMore() {
        if (loading || currentPage >= totalPages) return;
        loading = true;
        loadMoreBtn.disabled = true;
        statusEl.textContent = 'Memuat…';

        const nextPage = currentPage + 1;
        const qs = (baseQs ? baseQs + '&' : '') + 'p=' + nextPage + '&ajax=1';

        try {
            const res = await fetch('index.php?' + qs);
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const html = await res.text();
            if (html.trim() !== '') {
                catalogList.insertAdjacentHTML('beforeend', html);
            }
            currentPage = nextPage;
            statusEl.textContent = '';
            updateVisibility();
        } catch (e) {
            statusEl.textContent = 'Gagal memuat item berikutnya. Coba lagi.';
        } finally {
            loading = false;
            loadMoreBtn.disabled = false;
        }
    }

    loadMoreBtn.addEventListener('click', loadMore);

    // Auto-load saat tombol/area ini mulai kelihatan di layar (efek "infinite
    // scroll"), sambil tombolnya tetap ada & bisa diklik manual sebagai
    // fallback (juga lebih ramah pembaca layar daripada auto-load murni).
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) loadMore();
            });
        }, { rootMargin: '600px 0px' });
        observer.observe(loadMoreWrap);
    }
})();
</script>
</body>
</html>
