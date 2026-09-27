# Changelog

## [1.15.1] - 2026-09-27

### Changed
- **Cache in-memory per-request** untuk `get_setting()` (otomatis ikut
  menguntungkan semua fungsi turunan: `get_display_jenis()`,
  `get_show_stok_kosong()`, `get_harga_pembulatan()`,
  `get_bulatkan_harga_detail()`, `get_api_enabled()`, dst - tanpa perlu
  diubah satu-satu) serta `get_kantor_list()`/`get_jenis_list()`.
  `set_setting()` menyinkronkan cache-nya supaya baca-setelah-tulis dalam
  request yang sama tetap dapat nilai terbaru. Query SQLite/PostgreSQL
  berulang untuk key/data yang sama kini cuma sekali per request.

## [1.15.0] - 2026-09-27

### Added
- **Nonaktifkan tampilan detail error PHP ke browser** (`display_errors`
  off, `log_errors` on, `error_reporting(E_ALL)`) lewat `ini_set()` di
  `includes/functions.php` — file yang selalu di-require paling awal oleh
  semua entry point (halaman publik, panel admin, API). Sebelumnya tidak
  ada pengaturan ini sama sekali, artinya image resmi `php:8.2-apache`
  jalan dengan default `display_errors=On` - warning/fatal error PHP apa
  pun (bukan cuma exception database yang sengaja ditangkap try/catch)
  berisiko bocor ke output halaman. Berlaku sama persis di semua cara
  deploy (Docker, native Apache mod_php, Synology Web Station) karena
  pakai `ini_set()` di kode, bukan `php.ini`/`.user.ini`.
- **Hardening cookie sesi** — `ensure_session()` kini set `httponly`
  (cegah akses lewat JavaScript/XSS) dan `samesite=Lax` (mitigasi CSRF).
  `secure` dideteksi dinamis dari skema koneksi (sama seperti deteksi di
  `api/v1/openapi.php`), bukan dipaksa `true`, supaya tidak mematahkan
  login di deployment yang belum pakai HTTPS.

### Changed
- `TODO.md`: item "Pisahkan pesan error database" dianggap selesai oleh
  perbaikan `display_errors` di atas (lebih menyeluruh - berlaku untuk
  semua jenis error, tidak cuma PDOException). Item "Hardening session"
  juga selesai; catatan soal "belum regenerate session ID saat login"
  di deskripsi lamanya sudah tidak akurat - `admin/login.php` sebenarnya
  sudah memanggil `session_regenerate_id(true)` sejak sebelumnya, cuma
  flag cookie yang belum diset.

## [1.14.0] - 2026-09-27

### Added
- **Security header** untuk semua respons, diset lewat `.htaccess`
  (`mod_headers`, satu titik kontrol untuk halaman publik, panel admin,
  dan `/api/v1/*` sekaligus): `X-Frame-Options: DENY`,
  `X-Content-Type-Options: nosniff`, `Referrer-Policy:
  strict-origin-when-cross-origin`, dan `Content-Security-Policy`
  (`default-src 'self'` + `frame-ancestors 'none'` + `object-src 'none'`
  + `base-uri`/`form-action 'self'`). CSP sengaja masih mengizinkan
  `'unsafe-inline'` untuk `script-src`/`style-src` karena aplikasi masih
  pakai banyak `onclick`/`onsubmit`/`<script>` inline - proteksi utamanya
  membatasi SEMUA resource cuma boleh dari origin sendiri (menutup celah
  eksfiltrasi ke domain lain meski XSS terjadi). Tidak ada perubahan kode
  PHP; `mod_headers` sudah aktif di Dockerfile sejak v1.2.6.

## [1.13.1] - 2026-09-27

### Changed
- `TODO.md`: item "Alur reset password admin (skrip CLI)" ditunda — target
  deployment utama aplikasi ini adalah Synology Web Station, di mana skrip
  CLI/SSH bukan alur kerja yang wajar buat pemilik toko. Dipindah ke
  catatan bawah, solusi berbasis web perlu dipikirkan ulang sebelum masuk
  lagi jadi item kerja aktif.

## [1.13.0] - 2026-09-27

### Added
- **Proteksi brute-force di `admin/login.php`** — lockout sementara
  (berbasis waktu, bukan permanen) setelah percobaan login gagal
  bertubi-tubi, dilacak per kombinasi IP+username. Jeda tunggu naik
  eksponensial (30 detik dasar, maksimum 15 menit) setelah 5 kali gagal;
  hitungan otomatis reset kalau tidak ada percobaan baru selama 15 menit,
  dan langsung dihapus setelah login berhasil. Tabel `login_attempts` baru
  di `settings.db`, dibersihkan otomatis sesekali seperti retensi log API
  — tidak butuh cron terpisah.

## [1.12.6] - 2026-09-27

### Changed
- `TODO.md`: kandidat terakhir dari `docs/OPTIMIZATION.md` (garbage
  collection otomatis untuk `data/img-cache/`) dinilai dan ditunda —
  di skala ~3.620 item dampaknya nyaris nol, tombol manual "Bersihkan
  cache gambar" sudah cukup. Seluruh 15 kandidat `docs/OPTIMIZATION.md`
  kini sudah dipilah (diterima jadi item kerja atau ditunda/dicoret
  dengan alasan).

## [1.12.5] - 2026-09-26

### Changed
- `TODO.md`: tambah catatan batas eksplisit — database PostgreSQL
  (`i5_2026`) adalah **server produksi POS iPos5 milik toko yang sedang
  berjalan**, aplikasi ini cuma konsumen read-only dan tidak boleh
  mengusulkan perubahan skema/index/extension di server itu.
- Dicoret dari daftar kerja (butuh perubahan di server produksi, di luar
  cakupan aplikasi): index `tbl_itemstok(kantor, kodeitem)` dan index
  trigram (`pg_trgm`). Dipindah ke catatan di bagian bawah `TODO.md`,
  bukan lagi item aktif.

## [1.12.4] - 2026-09-26

### Changed
- `TODO.md`: 3 item dugaan diverifikasi langsung ke database
  (`i5_2026`) lewat `mcp-pgsql`, bukan lagi tebakan dari DDL:
  - `tbl_itemstok` benar-benar **tanpa index sama sekali** (dikonfirmasi,
    bukan sekadar dugaan) - tapi cuma ~1.727 baris jadi dampaknya nyaris
    nol saat ini; tetap dicatat sebagai best practice murah.
  - `tbl_item`, `tbl_itemhj`, `tbl_itemsatuanjml` **sudah punya index
    yang relevan** untuk pola query aplikasi ini - dicoret dari daftar
    "perlu index".
  - Index trigram (`pg_trgm`) & keyset pagination **ditunda**: extension
    `pg_trgm` belum terpasang dan `tbl_item` cuma ~3.620 baris - di
    skala ini keduanya premature optimization.

## [1.12.3] - 2026-09-26

### Changed
- `TODO.md` disusun ulang: digabung dengan hasil review teknis
  `docs/OPTIMIZATION.md` (6 kandidat performa/keamanan diterima -
  cache setting per-request, atomic write & validasi MIME cache
  gambar, throttle write SQLite di API, pisahkan error DB untuk
  UI/log, hardening session). 2 kandidat lain ditandai sudah
  selesai/tidak relevan (ETag image cache sudah ada sejak v1.2.6;
  invalidasi cache gambar otomatis ditolak karena bertentangan
  dengan tujuan cache itu sendiri) dan tidak dimasukkan. Item
  duplikat dengan bagian Keamanan yang sudah ada tidak digandakan.

## [1.12.2] - 2026-09-18

### Changed
- Halaman Pengaturan API: hapus paragraf basi yang bilang endpoint
  `/api/v1/*` "belum tersedia" (padahal sudah lengkap), diganti pointer
  ke `docs/api.md`. Hapus juga kalimat "kosongkan untuk akses semua
  kantor" yang terduplikasi.

### Removed
- Badge shortcut `/` di kolom pencarian katalog publik — tumpang
  tindih dengan tombol clear (×) bawaan `input[type=search]` di
  beberapa browser sehingga tampilannya berantakan. Shortcut keyboard
  `/` untuk fokus ke pencarian tetap berfungsi, hanya indikator
  visualnya yang dihapus.

## [1.12.1] - 2026-09-17

### Changed
- `TODO.md` dirapikan: hapus entri duplikat lama yang seharusnya sudah
  kehapus tiap fitur API selesai (kantor/health/OpenAPI/docs/rate limit
  yang sempat numpuk jadi entri "hantu"), dan item MCP server wrapper
  dikeluarkan dari TODO proyek ini — jadi proyek terpisah di masa depan
  (pola sama seperti `mcp-pgsql-server`), bukan cakupan `katalog-harga`.

## [1.12.0] - 2026-09-17

### Added
- **Log akses & siklus hidup API key** (`admin/api-log.php`) — tabel
  `api_log` baru mencatat tiap request yang berhasil diautentikasi
  (label key, endpoint, waktu) serta event key dibuat/dihapus. Label key
  disimpan sebagai salinan teks, jadi riwayat tetap kebaca meski key-nya
  sudah dihapus permanen. Tidak pernah mencatat key plaintext. Otomatis
  disusutkan (retensi ~1% tiap insert), cuma menyimpan 5.000 baris
  terbaru — tanpa perlu cron terpisah.
- Link "Log Akses API" ditambahkan di halaman Pengaturan API.

## [1.11.0] - 2026-09-17

### Added
- **Rate limit per API key** — token bucket sederhana (tabel
  `api_rate_limit` di `settings.db`, tanpa Redis/cron): burst 30 request,
  terisi ulang 0.5 request/detik (30/menit sustained). Kena limit dibalas
  `429 rate_limited` + header `Retry-After`. Terintegrasi otomatis di
  `api_authenticate()`, berlaku untuk semua endpoint kecuali `/health`.
  Baris rate limit ikut terhapus saat API key-nya dihapus.
- `docs/api.md` dan `openapi.json` diupdate mencantumkan `429`.

## [1.10.1] - 2026-09-17

### Added
- **`docs/api.md`** — dokumentasi teknis lengkap API: autentikasi, format
  error, scope kantor, dan contoh `curl` + contoh response tiap endpoint
  (`items`, `items/{kodeitem}`, `kantor`, `health`). README ditambah satu
  baris fitur + link ke dokumen ini (detail teknis sengaja tidak numpuk
  di README).

## [1.10.0] - 2026-09-17

### Added
- **`GET /api/v1/openapi.json`** — skema OpenAPI 3.0 lengkap untuk semua
  endpoint `/api/v1/*` (items, item detail, kantor, health), self-describing
  supaya agent/LLM bisa baca sendiri cara pakai API tanpa dokumentasi
  terpisah. Tidak wajib API key (skemanya sendiri bukan data sensitif),
  tapi tetap balas 404 kalau API dimatikan lewat toggle - konsisten dengan
  endpoint lain.

## [1.9.0] - 2026-09-17

### Added
- **`GET /api/v1/health`** — endpoint monitoring/uptime, sengaja tanpa
  autentikasi dan tidak tunduk toggle "Aktifkan API" (soal status
  infrastruktur, bukan bagian fitur katalog-nya). Balas `200` status `ok`
  kalau database bisa disambung, `503` status `degraded` kalau tidak -
  tidak pernah membocorkan detail error atau data katalog.

## [1.8.0] - 2026-09-17

### Added
- **`GET /api/v1/kantor`** — daftar kantor/gudang yang boleh diakses API
  key ini (mengikuti scope key, diirisankan dengan kantor yang benar-benar
  ada di database - kantor yang sudah dihapus/berganti nama otomatis tidak
  ikut muncul). Berguna buat client tahu nilai `?kantor=` yang valid
  sebelum menembak `/api/v1/items` atau `/api/v1/items/{kodeitem}`.

## [1.7.0] - 2026-09-17

### Added
- **`GET /api/v1/items/{kodeitem}`** — detail satu item: info dasar,
  daftar harga per satuan + barcode + stok di kantor terpilih (`?kantor=`).
  Auth & aturan scope kantor sama seperti `/api/v1/items`.
- `get_item_harga_list()` di `includes/functions.php` — logika hitung
  daftar harga per satuan (sistem 'O'/'S'/'L'/'J') diekstrak dari
  `detail.php` jadi fungsi bersama, dipakai baik oleh halaman detail
  publik maupun endpoint API ini (sebelumnya logikanya dobel).

### Changed
- `detail.php` direfaktor memakai `get_item_harga_list()` — tidak ada
  perubahan perilaku/tampilan, cuma menghapus duplikasi kode.

## [1.6.0] - 2026-09-17

### Added
- **`GET /api/v1/items`** — endpoint pertama dari fitur API: cari item
  (`?q=`), harga & stok di satu kantor (`?kantor=`, wajib kecuali key
  cuma punya scope 1 kantor), pagination (`?limit=`, `?offset=`, default
  20/maks 100). Wajib `Authorization: Bearer <key>` yang aktif & scope
  kantor-nya sesuai. Query harga sama persis dengan katalog publik
  (`index.php`) supaya hasilnya konsisten.
- Helper API baru di `includes/functions.php`: `api_authenticate()`
  (cek API aktif + validasi key, balas 404/401 JSON kalau gagal),
  `api_resolve_kantor()` (validasi `?kantor=` terhadap data asli +
  scope key), `api_pagination_params()`, `api_bearer_token()`,
  `api_json()`/`api_error()`.
- URL bersih `/api/v1/items` (lewat `RewriteRule` di `.htaccess`, tidak
  perlu akses `api/v1/items.php` langsung).

## [1.5.1] - 2026-09-17

### Changed
- **API key: revoke (soft-delete) diganti hapus permanen** — di 1.5.0 key
  yang "dicabut" tetap tersimpan sebagai baris berstatus "Dicabut". Ternyata
  itu bukan standar industri (GitHub/GitLab/Gitea/n8n semuanya hapus token
  sepenuhnya saat di-revoke), jadi diperbaiki: tombol "Hapus" sekarang
  langsung menghapus baris (`DELETE`), bukan `UPDATE revoked_at`. Kolom
  `revoked_at` di tabel `api_keys` juga dihapus dari skema.

## [1.5.0] - 2026-09-17

### Added
- **Manajemen API key** di `admin/api-settings.php` — generate key format
  `kh_live_...` (ditampilkan sekali saat dibuat, disimpan ter-hash SHA-256
  di `settings.db`), label per key, revoke (soft-delete, riwayat tetap
  kelihatan), dan **scope kantor** per key (kosong = semua kantor). Tabel
  `api_keys` baru dibuat otomatis. Tahap kedua dari rencana fitur API di
  `TODO.md` — endpoint `/api/v1/*` yang benar-benar memvalidasi key ini
  menyusul di rilis berikutnya.

## [1.4.0] - 2026-09-17

### Added
- **Pengaturan API** (`admin/api-settings.php`) — halaman admin baru untuk
  mengaktifkan/menonaktifkan grup endpoint `/api/v1/*` (akses baca-saja
  untuk integrasi program/agent LLM). Tahap pertama dari rencana fitur API
  di `TODO.md` — belum ada endpoint atau manajemen API key, itu menyusul
  di rilis berikutnya. Default: nonaktif.

## [1.3.1] - 2026-09-17

### Added
- File `VERSION` sebagai single source of truth nomor versi — `APP_VERSION`
  kini dibaca otomatis dari file ini, bukan hardcoded di `includes/functions.php`.
- `tests/test-version.php` — test ringan tanpa dependensi eksternal yang
  memastikan `VERSION`, `APP_VERSION`, dan entri `CHANGELOG.md` selalu sinkron.
- `.gitignore` — mengabaikan data runtime (`data/*.db`, cache gambar), file
  `.env`, dan artefak editor/OS.
- `CONTRIBUTING.md` — panduan kontribusi: struktur proyek, gaya kode, alur
  versioning/rilis, dan checklist pull request.
- `.github/workflows/ci.yml` — CI penuh di GitHub (lint semua file PHP, jalan
  test VERSION, validasi `docker-compose.yml`, build image Docker) di setiap
  push/PR ke `main`. Workflow rilis (`release.yml`) tetap terpisah, khusus
  memicu rilis saat `CHANGELOG.md` berubah.

## [1.3.0] - 2026-08-03

### Added
- **Pencarian cepat `[/]`** di katalog publik (`index.php`): tekan `/` di
  mana saja pada halaman untuk langsung fokus + select ke kolom
  pencarian, mirip shortcut di GitHub/Slack. Diabaikan otomatis kalau
  fokus sedang ada di input/textarea/select/elemen contenteditable lain,
  atau ada modifier key (Ctrl/Alt/Meta) yang ditekan bareng, supaya tidak
  bentrok dengan pengetikan normal atau shortcut browser.
- Badge kecil `[/]` di ujung kanan kolom pencarian sebagai petunjuk
  visual shortcut di atas, otomatis hilang saat kolom fokus/sudah berisi
  teks, dan disembunyikan di layar mobile (<720px) supaya tidak
  mengganggu tombol clear bawaan `input[type=search]` maupun keyboard
  virtual.

### Changed
- `APP_VERSION` dinaikkan ke `1.3.0` supaya cache aset statis service
  worker (termasuk `style.css` yang berisi styling badge shortcut di
  atas) ikut ter-invalidate untuk pengguna yang sudah install PWA-nya -
  lihat catatan strategi cache di `sw.php`.

## [1.2.9]

### Added
- **Bisa di-install sebagai app (PWA)**: tambah `manifest.json` + ikon
  (`icons/icon-192.png`, `icons/icon-512.png`, versi maskable) supaya
  katalog bisa di-"Add to Home Screen"/di-install lewat browser (ikon
  sendiri, tampil `standalone` tanpa address bar).
- Service worker (`sw.php`) untuk syarat instalasi PWA. **Sengaja cuma
  cache aset statis** (CSS, font, ikon, manifest) - `index.php`,
  `detail.php`, `image.php`, endpoint `?ajax=1`, dan semua `admin/*`
  SELALU lewat jaringan langsung, tidak pernah disajikan dari cache,
  supaya harga & stok yang tampil selalu data terbaru (tidak ada risiko
  data basi ala cache offline). Dibuat `.php` (bukan `.js` statis) supaya
  nama cache otomatis ikut naik tiap rilis lewat `APP_VERSION`, jadi
  klien lama otomatis dibersihkan tanpa perlu bump versi manual di kode
  service worker.
- `<link rel="manifest">`, `theme-color`, dan `apple-touch-icon` di
  `index.php` & `detail.php` (dua halaman yang bisa jadi entry point,
  termasuk landing langsung dari scan barcode/QR ke halaman detail).

## [1.2.8]

### Added
- Versi rilis di aplikasi: konstanta `APP_VERSION` (di
  `includes/functions.php`) sekarang ditampilkan di Panel Admin ->
  Dashboard, supaya versi yang berjalan di server bisa dicek langsung dari
  UI tanpa buka `CHANGELOG.md`.

## [1.2.7]

### Added
- **Fallback tanpa JavaScript** untuk navigasi katalog: kalau JS nonaktif,
  tombol "Muat lebih banyak"/infinite scroll disembunyikan dan diganti link
  klasik Sebelumnya/Berikutnya (`<noscript>`) yang tetap bekerja lewat
  parameter `?p=`. Sengaja dibuat minimal (cuma dua link + info halaman,
  bukan daftar nomor halaman lengkap) supaya ringan untuk katalog dengan
  banyak halaman.
- `.gitignore` (baru); update `.dockerignore`: `TODO.md` (catatan kerja
  internal) tidak lagi ikut ke repo git maupun image Docker.

### Changed
- **Optimalkan jumlah item per halaman**: dinaikkan dari 50 menjadi 80,
  supaya pengguna tanpa JS butuh lebih sedikit klik untuk menjelajah
  katalog. Query & gambar sudah cukup ringan (LATERAL join, cache gambar,
  lazy loading) untuk menampung batch lebih besar tanpa dampak berarti.
- README dipersingkat, termasuk memangkas catatan overhead performa Docker
  yang tidak lagi krusial (image cache sudah membuat bedanya kecil).

## [1.2.6]

### Added
- **Infinite scroll di katalog** (`index.php`): item baru otomatis dimuat
  saat scroll ke bawah, tanpa reload halaman penuh - lewat endpoint fragment
  (`?ajax=1`) yang cuma mengembalikan baris item, bukan seluruh HTML.
  Tombol "Muat lebih banyak" manual juga selalu ada (auto-load pakai
  `IntersectionObserver`, tombol untuk kontrol eksplisit / pembaca layar).
- Tombol **"Bersihkan cache gambar"** di Panel Admin -> Pengaturan
  Tampilan, plus info jumlah file & ukuran cache saat ini.
- `.htaccess` di root: kompresi gzip (HTML/CSS/JS) dan cache header untuk
  aset statis (CSS/JS/ikon). Dockerfile mengaktifkan modul Apache
  `deflate`, `expires`, `headers` dan `AllowOverride All` supaya
  `.htaccess` benar-benar dipakai.
- Thumbnail terpisah (maks ~160px, kualitas 75) untuk daftar katalog lewat
  ekstensi GD, dipakai lewat `image.php?...&thumb=1`. Kalau GD tidak
  tersedia, otomatis fallback ke gambar ukuran penuh (tetap jalan, cuma
  tidak seringan dengan thumbnail).
- Dukungan `ETag`/`If-None-Match` di `image.php` supaya browser bisa dapat
  `304 Not Modified` tanpa transfer ulang gambar setelah cache 1 hari di
  browser habis.
- Dockerfile: ekstensi `gd` (untuk thumbnail) dan aktifkan `opcache`
  dengan konfigurasi di `docker/opcache.ini`.

### Changed
- **Font di-self-host** (Space Grotesk, Inter, JetBrains Mono, format
  woff2, lisensi SIL OFL - lihat `fonts/LICENSE.txt`), menggantikan
  request ke `fonts.googleapis.com`/`fonts.gstatic.com` yang sebelumnya
  dipanggil di setiap halaman (index, detail, semua halaman admin).
- **Performa besar di `image.php`**: gambar produk (termasuk thumbnail di
  daftar katalog) sekarang di-cache ke `data/img-cache/` setelah pertama
  kali diambil. Sebelumnya SETIAP gambar membuka koneksi PostgreSQL baru -
  satu halaman katalog dengan 50 item = 50 koneksi DB. Sekarang hanya
  cache miss yang menyentuh database.

### Removed
- **Pagination klasik dihapus** dari `index.php` — infinite scroll
  (tombol "Muat lebih banyak" + auto-load saat scroll) sekarang jadi
  satu-satunya cara melihat item di luar 50 pertama. Menyederhanakan
  halaman (HTML lebih ringkas, tidak ada dua sistem navigasi paralel),
  dengan konsekuensi: **butuh JavaScript aktif** untuk mengakses item di
  luar batch pertama.

## [1.2.0]

### Added
- Dukungan Docker: `Dockerfile` & `docker-compose.yml` untuk menjalankan
  aplikasi via `docker compose up -d --build` (PHP 8.2 + Apache, ekstensi
  `pdo_pgsql` & `pdo_sqlite` sudah termasuk, folder `data/` dipersist
  lewat Docker volume).
- Panduan instalasi via Docker di README.

### Changed
- Ganti nama file penyimpanan pengaturan dari `data/settings.sqlite`
  menjadi `data/settings.db`.

### Fixed
- `Dockerfile`: hapus langkah purge `libpq-dev` setelah build, karena
  `apt-get purge --auto-remove` ikut menghapus `libpq5` (runtime lib
  untuk `pdo_pgsql`) dan menyebabkan error "could not find driver".
- `admin/index.php`: kartu Dashboard "Pengaturan Database" menampilkan
  "Belum diatur" (bukan warning deprecated) saat koneksi database belum
  pernah disetting.
- `admin/database.php`: field Host/Port/Nama Database/User di form
  Pengaturan Database default ke string kosong (bukan `null`) saat
  koneksi belum pernah disimpan, supaya tidak muncul warning deprecated
  `htmlspecialchars()`.

## [1.1.7]

### Added
- Opsi **Backup & Restore** untuk admin (`admin/backup.php`): ekspor
  seluruh pengaturan aplikasi (termasuk kredensial database) ke file
  JSON, dan restore dari file tersebut. Akun login (admin/user) tidak
  termasuk di backup.

## [1.1.6]

### Fixed
- `admin/display.php`: koneksi database yang belum di-setting atau gagal
  terhubung dulu menyebabkan fatal error, sekarang tampil pesan error
  yang mengarahkan ke Pengaturan Database.

## [1.1.5]

### Added
- Pengaturan pembulatan harga (ceil) di katalog, opsional diterapkan juga
  di halaman detail.

## [1.1.1]

### Added
- Pengaturan tampilan untuk admin (kantor default, tipe item, stok
  kosong).
- Peran pengguna **admin** dan **user**, selain **umum** (tanpa login).

## [0.1.0] - 2026-01-21
- Initial commit
