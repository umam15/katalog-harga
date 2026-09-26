# Optimization Review

Status: Review / bahan pertimbangan.

Dokumen ini mencatat usulan optimasi dan belum mengubah kode aplikasi.

## Prinsip

- Benchmark sebelum optimasi.
- Prioritaskan perubahan berisiko rendah.
- Jangan menambah dependency hanya untuk optimasi kecil.
- Pertahankan keamanan dan kompatibilitas.
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

## Benchmark

Sebelum dan sesudah perubahan, bandingkan waktu response, jumlah query, execution plan, ukuran cache, dan penggunaan resource bila tersedia.

## Keputusan

Setiap kandidat diputuskan secara individual setelah benchmark. Dokumen ini bukan persetujuan untuk mengubah implementasi.
