# Changelog

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
