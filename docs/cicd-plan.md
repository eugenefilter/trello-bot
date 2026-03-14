# План CI/CD для веток `main` и `develop`

## Контекст проекта

- **Laravel 12**, PHP ^8.2, SQLite in-memory для тестов
- **Тесты:** `composer test` (PHPUnit 11)
- **Линтер:** `laravel/pint` (уже в dev-зависимостях)
- **Frontend:** `npm run build` (Vite 7 + Tailwind 4)
- **Очередь:** database queue, Horizon
- **CI/CD платформа:** GitHub Actions (`.github/workflows/`)

---

## Шаг 1 — CI для ветки `develop` (`.github/workflows/ci.yml`)

**Триггеры:** `push` и `pull_request` на ветку `develop`

**Джобы:**

### 1.1 `lint`

```
Инструменты: PHP 8.4, composer
Шаги:
  1. checkout
  2. composer install --no-interaction --prefer-dist --optimize-autoloader
  3. ./vendor/bin/pint --test   # завершает с кодом 1 если есть нарушения
```

### 1.2 `test` (зависит от `lint`)

```
Инструменты: PHP 8.4, composer, Node.js 20
Шаги:
  1. checkout
  2. composer install --no-interaction --prefer-dist
  3. npm ci
  4. npm run build
  5. cp .env.example .env
  6. php artisan key:generate
  7. php artisan migrate --force   # SQLite :memory: из phpunit.xml — не нужно
  8. composer test
```

**Матрица:** пока только PHP 8.4

**Кеширование:**

- `vendor/` кешировать по хешу `composer.lock`
- `node_modules/` кешировать по хешу `package-lock.json` (если появится)

---

## Шаг 2 — CI + CD для ветки `main` (`.github/workflows/deploy.yml`)

**Триггеры:** только `push` на `main`

**Джобы:**

### 2.1 `ci` — те же джобы `lint` + `test` из шага 1

### 2.2 `deploy` (зависит от `ci`)

**Деплой через SSH (типичный для shared hosting / VPS):**

```
Шаги:
  1. checkout
  2. Настройка SSH-ключа (из GitHub Secrets)
  3. Копирование файлов на сервер (rsync или git pull)
  4. На сервере: composer install --no-dev --optimize-autoloader
  5. На сервере: npm ci && npm run build
  6. На сервере: php artisan migrate --force
  7. На сервере: php artisan config:cache
  8. На сервере: php artisan route:cache
  9. На сервере: php artisan view:cache
  10. На сервере: php artisan horizon:terminate  # перезапуск Horizon
  11. На сервере: php artisan queue:restart
```

---

## Шаг 3 — GitHub Secrets (настраиваются вручную)

| Secret            | Назначение                       |
|-------------------|----------------------------------|
| `SSH_PRIVATE_KEY` | Приватный ключ для SSH на сервер |
| `SSH_HOST`        | IP или домен сервера             |
| `SSH_USER`        | Пользователь SSH                 |
| `SSH_PATH`        | Путь к проекту на сервере        |
| `APP_KEY`         | Production APP_KEY               |

---

## Шаг 4 — Политика веток (Branch Protection Rules)

**Для `main`:**

- Запрет прямого push (только через PR)
- Обязательное прохождение `ci` workflow
- Минимум 1 review

**Для `develop`:**

- Обязательное прохождение `ci` workflow

---

## Итоговая структура файлов

```
.github/
  workflows/
    ci.yml          # lint + test для develop
    deploy.yml      # lint + test + deploy для main
```

---

## Порядок реализации для агента

1. Создать `.github/workflows/ci.yml` — CI для `develop`
2. Создать `.github/workflows/deploy.yml` — CI+CD для `main`
3. Добавить `composer lint` скрипт в `composer.json` (`pint --test`)
4. Настроить Branch Protection Rules через GitHub API или вручную
5. Добавить GitHub Secrets
6. Сделать тестовый push в `develop` для проверки

---

## Открытые вопросы

1. Куда деплоится `main` — VPS по SSH, или другая платформа (Forge, Envoyer, CapRover, Docker)?
2. Есть ли уже `package-lock.json` или используется только `composer.lock`?
