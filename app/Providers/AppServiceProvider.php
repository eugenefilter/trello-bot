<?php

declare(strict_types=1);

namespace App\Providers;

use App\Adapters\TelegramAdapter;
use App\Models\TrelloConnection;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use Telegram\Bot\Api;
use TelegramBot\Adapters\TrelloAdapter;
use TelegramBot\CallbackHandlers\CreateCardHandler;
use TelegramBot\CallbackHandlers\DeleteAttachmentHandler;
use TelegramBot\CallbackHandlers\DeleteCardHandler;
use TelegramBot\CallbackHandlers\DeleteCommentHandler;
use TelegramBot\Contracts\CardLogRepositoryInterface;
use TelegramBot\Contracts\RequestLogRepositoryInterface;
use TelegramBot\Contracts\RoutingEngineInterface;
use TelegramBot\Contracts\RoutingRuleRepositoryInterface;
use TelegramBot\Contracts\TelegramAdapterInterface;
use TelegramBot\Contracts\TelegramFileRepositoryInterface;
use TelegramBot\Contracts\TelegramMessageRepositoryInterface;
use TelegramBot\Contracts\TrelloAdapterInterface;
use TelegramBot\Contracts\TrelloApiLogRepositoryInterface;
use TelegramBot\Contracts\UpdateParserInterface;
use TelegramBot\Parsers\TelegramUpdateParser;
use TelegramBot\Repositories\EloquentRequestLogRepository;
use TelegramBot\Repositories\EloquentRoutingRuleRepository;
use TelegramBot\Repositories\EloquentTelegramFileRepository;
use TelegramBot\Repositories\EloquentTelegramMessageRepository;
use TelegramBot\Repositories\EloquentTrelloApiLogRepository;
use TelegramBot\Repositories\TrelloCardLogRepository;
use TelegramBot\Routing\RoutingEngine;
use TelegramBot\Services\CallbackQueryProcessor;
use TelegramBot\Services\TelegramUpdateProcessor;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Привязываем интерфейсы пакета TelegramBot к их реализациям.
     *
     * Все реализации создаются лениво — только при первом обращении к контейнеру.
     */
    public function register(): void
    {
        // Парсер входящих Telegram update — не имеет зависимостей
        $this->app->bind(UpdateParserInterface::class, TelegramUpdateParser::class);

        // Репозиторий сырых входящих запросов (request log)
        $this->app->bind(RequestLogRepositoryInterface::class, EloquentRequestLogRepository::class);

        // Репозиторий логов Trello API
        $this->app->bind(TrelloApiLogRepositoryInterface::class, EloquentTrelloApiLogRepository::class);

        // Репозиторий входящих Telegram update
        $this->app->bind(TelegramMessageRepositoryInterface::class, EloquentTelegramMessageRepository::class);

        // Репозитории
        $this->app->bind(CardLogRepositoryInterface::class, TrelloCardLogRepository::class);
        $this->app->bind(RoutingRuleRepositoryInterface::class, EloquentRoutingRuleRepository::class);
        $this->app->bind(TelegramFileRepositoryInterface::class, EloquentTelegramFileRepository::class);

        // Routing Engine — зависит от RoutingRuleRepositoryInterface (разрешается контейнером)
        $this->app->bind(RoutingEngineInterface::class, RoutingEngine::class);

        // Telegram Bot API клиент
        $this->app->bind(TelegramAdapterInterface::class, function () {
            $token = config('telegram.bots.mybot.token', '');

            return new TelegramAdapter(
                telegram: new Api($token),
                botToken: $token,
                storageDir: storage_path('app/telegram_files'),
            );
        });

        // CallbackQueryProcessor — роутер действий inline-кнопок
        $this->app->bind(CallbackQueryProcessor::class, function ($app) {
            return new CallbackQueryProcessor(
                handlers: [
                    'create_card' => new CreateCardHandler(
                        telegram: $app->make(TelegramAdapterInterface::class),
                        processor: $app->make(TelegramUpdateProcessor::class),
                        ruleRepository: $app->make(RoutingRuleRepositoryInterface::class),
                    ),
                    'delete' => new DeleteCardHandler(
                        telegram: $app->make(TelegramAdapterInterface::class),
                        trello: $app->make(TrelloAdapterInterface::class),
                    ),
                    'delete_comment' => new DeleteCommentHandler(
                        telegram: $app->make(TelegramAdapterInterface::class),
                        trello: $app->make(TrelloAdapterInterface::class),
                    ),
                    'delete_attachment' => new DeleteAttachmentHandler(
                        telegram: $app->make(TelegramAdapterInterface::class),
                        trello: $app->make(TrelloAdapterInterface::class),
                    ),
                ],
            );
        });

        // Trello API клиент — credentials берутся из первого активного подключения в БД
        $this->app->bind(TrelloAdapterInterface::class, function ($app) {
            $connection = TrelloConnection::where('is_active', true)->first();

            return new TrelloAdapter(
                http: $app->make(HttpFactory::class),
                apiKey: $connection?->api_key ?? '',
                apiToken: $connection?->api_token ?? '',
                apiLog: $app->make(TrelloApiLogRepositoryInterface::class),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
