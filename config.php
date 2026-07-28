<?php
// Bootstrap halaman publik (index, detail, image).
// Kredensial database TIDAK lagi disimpan di sini - lihat includes/functions.php
// dan admin/database.php. Pengaturan tersimpan di data/settings.sqlite.

define('ROOT_PATH', __DIR__);
require_once ROOT_PATH . '/includes/functions.php';

ensure_session();

try {
    $pdo = get_pgsql_pdo();
} catch (PDOException $e) {
    // Arahkan ke halaman maintenance jika koneksi gagal
    header('Location: maintenance.php');
    exit;
}
