<?php

declare(strict_types=1);

namespace TelegramBot\Exceptions;

/**
 * Trello вернул 401 — неверный api_key или api_token.
 * Требует вмешательства администратора для обновления credentials.
 */
class TrelloAuthException extends \RuntimeException {}
