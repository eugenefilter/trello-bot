<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use TelegramBot\Adapters\TrelloAdapter;
use TelegramBot\Contracts\CardLogRepositoryInterface;
use TelegramBot\Contracts\RoutingEngineInterface;
use TelegramBot\Contracts\RoutingRuleRepositoryInterface;
use TelegramBot\Contracts\TelegramMessageRepositoryInterface;
use TelegramBot\Contracts\TrelloAdapterInterface;
use TelegramBot\Contracts\UpdateParserInterface;
use TelegramBot\Parsers\TelegramUpdateParser;
use TelegramBot\Repositories\EloquentRoutingRuleRepository;
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

        // Routing Engine — зависит от RoutingRuleRepositoryInterface (разрешается контейнером)
        $this->app->bind(RoutingEngineInterface::class, RoutingEngine::class);

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
