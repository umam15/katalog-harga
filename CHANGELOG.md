# Changelog

## v1.1.7
- Tambah opsi **Backup & Restore** untuk admin (`admin/backup.php`): ekspor seluruh pengaturan aplikasi (termasuk kredensial database) ke file JSON, dan restore dari file tersebut. Akun login (admin/user) tidak termasuk di backup.

## v1.1.6
- Perbaiki `admin/display.php`: koneksi database yang belum di-setting atau gagal terhubung dulu menyebabkan fatal error, sekarang tampil pesan error yang mengarahkan ke Pengaturan Database.

## v1.1.5
- Tambah pengaturan pembulatan harga (ceil) di katalog, opsional diterapkan juga di halaman detail.

## v1.1.1
- Tambah pengaturan tampilan untuk admin (kantor default, tipe item, stok kosong).
- Tambah peran pengguna **admin** dan **user**, selain **umum** (tanpa login).
