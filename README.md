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

> **Catatan:** Docker menambah sedikit overhead resource dibanding instalasi native, tapi sejak `image.php` memakai cache disk (lihat [Performa](#performa) di bawah), bedanya kecil untuk katalog dengan banyak item bergambar. Pakai Docker kalau lebih mengutamakan kemudahan setup/isolasi.

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

## Performa

**Cache gambar.** `image.php` menyimpan salinan gambar produk (dan versi
thumbnail untuk daftar katalog) di `data/img-cache/` setelah pertama kali
diambil dari database, supaya request berikutnya tidak perlu buka koneksi
PostgreSQL lagi. Konsekuensinya: kalau foto produk diganti di iPos5 tapi
`kodeitem`-nya sama, perubahan itu **tidak otomatis muncul** selama cache
masih ada. Bersihkan lewat **Panel Admin -> Pengaturan Tampilan -> Bersihkan
cache gambar** setiap kali mengganti foto produk.

**Index database (opsional, untuk katalog dengan banyak item).** Query
katalog & detail sudah dioptimalkan di sisi SQL (LATERAL join, tanpa N+1),
tapi kecepatan akhirnya tetap tergantung index di sisi PostgreSQL iPos5.
Kalau katalog terasa lambat khususnya saat pencarian atau ganti kantor,
jalankan `EXPLAIN ANALYZE` pada query di `index.php`/`detail.php` dan
pertimbangkan index berikut kalau belum ada (sesuaikan nama index dengan
konvensi database kamu, dan uji dulu di database non-produksi):

```sql
-- Filter & join yang sering dipakai katalog
CREATE INDEX IF NOT EXISTS idx_itemstok_kantor_kodeitem ON tbl_itemstok (kantor, kodeitem);
CREATE INDEX IF NOT EXISTS idx_item_jenis               ON tbl_item (jenis);
CREATE INDEX IF NOT EXISTS idx_itemhj_kodeitem_satuan    ON tbl_itemhj (kodeitem, satuan);
CREATE INDEX IF NOT EXISTS idx_itemsatuanjml_kodeitem    ON tbl_itemsatuanjml (kodeitem);

-- Pencarian ILIKE '%kata%' tidak bisa pakai index B-tree biasa secara
-- optimal; kalau volume item besar dan pencarian terasa lambat,
-- pertimbangkan index trigram (butuh extension pg_trgm):
-- CREATE EXTENSION IF NOT EXISTS pg_trgm;
-- CREATE INDEX IF NOT EXISTS idx_item_namaitem_trgm ON tbl_item USING gin (namaitem gin_trgm_ops);
```

**OPcache & kompresi.** Sudah aktif otomatis lewat Dockerfile
(`docker-php-ext-enable opcache`, `a2enmod deflate expires headers`, plus
`.htaccess` di root). Kalau deploy native (bukan Docker), pastikan opcache
diaktifkan di `php.ini` dan modul Apache di atas ikut di-`a2enmod` +
`AllowOverride All` supaya `.htaccess` terbaca.

## Kebutuhan sistem
- PHP dengan ekstensi `pdo_pgsql`, `pdo_sqlite`
- Database katalog: PostgreSQL (iPos5)
- (Opsional) Docker & Docker Compose, jika memakai instalasi via Docker

## Changelog
Lihat [CHANGELOG.md](CHANGELOG.md).
