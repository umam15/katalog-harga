<?php
// Serve gambar produk. Sebelumnya file ini membuka koneksi PostgreSQL baru
// untuk SETIAP gambar (berat kalau satu halaman katalog menampilkan 50
// thumbnail sekaligus). Sekarang gambar disimpan sebagai cache di
// data/img-cache/ setelah pertama kali diambil dari DB - request berikutnya
// untuk kodeitem yang sama cukup baca file dari disk, tanpa sentuh database.
//
// Parameter:
//   ?id=<kodeitem>        (wajib)
//   &thumb=1               minta versi thumbnail kecil (dipakai daftar katalog)
require_once __DIR__ . '/includes/functions.php';

if (!defined('ROOT_PATH')) define('ROOT_PATH', __DIR__);

$id = $_GET['id'] ?? '';
if ($id === '') { http_response_code(400); exit; }

$wantThumb = isset($_GET['thumb']);
$paths     = img_cache_paths($id);
$cacheFile = $wantThumb ? $paths['thumb'] : $paths['full'];

/** Placeholder 1x1 transparan untuk item tanpa gambar. */
function serve_placeholder(): void {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
}

/**
 * Deteksi MIME image sebenarnya dari file di disk - foto produk di database
 * bisa saja PNG/WEBP/GIF, bukan cuma JPEG, jadi tidak boleh diasumsikan.
 * Pakai getimagesize() (bukan …fromstring): cuma baca header file secukupnya
 * untuk kenali format, bukan seluruh isi file - murah walau filenya besar.
 * Fallback ke image/jpeg kalau gagal dideteksi (format tak dikenal/rusak),
 * sama seperti perilaku lama.
 */
function detect_image_mime(string $path): string {
    $info = @getimagesize($path);
    return ($info && !empty($info['mime'])) ? $info['mime'] : 'image/jpeg';
}

/**
 * Serve file cache di disk, dengan dukungan ETag/If-None-Match supaya
 * browser bisa dapat 304 (tanpa transfer ulang byte gambar) setelah
 * max-age 1 hari habis, tanpa perlu balik ke database.
 * $mime: pakai nilai ini langsung kalau sudah pasti diketahui (mis. file
 * thumbnail selalu JPEG dari make_thumbnail()) - hindari deteksi berulang
 * yang tidak perlu. null berarti deteksi otomatis (dipakai untuk file
 * "full" yang formatnya mengikuti data asli di database).
 */
function serve_cached_file(string $path, ?string $mime = null): void {
    $etag = '"' . md5_file($path) . '"';
    header('Cache-Control: public, max-age=86400');
    header('ETag: ' . $etag);

    $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
    if ($ifNoneMatch !== '' && trim($ifNoneMatch) === $etag) {
        http_response_code(304);
        return;
    }

    header('Content-Type: ' . ($mime ?? detect_image_mime($path)));
    header('Content-Length: ' . filesize($path));
    readfile($path);
}

// --- 1) Sudah diketahui item ini tidak punya gambar -> langsung placeholder,
//        tanpa sentuh database sama sekali.
if (file_exists($paths['none'])) {
    serve_placeholder();
    exit;
}

// --- 2) Ada di cache -> serve dari disk, tanpa sentuh database.
if (file_exists($cacheFile)) {
    serve_cached_file($cacheFile, $wantThumb ? 'image/jpeg' : null);
    exit;
}
// Kalau yang diminta thumbnail tapi belum ada (mis. GD tidak tersedia saat
// dibuat) namun versi full sudah ada, pakai versi full saja daripada balik
// ke database.
if ($wantThumb && file_exists($paths['full'])) {
    serve_cached_file($paths['full']);
    exit;
}

// --- 3) Cache miss -> baru sentuh database, lalu simpan hasilnya ke cache.
try {
    $pdo = get_pgsql_pdo();
} catch (PDOException $e) {
    serve_placeholder();
    exit;
}

$stmt = $pdo->prepare("SELECT encode(gambar, 'base64') AS data FROM tbl_item WHERE kodeitem = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row || empty($row['data'])) {
    ensure_img_cache_dir();
    @touch($paths['none']);
    serve_placeholder();
    exit;
}

$binary = base64_decode($row['data']);

ensure_img_cache_dir();
atomic_file_put_contents($paths['full'], $binary);

$thumbData = make_thumbnail($binary);
if ($thumbData !== null) {
    atomic_file_put_contents($paths['thumb'], $thumbData);
}

$serveThumb = $wantThumb && $thumbData !== null;
serve_cached_file($serveThumb ? $paths['thumb'] : $paths['full'], $serveThumb ? 'image/jpeg' : null);
