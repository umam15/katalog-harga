<?php
// Service worker untuk PWA. Sengaja dibuat .php (bukan .js statis) supaya
// nama cache otomatis ikut naik tiap rilis (pakai APP_VERSION) - jadi tidak
// perlu inget bump versi manual tiap deploy, dan klien lama otomatis
// dibersihkan lewat 'activate' di bawah.
define('ROOT_PATH', __DIR__);
require_once ROOT_PATH . '/includes/functions.php';

header('Content-Type: application/javascript; charset=UTF-8');
// Wajib supaya scope service worker bisa root ('/'), bukan cuma folder sw.php.
header('Service-Worker-Allowed: ./');
// File SW sendiri jangan di-cache lama-lama oleh browser, biar update
// ke-detect cepat (browser tetap punya logika update sendiri di atas ini).
header('Cache-Control: no-cache');
?>
const CACHE_NAME = 'katalog-harga-static-v<?= APP_VERSION ?>';

// HANYA aset statis yang aman di-cache selamanya (tidak berubah per
// request, tidak mengandung harga/stok). Path relatif terhadap sw.php.
const STATIC_ASSETS = [
    'style.css',
    'fonts/fonts.css',
    'fonts/space-grotesk-latin-700-normal.woff2',
    'fonts/space-grotesk-latin-600-normal.woff2',
    'fonts/space-grotesk-latin-500-normal.woff2',
    'fonts/inter-latin-400-normal.woff2',
    'fonts/inter-latin-500-normal.woff2',
    'fonts/inter-latin-600-normal.woff2',
    'fonts/jetbrains-mono-latin-500-normal.woff2',
    'fonts/jetbrains-mono-latin-600-normal.woff2',
    'favicon.ico',
    'manifest.json',
    'icons/icon-192.png',
    'icons/icon-512.png',
    'icons/icon-maskable-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))
            ))
            .then(() => self.clients.claim())
    );
});

// PENTING: cache-first HANYA untuk STATIC_ASSETS di atas. Semua request lain
// - index.php, detail.php, image.php, endpoint ?ajax=1, seluruh admin/*,
// login, dsb - SENGAJA dibiarkan lewat langsung ke jaringan tanpa campur
// tangan service worker sama sekali. Ini bukan kelalaian: katalog ini
// menampilkan harga & stok real-time, jadi halaman-halaman itu TIDAK BOLEH
// pernah disajikan dari cache offline (bisa nunjukin harga/stok basi tanpa
// disadari pengguna). Kalau nanti mau dukungan offline yang lebih pintar
// (mis. cache-lalu-network untuk shell UI), pisahkan strategi-nya - jangan
// ubah default aman ini jadi cache-first untuk halaman dinamis.
self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    const isStaticAsset = STATIC_ASSETS.some((asset) => url.pathname.endsWith('/' + asset));
    if (!isStaticAsset) return;

    event.respondWith(
        caches.open(CACHE_NAME).then(async (cache) => {
            const cached = await cache.match(req);
            if (cached) return cached;
            try {
                const resp = await fetch(req);
                if (resp && resp.ok) cache.put(req, resp.clone());
                return resp;
            } catch (err) {
                // Offline & belum ke-cache - biarkan gagal secara normal,
                // browser yang tampilkan halaman offline default.
                throw err;
            }
        })
    );
});
