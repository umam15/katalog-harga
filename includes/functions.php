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
    $pdo->exec('CREATE TABLE IF NOT EXISTS admin_users (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        username      TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        created_at    TEXT NOT NULL
    )');

    if ($isNew) {
        // Nilai default = kredensial yang sebelumnya hardcoded di db_config.php.
        // Admin bisa mengubahnya lewat admin/database.php kapan saja.
        $defaults = [
            'db_host'        => 'xvm12.sumberbarokah.com',
            'db_port'        => '5444',
            'db_name'        => 'i5_2026',
            'db_user'        => 'sysi5adm',
            'db_pass'        => 'u&aV23cc.o82dtr1x89c',
            'default_kantor' => 'UTM',
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

/* ------------------------- Autentikasi admin ------------------------- */

function admin_count(): int {
    return (int) get_settings_pdo()->query('SELECT COUNT(*) AS c FROM admin_users')->fetch()['c'];
}

function find_admin_by_username(string $username): ?array {
    $stmt = get_settings_pdo()->prepare('SELECT * FROM admin_users WHERE username = ?');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function create_admin(string $username, string $password): void {
    $stmt = get_settings_pdo()->prepare(
        'INSERT INTO admin_users (username, password_hash, created_at) VALUES (?, ?, ?)'
    );
    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), date('c')]);
}

function is_admin_logged_in(): bool {
    ensure_session();
    return !empty($_SESSION['admin_id']);
}

/** Panggil di awal setiap halaman admin (kecuali login.php) untuk memaksa login. */
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
