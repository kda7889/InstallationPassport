<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];

$checks[] = ['PHP >= 8.0', version_compare(PHP_VERSION, '8.0.0', '>=')];

$requiredExtensions = ['pdo_sqlite', 'gd', 'fileinfo', 'mbstring'];
foreach ($requiredExtensions as $ext) {
    $checks[] = ["Extension: $ext", extension_loaded($ext)];
}
$checks[] = ['Extension: exif (recommended)', extension_loaded('exif')];

$checks[] = ['database.sql exists', file_exists($root . '/database.sql')];
$checks[] = ['public/ exists', is_dir($root . '/public')];
$checks[] = ['storage/ exists', is_dir($root . '/storage') || mkdir($root . '/storage', 0775, true)];
$checks[] = ['storage/ writable', is_writable($root . '/storage')];

$checks[] = ['mPDF autoload available', file_exists($root . '/vendor/autoload.php')];
if (file_exists($root . '/vendor/autoload.php')) {
    require_once $root . '/vendor/autoload.php';
    $checks[] = ['mPDF class available', class_exists('Mpdf\\Mpdf')];
}

$failed = false;
foreach ($checks as [$label, $ok]) {
    $status = $ok ? 'OK' : 'FAIL';
    echo sprintf("[%s] %s\n", $status, $label);
    if (!$ok) {
        $failed = true;
    }
}

if ($failed) {
    echo "\nPreflight result: FAIL\n";
    exit(1);
}

echo "\nPreflight result: OK\n";
