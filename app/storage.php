<?php

declare(strict_types=1);

function ensure_installation_dirs(string $installationNumber): array
{
    $config = require __DIR__ . '/config.php';
    $year = preg_match('/^MP-(\d{4})/', $installationNumber, $m) ? $m[1] : date('Y');
    $base = $config['storage_path'] . '/' . $year . '/' . $installationNumber;
    $dirs = [
        "$base/common/photos/compressed",
        "$base/common/photos/thumbnails",
        "$base/documents",
    ];

    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    return ['base' => $base];
}
