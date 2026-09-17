<?php
// GET /api/v1/items?q=&kantor=&limit=&offset=
// Cari item katalog beserta harga & stok di satu kantor. Baca-saja, wajib
// API key (lihat api_authenticate() di includes/functions.php).

define('ROOT_PATH', dirname(__DIR__, 2));
require_once ROOT_PATH . '/includes/functions.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    api_error(405, 'method_not_allowed', 'Hanya method GET yang didukung.');
}

$apiKey = api_authenticate();

try {
    $pdo = get_pgsql_pdo();
} catch (PDOException $e) {
    api_error(503, 'database_unavailable', 'Database katalog sedang tidak bisa diakses.');
}

$kantor = api_resolve_kantor($pdo, $apiKey, $_GET['kantor'] ?? null);
[$limit, $offset] = api_pagination_params();
$search = trim($_GET['q'] ?? '');

// Query sama seperti index.php (katalog publik): sistem 'O' -> hargajual1
// langsung, sistem 'S'/'L'/'J' -> harga dari tbl_itemhj pada satuan dasar,
// diambil tingkatan terendah lewat LATERAL join (1 baris harga per item,
// tanpa N+1 query). COUNT(*) OVER() sekalian menghitung total baris untuk
// pagination, tanpa query count terpisah.
$sql = "SELECT i.kodeitem, i.namaitem, i.merek, i.jenis, i.satuan AS satuan_dasar, s.stok,
               CASE WHEN UPPER(i.sistemhargajual) = 'O' THEN i.hargajual1 ELSE hj.hargajual END AS harga,
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

if ($search !== '') {
    $sql .= " AND (i.namaitem ILIKE ? OR i.merek ILIKE ? OR i.keterangan ILIKE ? OR i.jenis ILIKE ? OR i.kodeitem ILIKE ?
               OR EXISTS (
                    SELECT 1 FROM tbl_itemsatuanjml b
                    WHERE b.kodeitem = i.kodeitem AND b.kodebarcode ILIKE ?
               ))";
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like, $like, $like);
}

// $limit/$offset sudah divalidasi & dipaksa integer oleh api_pagination_params(),
// aman disisipkan langsung (konsisten dengan pola yang sama di index.php).
$sql .= " ORDER BY i.kodeitem ASC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$totalRows = (int) ($rows[0]['total_rows'] ?? 0);
$hargaPembulatan = get_harga_pembulatan();

$items = array_map(static function (array $row) use ($hargaPembulatan): array {
    return [
        'kodeitem'     => $row['kodeitem'],
        'nama'         => $row['namaitem'],
        'merek'        => $row['merek'],
        'jenis'        => $row['jenis'],
        'satuan_dasar' => $row['satuan_dasar'],
        'stok'         => (float) $row['stok'],
        'harga'        => (float) bulatkan_harga((float) $row['harga'], $hargaPembulatan),
    ];
}, $rows);

api_json([
    'kantor'     => $kantor,
    'items'      => $items,
    'pagination' => [
        'limit'  => $limit,
        'offset' => $offset,
        'total'  => $totalRows,
    ],
]);
