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
1. Deploy ke server PHP yang mendukung `pdo_pgsql` dan `pdo_sqlite`.
2. Pastikan folder `data/` bisa ditulis web server.
3. Buka `admin/login.php`, buat akun admin pertama.
4. Atur koneksi database di **Panel Admin -> Pengaturan Database**.
5. (Opsional) Atur tampilan katalog & tambah akun `user`.

### Instalasi dengan Docker
Cara tercepat menjalankan aplikasi tanpa setup PHP manual.

> **Catatan:** Docker menambah overhead resource. Untuk katalog dengan banyak item bergambar (`image.php` encode/decode base64 dari BLOB PostgreSQL tiap request, cukup berat), instalasi native lewat langkah **Instalasi** di atas lebih ringan untuk server dengan resource terbatas. Pakai Docker kalau lebih mengutamakan kemudahan setup/isolasi.

1. Pastikan [Docker](https://docs.docker.com/get-docker/) & Docker Compose sudah terpasang.
2. Dari folder project, jalankan:
   ```bash
   docker compose up -d --build
   ```
3. Buka `http://localhost:8080` di browser. Halaman awal akan mengarah ke `maintenance.php` sampai database katalog di-setting.
4. Buka `http://localhost:8080/admin/login.php`, buat akun admin pertama.
5. Atur koneksi database di **Panel Admin -> Pengaturan Database** (arahkan ke server PostgreSQL iPos5 kamu; kalau Postgres-nya juga jalan di Docker di komputer yang sama, pakai `host.docker.internal` sebagai host).

Data pengaturan & akun (`data/settings.db`) disimpan di Docker volume `katalog-data` supaya tidak hilang saat container dibuat ulang.

Perintah lain yang berguna:
```bash
docker compose logs -f     # lihat log
docker compose down        # hentikan & hapus container (volume tetap ada)
docker compose down -v     # hentikan & hapus container + volume (reset total)
```

## Kebutuhan sistem
- PHP dengan ekstensi `pdo_pgsql`, `pdo_sqlite`
- Database katalog: PostgreSQL (iPos5)
- (Opsional) Docker & Docker Compose, jika memakai instalasi via Docker

## Changelog
Lihat [CHANGELOG.md](CHANGELOG.md).
