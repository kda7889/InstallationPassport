<?php

declare(strict_types=1);

function render_installation_pdf_html(array $installation, array $items, array $commonPhotos, array $itemPhotosMap, ?string $verifyBaseUrl = null): string
{
    $root = realpath(__DIR__ . '/..') ?: dirname(__DIR__);

    $photoHtml = static function (array $photo) use ($root): string {
        $rel = (string) ($photo['thumb_path'] ?? $photo['file_path'] ?? '');
        if ($rel === '') {
            return '';
        }
        $abs = $root . '/' . ltrim($rel, '/');
        if (!is_file($abs)) {
            return '';
        }
        return '<img src="' . h($abs) . '" style="width:260px;">';
    };

    ob_start();
    ?>
    <h1>ГАРАНТИЙНЫЙ ТАЛОН</h1>
    <p><strong>Номер талона:</strong> <?= h((string) $installation['number']) ?></p>
    <p><strong>Дата:</strong> <?= h((string) ($installation['install_date'] ?? '')) ?></p>
    <p><strong>Тип работ:</strong> <?= h((string) ($installation['work_type_name'] ?? '')) ?></p>
    <p><strong>Адрес:</strong> <?= h((string) $installation['address']) ?></p>
    <p><strong>Заказчик:</strong> <?= h((string) ($installation['customer_name'] ?? '')) ?>, <?= h((string) ($installation['customer_phone'] ?? '')) ?></p>
    <p><strong>Исполнитель:</strong> <?= h((string) ($installation['installer_name'] ?? '')) ?>, <?= h((string) ($installation['installer_phone'] ?? '')) ?></p>
    <hr>
    <h2>Состав оборудования</h2>
    <?php foreach ($items as $idx => $item): ?>
        <p><strong><?= $idx + 1 ?>. <?= h((string) $item['title']) ?></strong> (<?= h((string) ($item['location'] ?? '')) ?>)<br>
        Марка: <?= h((string) ($item['brand'] ?? '')) ?>, Модель: <?= h((string) ($item['model'] ?? '')) ?></p>
    <?php endforeach; ?>
    <hr>
    <h2>Фотоотчет</h2>
    <?php if ($commonPhotos): ?>
        <h3>Общие фото объекта</h3>
        <?php foreach ($commonPhotos as $photo): ?>
            <table style="margin-bottom:8px;"><tr>
                <td style="vertical-align:top; width:280px;"><?= $photoHtml($photo) ?></td>
                <td style="vertical-align:top; padding-left:10px;"><?= h((string) $photo['title']) ?></td>
            </tr></table>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php foreach ($items as $item): ?>
        <?php $itemPhotos = $itemPhotosMap[(int) $item['id']] ?? []; ?>
        <?php if ($itemPhotos): ?>
            <h3><?= h((string) $item['title']) ?></h3>
            <?php foreach ($itemPhotos as $photo): ?>
                <table style="margin-bottom:8px;"><tr>
                    <td style="vertical-align:top; width:280px;"><?= $photoHtml($photo) ?></td>
                    <td style="vertical-align:top; padding-left:10px;"><?= h((string) $photo['title']) ?></td>
                </tr></table>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php
    $number = (string) $installation['number'];
    $code = (string) ($installation['verification_code'] ?? '');
    $generatedAt = date('d.m.Y H:i');
    $verifyUrl = $verifyBaseUrl
        ? rtrim($verifyBaseUrl, '/') . '/verify.php?n=' . urlencode($number) . '&c=' . urlencode($code)
        : null;
    ?>

    <hr>
    <table style="margin-top:20px; font-size:9pt; color:#444; border:1px solid #999; padding:0; width:100%;">
        <tr>
            <td style="padding:8px; vertical-align:top;">
                <div><strong>Подлинность документа</strong></div>
                <div>Номер: <strong><?= h($number) ?></strong></div>
                <div>Код проверки: <strong><?= h($code) ?></strong></div>
                <div>Сформирован: <?= h($generatedAt) ?></div>
                <?php if ($verifyUrl): ?>
                    <div style="margin-top:4px;">Сверить с базой: <?= h($verifyUrl) ?></div>
                <?php endif; ?>
                <div style="margin-top:4px; color:#777;">Если кода нет в нашей базе — гарантийный талон поддельный.</div>
            </td>
        </tr>
    </table>
    <?php
    return (string) ob_get_clean();
}
