# TODO

Belum ada pekerjaan yang sedang berjalan. Daftar di bawah adalah usulan
hasil review singkat kode (bukan item yang sudah diminta/dikonfirmasi),
diurutkan dari yang menurut saya paling berdampak.

## Keamanan
- [ ] **Proteksi brute-force di `admin/login.php`** — saat ini tidak ada
  pembatasan percobaan login (tidak ada lockout/delay setelah beberapa
  kali password salah). Pertimbangkan rate-limit sederhana per
  IP/username (mis. counter di `settings.db` + jeda setelah N kali gagal).
- [ ] **Security header di `.htaccess`/`config.php`** — belum ada
  `X-Frame-Options`, `X-Content-Type-Options: nosniff`,
  `Referrer-Policy`, dan `Content-Security-Policy`. Kompresi & cache
  header sudah ada, tinggal tambah header keamanan di blok
  `mod_headers` yang sama.
- [ ] **Alur reset password admin** — kalau satu-satunya akun admin lupa
  password, tidak ada jalan keluar selain akses langsung ke
  `data/settings.db`. Pertimbangkan perintah CLI/skrip kecil untuk reset
  password dari server (bukan lewat web, supaya tidak jadi vektor baru).

## Fitur
- [ ] **Ekspor katalog (CSV/Excel)** dari Panel Admin, supaya data
  harga/stok bisa diunduh tanpa akses langsung ke database.
- [ ] **Index trigram (`pg_trgm`) otomatis** — README masih menyuruh admin
  menjalankan `EXPLAIN ANALYZE` & bikin index manual untuk pencarian
  `ILIKE`. Bisa ditambah tombol "Cek & buat index yang disarankan" di
  Panel Admin -> Pengaturan Database (opsional, dengan konfirmasi).
- [ ] **Tema gelap (dark mode)** — toggle sederhana pakai CSS variables
  yang sudah ada di `style.css`.

## Dokumentasi
- [x] **Screenshot UI di README** — tambahkan tangkapan layar (katalog
  publik, halaman detail, Panel Admin) di `README.md` supaya calon
  pengguna langsung dapat gambaran tampilan sebelum instal.
- [ ] **Panduan deploy ke shared hosting (non-Docker)** — README saat ini
  fokus ke setup via Docker; perlu bagian terpisah untuk deploy manual
  (upload lewat FTP/cPanel, persyaratan versi PHP, cara set
  `config.php`, permission folder `data/`) buat pengguna yang tidak
  punya akses Docker.

## Lain-lain
- [ ] Review apakah `admin/backup.php` (ekspor/restore pengaturan) perlu
  opsi backup terjadwal (mis. cron generate file ke `data/`), bukan cuma
  manual lewat UI.

---
Catatan: kalau ada prioritas atau item lain yang mau ditambahkan/dicoret,
tinggal update file ini langsung — file ini sekarang ikut ter-track di
git (lihat `.gitignore`).
