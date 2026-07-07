<?php
// Kredensial bisa dioverride lewat environment variable (lebih aman untuk deployment),
// dengan fallback ke nilai default agar tetap kompatibel dengan setup yang sudah ada.
$host     = getenv('DB_HOST') ?: 'xvm12.sumberbarokah.com';
$port     = getenv('DB_PORT') ?: '5444';
$dbname   = getenv('DB_NAME') ?: 'i5_SBM';
$user     = getenv('DB_USER') ?: 'sysi5adm';
$password = getenv('DB_PASS') ?: 'u&aV23cc.o82dtr1x89c';

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // hilangkan pengulangan FETCH_ASSOC di tiap query
        PDO::ATTR_EMULATE_PREPARES   => false,            // prepared statement asli, lebih aman & sedikit lebih cepat
        PDO::ATTR_PERSISTENT         => false,
    ]);
} catch (PDOException $e) {
    // Arahkan ke halaman maintenance jika koneksi gagal
    header('Location: maintenance.php');
    exit;
}
