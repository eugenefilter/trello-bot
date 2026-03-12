<?php

declare(strict_types=1);

namespace App\Providers;

use App\Adapters\TelegramAdapter;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use Telegram\Bot\Api;
use TelegramBot\Adapters\TrelloAdapter;
use TelegramBot\Contracts\TelegramAdapterInterface;
use TelegramBot\Contracts\CardLogRepositoryInterface;
use TelegramBot\Contracts\RoutingEngineInterface;
use TelegramBot\Contracts\RoutingRuleRepositoryInterface;
use TelegramBot\Contracts\TelegramFileRepositoryInterface;
use TelegramBot\Contracts\TelegramMessageRepositoryInterface;
use TelegramBot\Contracts\TrelloAdapterInterface;
use TelegramBot\Contracts\UpdateParserInterface;
use TelegramBot\Parsers\TelegramUpdateParser;
use TelegramBot\Repositories\EloquentRoutingRuleRepository;
use TelegramBot\Repositories\EloquentTelegramFileRepository;
use TelegramBot\Repositories\EloquentTelegramMessageRepository;
use TelegramBot\Repositories\TrelloCardLogRepository;
use TelegramBot\Routing\RoutingEngine;

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
            $token = config('telegram.bot_token', '');

            return new TelegramAdapter(
                telegram:   new Api($token),
                botToken:   $token,
                storageDir: storage_path('app/telegram_files'),
            );
        });

        // Trello API клиент — credentials берутся из config/services.php
        $this->app->bind(TrelloAdapterInterface::class, function ($app) {
            return new TrelloAdapter(
                http: $app->make(HttpFactory::class),
                apiKey: config('services.trello.api_key', ''),
                apiToken: config('services.trello.api_token', ''),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
