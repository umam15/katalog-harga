# Contributing

Panduan singkat untuk berkontribusi ke proyek ini.

## Setup lokal

Cara tercepat: lewat Docker (lihat `README.md` bagian Instalasi). Untuk
pengembangan tanpa Docker, butuh PHP dengan ekstensi `pdo_pgsql` dan
`pdo_sqlite`, lalu arahkan document root ke folder proyek ini.

## Struktur proyek

- `index.php`, `detail.php`, `image.php` — halaman katalog publik.
- `admin/` — panel admin (auth, pengaturan, manajemen user).
- `includes/functions.php` — helper bersama (auth, koneksi DB, cache gambar).
- `data/` — data runtime (SQLite `settings.db`, cache gambar) — tidak ikut di-commit.
- `docs/` — dokumentasi tambahan & screenshot.
- `tests/` — skrip test ringan (tanpa framework, jalankan dengan `php tests/<nama>.php`).

## Gaya kode

- PHP vanilla, tanpa framework/dependensi eksternal — pertahankan pendekatan
  ini kecuali ada alasan kuat untuk berubah.
- Ikuti gaya penulisan yang sudah ada di sekitar kode yang diubah
  (indentasi, penamaan, komentar berbahasa Indonesia).
- Prioritaskan solusi ringan dan mudah dirawat dibanding menambah dependensi.
- Jalankan `php -l <file>` untuk setiap file PHP yang diubah sebelum commit.

## Versioning & rilis

Proyek ini mengikuti [Semantic Versioning](https://semver.org/lang/id/)
(`MAYOR.MINOR.PATCH`):

- **PATCH** — perbaikan bug/kecil, tanpa perubahan fitur/perilaku.
- **MINOR** — fitur baru yang kompatibel ke belakang.
- **MAYOR** — perubahan yang tidak kompatibel ke belakang.

File `VERSION` di root adalah *single source of truth* nomor versi —
`APP_VERSION` di kode membacanya otomatis, jangan hardcode versi di
tempat lain. Setiap bump versi wajib:

1. Update isi file `VERSION`.
2. Tambah entri baru di `CHANGELOG.md` (format
   [Keep a Changelog](https://keepachangelog.com/id-ID/)) dengan nomor
   versi yang sama.
3. Jalankan `php tests/test-version.php` untuk memastikan keduanya sinkron.

Push ke branch `main` dengan perubahan `CHANGELOG.md` otomatis memicu
rilis (lihat `.github/workflows/` dan `.gitea/workflows/`).

## Pull request

- Satu PR untuk satu perubahan yang jelas cakupannya.
- Sertakan alasan perubahan (bukan cuma "apa"-nya) di deskripsi PR.
- Pastikan `php -l` dan `tests/test-version.php` lolos sebelum minta review.

## Lisensi

Dengan berkontribusi, kamu setuju kontribusimu dilisensikan di bawah
lisensi MIT proyek ini (lihat `LICENSE`).
