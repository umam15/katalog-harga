<?php
// GET /api/v1/health
// Endpoint monitoring/uptime - sengaja TANPA autentikasi dan TIDAK
// tunduk pada toggle "Aktifkan API" (lihat api_authenticate()), karena
// ini soal status infrastruktur, bukan bagian dari fitur API katalog itu
// sendiri. Tidak pernah membocorkan data katalog ataupun detail error -
// cuma status up/down.

define('ROOT_PATH', dirname(__DIR__, 2));
require_once ROOT_PATH . '/includes/functions.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    api_error(405, 'method_not_allowed', 'Hanya method GET yang didukung.');
}

$databaseOk = true;
try {
    get_pgsql_pdo()->query('SELECT 1');
} catch (PDOException $e) {
    $databaseOk = false; // detail error sengaja tidak diteruskan ke response
}

api_json([
    'status'   => $databaseOk ? 'ok' : 'degraded',
    'database' => $databaseOk ? 'ok' : 'unreachable',
    'time'     => date('c'),
], $databaseOk ? 200 : 503);
