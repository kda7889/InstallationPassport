# Деплой на HestiaCP (поддомен doc.krdalp.ru)

## 1) Создать веб-домен в HestiaCP

1. В панели HestiaCP: **Web → Add Web Domain**
   - Domain: `doc.krdalp.ru`
   - IP: основной адрес VPS
   - Включить **DNS Support** (если NS управляются HestiaCP) — иначе создайте A-запись `doc → IP VPS` у регистратора домена.
   - **SSL Support**: включить, поставить галку **Lets Encrypt Support** (выпустит сертификат автоматически после того, как DNS прорастёт).
2. После того как сертификат выпустится — открыть домен и включить **Force HTTPS redirect**.

По умолчанию HestiaCP даёт такую структуру:
```
/home/<user>/web/doc.krdalp.ru/
├── public_html/      ← document root (то, что видно из веба)
├── public_shtml/     ← document root для HTTPS (обычно симлинк на public_html)
├── private/          ← НЕ обслуживается вебом, идеально для исходников
└── stats/, logs/
```

## 2) Залить код

По SSH под пользователем-владельцем домена (НЕ root):

```bash
ssh <user>@krdalp.ru
cd ~/web/doc.krdalp.ru

# Залить исходники в private/ (вне webroot)
git clone https://github.com/kda7889/installationpassport.git private/installationpassport
cd private/installationpassport
git checkout main   # или нужная вам стабильная ветка
```

## 3) Указать webroot на public/

Самый безопасный способ — заменить `public_html` симлинком на `private/installationpassport/public`:

```bash
cd ~/web/doc.krdalp.ru
# сохраним то, что HestiaCP положил по умолчанию
mv public_html public_html.hestia_default
ln -s private/installationpassport/public public_html

# то же для HTTPS-папки (если она физическая, а не симлинк)
if [ -d public_shtml ] && [ ! -L public_shtml ]; then
  mv public_shtml public_shtml.hestia_default
  ln -s private/installationpassport/public public_shtml
fi
```

Проверить, что Apache следует по симлинкам — в HestiaCP по умолчанию `Options +SymLinksIfOwnerMatch`, владелец совпадает, всё ок.

## 4) Установить зависимости (mPDF)

Composer обычно уже стоит. Если нет — `sudo apt install composer`.

```bash
cd ~/web/doc.krdalp.ru/private/installationpassport
composer require mpdf/mpdf --no-dev --optimize-autoloader
```

## 5) Подготовить storage/

```bash
cd ~/web/doc.krdalp.ru/private/installationpassport
mkdir -p storage/installations storage/tmp
chmod 750 storage storage/installations storage/tmp
```

В HestiaCP PHP-FPM работает от того же пользователя, что и SSH, поэтому отдельной смены владельца обычно не нужно. Проверить можно так:

```bash
ps -o user= -p $(pgrep -f "php-fpm: pool $(whoami)" | head -n1)
```

## 6) Опционально — задать креды админа через ENV

По умолчанию при первом запуске создастся пользователь `admin@example.com` со случайным паролем; пароль будет записан в `storage/initial-admin-credentials.txt` (после первого захода — войдите, смените пароль через `/users.php` и **удалите файл**).

Если хотите задать креды заранее — в HestiaCP **Web → doc.krdalp.ru → Edit → Advanced Options → PHP-FPM Pool → Custom config**:

```ini
env[ADMIN_EMAIL] = "you@krdalp.ru"
env[ADMIN_PASSWORD] = "<стойкий-пароль>"
```

После сохранения — `service php8.X-fpm restart` (или через HestiaCP).

## 7) Preflight

```bash
cd ~/web/doc.krdalp.ru/private/installationpassport
php scripts/preflight.php
```

Должно быть `Preflight result: OK`. Если какое-то расширение не найдено:
- `pdo_sqlite`, `gd`, `fileinfo`, `mbstring`, `exif` — ставятся пакетами `php8.X-sqlite3`, `php8.X-gd`, `php8.X-mbstring`. Версия PHP — та, что выбрана для домена в HestiaCP (**Web → Edit → PHP Version**, рекомендую 8.2+).

## 8) Первый заход

1. Открыть `https://doc.krdalp.ru/` — должен редиректнуть на `/login.php`.
2. Прочитать пароль:
   ```bash
   cat ~/web/doc.krdalp.ru/private/installationpassport/storage/initial-admin-credentials.txt
   ```
3. Войти, сменить пароль через `/users.php` (создать нового админа, отключить дефолтного).
4. Удалить файл с дефолтным паролем:
   ```bash
   rm ~/web/doc.krdalp.ru/private/installationpassport/storage/initial-admin-credentials.txt
   ```

## 9) Дополнительная защита (опционально, для nginx-only режима)

Если в HestiaCP включён шаблон **nginx-only** (без Apache backend), то `.htaccess` игнорируется. В этом случае добавить кастомные правила через **Web → Edit → Advanced Options → Nginx Settings**:

```nginx
location ~ ^/(app|scripts|storage|database\.sql|\.git|\.htaccess|composer\.(json|lock))(/|$) {
    deny all;
    return 404;
}

location ~* /storage/.*\.(php|phtml|phar)$ {
    deny all;
    return 404;
}
```

В стандартном шаблоне (nginx-proxy + apache) `.htaccess`-файлы из репозитория уже всё закрывают.

## 10) Бэкапы

- БД: `~/web/doc.krdalp.ru/private/installationpassport/storage/database.sqlite`
- Фото и PDF: `~/web/doc.krdalp.ru/private/installationpassport/storage/installations/`

Простой cron-бэкап (HestiaCP → **Cron Jobs**):

```bash
0 3 * * * tar czf /home/<user>/backup/doc-$(date +\%Y\%m\%d).tar.gz -C /home/<user>/web/doc.krdalp.ru/private/installationpassport storage && find /home/<user>/backup/ -name "doc-*.tar.gz" -mtime +14 -delete
```

(каталог `~/backup/` создать заранее, права 700).

## 11) Обновление кода

```bash
cd ~/web/doc.krdalp.ru/private/installationpassport
git pull
composer install --no-dev --optimize-autoloader
```

БД и storage не трогаются — обновляется только код.
