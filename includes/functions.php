<?php
// Semua helper aplikasi: penyimpanan pengaturan (SQLite), autentikasi admin,
// koneksi PostgreSQL yang kredensialnya kini disimpan di pengaturan (bukan hardcoded),
// dan daftar kantor/gudang untuk dipilih user.

if (!defined('ROOT_PATH')) {
    // Fallback jika file ini di-require langsung tanpa lewat config.php
    define('ROOT_PATH', dirname(__DIR__));
}
define('SETTINGS_DB_PATH', ROOT_PATH . '/data/settings.sqlite');

/**
 * Buka (atau buat) settings.sqlite lewat PDO SQLite.
 * Skema dibuat otomatis kalau belum ada, dan nilai default database
 * (migrasi dari db_config.php versi lama) diisi sekali di awal supaya
 * situs tetap jalan tanpa admin harus setting ulang dari nol.
 */
function get_settings_pdo(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $isNew = !file_exists(SETTINGS_DB_PATH);

    $pdo = new PDO('sqlite:' . SETTINGS_DB_PATH, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec('CREATE TABLE IF NOT EXISTS app_settings (
        key   TEXT PRIMARY KEY,
        value TEXT NOT NULL
    )');
    // role: 'admin' (akses penuh, termasuk panel admin) atau 'user' (login,
    // tapi akses terbatas - cuma katalog tanpa filter tampilan umum).
    $pdo->exec('CREATE TABLE IF NOT EXISTS admin_users (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        username      TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        role          TEXT NOT NULL DEFAULT \'admin\',
        created_at    TEXT NOT NULL
    )');

    // Migrasi untuk instalasi lama (v1.1.0 ke bawah) yang tabelnya belum
    // punya kolom role.
    $hasRoleColumn = false;
    foreach ($pdo->query('PRAGMA table_info(admin_users)')->fetchAll() as $col) {
        if ($col['name'] === 'role') { $hasRoleColumn = true; break; }
    }
    if (!$hasRoleColumn) {
        $pdo->exec("ALTER TABLE admin_users ADD COLUMN role TEXT NOT NULL DEFAULT 'admin'");
    }

    if ($isNew) {
        // Nilai default untuk pengaturan tampilan saja. Kredensial database
        // TIDAK diisi otomatis lagi (dulu berasal dari db_config.php lama) -
        // admin mengisinya sendiri lewat admin/database.php setelah login,
        // supaya kredensial produksi tidak pernah ikut tersimpan di source code.
        $defaults = [
            'default_kantor'   => 'UTM',
            // Pengaturan tampilan untuk pengunjung umum (tanpa login).
            'display_jenis'    => '',  // kosong = tampilkan semua tipe item
            'show_stok_kosong' => '0', // default: item stok kosong disembunyikan
        ];
        $ins = $pdo->prepare('INSERT INTO app_settings (key, value) VALUES (?, ?)');
        foreach ($defaults as $k => $v) {
            $ins->execute([$k, $v]);
        }
    }

    return $pdo;
}

function get_setting(string $key, ?string $default = null): ?string {
    $stmt = get_settings_pdo()->prepare('SELECT value FROM app_settings WHERE key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['value'] : $default;
}

function set_setting(string $key, string $value): void {
    $stmt = get_settings_pdo()->prepare(
        'INSERT INTO app_settings (key, value) VALUES (?, ?)
         ON CONFLICT(key) DO UPDATE SET value = excluded.value'
    );
    $stmt->execute([$key, $value]);
}

/**
 * Koneksi ke database katalog (PostgreSQL). Kredensial diambil dari pengaturan
 * kecuali di-override manual (dipakai fitur "Tes Koneksi" di admin/database.php).
 * Melempar PDOException kalau gagal - biar pemanggil yang memutuskan mau
 * redirect ke maintenance.php atau cuma menampilkan pesan error.
 */
function get_pgsql_pdo(?array $overrides = null): PDO {
    $host   = $overrides['host']   ?? get_setting('db_host');
    $port   = $overrides['port']   ?? get_setting('db_port');
    $dbname = $overrides['dbname'] ?? get_setting('db_name');
    $user   = $overrides['user']   ?? get_setting('db_user');
    $pass   = $overrides['pass']   ?? get_setting('db_pass');

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
    ]);
}

/** Ambil daftar kantor/gudang unik dari tbl_itemstok. */
function get_kantor_list(PDO $pdo): array {
    try {
        $stmt = $pdo->query(
            "SELECT DISTINCT kantor FROM tbl_itemstok
             WHERE kantor IS NOT NULL AND kantor <> ''
             ORDER BY kantor"
        );
        return array_column($stmt->fetchAll(), 'kantor');
    } catch (PDOException $e) {
        return [];
    }
}

/** Ambil daftar tipe/jenis item unik dari tbl_item (untuk filter tampilan umum). */
function get_jenis_list(PDO $pdo): array {
    try {
        $stmt = $pdo->query(
            "SELECT DISTINCT jenis FROM tbl_item
             WHERE jenis IS NOT NULL AND jenis <> ''
             ORDER BY jenis"
        );
        return array_column($stmt->fetchAll(), 'jenis');
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Tipe item yang boleh tampil untuk pengunjung umum (hasil pengaturan admin).
 * Array kosong berarti tidak ada pembatasan (semua tipe ditampilkan).
 */
function get_display_jenis(): array {
    $raw = get_setting('display_jenis', '') ?? '';
    if (trim($raw) === '') return [];
    return array_values(array_filter(array_map('trim', explode(',', $raw)), fn($v) => $v !== ''));
}

function set_display_jenis(array $jenisList): void {
    set_setting('display_jenis', implode(',', $jenisList));
}

/** Apakah item dengan stok kosong ditampilkan untuk pengunjung umum. Default: tidak. */
function get_show_stok_kosong(): bool {
    return get_setting('show_stok_kosong', '0') === '1';
}

/** Kantor/gudang yang sedang aktif untuk user (disimpan di session). */
function current_kantor(PDO $pdo): string {
    ensure_session();
    if (!empty($_SESSION['kantor'])) {
        return $_SESSION['kantor'];
    }
    $default = get_setting('default_kantor', 'UTM');
    $_SESSION['kantor'] = $default;
    return $default;
}

function set_current_kantor(string $kantor): void {
    ensure_session();
    $_SESSION['kantor'] = $kantor;
}

function ensure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/* --------------------- Autentikasi (admin & user) --------------------- */
// Dua peran bisa login lewat form yang sama (admin/login.php):
//   - admin : akses penuh, termasuk panel admin/pengaturan
//   - user  : bisa login, tapi akses terbatas (katalog tanpa filter tampilan umum)
// Pengunjung tanpa login ("umum") tidak punya baris di admin_users sama sekali.

/** Jumlah akun dengan role admin (dipakai untuk cek setup awal & proteksi hapus admin terakhir). */
function admin_count(): int {
    $stmt = get_settings_pdo()->prepare("SELECT COUNT(*) AS c FROM admin_users WHERE role = 'admin'");
    $stmt->execute();
    return (int) $stmt->fetch()['c'];
}

/** Cari akun (admin atau user) berdasarkan username, dipakai saat login. */
function find_admin_by_username(string $username): ?array {
    $stmt = get_settings_pdo()->prepare('SELECT * FROM admin_users WHERE username = ?');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Buat akun baru. $role harus 'admin' atau 'user'. */
function create_admin(string $username, string $password, string $role = 'admin'): void {
    if (!in_array($role, ['admin', 'user'], true)) $role = 'user';
    $stmt = get_settings_pdo()->prepare(
        'INSERT INTO admin_users (username, password_hash, role, created_at) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role, date('c')]);
}

/** Apakah ada sesi login yang aktif (peran apa pun). */
function is_logged_in(): bool {
    ensure_session();
    return !empty($_SESSION['user_id']);
}

/** Peran user yang sedang login, atau null kalau belum login (umum). */
function current_user_role(): ?string {
    ensure_session();
    return $_SESSION['role'] ?? null;
}

/** Khusus akses penuh (panel admin). */
function is_admin_logged_in(): bool {
    return is_logged_in() && current_user_role() === 'admin';
}

/** Panggil di awal setiap halaman admin (kecuali login.php) untuk memaksa login sebagai admin. */
function require_admin(): void {
    if (!is_admin_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/* ------------------------------- CSRF -------------------------------- */

function csrf_token(): string {
    ensure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(?string $token): bool {
    ensure_session();
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
