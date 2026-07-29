# Changelog

## v1.2.7
- **Fallback tanpa JavaScript** untuk navigasi katalog: kalau JS nonaktif,
  tombol "Muat lebih banyak"/infinite scroll disembunyikan dan diganti link
  klasik Sebelumnya/Berikutnya (`<noscript>`) yang tetap bekerja lewat
  parameter `?p=`. Sengaja dibuat minimal (cuma dua link + info halaman,
  bukan daftar nomor halaman lengkap) supaya ringan untuk katalog dengan
  banyak halaman.
- **Optimalkan jumlah item per halaman**: dinaikkan dari 50 menjadi 80,
  supaya pengguna tanpa JS butuh lebih sedikit klik untuk menjelajah
  katalog. Query & gambar sudah cukup ringan (LATERAL join, cache gambar,
  lazy loading) untuk menampung batch lebih besar tanpa dampak berarti.
- README dipersingkat, termasuk memangkas catatan overhead performa Docker
  yang tidak lagi krusial (image cache sudah membuat bedanya kecil).
- Tambah `.gitignore` (baru) dan update `.dockerignore`: `TODO.md` (catatan
  kerja internal) tidak lagi ikut ke repo git maupun image Docker.

## v1.2.6
- **Pagination klasik dihapus** dari `index.php` — infinite scroll (tombol
  "Muat lebih banyak" + auto-load saat scroll) sekarang jadi satu-satunya
  cara melihat item di luar 50 pertama. Menyederhanakan halaman (HTML lebih
  ringkas, tidak ada dua sistem navigasi paralel), dengan konsekuensi:
  **butuh JavaScript aktif** untuk mengakses item di luar batch pertama.
- **Infinite scroll di katalog** (`index.php`): item baru otomatis dimuat
  saat scroll ke bawah, tanpa reload halaman penuh - lewat endpoint fragment
  (`?ajax=1`) yang cuma mengembalikan baris item, bukan seluruh HTML.
  Tombol "Muat lebih banyak" manual juga selalu ada (auto-load pakai
  `IntersectionObserver`, tombol untuk kontrol eksplisit / pembaca layar).
- Tambah tombol **"Bersihkan cache gambar"** di Panel Admin -> Pengaturan
  Tampilan, plus info jumlah file & ukuran cache saat ini.
- Tambah `.htaccess` di root: kompresi gzip (HTML/CSS/JS) dan cache header
  untuk aset statis (CSS/JS/ikon). Dockerfile mengaktifkan modul Apache
  `deflate`, `expires`, `headers` dan `AllowOverride All` supaya
  `.htaccess` benar-benar dipakai.
- **Font di-self-host** (Space Grotesk, Inter, JetBrains Mono, format
  woff2, lisensi SIL OFL - lihat `fonts/LICENSE.txt`), menggantikan request
  ke `fonts.googleapis.com`/`fonts.gstatic.com` yang sebelumnya dipanggil
  di setiap halaman (index, detail, semua halaman admin).
- **Performa besar di `image.php`**: gambar produk (termasuk thumbnail di
  daftar katalog) sekarang di-cache ke `data/img-cache/` setelah pertama
  kali diambil. Sebelumnya SETIAP gambar membuka koneksi PostgreSQL baru -
  satu halaman katalog dengan 50 item = 50 koneksi DB. Sekarang hanya cache
  miss yang menyentuh database.
- Tambah thumbnail terpisah (maks ~160px, kualitas 75) untuk daftar katalog
  lewat ekstensi GD, dipakai lewat `image.php?...&thumb=1`. Kalau GD tidak
  tersedia, otomatis fallback ke gambar ukuran penuh (tetap jalan, cuma
  tidak seringan dengan thumbnail).
- Tambah dukungan `ETag`/`If-None-Match` di `image.php` supaya browser bisa
  dapat `304 Not Modified` tanpa transfer ulang gambar setelah cache 1 hari
  di browser habis.
- Dockerfile: tambah ekstensi `gd` (untuk thumbnail) dan aktifkan `opcache`
  dengan konfigurasi di `docker/opcache.ini`.

## v1.2
- Tambah dukungan Docker: `Dockerfile` & `docker-compose.yml` untuk menjalankan aplikasi via `docker compose up -d --build` (PHP 8.2 + Apache, ekstensi `pdo_pgsql` & `pdo_sqlite` sudah termasuk, folder `data/` dipersist lewat Docker volume).
- Tambah panduan instalasi via Docker di README.
- Perbaiki `Dockerfile`: hapus langkah purge `libpq-dev` setelah build, karena `apt-get purge --auto-remove` ikut menghapus `libpq5` (runtime lib untuk `pdo_pgsql`) dan menyebabkan error "could not find driver".
- Perbaiki `admin/index.php`: kartu Dashboard "Pengaturan Database" menampilkan "Belum diatur" (bukan warning deprecated) saat koneksi database belum pernah disetting.
- Perbaiki `admin/database.php`: field Host/Port/Nama Database/User di form Pengaturan Database default ke string kosong (bukan `null`) saat koneksi belum pernah disimpan, supaya tidak muncul warning deprecated `htmlspecialchars()`.
- Ganti nama file penyimpanan pengaturan dari `data/settings.sqlite` menjadi `data/settings.db`.

## v1.1.7
- Tambah opsi **Backup & Restore** untuk admin (`admin/backup.php`): ekspor seluruh pengaturan aplikasi (termasuk kredensial database) ke file JSON, dan restore dari file tersebut. Akun login (admin/user) tidak termasuk di backup.

## v1.1.6
- Perbaiki `admin/display.php`: koneksi database yang belum di-setting atau gagal terhubung dulu menyebabkan fatal error, sekarang tampil pesan error yang mengarahkan ke Pengaturan Database.

## v1.1.5
- Tambah pengaturan pembulatan harga (ceil) di katalog, opsional diterapkan juga di halaman detail.

## v1.1.1
- Tambah pengaturan tampilan untuk admin (kantor default, tipe item, stok kosong).
- Tambah peran pengguna **admin** dan **user**, selain **umum** (tanpa login).
