# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Initial setup
composer setup

# Development (runs server, queue, log streaming, and Vite dev server concurrently)
composer run dev

# Run tests
composer test

# Build frontend assets
npm run build

# Database migrations
php artisan migrate

# Register Telegram webhook
php artisan telegram:set-webhook

# Sync Trello board data (lists, labels, members)
php artisan trello:sync {connection_id}
```

## Architecture

This is a Laravel 12 application that serves as a Telegram bot to automatically create Trello cards from Telegram messages.

### Main Flow

```
Telegram Message → POST /webhooks/telegram
  → TelegramWebhookController (validates secret, saves to DB)
  → ProcessTelegramUpdateJob (async via database queue)
  → TelegramUpdateParser (extracts message data into DTO)
  → RoutingEngine (finds matching rule by chat_id, command, etc.)
  → TrelloAdapter (creates card, attaches files, assigns labels/members)
  → TelegramAdapter (sends response with card link back to user)
```

### Layered Architecture

The codebase is split into two distinct layers:

- **`src/TelegramBot/`** — Framework-agnostic pure PHP module with interfaces, DTOs, services, and business logic. This layer should not depend on Laravel directly.
- **`app/`** — Laravel-specific layer: controllers, jobs, Filament admin resources, Eloquent models, service providers that bind interfaces to implementations.

DI bindings between layers are registered in `app/Providers/AppServiceProvider.php`.

### Key Components

| Component | Location | Purpose |
|-----------|----------|---------|
| `RoutingEngine` | `src/TelegramBot/Routing/` | Evaluates routing rules in priority order, matches on chat_id, command, has_photo |
| `TelegramUpdateParser` | `src/TelegramBot/Parsers/` | Parses Telegram updates into `TelegramMessageDTO` with template variables |
| `TrelloAdapter` | `src/TelegramBot/Adapters/` | Trello API integration (create cards, upload files, sync board data) |
| `TelegramAdapter` | `src/TelegramBot/Adapters/` | Telegram API integration (send messages, download files) |
| `ProcessTelegramUpdateJob` | `app/Jobs/` | Async job — all core processing logic runs here, not in the controller |
| Filament Resources | `app/Filament/` | Admin panel at `/admin` for managing connections, routing rules, logs |

### Template Variables

Trello card titles/descriptions support variables rendered from message data:
`{{first_name}}`, `{{username}}`, `{{text}}`, `{{date}}`, `{{time}}`, `{{chat_id}}`, etc.

### Admin Panel

Filament 5 admin panel at `/admin` manages:
- Trello connections (API keys per board)
- Routing rules (which chats/commands map to which Trello lists)
- Trello metadata (lists, labels, members — synced from board)
- Message logs and card creation audit trail

### Queue

All webhook processing is async. The controller only saves the raw update to `telegram_messages` and dispatches `ProcessTelegramUpdateJob`. Queue connection is `database`. Laravel Horizon dashboard is available at port 8050.

## Testing

Tests use an in-memory SQLite database (configured in `phpunit.xml`). Unit tests are in `tests/Unit/`, feature tests in `tests/Feature/`.

```bash
# Run all tests
composer test

# Run a single test file
php artisan test tests/Unit/RoutingEngineTest.php

# Run a specific test method
php artisan test --filter=test_method_name
```
