<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$number = trim((string) ($_GET['n'] ?? $_POST['n'] ?? ''));
$code = trim((string) ($_GET['c'] ?? $_POST['c'] ?? ''));

$installation = null;
$status = null;

if ($number !== '' && $code !== '') {
    $stmt = db()->prepare('SELECT i.number, i.install_date, i.address, i.customer_name, i.company_name, i.warranty_months, i.verification_code, w.name AS work_type_name FROM installations i JOIN work_types w ON w.id = i.work_type_id WHERE i.number = :number LIMIT 1');
    $stmt->execute(['number' => $number]);
    $row = $stmt->fetch();

    if ($row && is_string($row['verification_code']) && hash_equals((string) $row['verification_code'], $code)) {
        $installation = $row;
        $status = 'ok';
    } else {
        $status = 'fail';
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Проверка гарантийного талона — МонтажПаспорт</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">

            <h1 class="h4 mb-3">Проверка гарантийного талона</h1>
            <p class="text-muted small">Введите номер документа и код проверки — они напечатаны в нижней части PDF-талона. Если номер и код совпадают с записью в базе — талон подлинный.</p>

            <form method="get" class="card card-body mb-3 shadow-sm">
                <label class="form-label">Номер документа</label>
                <input class="form-control mb-2" name="n" value="<?= h($number) ?>" placeholder="MP-20260519-A3F4E2" required>

                <label class="form-label">Код проверки</label>
                <input class="form-control mb-3" name="c" value="<?= h($code) ?>" placeholder="abcdef123456" required>

                <button class="btn btn-primary" type="submit">Проверить</button>
            </form>

            <?php if ($status === 'ok' && $installation): ?>
                <div class="alert alert-success">
                    <div class="fw-semibold mb-1">Документ подлинный ✓</div>
                </div>
                <div class="card card-body shadow-sm">
                    <div class="mb-2"><span class="text-muted">Номер:</span> <strong><?= h((string) $installation['number']) ?></strong></div>
                    <div class="mb-2"><span class="text-muted">Дата монтажа:</span> <?= h((string) ($installation['install_date'] ?? '')) ?></div>
                    <div class="mb-2"><span class="text-muted">Тип работ:</span> <?= h((string) $installation['work_type_name']) ?></div>
                    <div class="mb-2"><span class="text-muted">Адрес:</span> <?= h((string) $installation['address']) ?></div>
                    <?php if (!empty($installation['customer_name'])): ?>
                        <div class="mb-2"><span class="text-muted">Заказчик:</span> <?= h((string) $installation['customer_name']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($installation['company_name'])): ?>
                        <div class="mb-2"><span class="text-muted">Исполнитель:</span> <?= h((string) $installation['company_name']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($installation['warranty_months'])): ?>
                        <div><span class="text-muted">Срок гарантии:</span> <?= (int) $installation['warranty_months'] ?> мес.</div>
                    <?php endif; ?>
                </div>
            <?php elseif ($status === 'fail'): ?>
                <div class="alert alert-danger">
                    <div class="fw-semibold mb-1">Не нашли такой документ</div>
                    <div class="small">Проверьте номер и код. Если они напечатаны верно — это не наш гарантийный талон.</div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>
