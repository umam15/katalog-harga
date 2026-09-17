<?php
// GET /api/v1/kantor
// Daftar kantor/gudang yang boleh diakses API key ini. Baca-saja, wajib
// API key. Tidak ada parameter - hasilnya sudah otomatis mengikuti scope
// key (lihat api_key_kantor_scope()), berguna buat client tahu kantor apa
// saja yang valid dipakai di ?kantor= pada endpoint lain sebelum menembak
// item/detail satu-satu.

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

$semuaKantor = get_kantor_list($pdo);
$scope = api_key_kantor_scope($apiKey); // [] = key ini boleh akses semua kantor

// Scope key cuma daftar nama kantor yang disimpan saat key dibuat - bisa
// saja sejak itu kantor tsb dihapus/berubah nama di sumber data. Selalu
// irisankan dengan kantor yang benar-benar ada sekarang, supaya tidak
// pernah membalas nama kantor "hantu" yang sudah tidak valid.
$kantor = empty($scope) ? $semuaKantor : array_values(array_intersect($semuaKantor, $scope));

api_json(['kantor' => $kantor]);
