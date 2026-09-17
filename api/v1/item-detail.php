<?php
// GET /api/v1/items/{kodeitem}?kantor=
// Detail satu item: info dasar + daftar harga per satuan + barcode + stok
// di satu kantor. Baca-saja, wajib API key.

define('ROOT_PATH', dirname(__DIR__, 2));
require_once ROOT_PATH . '/includes/functions.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    api_error(405, 'method_not_allowed', 'Hanya method GET yang didukung.');
}

$apiKey = api_authenticate();

$kodeitem = trim($_GET['kodeitem'] ?? '');
if ($kodeitem === '') {
    api_error(400, 'invalid_request', 'Kode item wajib diisi.');
}

try {
    $pdo = get_pgsql_pdo();
} catch (PDOException $e) {
    api_error(503, 'database_unavailable', 'Database katalog sedang tidak bisa diakses.');
}

$kantor = api_resolve_kantor($pdo, $apiKey, $_GET['kantor'] ?? null);

// Sama seperti detail.php: item + stok di kantor terpilih dalam satu query.
$sqlItem = "SELECT i.kodeitem, i.namaitem, i.satuan AS satuandasar, i.jenis, i.merek, i.keterangan, i.sistemhargajual, i.hargajual1, s.stok
            FROM tbl_item i
            JOIN tbl_itemstok s ON i.kodeitem = s.kodeitem
            WHERE i.kodeitem = ? AND s.kantor = ?";
$stmt = $pdo->prepare($sqlItem);
$stmt->execute([$kodeitem, $kantor]);
$item = $stmt->fetch();

if (!$item) {
    api_error(404, 'item_not_found', "Item '$kodeitem' tidak ditemukan di kantor '$kantor'.");
}

$hargaList = get_item_harga_list($pdo, $kodeitem, $item['sistemhargajual'], (float) $item['hargajual1'], $item['satuandasar']);

if (get_bulatkan_harga_detail()) {
    $pembulatan = get_harga_pembulatan();
    foreach ($hargaList as &$hl) {
        $hl['harga'] = bulatkan_harga($hl['harga'], $pembulatan);
    }
    unset($hl);
}

api_json([
    'kodeitem'     => $item['kodeitem'],
    'nama'         => $item['namaitem'],
    'merek'        => $item['merek'],
    'jenis'        => $item['jenis'],
    'keterangan'   => $item['keterangan'],
    'satuan_dasar' => $item['satuandasar'],
    'stok'         => (float) $item['stok'],
    'kantor'       => $kantor,
    'harga_list'   => $hargaList,
]);
