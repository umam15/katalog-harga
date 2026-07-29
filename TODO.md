# TODO — Optimasi Performa

Semua item optimasi yang direncanakan sudah selesai dikerjakan — detail
masing-masing ada di [CHANGELOG.md](CHANGELOG.md) (lihat entri v1.2.6 dan
v1.2.7). Tidak ada item pending saat ini.

## Sudah baik, tidak perlu diubah
- Query katalog: 1 query dengan `LATERAL JOIN` + `COUNT(*) OVER()` untuk
  pagination — sudah menghindari N+1 dan query count terpisah.
- Query detail: barcode diambil sekali lalu dikelompokkan di PHP — sudah
  menghindari N+1 loop.
- `get_settings_pdo()` pakai static singleton per-request — sudah tepat.
