<?php

declare(strict_types=1);

function image_load_by_mime(string $tmpPath, string $mime)
{
    return match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($tmpPath),
        'image/png' => imagecreatefrompng($tmpPath),
        'image/webp' => imagecreatefromwebp($tmpPath),
        default => false,
    };
}

function image_fix_orientation(string $tmpPath, $image)
{
    if (!function_exists('exif_read_data')) {
        return $image;
    }

    $exif = @exif_read_data($tmpPath);
    $orientation = (int) ($exif['Orientation'] ?? 1);

    return match ($orientation) {
        3 => imagerotate($image, 180, 0),
        6 => imagerotate($image, -90, 0),
        8 => imagerotate($image, 90, 0),
        default => $image,
    };
}

function image_resize($source, int $maxSide)
{
    $w = imagesx($source);
    $h = imagesy($source);
    $scale = min(1, $maxSide / max($w, $h));
    $nw = max(1, (int) floor($w * $scale));
    $nh = max(1, (int) floor($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($dst, $source, 0, 0, 0, 0, $nw, $nh, $w, $h);

    return $dst;
}
