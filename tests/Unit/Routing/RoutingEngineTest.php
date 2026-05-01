<?php

declare(strict_types=1);

namespace Tests\Unit\Routing;

use DateTimeImmutable;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use TelegramBot\Contracts\RoutingRuleRepositoryInterface;
use TelegramBot\DTOs\ForwardOriginDTO;
use TelegramBot\DTOs\RoutingRuleData;
use TelegramBot\DTOs\TelegramMessageDTO;
use TelegramBot\Routing\RoutingEngine;

/**
 * Unit-тесты RoutingEngine.
 *
 * RoutingEngine — чистая логика без I/O: зависит только от RoutingRuleRepositoryInterface,
 * который мокируется. Тест проверяет логику приоритетов и матчинга условий.
 */
class RoutingEngineTest extends TestCase
{
    private MockInterface $repository;

    private RoutingEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(RoutingRuleRepositoryInterface::class);
        $this->engine = new RoutingEngine($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Вспомогательный метод — строит TelegramMessageDTO с нужными полями
    // -------------------------------------------------------------------------

    private function makeMessage(
        string $chatId = '100',
        string $chatType = 'private',
        ?string $command = null,
        array $photos = [],
        ?ForwardOriginDTO $forwardOrigin = null,
    ): TelegramMessageDTO {
        return new TelegramMessageDTO(
            messageType: $command !== null ? 'command' : ($photos ? 'photo' : 'text'),
            text: null,
            caption: null,
            photos: $photos,
            documents: [],
            userId: 42,
            chatId: $chatId,
            chatType: $chatType,
            command: $command,
            username: 'testuser',
            firstName: 'Test',
            sentAt: new DateTimeImmutable,
            forwardOrigin: $forwardOrigin,
        );
    }

    private function makeRule(array $overrides = []): RoutingRuleData
    {
        return new RoutingRuleData(
            chatId: $overrides['chatId'] ?? null,
            chatType: $overrides['chatType'] ?? null,
            command: $overrides['command'] ?? null,
            hasPhoto: $overrides['hasPhoto'] ?? null,
            isForwarded: $overrides['isForwarded'] ?? null,
            trelloListId: $overrides['trelloListId'] ?? 'list_default',
            listName: $overrides['listName'] ?? 'Default List',
            labelIds: $overrides['labelIds'] ?? [],
            memberIds: $overrides['memberIds'] ?? [],
            cardTitleTemplate: $overrides['cardTitleTemplate'] ?? '{{first_name}}: {{text_preview}}',
            cardDescriptionTemplate: $overrides['cardDescriptionTemplate'] ?? '{{text}}',
            priority: $overrides['priority'] ?? 0,
        );
    }

    // -------------------------------------------------------------------------
    // Тесты
    // -------------------------------------------------------------------------

    /**
     * Нет активных правил → resolve() возвращает null.
     */
    public function test_returns_null_when_no_rules(): void
    {
        $this->repository->shouldReceive('getActiveRules')->once()->andReturn([]);

        $result = $this->engine->resolve($this->makeMessage());

        self::assertNull($result);
    }

    /**
     * Есть только catch-all правило (все условия null) → оно всегда срабатывает.
     */
    public function test_catch_all_rule_matches_any_message(): void
    {
        $rule = $this->makeRule(['trelloListId' => 'list_catchall']);

        $this->repository->shouldReceive('getActiveRules')->once()->andReturn([$rule]);

        $result = $this->engine->resolve($this->makeMessage());

        self::assertNotNull($result);
        self::assertSame('list_catchall', $result->listId);
    }

    /**
     * Правило с конкретным chatId совпадает только для этого чата.
     */
    public function test_chat_id_rule_matches_exact_chat(): void
    {
        $rule = $this->makeRule(['chatId' => 100, 'trelloListId' => 'list_chat100']);

        $this->repository->shouldReceive('getActiveRules')->andReturn([$rule]);

        // Совпадение
        $result = $this->engine->resolve($this->makeMessage(chatId: '100'));
        self::assertSame('list_chat100', $result?->listId);

        // Другой чат — не совпадает
        $result = $this->engine->resolve($this->makeMessage(chatId: '999'));
        self::assertNull($result);
    }

    /**
     * Правило с конкретной командой совпадает только для этой команды.
     */
    public function test_command_rule_matches_exact_command(): void
    {
        $rule = $this->makeRule(['command' => '/bug', 'trelloListId' => 'list_bugs']);

        $this->repository->shouldReceive('getActiveRules')->andReturn([$rule]);

        $result = $this->engine->resolve($this->makeMessage(command: '/bug'));
        self::assertSame('list_bugs', $result?->listId);

        $result = $this->engine->resolve($this->makeMessage(command: '/task'));
        self::assertNull($result);
    }

    /**
     * При совпадении нескольких правил побеждает правило с наибольшим priority.
     * Репозиторий возвращает уже отсортированные по DESC priority правила.
     */
    public function test_highest_priority_rule_wins(): void
    {
        $lowPriority = $this->makeRule(['priority' => 0, 'trelloListId' => 'list_low']);
        $highPriority = $this->makeRule(['priority' => 10, 'trelloListId' => 'list_high']);

        // repository отдаёт уже в порядке убывания (как делает Eloquent-реализация)
        $this->repository->shouldReceive('getActiveRules')->andReturn([$highPriority, $lowPriority]);

        $result = $this->engine->resolve($this->makeMessage());

        self::assertSame('list_high', $result?->listId);
    }

    /**
     * Правило с has_photo = true совпадает только при наличии фото.
     */
    public function test_has_photo_rule_matches_only_photo_messages(): void
    {
        $rule = $this->makeRule(['hasPhoto' => true, 'trelloListId' => 'list_photos']);

        $this->repository->shouldReceive('getActiveRules')->andReturn([$rule]);

        // С фото — совпадает
        $result = $this->engine->resolve($this->makeMessage(photos: ['file_id_1']));
        self::assertSame('list_photos', $result?->listId);

        // Без фото — не совпадает
        $result = $this->engine->resolve($this->makeMessage(photos: []));
        self::assertNull($result);
    }

    /**
     * Правило с chat_type = group совпадает только для групп.
     */
    public function test_chat_type_rule_matches_exact_type(): void
    {
        $rule = $this->makeRule(['chatType' => 'group', 'trelloListId' => 'list_groups']);

        $this->repository->shouldReceive('getActiveRules')->andReturn([$rule]);

        $result = $this->engine->resolve($this->makeMessage(chatType: 'group'));
        self::assertSame('list_groups', $result?->listId);

        $result = $this->engine->resolve($this->makeMessage(chatType: 'private'));
        self::assertNull($result);
    }

    /**
     * Правило с is_forwarded = true совпадает только для пересланных сообщений.
     */
    public function test_is_forwarded_rule_matches_only_forwarded_messages(): void
    {
        $rule = $this->makeRule(['isForwarded' => true, 'trelloListId' => 'list_forwarded']);

        $this->repository->shouldReceive('getActiveRules')->andReturn([$rule]);

        $forward = new ForwardOriginDTO(type: 'user', firstName: 'Александр', username: 'Alex_itsellopt', userId: 579219779);

        // Пересланное — совпадает
        $result = $this->engine->resolve($this->makeMessage(forwardOrigin: $forward));
        self::assertSame('list_forwarded', $result?->listId);

        // Обычное — не совпадает
        $result = $this->engine->resolve($this->makeMessage());
        self::assertNull($result);
    }

    /**
     * Правило с is_forwarded = false совпадает только для НЕпересланных сообщений.
     */
    public function test_is_forwarded_false_matches_only_non_forwarded(): void
    {
        $rule = $this->makeRule(['isForwarded' => false, 'trelloListId' => 'list_direct']);

        $this->repository->shouldReceive('getActiveRules')->andReturn([$rule]);

        $forward = new ForwardOriginDTO(type: 'user', firstName: 'Александр');

        // Обычное — совпадает
        $result = $this->engine->resolve($this->makeMessage());
        self::assertSame('list_direct', $result?->listId);

        // Пересланное — не совпадает
        $result = $this->engine->resolve($this->makeMessage(forwardOrigin: $forward));
        self::assertNull($result);
    }

    /**
     * Правило с is_forwarded = null совпадает и для пересланных, и для обычных.
     */
    public function test_is_forwarded_null_matches_any_message(): void
    {
        $rule = $this->makeRule(['isForwarded' => null, 'trelloListId' => 'list_any']);

        $this->repository->shouldReceive('getActiveRules')->andReturn([$rule]);

        $forward = new ForwardOriginDTO(type: 'user', firstName: 'Александр');

        $result = $this->engine->resolve($this->makeMessage());
        self::assertSame('list_any', $result?->listId);

        $result = $this->engine->resolve($this->makeMessage(forwardOrigin: $forward));
        self::assertSame('list_any', $result?->listId);
    }

    /**
     * RoutingResultDTO корректно заполняется из подходящего правила.
     */
    public function test_result_dto_is_populated_from_matched_rule(): void
    {
        $rule = $this->makeRule([
            'trelloListId' => 'list_abc',
            'labelIds' => ['lbl1', 'lbl2'],
            'memberIds' => ['mbr1'],
            'cardTitleTemplate' => '{{first_name}}: {{text_preview}}',
            'cardDescriptionTemplate' => '{{text}}',
        ]);

        $this->repository->shouldReceive('getActiveRules')->once()->andReturn([$rule]);

        $result = $this->engine->resolve($this->makeMessage());

        self::assertSame('list_abc', $result?->listId);
        self::assertSame('Default List', $result?->listName);
        self::assertSame(['lbl1', 'lbl2'], $result?->labelIds);
        self::assertSame(['mbr1'], $result?->memberIds);
        self::assertSame('{{first_name}}: {{text_preview}}', $result?->cardTitleTemplate);
        self::assertSame('{{text}}', $result?->cardDescriptionTemplate);
    }
}
