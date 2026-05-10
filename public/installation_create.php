<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_auth();
$user = current_user();

$types = db()->query('SELECT id, name FROM work_types WHERE is_active = 1 ORDER BY sort_order')->fetchAll();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate(post('_csrf'))) {
        $error = 'Ошибка CSRF.';
    } else {
        $workTypeId = (int) post('work_type_id', '0');
        $address = (string) post('address', '');
        if ($workTypeId <= 0 || $address === '') {
            $error = 'Выберите тип работ и укажите адрес.';
        } else {
            $year = date('Y');
            $count = (int) db()->query('SELECT COUNT(*) FROM installations')->fetchColumn() + 1;
            $number = sprintf('MP-%s-%06d', $year, $count);
            $stmt = db()->prepare('INSERT INTO installations (number, work_type_id, user_id, install_date, address, status, created_at, updated_at) VALUES (:number, :work_type_id, :user_id, :install_date, :address, :status, :created_at, :updated_at)');
            $stmt->execute([
                'number' => $number,
                'work_type_id' => $workTypeId,
                'user_id' => $user['id'],
                'install_date' => date('Y-m-d'),
                'address' => $address,
                'status' => 'draft',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            ensure_installation_dirs($number);
            redirect('/dashboard.php');
        }
    }
}
?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Новый монтаж</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body>
<div class="container py-3"><a href="/dashboard.php" class="btn btn-link px-0">← Назад</a><h1 class="h4">Новый монтаж</h1>
<?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
<form method="post" class="card card-body">
<input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
<label class="form-label">Тип работ</label>
<select name="work_type_id" class="form-select form-select-lg mb-3" required>
<option value="">Выберите</option>
<?php foreach ($types as $type): ?><option value="<?= (int) $type['id'] ?>"><?= h((string) $type['name']) ?></option><?php endforeach; ?>
</select>
<label class="form-label">Адрес объекта</label>
<textarea name="address" class="form-control mb-3" rows="3" required></textarea>
<button class="btn btn-primary btn-lg">Создать черновик</button>
</form></div></body></html>
