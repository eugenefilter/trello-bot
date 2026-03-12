# Реализация поддержки Telegram Media Group

## Суть проблемы

Telegram разбивает одну отправку нескольких файлов на N отдельных `update`, объединённых полем `media_group_id`. Caption присваивается только одному сообщению из группы (обычно последнему). Каждый update обрабатывается независимо → файлы без caption теряются.

---

## Концепция решения

Не буферизировать — реагировать на каждый update сразу:

- **Первый update группы с caption** → создаём карточку Trello, сохраняем `card_id` + `media_group_id`
- **Следующие update той же группы** → находим карточку по `media_group_id`, прикрепляем файл напрямую

Медленный интернет не создаёт проблем: каждый файл прикрепляется в момент получения, независимо от остальных.

---

## Шаг 1 — Миграция: добавить `media_group_id` в `telegram_messages`

```php
$table->string('media_group_id', 32)->nullable()->index();
```

---

## Шаг 2 — Парсер: извлекать `media_group_id`

В `TelegramMessageDTO` добавить поле:

```php
public ?string $mediaGroupId
```

Заполнять из `$message['media_group_id'] ?? null`.

---

## Шаг 3 — Репозиторий: сохранять `media_group_id`

В `EloquentTelegramMessageRepository::firstOrCreate()` сохранять поле в БД.

Добавить метод поиска уже созданной карточки по группе:

```php
public function findCardIdByMediaGroup(string $mediaGroupId): ?string;
// SELECT card_id FROM trello_cards_log
//   JOIN telegram_messages ON ...
//   WHERE media_group_id = ? AND card_id IS NOT NULL
//   LIMIT 1
```

---

## Шаг 4 — Логика в `TelegramUpdateProcessor`

```
При обработке update:

1. Если mediaGroupId = null → обычный флоу (создать карточку)

2. Если mediaGroupId != null:
   a. Есть ли уже card_id для этой группы в trello_cards_log?

   ДА → это "догоняющий" update:
      - скачать файл
      - прикрепить к существующей карточке через attachFile(card_id, ...)
      - пометить сообщение как processed (linked to group)
      - НЕ создавать новую карточку, НЕ отправлять reply

   НЕТ → это первый update группы:
      - проверить наличие caption с командой
      - если caption есть → создать карточку (обычный флоу), прикрепить свой файл
      - если caption нет → markSkipped (ждём первый update с caption)
```

---

## Шаг 5 — Обработка случая "caption пришёл не первым"

Возможна ситуация: сначала приходят фото без caption, потом — сообщение с caption.
"Догоняющие" update без caption будут помечены как skipped до появления карточки.

Решение: при создании карточки (шаг 4, ветка НЕТ) — проверить, есть ли в БД
уже сохранённые части этой группы со статусом `skipped`. Если есть — прикрепить
их файлы к только что созданной карточке.

```php
$pendingParts = $repository->findSkippedGroupParts($mediaGroupId);
foreach ($pendingParts as $part) {
    // скачать файл, прикрепить к $cardId, пометить как processed
}
```

---

## Шаг 6 — Тесты

| Тест | Тип |
|------|-----|
| Parser извлекает `media_group_id` | Unit |
| Первый update группы создаёт карточку | Unit |
| Второй update прикрепляет файл к существующей карточке | Unit |
| Update без caption пропускается если карточки ещё нет | Unit |
| При создании карточки retroactively прикрепляются ранее пришедшие файлы | Feature |

---

## Граничные случаи

- **Нет caption ни у одного update группы** → вся группа skipped (нет команды)
- **Карточка была удалена в Trello вручную** → `attachFile` вернёт 404, логировать ошибку, не падать
- **Два update пришли одновременно (race condition)** → `findCardIdByMediaGroup` должен работать в транзакции или через `firstOrCreate` на `trello_cards_log`
