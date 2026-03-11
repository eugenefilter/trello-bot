<?php

declare(strict_types=1);

namespace TelegramBot\Exceptions;

/**
 * Сетевая ошибка при обращении к Trello API.
 * Job поймает это исключение и уйдёт в retry.
 */
class TrelloConnectionException extends \RuntimeException {}
