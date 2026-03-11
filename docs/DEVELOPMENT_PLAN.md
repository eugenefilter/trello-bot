# План разработки: Telegram-бот для создания карточек Trello

## Принципы разработки

- **TDD First** — тест пишется до реализации (Red → Green → Refactor)
- **SOLID** — каждый класс одна ответственность, зависимости через интерфейсы
- **KISS** — минимальная сложность, без преждевременных абстракций
- **Laravel 12 / PHP 8.2**
- **MySQL** для хранения
- **Queue** для обработки сообщений
- **Пакет для работы с телеграм ботом** - Telegram Bot SDK Документация по ссылке https://telegram-bot-sdk.com/docs/

---

## Архитектура слоёв

```
HTTP Layer       → TelegramWebhookController
Parser Layer     → TelegramUpdateParser
Routing Layer    → RoutingEngine
Integration      → TrelloAdapter, TelegramAdapter
Job Layer        → ProcessTelegramUpdateJob
Storage Layer    → Eloquent Models + Repositories
```

---

## Фазы разработки

### Фаза 0. Подготовка окружения

### Фаза 1. Telegram Webhook + сохранение update

### Фаза 2. Parser — разбор входящего сообщения

### Фаза 3. Базовая интеграция Trello (create card)

### Фаза 4. Routing Engine — правила маршрутизации

### Фаза 5. Labels + Members

### Фаза 6. Photo attachment

### Фаза 7. Ответ пользователю

### Фаза 8. Sync Trello-справочников

### Фаза 9. Команды бота

### Фаза 10. Надёжность (idempotency, retry, logging)

---

## Фаза 0. Подготовка окружения

### 0.1 Структура директорий

БЛ вынесена в отдельный модуль `src/TelegramBot/` — чистый PHP без зависимостей от Laravel.
`app/` содержит только фреймворк-специфичный код (контроллеры, джобы, модели Eloquent).

```
src/
  TelegramBot/                    ← чистый PHP-модуль (namespace: TelegramBot\)
    Contracts/
      TelegramAdapterInterface.php
      TrelloAdapterInterface.php
      RoutingEngineInterface.php
      UpdateParserInterface.php
    DTOs/
      TelegramMessageDTO.php
      TrelloCardDTO.php
      RoutingResultDTO.php
      TelegramFileInfo.php
      DownloadedFile.php
      CreatedCardResult.php
    Parsers/
      TelegramUpdateParser.php
    Routing/
      RoutingEngine.php
      RoutingRuleMatcher.php
    Services/
      TrelloCardCreator.php
      TelegramFileDownloader.php
      CardTemplateRenderer.php
    Adapters/
      TrelloAdapter.php
      TelegramAdapter.php
    Exceptions/
      TrelloAuthException.php
      TrelloValidationException.php
      TrelloConnectionException.php

app/                              ← Laravel-слой (namespace: App\)
  Http/
    Controllers/
      TelegramWebhookController.php
  Jobs/
    ProcessTelegramUpdateJob.php
  Models/
    TelegramMessage.php
    TelegramFile.php
    TrelloConnection.php
    TrelloList.php
    TrelloLabel.php
    TrelloMember.php
    RoutingRule.php
    TrelloCardLog.php
  Console/
    Commands/
      SyncTrelloBoardCommand.php
  Providers/
    AppServiceProvider.php        ← биндинги интерфейсов → реализации

tests/
  Unit/                           ← тесты src/TelegramBot/, без Laravel
    Parsers/
    Routing/
    Adapters/
    Services/
    Commands/
  Feature/                        ← тесты app/, с Laravel
    Webhook/
    Jobs/
    Commands/
```

### 0.2 Конфигурация

Добавить в `.env`:

```env
TELEGRAM_BOT_TOKEN=
TELEGRAM_WEBHOOK_SECRET=
TRELLO_API_KEY=
TRELLO_API_TOKEN=
```

Создать `config/telegram.php` и `config/trello.php`.

### 0.3 Базовые интерфейсы

Файл: `src/TelegramBot/Contracts/UpdateParserInterface.php`

```php
interface UpdateParserInterface
{
    public function parse(array $update): ?TelegramMessageDTO;
}
```

Файл: `src/TelegramBot/Contracts/RoutingEngineInterface.php`

```php
interface RoutingEngineInterface
{
    public function resolve(TelegramMessageDTO $message): ?RoutingResultDTO;
}
```

Файл: `src/TelegramBot/Contracts/TrelloAdapterInterface.php`

```php
interface TrelloAdapterInterface
{
    public function createCard(TrelloCardDTO $dto): CreatedCardResult;
    public function attachFile(string $cardId, string $filePath, string $mimeType): void;
    public function addMembersToCard(string $cardId, array $memberIds): void;
    public function addLabelsToCard(string $cardId, array $labelIds): void;
    public function getBoardLists(string $boardId): array;
    public function getBoardLabels(string $boardId): array;
    public function getBoardMembers(string $boardId): array;
}
```

Файл: `src/TelegramBot/Contracts/TelegramAdapterInterface.php`

```php
interface TelegramAdapterInterface
{
    public function sendMessage(string $chatId, string $text, array $options = []): void;
    public function getFile(string $fileId): TelegramFileInfo;
    public function downloadFile(string $filePath): string;
}
```

---

## Фаза 1. Telegram Webhook + сохранение update

### 1.1 Миграция `telegram_messages`

**Тест (Feature):** `tests/Feature/Webhook/TelegramWebhookTest.php`

```
[ ] POST /webhooks/telegram возвращает 200
[ ] POST с валидным update сохраняет запись в telegram_messages
[ ] POST с невалидным secret token возвращает 403
[ ] POST с уже известным update_id не создаёт дубль (idempotency)
[ ] POST без body возвращает 400
```

**Шаги реализации:**

1. Написать тесты (все Red)
2. Создать миграцию `create_telegram_messages_table`

```sql
id
, update_id (unique), message_id, chat_id, chat_type,
user_id, username, first_name, text, caption,
payload_json (json), received_at, created_at
```

3. Создать модель `TelegramMessage`
4. Создать `TelegramWebhookController@handle`
    - Проверка `X-Telegram-Bot-Api-Secret-Token`
    - Сохранение raw payload
    - Dispatch `ProcessTelegramUpdateJob`
    - Возврат `200 OK`
5. Добавить роут `POST /webhooks/telegram` (без CSRF)
6. Прогнать тесты (все Green)

### 1.2 Миграция `telegram_files`

```sql
id
, telegram_message_id (FK), file_id, file_unique_id,
file_path, file_type (photo/document), local_path,
mime_type, size, created_at
```

---

## Фаза 2. Parser — разбор входящего сообщения

### 2.1 DTO

**Файл:** `app/DTOs/TelegramMessageDTO.php`

```php
readonly class TelegramMessageDTO {
    string $messageType;   // text | photo | text_photo | command
    ?string $text;
    ?string $caption;
    array $photos;         // массив file_id
    array $documents;
    int $userId;
    string $chatId;
    string $chatType;      // private | group | supergroup
    ?string $command;      // /start | /bug | /task | null
    ?string $username;
    ?string $firstName;
    \DateTimeImmutable $sentAt;
}
```

### 2.2 Parser

**Тест (Unit):** `tests/Unit/Parsers/TelegramUpdateParserTest.php`

```
[ ] Парсит текстовое сообщение → messageType = 'text'
[ ] Парсит фото без текста → messageType = 'photo'
[ ] Парсит фото с caption → messageType = 'text_photo'
[ ] Определяет команду /bug → command = '/bug'
[ ] Определяет тип чата: private / group / supergroup
[ ] Возвращает null если update не содержит message
[ ] Правильно извлекает user_id, username, chat_id
[ ] Правильно разбирает массив photos (берёт наибольший file_id)
```

**Реализация:** `app/Parsers/TelegramUpdateParser.php`

```php
interface UpdateParserInterface {
    public function parse(array $update): ?TelegramMessageDTO;
}
```

- Один метод `parse(array $update): ?TelegramMessageDTO`
- Никаких HTTP-вызовов — чистая трансформация данных
- Легко тестируется с fixture-массивами

---

## Фаза 3. Базовая интеграция Trello (create card)

### 3.1 Миграции Trello-справочников

```
trello_connections (id, name, api_key, api_token, board_id, is_active)
trello_lists       (id, connection_id, trello_list_id, board_id, name, is_active)
trello_labels      (id, connection_id, trello_label_id, board_id, name, color, is_active)
trello_members     (id, connection_id, trello_member_id, username, full_name, is_active)
trello_cards_log   (id, telegram_message_id FK, trello_card_id, trello_card_url,
                    trello_list_id, status, error_message, created_at)
```

### 3.2 TrelloAdapter

**Тест (Unit):** `tests/Unit/Adapters/TrelloAdapterTest.php`

```
[ ] createCard вызывает POST /cards с правильными параметрами
[ ] createCard возвращает TrelloCardDTO с id и url
[ ] При 401 от Trello выбрасывает TrelloAuthException
[ ] При 422 от Trello выбрасывает TrelloValidationException
[ ] При сетевой ошибке выбрасывает TrelloConnectionException
```

**Реализация:** `app/Adapters/TrelloAdapter.php`

```php
interface TrelloAdapterInterface {
    public function createCard(TrelloCardDTO $dto): CreatedCardResult;
    public function attachFile(string $cardId, string $filePath, string $mimeType): void;
    public function addMembersToCard(string $cardId, array $memberIds): void;
    public function addLabelsToCard(string $cardId, array $labelIds): void;
    public function getBoardLists(string $boardId): array;
    public function getBoardLabels(string $boardId): array;
    public function getBoardMembers(string $boardId): array;
}
```

- HTTP-клиент инжектируется через конструктор (для mock в тестах)
- Использовать `Http::fake()` в feature-тестах

### 3.3 TrelloCardDTO

```php
readonly class TrelloCardDTO {
    string $listId;
    string $name;
    string $description;
    array $memberIds;   // []
    array $labelIds;    // []
}
```

### 3.4 TrelloCardCreator (Service)

**Тест (Unit):** `tests/Unit/Services/TrelloCardCreatorTest.php`

```
[ ] Создаёт карточку с названием по шаблону из routing rule
[ ] Создаёт карточку с описанием из DTO сообщения
[ ] Вызывает addMembers после создания
[ ] Вызывает addLabels после создания
[ ] Сохраняет запись в trello_cards_log со статусом 'success'
[ ] При ошибке Trello сохраняет запись в trello_cards_log со статусом 'error'
[ ] При ошибке пробрасывает исключение выше
```

---

## Фаза 4. Routing Engine

### 4.1 Миграция `routing_rules`

```sql
id
, name, telegram_chat_id (nullable), chat_type (nullable),
command (nullable), keyword (nullable), has_photo (nullable bool),
target_list_id (FK trello_lists), label_ids (json),
member_ids (json), card_title_template, card_description_template,
priority (int, default 0), is_active (bool), created_at
```

### 4.2 RoutingEngine

**Тест (Unit):** `tests/Unit/Routing/RoutingEngineTest.php`

```
[ ] Возвращает правило по точному совпадению chat_id + command
[ ] Возвращает правило только по chat_id если нет совпадения с командой
[ ] Возвращает правило только по command если нет chat_id
[ ] Возвращает правило по has_photo = true
[ ] Возвращает правило с наибольшим priority при нескольких совпадениях
[ ] Возвращает default-правило если ничего не совпало
[ ] Возвращает null если нет ни одного активного правила
```

**Реализация:** `app/Routing/RoutingEngine.php`

```php
interface RoutingEngineInterface {
    public function resolve(TelegramMessageDTO $message): ?RoutingResultDTO;
}
```

**RoutingResultDTO:**

```php
readonly class RoutingResultDTO {
    string $listId;
    array $memberIds;
    array $labelIds;
    string $cardTitleTemplate;
    string $cardDescriptionTemplate;
}
```

**Порядок поиска правила (KISS — только 4 критерия для MVP):**

```
1. chat_id + command
2. chat_id
3. command
4. has_photo
5. default (нет условий)
```

Правила отсортированы по `priority DESC`.

### 4.3 Job: ProcessTelegramUpdateJob

**Тест (Feature):** `tests/Feature/Jobs/ProcessTelegramUpdateJobTest.php`

```
[ ] Job парсит update и создаёт карточку Trello
[ ] Job не создаёт карточку если routing rule не найден
[ ] Job помечает update как обработанный
[ ] Job при ошибке Trello не теряет данные (запись в log)
```

**Реализация:** `app/Jobs/ProcessTelegramUpdateJob.php`

```php
class ProcessTelegramUpdateJob implements ShouldQueue {
    // Конструктор принимает telegram_message_id
    // handle(): Parser → Routing → TrelloCardCreator → TelegramAdapter::sendMessage
}
```

---

## Фаза 5. Labels + Members

### 5.1 Формат шаблона карточки

Переменные в шаблоне:

```
{{first_name}} {{username}} {{date}} {{time}} {{text_preview}} {{chat_name}}
```

**Тест (Unit):** `tests/Unit/Services/CardTemplateRendererTest.php`

```
[ ] Заменяет {{first_name}} на имя пользователя
[ ] Заменяет {{date}} на дату отправки
[ ] Заменяет {{text_preview}} на первые 80 символов текста
[ ] Неизвестные переменные остаются как есть
```

### 5.2 Описание карточки

Стандартный шаблон описания:

```
Источник: Telegram
Чат: {{chat_name}} ({{chat_type}})
Пользователь: {{first_name}} (@{{username}})
Telegram user id: {{user_id}}
Дата: {{date}} {{time}}

Текст:
{{text}}
```

---

## Фаза 6. Photo attachment

### 6.1 TelegramFileDownloader

**Тест (Unit):** `tests/Unit/Services/TelegramFileDownloaderTest.php`

```
[ ] Получает file_path через getFile API
[ ] Скачивает файл и сохраняет во временную директорию
[ ] Возвращает локальный путь и mime_type
[ ] Обновляет запись telegram_files с local_path
[ ] Выбрасывает исключение если file_id не найден
```

**Реализация:** `app/Services/TelegramFileDownloader.php`

- `downloadFile(string $fileId, int $telegramMessageId): DownloadedFile`
- Файлы сохраняются в `storage/app/telegram_files/`

### 6.2 Загрузка в Trello

**Тест:** добавить кейс в `TrelloAdapterTest`

```
[ ] attachFile отправляет multipart/form-data запрос
[ ] attachFile принимает локальный путь файла
[ ] Файл удаляется после успешной загрузки в Trello
```

---

## Фаза 7. Ответ пользователю

### 7.1 TelegramAdapter

**Тест (Unit):** `tests/Unit/Adapters/TelegramAdapterTest.php`

```
[ ] sendMessage отправляет POST /sendMessage с chat_id и text
[ ] sendMessage поддерживает parse_mode Markdown
[ ] При ошибке Telegram логирует, не кидает исключение (non-critical)
```

**Реализация:** `app/Adapters/TelegramAdapter.php`

```php
interface TelegramAdapterInterface {
    public function sendMessage(string $chatId, string $text, array $options = []): void;
    public function getFile(string $fileId): TelegramFileInfo;
    public function downloadFile(string $filePath): string; // returns local path
}
```

### 7.2 Шаблон ответа

```
✅ Карточка создана

Колонка: {list_name}
Ссылка: {card_url}
```

---

## Фаза 8. Sync Trello-справочников

### 8.1 SyncTrelloBoard Command

**Тест (Feature):** `tests/Feature/Commands/SyncTrelloBoardTest.php`

```
[ ] Команда создаёт/обновляет trello_lists из Trello API
[ ] Команда создаёт/обновляет trello_labels из Trello API
[ ] Команда создаёт/обновляет trello_members из Trello API
[ ] Неактивные записи помечаются is_active = false
[ ] Команда идемпотентна (повторный запуск не создаёт дублей)
```

**Реализация:** `app/Console/Commands/SyncTrelloBoardCommand.php`

```bash
php artisan trello:sync {connection_id}
```

---

## Фаза 9. Команды бота

### 9.1 CommandParser (часть TelegramUpdateParser)

```
[ ] /start → messageType = 'command', command = '/start'
[ ] /help  → messageType = 'command', command = '/help'
[ ] /bug   → messageType = 'command', command = '/bug'
[ ] /task  → messageType = 'command', command = '/task'
[ ] /new   → messageType = 'command', command = '/new'
```

### 9.2 CommandHandler

**Тест (Unit):** `tests/Unit/Commands/CommandHandlerTest.php`

```
[ ] /start возвращает приветственный текст
[ ] /help возвращает список правил для этого чата
[ ] /bug переопределяет routing на список Bugs
[ ] Неизвестная команда возвращает fallback-текст
```

---

## Фаза 10. Надёжность

### 10.1 Idempotency

**Тест:**

```
[ ] Повторный POST с тем же update_id не создаёт новую запись
[ ] Job с уже обработанным message_id пропускается
```

**Реализация:**

- Уникальный индекс по `update_id` в `telegram_messages`
- Проверка `processed_at` в Job перед обработкой

### 10.2 Retry

**Тест:**

```
[ ] Job при ошибке Trello попадает в failed_jobs
[ ] Job перезапускается через php artisan queue:retry
```

**Реализация:**

- `$tries = 3` на Job
- `$backoff = [30, 60, 120]` секунд

### 10.3 Logging

Логируем через стандартный `Log::`:

- Получение webhook (info)
- Начало обработки job (info)
- Ошибки Trello API (error)
- Ошибки Telegram API (warning)
- Успешное создание карточки (info)

---

## Структура тестов (итог)

```
tests/
  Unit/
    Parsers/
      TelegramUpdateParserTest.php
    Routing/
      RoutingEngineTest.php
    Adapters/
      TrelloAdapterTest.php
      TelegramAdapterTest.php
    Services/
      TrelloCardCreatorTest.php
      TelegramFileDownloaderTest.php
      CardTemplateRendererTest.php
    Commands/
      CommandHandlerTest.php
  Feature/
    Webhook/
      TelegramWebhookTest.php
    Jobs/
      ProcessTelegramUpdateJobTest.php
    Commands/
      SyncTrelloBoardTest.php
```

---

## Порядок реализации (итоговый)

| #  | Что                                 | Тесты                                 | Зависимости   |
|----|-------------------------------------|---------------------------------------|---------------|
| 1  | Webhook endpoint + сохранение       | Feature: TelegramWebhookTest          | —             |
| 2  | TelegramUpdateParser + DTO          | Unit: TelegramUpdateParserTest        | —             |
| 3  | TrelloAdapter (createCard)          | Unit: TrelloAdapterTest               | Http::fake()  |
| 4  | RoutingEngine + routing_rules       | Unit: RoutingEngineTest               | —             |
| 5  | ProcessTelegramUpdateJob            | Feature: ProcessTelegramUpdateJobTest | 1+2+3+4       |
| 6  | TrelloCardCreator (labels, members) | Unit: TrelloCardCreatorTest           | TrelloAdapter |
| 7  | CardTemplateRenderer                | Unit: CardTemplateRendererTest        | —             |
| 8  | TelegramFileDownloader              | Unit: TelegramFileDownloaderTest      | Http::fake()  |
| 9  | TelegramAdapter (sendMessage)       | Unit: TelegramAdapterTest             | Http::fake()  |
| 10 | SyncTrelloBoard command             | Feature: SyncTrelloBoardTest          | TrelloAdapter |
| 11 | CommandHandler                      | Unit: CommandHandlerTest              | RoutingEngine |
| 12 | Idempotency + retry + logging       | Feature: существующие тесты           | —             |

---

## Принципы SOLID в проекте

| Принцип                       | Применение                                                                                                               |
|-------------------------------|--------------------------------------------------------------------------------------------------------------------------|
| **S** — Single Responsibility | `TelegramUpdateParser` только парсит, `RoutingEngine` только маршрутизирует, `TrelloCardCreator` только создаёт карточку |
| **O** — Open/Closed           | Добавление нового типа routing rule не меняет `RoutingEngine`, только добавляет новый `RuleMatcher`                      |
| **L** — Liskov                | `TrelloAdapterInterface` — любая реализация взаимозаменяема                                                              |
| **I** — Interface Segregation | Отдельные интерфейсы для `TelegramAdapterInterface` и `TrelloAdapterInterface`                                           |
| **D** — Dependency Inversion  | Job зависит от `TrelloAdapterInterface`, не от конкретного класса                                                        |

---

## Важные технические решения

### Нет хардкода конфигурации

Все board/list/label/member — в БД через таблицы `routing_rules`, `trello_lists` и т.д.

### Queue-first

Webhook только сохраняет и диспатчит. Вся логика в Job.

### Sync перед использованием

Перед первым запуском: `php artisan trello:sync {id}` — заполняет справочники.

### Файлы — временные

Фото скачиваются → загружаются в Trello → удаляются локально.

### Secret token на webhook

`X-Telegram-Bot-Api-Secret-Token` проверяется в middleware/controller.
