<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/functions.php';
require_admin();

$message = '';
$messageType = 'success';
$newKeyPlaintext = null; // hanya terisi sesaat setelah key baru dibuat
$dbError = null;

try {
    $pdo = get_pgsql_pdo();
    $kantorList = get_kantor_list($pdo);
} catch (PDOException $e) {
    $dbError = $e->getMessage();
    $kantorList = [];
}

$current = ['api_enabled' => get_api_enabled()];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $message = 'Sesi tidak valid, silakan coba lagi.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_toggle') {
            $enabled = isset($_POST['api_enabled']);
            set_api_enabled($enabled);
            $current['api_enabled'] = $enabled;
            $message = $enabled ? 'API diaktifkan.' : 'API dinonaktifkan.';

        } elseif ($action === 'create_key') {
            $label = trim($_POST['label'] ?? '');
            $scopeInput = $_POST['kantor_scope'] ?? [];
            if (!is_array($scopeInput)) $scopeInput = [];
            // Cuma simpan kantor yang memang valid/ada di database - cegah
            // scope "hantu" kalau admin mengetik/kirim nilai sembarangan.
            $scope = array_values(array_intersect($kantorList, $scopeInput));

            if ($label === '') {
                $message = 'Label wajib diisi, supaya key ini gampang dikenali nanti (mis. nama integrasi/aplikasi yang memakainya).';
                $messageType = 'danger';
            } else {
                $result = create_api_key($label, $scope);
                $newKeyPlaintext = $result['key'];
                $message = 'API key baru berhasil dibuat. Salin sekarang - key ini tidak akan ditampilkan lagi.';
            }

        } elseif ($action === 'delete_key') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                delete_api_key($id);
                $message = 'API key dihapus.';
            }
        }
    }
}

$csrf = csrf_token();
$apiKeys = list_api_keys();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan API · Admin</title>
    <link rel="stylesheet" href="../fonts/fonts.css">
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
    <h1 class="section-title" style="margin-top:0;">Pengaturan API</h1>
    <p class="muted-text">
        Akses baca-saja ke katalog lewat <code>/api/v1/*</code> untuk integrasi program
        atau agent/LLM eksternal. Setiap request wajib pakai API key
        (<code>Authorization: Bearer &lt;key&gt;</code>) - tidak ada mode tanpa key.
    </p>

    <?php if ($dbError !== null): ?>
        <div class="alert alert-danger">
            Database tidak terhubung, daftar kantor untuk scope key tidak bisa dimuat.
            Cek <a href="database.php">Pengaturan Database</a>. Key tetap bisa dibuat
            dengan scope "semua kantor" selama database belum tersambung.
        </div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($newKeyPlaintext !== null): ?>
        <div class="alert alert-success">
            <strong>Simpan key ini sekarang, tidak akan ditampilkan lagi setelah halaman ditutup/di-refresh:</strong>
            <input type="text" readonly value="<?= htmlspecialchars($newKeyPlaintext) ?>"
                   class="form-input" style="font-family:monospace; margin-top:0.5rem;"
                   onclick="this.select();">
        </div>
    <?php endif; ?>

    <form method="POST" class="stack-form">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="save_toggle">

        <label class="checkbox-item">
            <input type="checkbox" name="api_enabled" <?= $current['api_enabled'] ? 'checked' : '' ?>>
            Aktifkan API
        </label>

        <div class="btn-row">
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
    </form>

    <h2 class="section-title">API Key</h2>
    <p class="muted-text">Tiap key bisa dibatasi ke kantor/gudang tertentu (kosongkan untuk akses semua kantor).</p>

    <?php if (empty($apiKeys)): ?>
        <p class="muted-text">Belum ada API key.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Label</th>
                    <th>Key</th>
                    <th>Scope kantor</th>
                    <th>Dibuat</th>
                    <th>Terakhir dipakai</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($apiKeys as $k): ?>
                <tr>
                    <td><?= htmlspecialchars($k['label']) ?></td>
                    <td><code><?= htmlspecialchars($k['key_prefix']) ?>&hellip;</code></td>
                    <td><?= $k['kantor_scope'] !== '' ? htmlspecialchars($k['kantor_scope']) : '<span class="muted-text">Semua kantor</span>' ?></td>
                    <td><?= htmlspecialchars(substr($k['created_at'], 0, 10)) ?></td>
                    <td><?= $k['last_used_at'] ? htmlspecialchars(substr($k['last_used_at'], 0, 16)) : '<span class="muted-text">Belum pernah</span>' ?></td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Hapus API key &quot;<?= htmlspecialchars(addslashes($k['label'])) ?>&quot;? Key langsung tidak bisa dipakai lagi dan tidak bisa dibatalkan.');" style="display:inline;">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="action" value="delete_key">
                            <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                            <button type="submit" class="btn btn-secondary btn-sm">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>

    <h3 class="section-title" style="margin-top:1.5rem;">Buat API key baru</h3>
    <form method="POST" class="stack-form">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="create_key">

        <label class="form-label">Label
            <span class="muted-text" style="font-weight:400;">Nama integrasi/aplikasi yang memakai key ini, mis. "Agent LLM Gudang".</span>
            <input type="text" name="label" class="form-input" required maxlength="100">
        </label>

        <label class="form-label">Scope kantor
            <span class="muted-text" style="font-weight:400;">Kosongkan semua untuk akses semua kantor.</span>
        </label>
        <?php if (empty($kantorList)): ?>
            <p class="muted-text">Tidak ada data kantor dari database - key yang dibuat sekarang otomatis berscope "semua kantor".</p>
        <?php else: ?>
        <div class="checkbox-list">
            <?php foreach ($kantorList as $k): ?>
            <label class="checkbox-item">
                <input type="checkbox" name="kantor_scope[]" value="<?= htmlspecialchars($k) ?>">
                <?= htmlspecialchars($k) ?>
            </label>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="btn-row">
            <button type="submit" class="btn btn-primary">Buat key</button>
        </div>
    </form>

    <p class="muted-text" style="margin-top:2rem;">
        Endpoint API (<code>/api/v1/items</code> dll) belum tersedia — menyusul di
        rilis berikutnya (lihat <code>TODO.md</code>). Key yang dibuat di sini sudah
        bisa disiapkan sejak sekarang.
    </p>
</main>
</body>
</html>
