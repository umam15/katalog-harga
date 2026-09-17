<?php
/**
 * Test ringan: pastikan file VERSION valid (format SemVer) dan APP_VERSION
 * benar-benar dibaca dari file VERSION (SSOT), bukan hardcoded di tempat lain.
 *
 * Sengaja tanpa framework test (PHPUnit dll) - proyek ini vanilla PHP tanpa
 * dependensi eksternal, jadi test-nya pun assertion sederhana. Jalankan:
 *   php tests/test-version.php
 * Exit code 0 = lolos, 1 = gagal (cocok dipakai di CI).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

function check(array &$failures, bool $condition, string $message): void {
    if (!$condition) {
        $failures[] = $message;
    }
}

// 1. File VERSION harus ada & bisa dibaca.
$versionFile = $root . '/VERSION';
check($failures, is_readable($versionFile), 'File VERSION tidak ditemukan atau tidak bisa dibaca.');

if (is_readable($versionFile)) {
    $version = trim((string) file_get_contents($versionFile));

    // 2. Isinya harus format SemVer (x.y.z, boleh ada suffix pre-release/build).
    $semverPattern = '/^\d+\.\d+\.\d+(-[0-9A-Za-z.-]+)?(\+[0-9A-Za-z.-]+)?$/';
    check($failures, (bool) preg_match($semverPattern, $version), "Isi VERSION ('$version') bukan format SemVer yang valid.");

    // 3. APP_VERSION (dibaca via functions.php) harus identik dengan isi VERSION.
    define('ROOT_PATH', $root);
    require $root . '/includes/functions.php';
    check($failures, defined('APP_VERSION'), 'Konstanta APP_VERSION tidak terdefinisi setelah require functions.php.');
    if (defined('APP_VERSION')) {
        check($failures, APP_VERSION === $version, "APP_VERSION ('" . APP_VERSION . "') tidak sinkron dengan file VERSION ('$version').");
    }

    // 4. CHANGELOG.md harus punya heading untuk versi ini (disiplin rilis).
    $changelog = (string) file_get_contents($root . '/CHANGELOG.md');
    check($failures, str_contains($changelog, "[$version]"), "CHANGELOG.md belum punya entri untuk versi $version.");
}

if ($failures) {
    fwrite(STDERR, "GAGAL - test-version.php:\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "OK - VERSION SSOT valid dan sinkron.\n";
exit(0);
