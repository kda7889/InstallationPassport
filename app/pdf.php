<?php

declare(strict_types=1);

function render_installation_pdf_html(array $installation, array $items, array $commonPhotos, array $itemPhotosMap): string
{
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
    <?php if ($commonPhotos): ?><h3>Общие фото объекта</h3><?php endif; ?>
    <?php foreach ($commonPhotos as $photo): ?>
        <p><?= h((string) $photo['title']) ?></p>
    <?php endforeach; ?>

    <?php foreach ($items as $item): ?>
        <h3><?= h((string) $item['title']) ?></h3>
        <?php foreach (($itemPhotosMap[(int) $item['id']] ?? []) as $photo): ?>
            <p><?= h((string) $photo['title']) ?></p>
        <?php endforeach; ?>
    <?php endforeach;
    return (string) ob_get_clean();
}
