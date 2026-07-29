# TODO — Optimasi Performa (menuju v1.3)

Urutan berdasarkan dampak terhadap "beban" aplikasi, bukan urutan pengerjaan wajib.

## 🔴 Prioritas tinggi

- [x] **`image.php`: hindari koneksi PostgreSQL baru per gambar.**
  ✅ Selesai — gambar sekarang di-cache ke `data/img-cache/` setelah pertama
  kali diambil (di-hash dari kodeitem), request berikutnya baca dari file,
  cache miss saja yang sentuh database.

- [x] **Buat thumbnail terpisah dari gambar detail.**
  ✅ Selesai — `image.php?...&thumb=1` generate & cache versi ~160px lewat
  GD (fallback ke gambar penuh kalau GD tidak ada). `index.php` sudah
  dipindah untuk pakai `&thumb=1`, `detail.php` tetap pakai gambar penuh.

- [x] **Tambahkan ETag/Last-Modified di `image.php`.**
  ✅ Selesai — pakai `ETag` (`md5_file`) + `If-None-Match` -> `304`.

- [x] **Tombol "Bersihkan cache gambar" di admin.**
  ✅ Selesai — ada di Panel Admin -> Pengaturan Tampilan, lengkap dengan info
  jumlah file & ukuran cache saat ini.

## 🟠 Prioritas menengah

- [x] **Aktifkan OPcache di Dockerfile.**
  ✅ Selesai — `docker-php-ext-enable opcache` + `docker/opcache.ini`
  (`validate_timestamps=1`, `revalidate_freq=2` biar aman untuk update
  manual; bisa diganti `0` kalau deploy selalu lewat rebuild image).
- [x] **Aktifkan kompresi respons** (mod_deflate) untuk HTML/CSS/JS.
  ✅ Selesai — `.htaccess` di root + `a2enmod deflate expires headers` dan
  `AllowOverride All` di Dockerfile.
- [x] **Tambah header cache untuk aset statis** (`style.css`, `admin.css`,
  favicon).
  ✅ Selesai — sama, lewat `.htaccess` (`mod_expires` + `mod_headers`).
- [x] **Self-host font** (Space Grotesk/Inter/JetBrains Mono).
  ✅ Selesai — file woff2 di `fonts/` (diambil dari paket `@fontsource/*`,
  lisensi SIL OFL), semua halaman (publik & admin) sudah dialihkan dari
  `fonts.googleapis.com` ke `fonts/fonts.css` lokal.

## 🟡 Nice to have

- [x] **Evaluasi index database.**
  ✅ Didokumentasikan di README bagian "Performa" — saran `CREATE INDEX`
  untuk kolom yang sering dipakai `WHERE`/`JOIN`, plus opsi index trigram
  (`pg_trgm`) untuk pencarian `ILIKE` kalau volume item besar. Perlu
  dijalankan manual & diuji di database iPos5 masing-masing (saya tidak
  punya akses ke database aktual untuk `EXPLAIN ANALYZE`).
- [x] Pertimbangkan lazy-load infinite scroll sebagai alternatif pagination
  klasik.
  ✅ Selesai — pagination klasik dihapus total, diganti infinite scroll
  (`index.php` punya mode fragment `?ajax=1` + JS yang auto-load item
  berikutnya lewat `IntersectionObserver`, atau tombol "Muat lebih banyak"
  manual). **Catatan:** karena tidak ada fallback lagi, item di luar 50
  pertama butuh JavaScript aktif untuk diakses.
- [x] **Dokumentasikan cache gambar di README.**
  ✅ Selesai — bagian "Performa" menjelaskan cara kerja cache & kapan perlu
  dibersihkan manual.

## ✅ Sudah baik, tidak perlu diubah

- Query katalog: 1 query dengan `LATERAL JOIN` + `COUNT(*) OVER()` untuk
  pagination — sudah menghindari N+1 dan query count terpisah.
- Query detail: barcode diambil sekali lalu dikelompokkan di PHP — sudah
  menghindari N+1 loop.
- `get_settings_pdo()` pakai static singleton per-request — sudah tepat.
