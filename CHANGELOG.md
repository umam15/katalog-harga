# Changelog

## [1.3.0] - 2026-08-03

### Added
- **Pencarian cepat `[/]`** di katalog publik (`index.php`): tekan `/` di
  mana saja pada halaman untuk langsung fokus + select ke kolom
  pencarian, mirip shortcut di GitHub/Slack. Diabaikan otomatis kalau
  fokus sedang ada di input/textarea/select/elemen contenteditable lain,
  atau ada modifier key (Ctrl/Alt/Meta) yang ditekan bareng, supaya tidak
  bentrok dengan pengetikan normal atau shortcut browser.
- Badge kecil `[/]` di ujung kanan kolom pencarian sebagai petunjuk
  visual shortcut di atas, otomatis hilang saat kolom fokus/sudah berisi
  teks, dan disembunyikan di layar mobile (<720px) supaya tidak
  mengganggu tombol clear bawaan `input[type=search]` maupun keyboard
  virtual.

### Changed
- `APP_VERSION` dinaikkan ke `1.3.0` supaya cache aset statis service
  worker (termasuk `style.css` yang berisi styling badge shortcut di
  atas) ikut ter-invalidate untuk pengguna yang sudah install PWA-nya -
  lihat catatan strategi cache di `sw.php`.

## [1.2.9]

### Added
- **Bisa di-install sebagai app (PWA)**: tambah `manifest.json` + ikon
  (`icons/icon-192.png`, `icons/icon-512.png`, versi maskable) supaya
  katalog bisa di-"Add to Home Screen"/di-install lewat browser (ikon
  sendiri, tampil `standalone` tanpa address bar).
- Service worker (`sw.php`) untuk syarat instalasi PWA. **Sengaja cuma
  cache aset statis** (CSS, font, ikon, manifest) - `index.php`,
  `detail.php`, `image.php`, endpoint `?ajax=1`, dan semua `admin/*`
  SELALU lewat jaringan langsung, tidak pernah disajikan dari cache,
  supaya harga & stok yang tampil selalu data terbaru (tidak ada risiko
  data basi ala cache offline). Dibuat `.php` (bukan `.js` statis) supaya
  nama cache otomatis ikut naik tiap rilis lewat `APP_VERSION`, jadi
  klien lama otomatis dibersihkan tanpa perlu bump versi manual di kode
  service worker.
- `<link rel="manifest">`, `theme-color`, dan `apple-touch-icon` di
  `index.php` & `detail.php` (dua halaman yang bisa jadi entry point,
  termasuk landing langsung dari scan barcode/QR ke halaman detail).

## [1.2.8]

### Added
- Versi rilis di aplikasi: konstanta `APP_VERSION` (di
  `includes/functions.php`) sekarang ditampilkan di Panel Admin ->
  Dashboard, supaya versi yang berjalan di server bisa dicek langsung dari
  UI tanpa buka `CHANGELOG.md`.

## [1.2.7]

### Added
- **Fallback tanpa JavaScript** untuk navigasi katalog: kalau JS nonaktif,
  tombol "Muat lebih banyak"/infinite scroll disembunyikan dan diganti link
  klasik Sebelumnya/Berikutnya (`<noscript>`) yang tetap bekerja lewat
  parameter `?p=`. Sengaja dibuat minimal (cuma dua link + info halaman,
  bukan daftar nomor halaman lengkap) supaya ringan untuk katalog dengan
  banyak halaman.
- `.gitignore` (baru); update `.dockerignore`: `TODO.md` (catatan kerja
  internal) tidak lagi ikut ke repo git maupun image Docker.

### Changed
- **Optimalkan jumlah item per halaman**: dinaikkan dari 50 menjadi 80,
  supaya pengguna tanpa JS butuh lebih sedikit klik untuk menjelajah
  katalog. Query & gambar sudah cukup ringan (LATERAL join, cache gambar,
  lazy loading) untuk menampung batch lebih besar tanpa dampak berarti.
- README dipersingkat, termasuk memangkas catatan overhead performa Docker
  yang tidak lagi krusial (image cache sudah membuat bedanya kecil).

## [1.2.6]

### Added
- **Infinite scroll di katalog** (`index.php`): item baru otomatis dimuat
  saat scroll ke bawah, tanpa reload halaman penuh - lewat endpoint fragment
  (`?ajax=1`) yang cuma mengembalikan baris item, bukan seluruh HTML.
  Tombol "Muat lebih banyak" manual juga selalu ada (auto-load pakai
  `IntersectionObserver`, tombol untuk kontrol eksplisit / pembaca layar).
- Tombol **"Bersihkan cache gambar"** di Panel Admin -> Pengaturan
  Tampilan, plus info jumlah file & ukuran cache saat ini.
- `.htaccess` di root: kompresi gzip (HTML/CSS/JS) dan cache header untuk
  aset statis (CSS/JS/ikon). Dockerfile mengaktifkan modul Apache
  `deflate`, `expires`, `headers` dan `AllowOverride All` supaya
  `.htaccess` benar-benar dipakai.
- Thumbnail terpisah (maks ~160px, kualitas 75) untuk daftar katalog lewat
  ekstensi GD, dipakai lewat `image.php?...&thumb=1`. Kalau GD tidak
  tersedia, otomatis fallback ke gambar ukuran penuh (tetap jalan, cuma
  tidak seringan dengan thumbnail).
- Dukungan `ETag`/`If-None-Match` di `image.php` supaya browser bisa dapat
  `304 Not Modified` tanpa transfer ulang gambar setelah cache 1 hari di
  browser habis.
- Dockerfile: ekstensi `gd` (untuk thumbnail) dan aktifkan `opcache`
  dengan konfigurasi di `docker/opcache.ini`.

### Changed
- **Font di-self-host** (Space Grotesk, Inter, JetBrains Mono, format
  woff2, lisensi SIL OFL - lihat `fonts/LICENSE.txt`), menggantikan
  request ke `fonts.googleapis.com`/`fonts.gstatic.com` yang sebelumnya
  dipanggil di setiap halaman (index, detail, semua halaman admin).
- **Performa besar di `image.php`**: gambar produk (termasuk thumbnail di
  daftar katalog) sekarang di-cache ke `data/img-cache/` setelah pertama
  kali diambil. Sebelumnya SETIAP gambar membuka koneksi PostgreSQL baru -
  satu halaman katalog dengan 50 item = 50 koneksi DB. Sekarang hanya
  cache miss yang menyentuh database.

### Removed
- **Pagination klasik dihapus** dari `index.php` — infinite scroll
  (tombol "Muat lebih banyak" + auto-load saat scroll) sekarang jadi
  satu-satunya cara melihat item di luar 50 pertama. Menyederhanakan
  halaman (HTML lebih ringkas, tidak ada dua sistem navigasi paralel),
  dengan konsekuensi: **butuh JavaScript aktif** untuk mengakses item di
  luar batch pertama.

## [1.2.0]

### Added
- Dukungan Docker: `Dockerfile` & `docker-compose.yml` untuk menjalankan
  aplikasi via `docker compose up -d --build` (PHP 8.2 + Apache, ekstensi
  `pdo_pgsql` & `pdo_sqlite` sudah termasuk, folder `data/` dipersist
  lewat Docker volume).
- Panduan instalasi via Docker di README.

### Changed
- Ganti nama file penyimpanan pengaturan dari `data/settings.sqlite`
  menjadi `data/settings.db`.

### Fixed
- `Dockerfile`: hapus langkah purge `libpq-dev` setelah build, karena
  `apt-get purge --auto-remove` ikut menghapus `libpq5` (runtime lib
  untuk `pdo_pgsql`) dan menyebabkan error "could not find driver".
- `admin/index.php`: kartu Dashboard "Pengaturan Database" menampilkan
  "Belum diatur" (bukan warning deprecated) saat koneksi database belum
  pernah disetting.
- `admin/database.php`: field Host/Port/Nama Database/User di form
  Pengaturan Database default ke string kosong (bukan `null`) saat
  koneksi belum pernah disimpan, supaya tidak muncul warning deprecated
  `htmlspecialchars()`.

## [1.1.7]

### Added
- Opsi **Backup & Restore** untuk admin (`admin/backup.php`): ekspor
  seluruh pengaturan aplikasi (termasuk kredensial database) ke file
  JSON, dan restore dari file tersebut. Akun login (admin/user) tidak
  termasuk di backup.

## [1.1.6]

### Fixed
- `admin/display.php`: koneksi database yang belum di-setting atau gagal
  terhubung dulu menyebabkan fatal error, sekarang tampil pesan error
  yang mengarahkan ke Pengaturan Database.

## [1.1.5]

### Added
- Pengaturan pembulatan harga (ceil) di katalog, opsional diterapkan juga
  di halaman detail.

## [1.1.1]

### Added
- Pengaturan tampilan untuk admin (kantor default, tipe item, stok
  kosong).
- Peran pengguna **admin** dan **user**, selain **umum** (tanpa login).

## [0.1.0] - 2026-01-21

- Initial commit