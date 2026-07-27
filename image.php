<?php
require_once 'config.php';

$id = $_GET['id'] ?? '';
if ($id === '') exit;

// Gambar produk jarang berubah -> aman untuk di-cache di browser selama 1 hari,
// mengurangi beban ke database untuk request gambar yang berulang (mis. saat scroll katalog).
$cacheHeaders = function () {
    header('Cache-Control: public, max-age=86400');
};

$sql = "SELECT encode(gambar, 'base64') AS data FROM tbl_item WHERE kodeitem = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$row = $stmt->fetch();

if ($row && !empty($row['data'])) {
    $cacheHeaders();
    header('Content-Type: image/jpeg');
    echo base64_decode($row['data']);
} else {
    // Placeholder 1x1 transparan jika item tidak punya gambar
    header('Content-Type: image/png');
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
}
