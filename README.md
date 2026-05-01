# Trello Bot

Trello Bot is a Laravel 12 application that receives Telegram updates via webhook, applies routing rules, and creates or
updates Trello cards automatically.

## What It Does

- Accepts Telegram webhook updates at `POST /webhooks/telegram`
- Stores updates and processes them asynchronously via queue jobs
- Routes messages by configurable rules (chat, command, media, priority)
- Creates Trello cards with template-based title/description
- Attaches photos/documents (including media groups)
- Supports callback actions from Telegram inline buttons (card deletion)
- Supports edited messages and updates existing Trello cards
- Provides Filament admin panel for connections, rules, logs, lists, labels, and members

## Tech Stack

- PHP 8.2+
- Laravel 12
- Filament 5
- Telegram Bot SDK (`irazasyed/telegram-bot-sdk`)
- Laravel Horizon

## Quick Start

1. Install dependencies:

```bash
composer install
npm install
```

2. Configure environment:

```bash
cp .env.example .env
php artisan key:generate
```

3. Set required env variables in `.env`:

```env
APP_URL=http://localhost
TELEGRAM_BOT_TOKEN=...
TELEGRAM_WEBHOOK_SECRET=...
```

4. Run migrations and build assets:

```bash
php artisan migrate
npm run build
```

5. Start local development services:

```bash
composer run dev
```

## Локальное тестирование вебхука

Для получения webhook-запросов от Telegram на локальной машине используйте [Expose](https://expose.dev/) (через Laravel Herd):

```bash
# Запустить туннель для проекта (из директории проекта)
cd ~/Documents/www/itsell/trello_bot && herd share
```

Скопируйте публичный URL из вывода expose (например, `https://xxxx.sharedwithexpose.com`), затем зарегистрируйте webhook:

```bash
APP_URL=https://xxxx.sharedwithexpose.com php artisan telegram:set-webhook
```

Убедитесь, что очередь запущена (`composer run dev`), — иначе сообщения сохраняются, но не обрабатываются.

## Trello Setup

1. Open admin panel and create a Trello connection (`/admin/trello-connections`)
2. Enter board ID, API key, and token
3. Sync board dictionaries (lists, labels, members)
4. Create routing rules in `/admin/routing-rules`

## Quality Checks

```bash
composer test
composer lint
```

## License

MIT (see `LICENSE`).
