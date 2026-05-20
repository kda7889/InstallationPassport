<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php';
    if (!is_dir(dirname($config['db_path']))) {
        mkdir(dirname($config['db_path']), 0775, true);
    }

    $pdo = new PDO('sqlite:' . $config['db_path']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    return $pdo;
}

function db_migrate_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $pdo = db();

    $hasVerification = false;
    foreach ($pdo->query('PRAGMA table_info(installations)')->fetchAll() as $col) {
        if (($col['name'] ?? '') === 'verification_code') {
            $hasVerification = true;
            break;
        }
    }
    if (!$hasVerification) {
        $pdo->exec('ALTER TABLE installations ADD COLUMN verification_code TEXT');
        $pdo->exec("UPDATE installations SET verification_code = lower(hex(randomblob(6))) WHERE verification_code IS NULL OR verification_code = ''");
    }

    $pdo->exec('CREATE TABLE IF NOT EXISTS login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip TEXT NOT NULL,
        email TEXT,
        success INTEGER NOT NULL DEFAULT 0,
        attempted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_login_attempts_ip_time ON login_attempts(ip, attempted_at)');

    $pdo->exec('CREATE TABLE IF NOT EXISTS audit_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        action TEXT NOT NULL,
        entity_type TEXT,
        entity_id INTEGER,
        metadata TEXT,
        ip TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_audit_log_created_at ON audit_log(created_at DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_audit_log_user_id ON audit_log(user_id, created_at DESC)');

    $pdo->exec('CREATE TABLE IF NOT EXISTS app_settings (
        key TEXT PRIMARY KEY,
        value TEXT
    )');

    db_migrate_multitenant();
    db_migrate_photo_stage();
    db_migrate_reviews();
    db_seed_photo_templates();

    $done = true;
}

function _table_has_column(string $table, string $name): bool
{
    foreach (db()->query("PRAGMA table_info({$table})")->fetchAll() as $col) {
        if (($col['name'] ?? '') === $name) {
            return true;
        }
    }
    return false;
}

function db_migrate_multitenant(): void
{
    $pdo = db();

    $pdo->exec('CREATE TABLE IF NOT EXISTS companies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        slug TEXT UNIQUE,
        inn TEXT,
        phone TEXT,
        email TEXT,
        address TEXT,
        logo_path TEXT,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    if (!_table_has_column('users', 'company_id')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN company_id INTEGER');
    }
    if (!_table_has_column('users', 'is_superadmin')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN is_superadmin INTEGER NOT NULL DEFAULT 0');
    }
    if (!_table_has_column('installations', 'company_id')) {
        $pdo->exec('ALTER TABLE installations ADD COLUMN company_id INTEGER');
    }
    if (!_table_has_column('installations', 'access_token')) {
        $pdo->exec('ALTER TABLE installations ADD COLUMN access_token TEXT');
        $pdo->exec("UPDATE installations SET access_token = lower(hex(randomblob(8))) WHERE access_token IS NULL OR access_token = ''");
    }

    $existing = (int) $pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn();
    if ($existing === 0) {
        $get = static function (string $key): ?string {
            $stmt = db()->prepare('SELECT value FROM app_settings WHERE key = :k');
            $stmt->execute(['k' => $key]);
            $v = $stmt->fetchColumn();
            return $v !== false ? (string) $v : null;
        };
        $name = $get('company_name');
        if ($name === null || $name === '') {
            $name = 'Моя компания';
        }
        $pdo->prepare('INSERT INTO companies (id, name, slug, inn, phone, email, address, logo_path, is_active) VALUES (1, :name, :slug, :inn, :phone, :email, :address, :logo, 1)')
            ->execute([
                'name' => $name,
                'slug' => 'default',
                'inn' => $get('company_inn'),
                'phone' => $get('company_phone'),
                'email' => $get('company_email'),
                'address' => $get('company_address'),
                'logo' => $get('company_logo_path'),
            ]);
    }

    $pdo->exec('UPDATE users SET company_id = (SELECT MIN(id) FROM companies) WHERE company_id IS NULL');
    $pdo->exec('UPDATE installations SET company_id = (SELECT MIN(id) FROM companies) WHERE company_id IS NULL');

    $hasSuperadmin = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_superadmin = 1')->fetchColumn();
    if ($hasSuperadmin === 0) {
        $pdo->exec("UPDATE users SET is_superadmin = 1 WHERE id = (SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id ASC LIMIT 1)");
    }
}

function db_migrate_photo_stage(): void
{
    if (!_table_has_column('installation_photos', 'photo_stage')) {
        db()->exec("ALTER TABLE installation_photos ADD COLUMN photo_stage TEXT DEFAULT 'other'");
    }
}

function db_migrate_reviews(): void
{
    $pdo = db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS reviews (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        installation_id INTEGER NOT NULL,
        period_label TEXT NOT NULL DEFAULT "initial",
        overall_rating INTEGER,
        text TEXT,
        suggestions TEXT,
        customer_name_provided TEXT,
        is_hidden INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(installation_id) REFERENCES installations(id) ON DELETE CASCADE
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reviews_installation ON reviews(installation_id, created_at DESC)');
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS ux_reviews_installation_period ON reviews(installation_id, period_label) WHERE period_label != 'custom'");

    $pdo->exec('CREATE TABLE IF NOT EXISTS review_ratings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        review_id INTEGER NOT NULL,
        criterion TEXT NOT NULL,
        stars INTEGER NOT NULL,
        FOREIGN KEY(review_id) REFERENCES reviews(id) ON DELETE CASCADE,
        UNIQUE(review_id, criterion)
    )');
}

function db_seed_photo_templates(): void
{
    $pdo = db();
    $seeds = [
        'electric' => [
            ['electric_panel_general', 'Электрощит — общий вид', 1, 10],
            ['breakers_labeled', 'Маркировка автоматов', 1, 20],
            ['ground_connection', 'Заземление', 1, 30],
            ['cable_routing', 'Прокладка кабеля', 0, 40],
            ['meter_reading', 'Показания счётчика', 0, 50],
        ],
        'plumbing' => [
            ['pipe_routing', 'Прокладка труб', 1, 10],
            ['pressure_test', 'Опрессовка системы', 1, 20],
            ['shutoff_valves', 'Запорная арматура', 1, 30],
            ['meter_install', 'Узел учёта воды', 0, 40],
            ['leak_check', 'Проверка на протечки', 0, 50],
        ],
        'ventilation' => [
            ['duct_routing', 'Прокладка воздуховодов', 1, 10],
            ['equipment_install', 'Установка оборудования', 1, 20],
            ['grilles_installed', 'Решётки на местах', 0, 30],
            ['airflow_measure', 'Замер расхода воздуха', 0, 40],
        ],
        'cctv_access' => [
            ['camera_locations', 'Расположение камер', 1, 10],
            ['nvr_installed', 'Установка регистратора', 1, 20],
            ['cable_routing', 'Кабельные трассы', 0, 30],
            ['ui_demo', 'Демонстрация ПО заказчику', 0, 40],
        ],
    ];

    $wt = $pdo->query('SELECT id, code FROM work_types')->fetchAll();
    $byCode = [];
    foreach ($wt as $r) {
        $byCode[$r['code']] = (int) $r['id'];
    }

    $insert = $pdo->prepare("INSERT INTO photo_templates (work_type_id, scope, code, title, is_important, sort_order, is_active) VALUES (:work_type_id, 'item', :code, :title, :is_important, :sort_order, 1) ON CONFLICT(work_type_id, scope, code) DO NOTHING");

    foreach ($seeds as $workCode => $rows) {
        if (!isset($byCode[$workCode])) {
            continue;
        }
        foreach ($rows as [$code, $title, $important, $sort]) {
            $insert->execute([
                'work_type_id' => $byCode[$workCode],
                'code' => $code,
                'title' => $title,
                'is_important' => $important,
                'sort_order' => $sort,
            ]);
        }
    }
}

function db_bootstrap_if_needed(): void
{
    $exists = db()->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetch();
    if ($exists) {
        db_migrate_schema();
        return;
    }

    $sql = file_get_contents(__DIR__ . '/../database.sql');
    if ($sql !== false) {
        db()->exec($sql);
    }

    $config = require __DIR__ . '/config.php';
    $defaultEmail = getenv('ADMIN_EMAIL') ?: 'admin@example.com';
    $envPassword = getenv('ADMIN_PASSWORD');
    $password = $envPassword !== false && $envPassword !== '' ? $envPassword : bin2hex(random_bytes(8));
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = db()->prepare('INSERT INTO users (name, email, password_hash, role, is_active) VALUES (:name, :email, :password_hash, :role, 1)');
    $stmt->execute([
        'name' => 'Администратор',
        'email' => $defaultEmail,
        'password_hash' => $hash,
        'role' => 'admin',
    ]);

    $storageDir = dirname($config['db_path']);
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0775, true);
    }
    $credPath = $storageDir . '/initial-admin-credentials.txt';
    file_put_contents(
        $credPath,
        "Initial admin account created at " . date('c') . "\n" .
        "Email:    {$defaultEmail}\n" .
        "Password: {$password}\n\n" .
        "Войдите, смените пароль и удалите этот файл.\n"
    );
    @chmod($credPath, 0600);

    db_migrate_schema();
}
