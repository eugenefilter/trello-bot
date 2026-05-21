# Trello Webhook Notifications — ТЗ

## Суть функционала

Бот получает веб-хуки от Trello и отправляет уведомления в Telegram-чаты при наступлении заданных условий.

---

## Сценарий 1 (базовый)

**Триггер:** карточка перемещена в колонку **"Проверка (WORK) ✅ (Алекс)"**

**Условия:**
- Карточка имеет метку **SEO**
- На карточке назначен хотя бы один из участников: `@aniia_kohan` или `@blazerbuttons1`

**Действие:** отправить сообщение в Telegram-группу `-4557341381` с упоминанием `@aniiahelpme`

---

## Архитектура

### Новые сущности

#### `TrelloMemberBinding` (модель + таблица)

Связь между участником Trello и пользователем Telegram. Используется в шаблонах сообщений и фильтрах правил.

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | uuid | PK |
| `connection_id` | FK | Trello-подключение (доска) |
| `trello_member_id` | string | ID участника в Trello |
| `trello_username` | string | username в Trello (`@aniia_kohan`) |
| `trello_full_name` | string | Отображаемое имя в Trello |
| `telegram_user_id` | string nullable | Числовой ID пользователя в Telegram |
| `telegram_username` | string nullable | @username в Telegram (`@aniiahelpme`) |
| `telegram_full_name` | string nullable | Отображаемое имя в Telegram |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

Участники Trello подтягиваются командой `trello:sync` (уже есть). Поля Telegram заполняются вручную в admin-панели.

#### `TrelloNotificationRule` (модель + таблица)

Правило уведомления, настраиваемое через веб-интерфейс.

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | uuid | PK |
| `connection_id` | FK | Trello-подключение (доска) |
| `name` | string | Название правила |
| `is_active` | bool | Включено / выключено |
| `trigger_action` | string | Тип триггера (`updateCard.listId`) |
| `trigger_list_id` | string | ID колонки-триггера |
| `filter_label_ids` | json | Массив ID меток |
| `filter_label_mode` | enum `any/all` | Режим проверки меток |
| `filter_member_ids` | json | Массив `TrelloMemberBinding.id` (хотя бы один) |
| `telegram_chat_id` | string | ID чата для уведомления |
| `mention_member_binding_ids` | json | Массив `TrelloMemberBinding.id` — кого упомянуть в сообщении |
| `message_template` | text | Шаблон сообщения (поддерживает переменные) |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Фильтр участников** (`filter_member_ids`) — правило сработает, если на карточке есть **хотя бы один** из перечисленных участников.

**Упоминания** (`mention_member_binding_ids`) — в сообщение подставятся `telegram_username` указанных привязок. Если у привязки нет telegram_username — используется `telegram_full_name` или `trello_full_name`.

#### Переменные шаблона сообщения

- `{{card_name}}` — название карточки
- `{{card_url}}` — ссылка на карточку
- `{{list_name}}` — название колонки
- `{{board_name}}` — название доски
- `{{members}}` — список участников карточки (Telegram username если привязан, иначе Trello имя)
- `{{labels}}` — список меток
- `{{mentions}}` — автоматически подставленные упоминания из `mention_member_binding_ids`

### Поток обработки

```
POST /webhooks/trello/{token}
  → TrelloWebhookController (валидирует X-Trello-Webhook, сохраняет в БД)
  → ProcessTrelloWebhookJob (async)
  → TrelloWebhookProcessor
      → получает тип события (action.type)
      → загружает активные TrelloNotificationRule для данного connection
      → для каждого правила → TrelloNotificationMatcher.matches()
          → проверяет trigger_list_id
          → проверяет filter_label_ids / filter_label_mode
          → проверяет filter_member_ids через TrelloMemberBinding
      → если матч → рендерит шаблон (подставляет Telegram username из привязок)
      → TelegramAdapter.sendMessage()
```

### Регистрация веб-хука в Trello

Trello требует регистрации хука через API:
```
POST https://api.trello.com/1/webhooks
  ?key=...&token=...
  &callbackURL=https://your-domain/webhooks/trello/{connection_token}
  &idModel={board_id}
```

**Реализуется как Action-кнопка в Filament** на странице редактирования Trello-подключения (не Artisan-команда).

Trello при регистрации делает HEAD-запрос на callbackURL — контроллер должен ответить 200.

Статус регистрации хранится в таблице `trello_connections`: поля `webhook_id` (string nullable) и `webhook_registered_at` (timestamp nullable).

---

## Веб-интерфейс (Filament)

### Trello Connections (расширение существующего ресурса)

- Новые поля в форме/infolist: `webhook_id`, `webhook_registered_at` (read-only)
- Action-кнопка **"Зарегистрировать веб-хук"** на странице редактирования:
  - Вызывает Trello API, сохраняет `webhook_id` + `webhook_registered_at`
  - Показывает успех / ошибку
- Action-кнопка **"Отозвать веб-хук"** (если `webhook_id` установлен):
  - Удаляет хук через Trello API, обнуляет поля

### TrelloMemberBinding (новый ресурс)

Таблица участников с полями Trello + Telegram:
- Участники подтягиваются через **"Синхронизировать участников"** (отдельная кнопка или через существующий `trello:sync`)
- Редактирование Telegram-данных прямо в таблице (inline edit) или в форме

### TrelloNotificationRule (новый ресурс)

- Список правил: название, доска, колонка-триггер, чат, статус
- Форма создания/редактирования:
  - Выбор Trello-подключения (доски)
  - Выбор колонки-триггера (select из `trello_lists` текущей доски)
  - Выбор меток (multi-select из `trello_labels`)
  - Режим фильтрации меток (`any` / `all`)
  - Выбор участников-фильтра (multi-select из `TrelloMemberBinding` текущей доски)
  - Telegram chat ID
  - Выбор кого упомянуть (multi-select из `TrelloMemberBinding` текущей доски)
  - Шаблон сообщения с подсказкой по переменным
  - Переключатель активности

---

## TODO (порядок реализации)

1. [ ] Миграция — таблица `trello_member_bindings`
2. [ ] Модель `TrelloMemberBinding`
3. [ ] Filament-ресурс `TrelloMemberBindingResource`
4. [ ] Миграция — добавить `webhook_id`, `webhook_registered_at` в `trello_connections`
5. [ ] Action-кнопки регистрации/отзыва хука в ресурсе Trello Connections
6. [ ] Миграция — таблица `trello_notification_rules`
7. [ ] Модель `TrelloNotificationRule`
8. [ ] `TrelloWebhookController` + маршрут (HEAD + POST) + валидация подписи
9. [ ] `ProcessTrelloWebhookJob`
10. [ ] `TrelloWebhookProcessor` + `TrelloNotificationMatcher`
11. [ ] Filament-ресурс `TrelloNotificationRuleResource`
12. [ ] Тесты
