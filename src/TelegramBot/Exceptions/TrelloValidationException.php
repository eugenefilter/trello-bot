<?php

declare(strict_types=1);

namespace TelegramBot\Exceptions;

/**
 * Trello вернул 422 — некорректные параметры запроса.
 * Например: несуществующий list_id, label_id или member_id.
 * Обычно означает рассинхронизацию справочников — нужен trello:sync.
 */
class TrelloValidationException extends \RuntimeException {}
