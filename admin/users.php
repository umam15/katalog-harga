<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
require_admin();

$sqlitePdo = get_settings_pdo();
$message = '';
$messageType = 'success';

function all_admins(PDO $pdo): array {
    return $pdo->query('SELECT id, username, role, created_at FROM admin_users ORDER BY id')->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $message = 'Sesi tidak valid, silakan coba lagi.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role     = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
            if ($username === '' || strlen($password) < 8) {
                $message = 'Username wajib diisi dan password minimal 8 karakter.';
                $messageType = 'danger';
            } elseif (find_admin_by_username($username)) {
                $message = 'Username sudah dipakai.';
                $messageType = 'danger';
            } else {
                create_admin($username, $password, $role);
                $message = 'Akun baru berhasil ditambahkan.';
            }
        } elseif ($action === 'password') {
            $targetId = (int)($_POST['id'] ?? 0);
            $password = $_POST['password'] ?? '';
            if (strlen($password) < 8) {
                $message = 'Password minimal 8 karakter.';
                $messageType = 'danger';
            } else {
                $stmt = $sqlitePdo->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?');
                $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $targetId]);
                $message = 'Password berhasil diperbarui.';
            }
        } elseif ($action === 'delete') {
            $targetId = (int)($_POST['id'] ?? 0);
            $targetStmt = $sqlitePdo->prepare('SELECT role FROM admin_users WHERE id = ?');
            $targetStmt->execute([$targetId]);
            $targetRole = $targetStmt->fetchColumn();

            if ($targetId === (int)$_SESSION['user_id']) {
                $message = 'Tidak bisa menghapus akun yang sedang digunakan.';
                $messageType = 'danger';
            } elseif ($targetRole === 'admin' && admin_count() <= 1) {
                $message = 'Tidak bisa menghapus admin terakhir.';
                $messageType = 'danger';
            } else {
                $stmt = $sqlitePdo->prepare('DELETE FROM admin_users WHERE id = ?');
                $stmt->execute([$targetId]);
                $message = 'Akun berhasil dihapus.';
            }
        }
    }
}

$admins = all_admins($sqlitePdo);
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengguna · Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin.css">
    <link rel="icon" href="../favicon.ico">
</head>
<body>
<header class="topbar topbar-simple">
    <div class="topbar-inner">
        <a href="index.php" class="btn-back">&lsaquo; Dashboard</a>
    </div>
</header>

<main class="container container-narrow">
    <h1 class="section-title" style="margin-top:0;">Manajemen Pengguna</h1>
    <p class="muted-text">Admin punya akses penuh termasuk panel ini. User bisa login tapi akses katalognya terbatas (tanpa filter tampilan umum, tapi tidak bisa masuk panel admin).</p>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr><th>Username</th><th>Peran</th><th>Dibuat</th><th>Ganti Password</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($admins as $a): ?>
                <tr>
                    <td><?= htmlspecialchars($a['username']) ?><?= (int)$a['id'] === (int)$_SESSION['user_id'] ? ' <span class="muted-text">(Anda)</span>' : '' ?></td>
                    <td><?= $a['role'] === 'admin' ? 'Admin' : 'User' ?></td>
                    <td class="mono-text"><?= htmlspecialchars(date('d-m-Y', strtotime($a['created_at']))) ?></td>
                    <td>
                        <form method="POST" class="inline-form">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="action" value="password">
                            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                            <input type="password" name="password" class="form-input form-input-sm" placeholder="Password baru" minlength="8" required>
                            <button type="submit" class="btn btn-secondary btn-sm">Simpan</button>
                        </form>
                    </td>
                    <td>
                        <?php if ((int)$a['id'] !== (int)$_SESSION['user_id'] && !($a['role'] === 'admin' && admin_count() <= 1)): ?>
                        <form method="POST" class="inline-form" onsubmit="return confirm('Hapus akun ini?');">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2 class="section-title">Tambah Akun Baru</h2>
    <form method="POST" class="stack-form">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="add">
        <label class="form-label">Username
            <input type="text" name="username" class="form-input" required>
        </label>
        <label class="form-label">Password
            <input type="password" name="password" class="form-input" required minlength="8">
        </label>
        <label class="form-label">Peran
            <select name="role" class="form-input">
                <option value="user">User (akses terbatas)</option>
                <option value="admin">Admin (akses penuh)</option>
            </select>
        </label>
        <button type="submit" class="btn btn-primary">Tambah Akun</button>
    </form>
</main>
</body>
</html>
