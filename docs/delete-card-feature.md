# Фича: удаление карточки Trello через Telegram

## Цель

После создания карточки Trello бот отправляет сообщение с inline-кнопкой **«🗑 Удалить карточку»**.
При нажатии кнопки карточка удаляется из Trello, кнопка убирается из сообщения.

---

## Полный флоу

```
[Создание карточки]
User → /bug Текст задачи
Bot  → создаёт карточку (shortLink: AbCd1234)
Bot  → сообщение + inline-кнопка [🗑 Удалить карточку | callback_data: "delete:AbCd1234"]

[Удаление карточки]
User → нажимает кнопку
TG   → POST /webhooks/telegram { callback_query: { data: "delete:AbCd1234", message_id: 42, ... } }
Bot  → CallbackQueryProcessor::process()
     → trello->deleteCard("AbCd1234")
     → telegram->answerCallbackQuery(callbackId, "✅ Карточка удалена")
     → telegram->removeInlineKeyboard(chatId, messageId)
```

---

## Архитектура по слоям

### Domain Layer — `src/TelegramBot/`

#### Новые DTOs

**`DTOs/TelegramCallbackDTO`** — аналог `TelegramMessageDTO` для `callback_query`:

```php
class TelegramCallbackDTO {
    public string  $callbackId;   // для answerCallbackQuery
    public string  $chatId;
    public int     $messageId;    // для удаления inline-кнопки
    public string  $data;         // "delete:AbCd1234"
    public ?string $languageCode;
}
```

**`DTOs/CallbackAction`** — распарсенный `callback_data`:

```php
class CallbackAction {
    public string $action;   // "delete"
    public string $payload;  // "AbCd1234"
}
```

#### Изменения в `DTOs/CreatedCardResult`

Добавить поле `shortLink`:

```php
class CreatedCardResult {
    public string $id;         // полный hex ID
    public string $shortLink;  // короткий ID (AbCd1234) — используется в кнопке удаления
    public string $url;        // ссылка на карточку
}
```

#### Изменения в контрактах

**`Contracts/TrelloAdapterInterface`** — добавить:

```php
public function deleteCard(string $shortLink): void;
```

**`Contracts/TelegramAdapterInterface`** — добавить:

```php
public function answerCallbackQuery(string $callbackId, string $text): void;
public function removeInlineKeyboard(string $chatId, int $messageId): void;
public function sendMessageWithKeyboard(string $chatId, string $text, array $keyboard, array $options = []): void;
```

#### Новый сервис `Services/CallbackQueryProcessor`

Отдельный сервис — не смешивать с `TelegramUpdateProcessor`:

```
CallbackQueryProcessor::process(string $callbackPayload)
  → парсит TelegramCallbackDTO
  → распознаёт CallbackAction ("delete", "AbCd1234")
  → trello->deleteCard(shortLink)
  → telegram->answerCallbackQuery(callbackId, "✅ Карточка удалена")
  → telegram->removeInlineKeyboard(chatId, messageId)
```

**Обработка ошибок:**
- карточка не найдена (404) → `answerCallbackQuery("❌ Карточка не найдена")`
- неизвестный action → игнорируем, логируем warning
- ошибка Trello API → `answerCallbackQuery("❌ Ошибка при удалении")`

#### Изменения в `Parsers/TelegramUpdateParser`

Добавить парсинг `callback_query` update → `TelegramCallbackDTO`.

---

### Application Layer — `app/`

#### `ProcessTelegramUpdateJob` — роутинг по типу update

```php
if (isset($payload['callback_query'])) {
    // CallbackQueryProcessor::process()
} else {
    // TelegramUpdateProcessor::process()  // текущая логика
}
```

---

## Переводы

Добавить во все 4 языка (`en`, `ru`, `uk`, `pl`):

| Ключ | RU |
|---|---|
| `bot.card_deleted` | ✅ Карточка удалена |
| `bot.card_not_found` | ❌ Карточка не найдена |
| `bot.delete_failed` | ❌ Ошибка при удалении |
| `bot.delete_button` | 🗑 Удалить карточку |

---

## Порядок реализации (TDD)

### Этап 1 — `CreatedCardResult` + `shortLink`
- [ ] Тест: `TrelloAdapterTest` — `cardResponse()` содержит `shortLink`, результат его возвращает
- [ ] Тест: `TelegramUpdateProcessorTest` — сообщение содержит `shortLink` в тексте (или кнопке)
- [ ] `CreatedCardResult` — добавить поле `shortLink`
- [ ] `TrelloAdapter::createCard` — читать `$body['shortLink']`

### Этап 2 — `TelegramAdapterInterface` + `TelegramAdapter`
- [ ] Тест: `TelegramAdapterTest` — `sendMessageWithKeyboard` передаёт `reply_markup`
- [ ] Тест: `TelegramAdapterTest` — `answerCallbackQuery` вызывает нужный метод
- [ ] Тест: `TelegramAdapterTest` — `removeInlineKeyboard` вызывает `editMessageReplyMarkup`
- [ ] Добавить методы в интерфейс и реализацию

### Этап 3 — `TrelloAdapterInterface` + `TrelloAdapter::deleteCard`
- [ ] Тест: `TrelloAdapterTest` — `DELETE /1/cards/{shortLink}` отправляется корректно
- [ ] Тест: `TrelloAdapterTest` — 404 не бросает исключение (карточка уже удалена)
- [ ] Добавить метод в интерфейс и реализацию

### Этап 4 — `TelegramUpdateParser` — парсинг `callback_query`
- [ ] Тест: парсинг `callback_query` payload → `TelegramCallbackDTO`
- [ ] Тест: `CallbackAction` — корректно парсит `"delete:AbCd1234"`
- [ ] Реализовать DTOs и расширить парсер

### Этап 5 — `CallbackQueryProcessor`
- [ ] Тест: успешное удаление → `answerCallbackQuery` с текстом успеха + `removeInlineKeyboard`
- [ ] Тест: карточка не найдена → `answerCallbackQuery` с текстом ошибки, `removeInlineKeyboard` не вызывается
- [ ] Тест: неизвестный action → ничего не делает, логирует warning
- [ ] Реализовать сервис

### Этап 6 — `ProcessTelegramUpdateJob`
- [ ] Тест: `callback_query` payload → роутится в `CallbackQueryProcessor`
- [ ] Тест: `message` payload → роутится в `TelegramUpdateProcessor` (регрессия)
- [ ] Обновить job

### Этап 7 — Интеграция в `TelegramUpdateProcessor`
- [ ] Обновить отправку — использовать `sendMessageWithKeyboard` с кнопкой удаления
- [ ] shortLink в тексте сообщения **не показываем** — он передаётся только в `callback_data` кнопки
- [ ] Переводы — добавить ключи во все языки

---

## Затронутые файлы

| Файл | Изменение |
|---|---|
| `src/TelegramBot/DTOs/CreatedCardResult.php` | Добавить `shortLink` |
| `src/TelegramBot/DTOs/TelegramCallbackDTO.php` | Новый |
| `src/TelegramBot/DTOs/CallbackAction.php` | Новый |
| `src/TelegramBot/Contracts/TrelloAdapterInterface.php` | Добавить `deleteCard` |
| `src/TelegramBot/Contracts/TelegramAdapterInterface.php` | Добавить 3 метода |
| `src/TelegramBot/Adapters/TrelloAdapter.php` | Реализовать `deleteCard` |
| `app/Adapters/TelegramAdapter.php` | Реализовать 3 метода |
| `src/TelegramBot/Parsers/TelegramUpdateParser.php` | Парсинг `callback_query` |
| `src/TelegramBot/Services/CallbackQueryProcessor.php` | Новый |
| `src/TelegramBot/Services/TelegramUpdateProcessor.php` | `sendMessageWithKeyboard` + shortLink |
| `app/Jobs/ProcessTelegramUpdateJob.php` | Роутинг по типу update |
| `lang/{en,ru,uk,pl}/bot.php` | Новые ключи |
