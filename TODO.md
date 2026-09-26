# TODO

Belum ada pekerjaan yang sedang berjalan. Daftar di bawah gabungan usulan
hasil review kode + review `docs/OPTIMIZATION.md` (bukan item yang sudah
diminta/dikonfirmasi), diurutkan dari yang menurut saya paling berdampak.
Tiap item performa tetap mengikuti prosedur di `docs/OPTIMIZATION.md`
(benchmark sebelum & sesudah, baseline PHP 8.2).

**Batas penting:** database PostgreSQL (`i5_2026` dkk) adalah **server
produksi POS iPos5 milik toko yang sedang berjalan** — aplikasi ini cuma
konsumen read-only. TODO di sini **tidak boleh** mengusulkan perubahan di
sisi server itu (index baru, extension baru, ubah skema, dll). Usulan yang
ternyata butuh itu dicoret dari daftar kerja dan dipindah ke catatan di
bagian bawah, bukan tanggung jawab aplikasi ini untuk dieksekusi.

## Keamanan
- [ ] **Proteksi brute-force di `admin/login.php`** — saat ini tidak ada
  pembatasan percobaan login. Pertimbangkan rate-limit sederhana per
  IP/username (mis. counter di `settings.db` + jeda setelah N kali gagal).
- [ ] **Security header di `.htaccess`/`config.php`** — belum ada
  `X-Frame-Options`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`,
  `Content-Security-Policy`.
- [ ] **Alur reset password admin** — belum ada jalan keluar selain akses
  langsung ke `data/settings.db` kalau satu-satunya admin lupa password.
  Pertimbangkan skrip CLI kecil (bukan lewat web).
- [ ] **Pisahkan pesan error database untuk UI vs log internal** — saat ini
  exception PDO berpotensi tampil apa adanya ke user; standar OWASP:
  pesan generik ke UI, detail teknis hanya ke log server.
- [ ] **Hardening session (PHP 8.2)** — `ensure_session()` di
  `includes/functions.php` masih pakai default PHP: belum set cookie
  `httponly`/`secure`/`samesite`, dan belum regenerate session ID saat
  login berhasil (mitigasi session fixation).

## Performa & Robustness
- [ ] **Cache `get_setting()`/`get_kantor_list()`/`get_jenis_list()` per-request**
  — dipanggil berkali-kali dalam satu request (kantor, display_jenis, stok
  kosong, pembulatan harga, dst). Static array in-memory, risiko rendah.
- [ ] **Atomic write cache gambar** — `image.php` pakai `file_put_contents()`
  polos tanpa `LOCK_EX`/temp+rename; request bersamaan pada item yang baru
  pertama kali diakses berisiko file cache korup/terbaca separuh.
- [ ] **Validasi MIME gambar di `image.php`** — `serve_cached_file()` selalu
  set `Content-Type: image/jpeg` walau data `tbl_item.gambar` belum tentu
  JPEG. Deteksi tipe asli (mis. `finfo`/`getimagesizefromstring`) sebelum
  set header.
- [ ] **Kurangi write SQLite per request API** — tiap request API menulis
  3x ke `settings.db` (`last_used_at`, rate limit, log). Pertimbangkan
  throttle `last_used_at` (mis. update maks 1x/menit per key) untuk
  kurangi write amplification di SQLite (single-writer).

## Fitur
- [ ] **Ekspor katalog (CSV/Excel)** dari Panel Admin.
- [ ] **Tema gelap (dark mode)** — toggle CSS variables (sudah ada di
  `style.css`).
- [ ] **Scan barang lewat HP (kamera)** — tombol "Scan" di kolom pencarian
  katalog publik (`getUserMedia` + ZXing/BarcodeDetector API), dengan
  fallback jelas untuk browser tanpa dukungan kamera.

## API (akses read-only, dirancang untuk LLM/agent)
Semua fondasi (pengaturan, manajemen key, endpoint items/detail/kantor/
health, skema OpenAPI, `docs/api.md`, rate limit, log akses) sudah selesai
— lihat CHANGELOG. MCP server wrapper sengaja bukan bagian TODO proyek
ini — jadi proyek terpisah (pola sama seperti `mcp-pgsql-server`), belum
dimulai.

## Dokumentasi
- [ ] **Panduan deploy ke shared hosting (non-Docker)** — README saat ini
  fokus ke setup via Docker; perlu bagian terpisah untuk deploy manual.
- [ ] **Perbaiki `docs/api.md`** — bagian "Batasan saat ini" masih menulis
  "Belum ada log akses", padahal fitur ini sudah selesai sejak v1.12.0
  (lihat CHANGELOG). Sinkronkan dengan `TODO.md`.

## Maintenance rutin
- [ ] **Pantau performa pagination** — dicek langsung ke database:
  `tbl_item` cuma ~3.620 baris (maks ~46 halaman @ 80/halaman). OFFSET-based
  pagination di `index.php` bukan masalah sama sekali di skala ini. Kalau
  jumlah item naik signifikan (mis. >50.000 baris), pertimbangkan keyset
  pagination — ini murni perubahan query di sisi aplikasi (`ORDER BY
  kodeitem` sudah pakai kolom ber-index/PK), tidak butuh perubahan skema.

## Lain-lain
- [ ] Review apakah `admin/backup.php` perlu opsi backup terjadwal (cron),
  bukan cuma manual lewat UI.

---
Catatan: dari review `docs/OPTIMIZATION.md`, beberapa kandidat sudah
selesai/tidak relevan/di luar cakupan dan tidak dimasukkan sebagai item
kerja:
- ETag image cache — sudah ada sejak v1.2.6.
- Invalidasi cache gambar otomatis — bertentangan dengan tujuan cache itu
  sendiri (menghindari hit database tiap request); desain manual-clear
  yang sudah ada tetap dipertahankan.
- ~~Index `tbl_itemstok(kantor, kodeitem)`~~ — **dicoret**: butuh
  `CREATE INDEX` di server PostgreSQL produksi (`i5_2026`), di luar
  cakupan aplikasi ini (lihat catatan batas di atas). Tabel lain
  (`tbl_item`, `tbl_itemhj`, `tbl_itemsatuanjml`) sudah punya index yang
  relevan, dikonfirmasi lewat `mcp-pgsql`, jadi tidak perlu diapa-apakan.
- ~~Index trigram (`pg_trgm`) otomatis~~ — **dicoret**: butuh instal
  extension baru di server PostgreSQL produksi, di luar cakupan aplikasi
  ini. (Sebagai catatan tambahan: extension ini belum terpasang dan
  `tbl_item` cuma ~3.620 baris, jadi walau bukan soal wewenang, secara
  performa juga belum dibutuhkan.)

Kalau ada prioritas atau item lain yang mau ditambahkan/dicoret, tinggal
update file ini langsung.
