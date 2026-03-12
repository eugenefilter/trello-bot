<?php

declare(strict_types=1);

namespace TelegramBot\Exceptions;

/**
 * Ошибка при работе с файлами Telegram API.
 *
 * Выбрасывается когда:
 *   - файл не найден по file_id (Telegram вернул 400)
 *   - скачивание файла завершилось ошибкой
 */
class TelegramFileException extends \RuntimeException {}
