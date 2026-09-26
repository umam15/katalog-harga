# Optimization Review

Status: Review / bahan pertimbangan.

Dokumen ini mencatat usulan optimasi dan belum mengubah kode aplikasi.

## Target runtime: Synology DSM / PHP 8.2

Deployment utama ditargetkan berjalan pada **PHP 8.2**, agar baseline optimasi mengikuti lingkungan Synology DSM/Web Station.

Synology mendokumentasikan PHP 8.2 sebagai backend PHP yang didukung Web Station pada DSM 7.2. Synology juga masih menyediakan paket PHP 8.2 pada lini 8.2.x; versi paket dapat berbeda menurut model/rollout DSM. Karena runtime produksi menjadi acuan kompatibilitas, review dan benchmark optimasi menggunakan PHP 8.2 sebagai baseline.

**Implikasi:**
- Jangan menjadikan PHP 8.3 sebagai baseline optimasi produksi.
- CI sebaiknya menggunakan PHP 8.2 agar versi pengujian sama dengan runtime target.
- Docker image produksi/development sebaiknya menggunakan PHP 8.2 bila Docker tetap menjadi jalur deployment.
- Fitur atau optimasi yang membutuhkan PHP >8.2 harus dianggap sebagai perubahan kompatibilitas dan ditinjau terpisah.

> Catatan: versi PHP yang benar-benar aktif pada NAS tetap harus dikonfirmasi dari **Web Station → Script Language Settings → PHP**. Dukungan paket Synology tidak otomatis berarti setiap NAS sudah menggunakan versi tersebut.

## Prinsip

- Benchmark sebelum optimasi.
- Benchmark dilakukan pada PHP 8.2, bukan hanya PHP versi development yang lebih baru.
- Prioritaskan perubahan berisiko rendah.
- Jangan menambah dependency hanya untuk optimasi kecil.
- Pertahankan keamanan dan kompatibilitas dengan PHP 8.2.
- Ikuti `CONTRIBUTING.md`.

## Kandidat

1. Request-level cache untuk `get_setting()`.
2. Audit index PostgreSQL dengan `EXPLAIN (ANALYZE, BUFFERS)`.
3. Evaluasi optimasi pencarian katalog.
4. Request-level cache untuk daftar kantor/jenis.
5. Evaluasi ETag image cache.
6. Garbage collection image cache.
7. Image-cache invalidation.
8. Benchmark pagination skala besar.
9. Pisahkan pesan error database untuk UI dan log internal.
10. Hardening session pada PHP 8.2.
11. Perlindungan brute-force login.
12. Security headers yang kompatibel dengan aplikasi.
13. Atomic write untuk image cache.
14. Validasi MIME image yang dikirim oleh `image.php`.
15. Evaluasi pengurangan write SQLite pada request API.

## Benchmark

Sebelum dan sesudah perubahan, bandingkan waktu response, jumlah query, execution plan, ukuran cache, penggunaan resource bila tersedia, dan hasil pada PHP 8.2.

### Baseline runtime

Catat minimal:

```text
PHP: 8.2.x
Web server: Apache 2.4 atau Nginx
Database: PostgreSQL versi yang digunakan production
OS: Synology DSM versi yang digunakan production
```

### Pemeriksaan kompatibilitas PHP 8.2

Sebelum merge perubahan:

- `php -v` harus menunjukkan PHP 8.2.x pada environment target.
- Jalankan `php -l` pada seluruh file PHP.
- Jalankan test repository yang tersedia.
- Pastikan extension yang dibutuhkan (`pdo_pgsql`, `pdo_sqlite`, dan extension lain yang memang digunakan aplikasi) aktif pada PHP profile Synology.
- Jangan mengaktifkan extension yang tidak diperlukan hanya demi optimasi.

## Keputusan

Setiap kandidat diputuskan secara individual setelah benchmark. Dokumen ini bukan persetujuan untuk mengubah implementasi.
