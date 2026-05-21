<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\TelegramRequestLog;
use App\Models\TrelloApiLog;
use App\Models\TrelloWebhookLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class LogsCleanupCommand extends Command
{
    protected $signature = 'logs:cleanup {--force : Run regardless of settings}';

    protected $description = 'Удаляет логи старше заданного количества дней';

    public function handle(): int
    {
        $days = (int) AppSetting::get('log_cleanup_days', '0');

        if ($days <= 0 && ! $this->option('force')) {
            $this->line('Очистка отключена (log_cleanup_days = 0).');

            return self::SUCCESS;
        }

        if ($days <= 0) {
            $days = 30;
        }

        $before = Carbon::now()->subDays($days);

        $deleted = [
            'trello_webhook_logs' => TrelloWebhookLog::where('received_at', '<', $before)->delete(),
            'trello_api_logs' => TrelloApiLog::where('created_at', '<', $before)->delete(),
            'telegram_request_logs' => TelegramRequestLog::where('received_at', '<', $before)->delete(),
        ];

        foreach ($deleted as $table => $count) {
            $this->line("  {$table}: удалено {$count} записей");
        }

        $total = array_sum($deleted);
        $this->info("Очистка завершена. Удалено записей: {$total} (старше {$days} дней).");

        return self::SUCCESS;
    }
}
