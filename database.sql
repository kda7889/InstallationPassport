PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  phone TEXT,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL CHECK(role IN ('admin', 'installer')),
  is_active INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS work_types (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  description TEXT,
  is_active INTEGER NOT NULL DEFAULT 1,
  sort_order INTEGER NOT NULL DEFAULT 100
);

CREATE TABLE IF NOT EXISTS installations (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  number TEXT NOT NULL UNIQUE,
  work_type_id INTEGER NOT NULL,
  user_id INTEGER NOT NULL,
  install_date TEXT,
  address TEXT NOT NULL,
  customer_name TEXT,
  customer_phone TEXT,
  installer_name TEXT,
  installer_phone TEXT,
  company_name TEXT,
  company_inn TEXT,
  warranty_months INTEGER DEFAULT 24,
  warranty_until TEXT,
  work_description TEXT,
  comment TEXT,
  status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','in_progress','photos_partial','ready','pdf_generated','closed')),
  pdf_path TEXT,
  verification_code TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(work_type_id) REFERENCES work_types(id),
  FOREIGN KEY(user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS photo_templates (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  work_type_id INTEGER NOT NULL,
  scope TEXT NOT NULL DEFAULT 'common',
  code TEXT NOT NULL,
  title TEXT NOT NULL,
  description TEXT,
  is_important INTEGER NOT NULL DEFAULT 0,
  sort_order INTEGER NOT NULL DEFAULT 100,
  is_active INTEGER NOT NULL DEFAULT 1,
  FOREIGN KEY(work_type_id) REFERENCES work_types(id),
  UNIQUE(work_type_id, scope, code)
);

CREATE TABLE IF NOT EXISTS installation_photos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  installation_id INTEGER NOT NULL,
  photo_template_id INTEGER,
  scope TEXT NOT NULL DEFAULT 'common',
  photo_code TEXT NOT NULL,
  title TEXT,
  comment TEXT,
  file_path TEXT NOT NULL,
  thumb_path TEXT NOT NULL,
  mime_type TEXT NOT NULL,
  file_size INTEGER NOT NULL,
  width INTEGER,
  height INTEGER,
  uploaded_by INTEGER NOT NULL,
  uploaded_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(installation_id) REFERENCES installations(id) ON DELETE CASCADE,
  FOREIGN KEY(photo_template_id) REFERENCES photo_templates(id),
  FOREIGN KEY(uploaded_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS generated_documents (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  installation_id INTEGER NOT NULL,
  document_type TEXT NOT NULL,
  file_path TEXT NOT NULL,
  version INTEGER NOT NULL DEFAULT 1,
  created_by INTEGER NOT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(installation_id) REFERENCES installations(id) ON DELETE CASCADE,
  FOREIGN KEY(created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS login_attempts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ip TEXT NOT NULL,
  email TEXT,
  success INTEGER NOT NULL DEFAULT 0,
  attempted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_login_attempts_ip_time ON login_attempts(ip, attempted_at);

CREATE TABLE IF NOT EXISTS audit_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER,
  action TEXT NOT NULL,
  entity_type TEXT,
  entity_id INTEGER,
  metadata TEXT,
  ip TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_audit_log_created_at ON audit_log(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_audit_log_user_id ON audit_log(user_id, created_at DESC);

CREATE TABLE IF NOT EXISTS app_settings (
  key TEXT PRIMARY KEY,
  value TEXT
);

INSERT INTO work_types (code, name, description, sort_order) VALUES
('air_conditioner', 'Монтаж кондиционера', 'Монтаж сплит-систем и мульти-сплит систем', 10),
('electric', 'Электромонтаж', 'Электрощиты, линии, автоматы, освещение', 20),
('plumbing', 'Сантехника', 'Монтаж сантехнических узлов и подключений', 30),
('ventilation', 'Вентиляция', 'Монтаж вентиляционного оборудования и каналов', 40),
('cctv_access', 'Видеонаблюдение / СКУД', 'Камеры, регистраторы, контроллеры доступа', 50),
('other', 'Другое', 'Прочие монтажные и сервисные работы', 60)
ON CONFLICT(code) DO NOTHING;


INSERT INTO photo_templates (work_type_id, scope, code, title, is_important, sort_order, is_active)
SELECT wt.id, 'common', 'indoor_unit_general', 'Общий вид внутреннего блока', 1, 10, 1 FROM work_types wt WHERE wt.code='air_conditioner'
UNION ALL SELECT wt.id, 'common', 'outdoor_unit_general', 'Общий вид наружного блока', 1, 20, 1 FROM work_types wt WHERE wt.code='air_conditioner'
UNION ALL SELECT wt.id, 'common', 'vacuuming', 'Вакуумирование трассы', 1, 30, 1 FROM work_types wt WHERE wt.code='air_conditioner'
UNION ALL SELECT wt.id, 'common', 'drainage', 'Дренаж', 1, 40, 1 FROM work_types wt WHERE wt.code='air_conditioner'
UNION ALL SELECT wt.id, 'common', 'nameplate_outdoor', 'Шильдик наружного блока', 1, 50, 1 FROM work_types wt WHERE wt.code='air_conditioner'
ON CONFLICT(work_type_id, scope, code) DO NOTHING;
