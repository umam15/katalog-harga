# Katalog Harga iPos5
Katalog Harga versi web untuk aplikasi POS iPos5 — pencarian item, harga & stok per kantor/gudang, plus panel admin.

## Fitur
- Cari & scan item (nama, merek, kode, jenis, barcode), lengkap gambar & harga.
- Pilih kantor/gudang aktif, harga & stok menyesuaikan otomatis.
- Halaman detail per item: harga per satuan, barcode, dan stok.
- Panel admin: koneksi database, akun pengguna, pengaturan tampilan katalog.

## Peran pengguna
| Peran | Login? | Akses |
|---|---|---|
| **Admin** | Ya | Katalog lengkap + panel admin. |
| **User** | Ya | Katalog lengkap, tanpa akses panel admin. |
| **Umum** | Tidak | Katalog sesuai pengaturan admin, tidak bisa ganti kantor. |

Tombol **Login** ada di pojok kanan atas. Akun pertama yang dibuat otomatis jadi admin.

## Instalasi
1. Deploy ke server PHP yang mendukung `pdo_pgsql`, `pdo_sqlite`, dan `zlib`.
2. Pastikan folder `data/` bisa ditulis web server.
3. Buka `admin/login.php`, buat akun admin pertama.
4. Atur koneksi database di **Panel Admin -> Pengaturan Database**.
5. (Opsional) Atur tampilan katalog & tambah akun `user`.

## Kebutuhan sistem
- PHP dengan ekstensi `pdo_pgsql`, `pdo_sqlite`, `zlib`
- Database katalog: PostgreSQL (iPos5)

## Changelog
Lihat [CHANGELOG.md](CHANGELOG.md).
