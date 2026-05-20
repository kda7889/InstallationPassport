<?php

declare(strict_types=1);

function render_installation_pdf_html(array $installation, array $items, array $commonPhotos, array $itemPhotosMap, ?string $verifyBaseUrl = null): string
{
    $root = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
    $branding = company_branding((int) ($installation['company_id'] ?? 0));

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

    $logoAbs = '';
    if (!empty($branding['logo_path'])) {
        $candidate = $root . '/' . ltrim((string) $branding['logo_path'], '/');
        if (is_file($candidate)) {
            $logoAbs = $candidate;
        }
    }

    $warrantyUntilFmt = '';
    if (!empty($installation['warranty_until'])) {
        $ts = strtotime((string) $installation['warranty_until']);
        if ($ts !== false) {
            $warrantyUntilFmt = date('d.m.Y', $ts);
        }
    }

    $hasBranding = $logoAbs !== '' || !empty($branding['name']) || !empty($branding['inn'])
        || !empty($branding['phone']) || !empty($branding['email']) || !empty($branding['address']);

    ob_start();
    ?>
    <?php if ($hasBranding): ?>
    <table style="width:100%; border-bottom:1px solid #999; margin-bottom:10px;">
        <tr>
            <?php if ($logoAbs !== ''): ?>
                <td style="vertical-align:middle; width:140px;">
                    <img src="<?= h($logoAbs) ?>" style="max-width:130px; max-height:80px;">
                </td>
            <?php endif; ?>
            <td style="vertical-align:middle; padding-left:10px;">
                <?php if (!empty($branding['name'])): ?>
                    <div style="font-size:13pt; font-weight:bold;"><?= h((string) $branding['name']) ?></div>
                <?php endif; ?>
                <?php if (!empty($branding['inn'])): ?>
                    <div style="font-size:9pt; color:#444;">ИНН/ОГРН: <?= h((string) $branding['inn']) ?></div>
                <?php endif; ?>
                <?php if (!empty($branding['phone']) || !empty($branding['email'])): ?>
                    <div style="font-size:9pt; color:#444;">
                        <?= h((string) $branding['phone']) ?>
                        <?php if (!empty($branding['email'])): ?> · <?= h((string) $branding['email']) ?><?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($branding['address'])): ?>
                    <div style="font-size:9pt; color:#444;"><?= h((string) $branding['address']) ?></div>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    <?php endif; ?>

    <h1 style="margin-top:5px;">ГАРАНТИЙНЫЙ ТАЛОН</h1>
    <p><strong>Номер талона:</strong> <?= h((string) $installation['number']) ?></p>
    <p><strong>Дата монтажа:</strong> <?= h(date('d.m.Y', strtotime((string) ($installation['install_date'] ?: 'today')))) ?></p>
    <p><strong>Тип работ:</strong> <?= h((string) ($installation['work_type_name'] ?? '')) ?></p>
    <p><strong>Адрес:</strong> <?= h((string) $installation['address']) ?></p>
    <p><strong>Заказчик:</strong> <?= h((string) ($installation['customer_name'] ?? '')) ?>, <?= h((string) ($installation['customer_phone'] ?? '')) ?></p>
    <p><strong>Исполнитель:</strong> <?= h((string) ($installation['installer_name'] ?? '')) ?>, <?= h((string) ($installation['installer_phone'] ?? '')) ?></p>
    <p><strong>Срок гарантии:</strong> <?= (int) ($installation['warranty_months'] ?? 0) ?> мес.<?php if ($warrantyUntilFmt !== ''): ?>, действует до <strong><?= h($warrantyUntilFmt) ?></strong><?php endif; ?></p>
    <?php if (!empty($installation['work_description'])): ?>
        <p><strong>Описание работ:</strong><br><?= nl2br(h((string) $installation['work_description'])) ?></p>
    <?php endif; ?>
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
    $verifyCode = (string) ($installation['verification_code'] ?? '');
    $accessToken = (string) ($installation['access_token'] ?? '');
    $generatedAt = date('d.m.Y H:i');
    $publicUrl = $verifyBaseUrl
        ? rtrim($verifyBaseUrl, '/') . '/customer.php?n=' . urlencode($number) . '&c=' . urlencode($verifyCode)
        : null;
    $personalUrl = $verifyBaseUrl && $accessToken !== ''
        ? rtrim($verifyBaseUrl, '/') . '/customer.php?n=' . urlencode($number) . '&c=' . urlencode($accessToken)
        : null;
    ?>

    <hr>
    <table style="margin-top:20px; font-size:9pt; color:#444; border:1px solid #999; padding:0; width:100%;">
        <tr>
            <td style="padding:8px; vertical-align:top;">
                <div><strong>Подлинность и онлайн-кабинет</strong></div>
                <div>Номер: <strong><?= h($number) ?></strong></div>
                <div>Публичный код проверки: <strong><?= h($verifyCode) ?></strong></div>
                <?php if ($accessToken !== ''): ?>
                    <div>Личный код доступа: <strong><?= h($accessToken) ?></strong></div>
                    <div style="font-size:8pt; color:#777;">с ним вы видите весь отчёт и оставляете отзывы. Никому не передавайте.</div>
                <?php endif; ?>
                <div style="margin-top:4px;">Сформирован: <?= h($generatedAt) ?></div>
                <?php if ($publicUrl): ?>
                    <div style="margin-top:4px;">Онлайн (публично): <?= h($publicUrl) ?></div>
                <?php endif; ?>
                <?php if ($personalUrl): ?>
                    <div>Личный кабинет: <?= h($personalUrl) ?></div>
                <?php endif; ?>
                <div style="margin-top:4px; color:#777;">Если кода нет в нашей базе — гарантийный талон поддельный.</div>
            </td>
            <?php if ($publicUrl): ?>
            <td style="width:120px; padding:8px; vertical-align:middle; text-align:center;">
                <barcode code="<?= h($publicUrl) ?>" type="QR" size="0.9" error="M" />
                <div style="font-size:7pt; color:#777; margin-top:2px;">QR — публичная проверка</div>
            </td>
            <?php endif; ?>
        </tr>
    </table>
    <?php
    return (string) ob_get_clean();
}
