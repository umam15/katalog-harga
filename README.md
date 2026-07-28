# Katalog Harga iPos5
Katalog Harga versi web, untuk aplikasi POS iPos5

## Fitur
- Cari & scan item (nama, merek, kode, jenis, barcode) dengan hasil otomatis (debounced), lengkap dengan gambar produk dan harga.
- Pilih kantor/gudang aktif, harga & stok menyesuaikan otomatis.
- Halaman detail per item: daftar harga per satuan, barcode, dan info stok.
- Panel admin untuk mengatur koneksi database, akun pengguna, dan tampilan katalog publik.

## Peran pengguna
| Peran | Login? | Akses |
|---|---|---|
| **Admin** | Ya | Akses penuh: katalog lengkap + panel admin (pengaturan database, manajemen pengguna, pengaturan tampilan). |
| **User** | Ya | Akses terbatas: katalog lengkap (tanpa filter tampilan umum), tapi tidak bisa masuk panel admin. |
| **Umum** | Tidak | Pengunjung tanpa login. Katalog yang dilihat mengikuti pengaturan tampilan yang diatur admin (kantor default, tipe item, dan status stok kosong) dan tidak bisa mengganti kantor. |

Tombol **Login** ada di pojok kanan atas, di sebelah kotak pencarian. Akun pertama yang dibuat (lewat halaman login saat belum ada admin) otomatis menjadi admin.

## Pengaturan tampilan (khusus untuk umum)
Diatur admin lewat **Panel Admin -> Pengaturan Tampilan**:
- **Kantor default untuk umum** - kantor/gudang yang ditampilkan ke pengunjung tanpa login saat pertama kali membuka katalog.
- **Tipe item yang ditampilkan** - batasi tipe/jenis item yang muncul di katalog umum. Kosongkan semua untuk menampilkan seluruh tipe.
- **Tampilkan item stok kosong** - default **tidak** (item dengan stok 0 disembunyikan dari pengunjung umum).

Admin dan user yang login selalu melihat katalog lengkap tanpa batasan-batasan di atas.

## Struktur file
```
katalog-harga/
├── index.php            Katalog publik (pencarian, daftar item)
├── detail.php           Detail item (harga per satuan, barcode, stok)
├── image.php            Gambar produk (dari database)
├── config.php           Bootstrap halaman publik
├── maintenance.php      Halaman fallback saat koneksi database gagal
├── includes/
│   └── functions.php    Helper: pengaturan, autentikasi, koneksi DB, dsb.
├── admin/
│   ├── login.php         Login (admin & user) / setup akun admin pertama
│   ├── logout.php
│   ├── index.php         Dashboard admin
│   ├── database.php      Pengaturan koneksi PostgreSQL
│   ├── users.php         Manajemen akun (admin & user)
│   └── display.php       Pengaturan tampilan katalog umum
└── data/
    └── settings.sqlite   Pengaturan aplikasi & akun (dibuat otomatis)
```

## Instalasi & setup awal
1. Deploy semua file ke server PHP yang mendukung `pdo_pgsql` dan `pdo_sqlite`.
2. Pastikan folder `data/` bisa ditulis oleh web server (untuk `settings.sqlite`).
3. Buka `admin/login.php`, buat akun admin pertama.
4. Atur koneksi database di **Panel Admin -> Pengaturan Database**.
5. (Opsional) Atur tampilan katalog umum di **Panel Admin -> Pengaturan Tampilan**, dan tambah akun `user` di **Manajemen Pengguna** bila diperlukan.

## Kebutuhan sistem
- PHP dengan ekstensi `pdo_pgsql` dan `pdo_sqlite`
- Database katalog: PostgreSQL (iPos5)

## Changelog
### v1.1.1
- Tambah pengaturan tampilan untuk admin: kantor default untuk umum, tipe item yang ditampilkan, dan opsi tampilkan stok kosong (default tidak).
- Tambah peran pengguna: **admin** (akses penuh) dan **user** (bisa login, akses terbatas), selain **umum** (tanpa login).
