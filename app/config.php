<?php

declare(strict_types=1);

return [
    'app_name' => 'МонтажПаспорт',
    'db_path' => __DIR__ . '/../storage/database.sqlite',
    'storage_path' => __DIR__ . '/../storage/installations',
    'max_upload_bytes' => 15 * 1024 * 1024,
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
    'image_max_side' => 1600,
    'thumb_max_side' => 400,
    'jpeg_quality' => 80,
];
