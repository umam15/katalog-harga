# Katalog Harga iPos5
Katalog Harga versi web, untuk aplikasi POS iPos5.

## Fitur
- Cari & scan item (nama, merek, kode, jenis, barcode) dengan hasil otomatis, lengkap gambar & harga.
- Pilih kantor/gudang aktif, harga & stok menyesuaikan otomatis.
- Halaman detail per item: harga per satuan, barcode, dan stok.
- Panel admin: koneksi database, akun pengguna, dan pengaturan tampilan katalog umum (kantor default, tipe item, stok kosong, pembulatan harga).

## Peran pengguna
| Peran | Login? | Akses |
|---|---|---|
| **Admin** | Ya | Akses penuh: katalog lengkap + panel admin. |
| **User** | Ya | Katalog lengkap (tanpa filter tampilan umum), tanpa akses panel admin. |
| **Umum** | Tidak | Katalog mengikuti pengaturan tampilan dari admin, tidak bisa ganti kantor. |

Tombol **Login** ada di pojok kanan atas. Akun pertama yang dibuat otomatis jadi admin.

## Struktur file
```
katalog-harga/
├── index.php / detail.php / image.php   Katalog publik
├── config.php           Bootstrap halaman publik
├── maintenance.php      Fallback saat koneksi database gagal
├── includes/functions.php   Helper: pengaturan, autentikasi, koneksi DB
├── admin/                Login, dashboard, pengaturan database/tampilan, manajemen user
└── data/settings.sqlite  Pengaturan aplikasi & akun (dibuat otomatis)
```

## Instalasi & setup awal
1. Deploy ke server PHP yang mendukung `pdo_pgsql` dan `pdo_sqlite`.
2. Pastikan folder `data/` bisa ditulis web server.
3. Buka `admin/login.php`, buat akun admin pertama.
4. Atur koneksi database di **Panel Admin -> Pengaturan Database**.
5. (Opsional) Atur tampilan katalog umum & tambah akun `user` bila diperlukan.

## Kebutuhan sistem
- PHP dengan ekstensi `pdo_pgsql`, `pdo_sqlite`, dan `zlib`
- Database katalog: PostgreSQL (iPos5)

## Changelog
### v1.1.6
- Perbaiki `admin/display.php`: koneksi database yang belum di-setting atau gagal terhubung dulu menyebabkan fatal error, sekarang tampil pesan error yang mengarahkan ke Pengaturan Database.

### v1.1.5
- Tambah pengaturan pembulatan harga (ceil) di katalog, opsional diterapkan juga di halaman detail.

### v1.1.1
- Tambah pengaturan tampilan untuk admin (kantor default, tipe item, stok kosong).
- Tambah peran pengguna **admin** dan **user**, selain **umum** (tanpa login).
