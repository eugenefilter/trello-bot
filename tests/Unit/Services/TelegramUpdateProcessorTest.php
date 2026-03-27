<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use DateTimeImmutable;
use Mockery;
use Mockery\MockInterface;
use TelegramBot\Contracts\CardLogRepositoryInterface;
use TelegramBot\Contracts\RoutingEngineInterface;
use TelegramBot\Contracts\TelegramAdapterInterface;
use TelegramBot\Contracts\TelegramMessageRepositoryInterface;
use TelegramBot\Contracts\TrelloAdapterInterface;
use TelegramBot\Contracts\UpdateParserInterface;
use TelegramBot\DTOs\CreatedCardResult;
use TelegramBot\DTOs\DownloadedFile;
use TelegramBot\DTOs\ReplyMessageDTO;
use TelegramBot\DTOs\RoutingResultDTO;
use TelegramBot\DTOs\TelegramMessageDTO;
use TelegramBot\Exceptions\TrelloConnectionException;
use TelegramBot\Services\CardTemplateRenderer;
use TelegramBot\Services\TelegramFileDownloader;
use TelegramBot\Services\TelegramUpdateProcessor;
use TelegramBot\Services\TrelloCardCreator;
use Tests\TestCase;

/**
 * Unit-тест TelegramUpdateProcessor.
 *
 * Все зависимости мокируются — БД и Trello не нужны.
 */
class TelegramUpdateProcessorTest extends TestCase
{
    private MockInterface $repository;

    private MockInterface $parser;

    private MockInterface $routing;

    private MockInterface $cardCreator;

    private MockInterface $telegram;

    private MockInterface $trello;

    private MockInterface $fileDownloader;

    private MockInterface $cardLog;

    private TelegramUpdateProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(TelegramMessageRepositoryInterface::class);
        $this->parser = Mockery::mock(UpdateParserInterface::class);
        $this->routing = Mockery::mock(RoutingEngineInterface::class);
        $this->cardCreator = Mockery::mock(TrelloCardCreator::class);
        $this->telegram = Mockery::mock(TelegramAdapterInterface::class);
        $this->trello = Mockery::mock(TrelloAdapterInterface::class);
        $this->fileDownloader = Mockery::mock(TelegramFileDownloader::class);
        $this->cardLog = Mockery::mock(CardLogRepositoryInterface::class);

        $this->processor = new TelegramUpdateProcessor(
            $this->repository,
            $this->parser,
            $this->routing,
            $this->cardCreator,
            new CardTemplateRenderer,
            $this->telegram,
            $this->trello,
            $this->fileDownloader,
            $this->cardLog,
        );
    }

    protected function tearDown(): void
    {
        // Регистрируем Mockery-ожидания как assertions в PHPUnit,
        // чтобы тесты не помечались как Risky.
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Happy path: парсер → routing → создание карточки → ответ пользователю → markProcessed.
     */
    public function test_happy_path_creates_card_and_marks_processed(): void
    {
        $dto = $this->makeMessageDTO();
        $routing = $this->makeRoutingResult();

        $this->repository->shouldReceive('getPayload')->once()->with(1)->andReturn(['update_id' => 1]);
        $this->parser->shouldReceive('parse')->once()->andReturn($dto);
        $this->routing->shouldReceive('resolve')->once()->with($dto)->andReturn($routing);
        $this->cardCreator->shouldReceive('create')
            ->once()
            ->withArgs(function (TelegramMessageDTO $msg, RoutingResultDTO $rendered, int $id) use ($routing) {
                return $id === 1
                    && $rendered->listId === $routing->listId
                    && ! str_contains($rendered->cardTitleTemplate, '{{')
                    && ! str_contains($rendered->cardDescriptionTemplate, '{{');
            })
            ->andReturn(new CreatedCardResult('card-1', 'AbCd1234', 'https://trello.com/c/card-1'));
        $this->telegram->shouldReceive('sendMessage')->once();
        $this->repository->shouldReceive('markProcessed')->once()->with(1);

        $this->processor->process(1);
    }

    /**
     * После успешного создания карточки пользователю отправляется подтверждение
     * с названием списка, ссылкой на карточку и shortLink.
     */
    public function test_sends_reply_with_list_name_and_card_url_after_creation(): void
    {
        $this->repository->shouldReceive('getPayload')->andReturn([]);
        $this->parser->shouldReceive('parse')->andReturn($this->makeMessageDTO());
        $this->routing->shouldReceive('resolve')->andReturn($this->makeRoutingResult());
        $this->cardCreator->shouldReceive('create')
            ->andReturn(new CreatedCardResult('card-1', 'AbCd1234', 'https://trello.com/c/card-1'));

        $this->telegram
            ->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function (string $chatId, string $text) {
                return $chatId === '100'
                    && str_contains($text, 'Backlog')
                    && str_contains($text, 'https://trello.com/c/card-1');
            });

        $this->repository->shouldReceive('markProcessed')->once();

        $this->processor->process(1);
    }

    /**
     * Парсер вернул null — markSkipped вызывается, Trello не вызывается.
     */
    public function test_marks_skipped_when_parser_returns_null(): void
    {
        $this->repository->shouldReceive('getPayload')->once()->andReturn([]);
        $this->parser->shouldReceive('parse')->once()->andReturn(null);
        $this->routing->shouldNotReceive('resolve');
        $this->cardCreator->shouldNotReceive('create');
        $this->repository->shouldReceive('markSkipped')->once()->with(1, 'Неподдерживаемый тип сообщения');

        $this->processor->process(1);
    }

    /**
     * Routing не нашёл правила — markSkipped вызывается, Trello не вызывается.
     */
    public function test_marks_skipped_when_no_routing_match(): void
    {
        $this->repository->shouldReceive('getPayload')->once()->andReturn([]);
        $this->parser->shouldReceive('parse')->once()->andReturn($this->makeMessageDTO());
        $this->routing->shouldReceive('resolve')->once()->andReturn(null);
        $this->cardCreator->shouldNotReceive('create');
        $this->repository->shouldReceive('markSkipped')->once()->with(1, 'Правило маршрутизации не найдено');

        $this->processor->process(1);
    }

    /**
     * Trello бросил исключение — markProcessed и sendMessage НЕ вызываются,
     * исключение пробрасывается.
     */
    public function test_does_not_mark_processed_on_trello_exception(): void
    {
        $this->repository->shouldReceive('getPayload')->once()->andReturn([]);
        $this->parser->shouldReceive('parse')->once()->andReturn($this->makeMessageDTO());
        $this->routing->shouldReceive('resolve')->once()->andReturn($this->makeRoutingResult());
        $this->cardCreator->shouldReceive('create')->andThrow(new TrelloConnectionException('timeout'));
        $this->telegram->shouldNotReceive('sendMessage');
        $this->repository->shouldNotReceive('markProcessed');

        $this->expectException(TrelloConnectionException::class);

        $this->processor->process(1);
    }

    /**
     * "Догоняющий" update медиагруппы: карточка уже создана →
     * скачивает фото и прикрепляет к существующей карточке.
     * Новая карточка НЕ создаётся, reply НЕ отправляется.
     */
    public function test_catching_up_update_attaches_photo_to_existing_card(): void
    {
        $dto = $this->makeMediaGroupDTO(photos: ['file-id-1']);

        $this->repository->shouldReceive('getPayload')->once()->andReturn([]);
        $this->parser->shouldReceive('parse')->once()->andReturn($dto);
        $this->repository->shouldReceive('findCardIdByMediaGroup')
            ->once()->with('group-123')->andReturn('existing-card-id');

        $this->fileDownloader->shouldReceive('download')
            ->once()->with('file-id-1', 1)
            ->andReturn(new DownloadedFile('/tmp/photo.jpg', 'image/jpeg'));

        $this->trello->shouldReceive('attachFile')
            ->once()->with('existing-card-id', '/tmp/photo.jpg', 'image/jpeg');

        $this->cardCreator->shouldNotReceive('create');
        $this->telegram->shouldNotReceive('sendMessage');
        $this->repository->shouldReceive('markProcessed')->once()->with(1);

        $this->processor->process(1);
    }

    /**
     * "Догоняющий" update с документом → прикрепляется документ.
     */
    public function test_catching_up_update_attaches_document_to_existing_card(): void
    {
        $dto = $this->makeMediaGroupDTO(documents: ['doc-file-id']);

        $this->repository->shouldReceive('getPayload')->once()->andReturn([]);
        $this->parser->shouldReceive('parse')->once()->andReturn($dto);
        $this->repository->shouldReceive('findCardIdByMediaGroup')
            ->once()->with('group-123')->andReturn('existing-card-id');

        $this->fileDownloader->shouldReceive('download')
            ->once()->with('doc-file-id', 1)
            ->andReturn(new DownloadedFile('/tmp/file.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));

        $this->trello->shouldReceive('attachFile')->once();

        $this->cardCreator->shouldNotReceive('create');
        $this->telegram->shouldNotReceive('sendMessage');
        $this->repository->shouldReceive('markProcessed')->once()->with(1);

        $this->processor->process(1);
    }

    /**
     * Команда без текста и без reply_to_message → карточка не создаётся, пользователю уведомление.
     */
    public function test_command_without_content_notifies_user_and_marks_skipped(): void
    {
        $dto = $this->makeCommandDTO(text: '/bug', command: '/bug');

        $this->repository->shouldReceive('getPayload')->once()->andReturn([]);
        $this->parser->shouldReceive('parse')->once()->andReturn($dto);
        $this->routing->shouldReceive('resolve')->once()->andReturn($this->makeRoutingResult());
        $this->cardCreator->shouldNotReceive('create');

        $this->telegram
            ->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function (string $chatId, string $text) {
                return $chatId === '100' && str_contains($text, 'опис');
            });

        $this->repository->shouldReceive('markSkipped')->once()->with(1, 'Команда без контента');

        $this->processor->process(1);
    }

    /**
     * Команда с только фото (без текста, без reply) → карточка не создаётся, уведомление.
     */
    public function test_command_with_only_photos_and_no_text_notifies_user_and_marks_skipped(): void
    {
        $dto = $this->makeCommandDTO(text: null, command: '/bug', photos: ['photo-id']);

        $this->repository->shouldReceive('getPayload')->once()->andReturn([]);
        $this->parser->shouldReceive('parse')->once()->andReturn($dto);
        $this->routing->shouldReceive('resolve')->once()->andReturn($this->makeRoutingResult());
        $this->cardCreator->shouldNotReceive('create');
        $this->telegram->shouldReceive('sendMessage')->once();
        $this->repository->shouldReceive('markSkipped')->once()->with(1, 'Команда без контента');

        $this->processor->process(1);
    }

    /**
     * /bug@botname без текста и без reply → карточка не создаётся.
     */
    public function test_command_with_botname_suffix_and_no_text_marks_skipped(): void
    {
        $dto = $this->makeCommandDTO(text: '/bug@itsell_trello_bot', command: '/bug');

        $this->repository->shouldReceive('getPayload')->once()->andReturn([]);
        $this->parser->shouldReceive('parse')->once()->andReturn($dto);
        $this->routing->shouldReceive('resolve')->once()->andReturn($this->makeRoutingResult());
        $this->cardCreator->shouldNotReceive('create');
        $this->telegram->shouldReceive('sendMessage')->once();
        $this->repository->shouldReceive('markSkipped')->once()->with(1, 'Команда без контента');

        $this->processor->process(1);
    }

    /**
     * Команда с текстом из одних символов → карточка не создаётся.
     */
    public function test_command_with_only_symbols_marks_skipped(): void
    {
        $dto = $this->makeCommandDTO(text: '/bug !!!---...', command: '/bug');

        $this->repository->shouldReceive('getPayload')->once()->andReturn([]);
        $this->parser->shouldReceive('parse')->once()->andReturn($dto);
        $this->routing->shouldReceive('resolve')->once()->andReturn($this->makeRoutingResult());
        $this->cardCreator->shouldNotReceive('create');
        $this->telegram->shouldReceive('sendMessage')->once();
        $this->repository->shouldReceive('markSkipped')->once()->with(1, 'Команда без контента');

        $this->processor->process(1);
    }

    /**
     * Команда с текстом из одних цифр → карточка не создаётся.
     */
    public function test_command_with_only_digits_marks_skipped(): void
    {
        $dto = $this->makeCommandDTO(text: '/bug 12345', command: '/bug');

        $this->repository->shouldReceive('getPayload')->once()->andReturn([]);
        $this->parser->shouldReceive('parse')->once()->andReturn($dto);
        $this->routing->shouldReceive('resolve')->once()->andReturn($this->makeRoutingResult());
        $this->cardCreator->shouldNotReceive('create');
        $this->telegram->shouldReceive('sendMessage')->once();
        $this->repository->shouldReceive('markSkipped')->once()->with(1, 'Команда без контента');

        $this->processor->process(1);
    }

    /**
     * Команда с текстом из символов и цифр → карточка не создаётся.
     */
    public function test_command_with_only_symbols_and_digits_marks_skipped(): void
    {
        $dto = $this->makeCommandDTO(text: '/bug #123 !!!', command: '/bug');

        $this->repository->shouldReceive('getPayload')->once()->andReturn([]);
        $this->parser->shouldReceive('parse')->once()->andReturn($dto);
        $this->routing->shouldReceive('resolve')->once()->andReturn($this->makeRoutingResult());
        $this->cardCreator->shouldNotReceive('create');
        $this->telegram->shouldReceive('sendMessage')->once();
        $this->repository->shouldReceive('markSkipped')->once()->with(1, 'Команда без контента');

        $this->processor->process(1);
    }

    /**
     * Команда с реальным текстом → карточка создаётся.
     */
    public function test_command_with_real_text_creates_card(): void
    {
        $dto = $this->makeCommandDTO(text: '/bug кнопка не работает', command: '/bug');

        $this->repository->shouldReceive('getPayload')->once()->andReturn([]);
        $this->parser->shouldReceive('parse')->once()->andReturn($dto);
        $this->routing->shouldReceive('resolve')->once()->andReturn($this->makeRoutingResult());
        $this->cardCreator->shouldReceive('create')->once()
            ->andReturn(new CreatedCardResult('card-1', 'AbCd1234', 'https://trello.com/c/card-1'));
        $this->telegram->shouldReceive('sendMessage')->once();
        $this->repository->shouldReceive('markProcessed')->once()->with(1);

        $this->processor->process(1);
    }

    /**
     * Текст с буквами + цифры + символы → карточка создаётся.
     */
    public function test_command_with_mixed_text_and_symbols_creates_card(): void
    {
        $dto = $this->makeCommandDTO(text: '/bug баг #123 !!!', command: '/bug');

        $this->repository->shouldReceive('getPayload')->once()->andReturn([]);
        $this->parser->shouldReceive('parse')->once()->andReturn($dto);
        $this->routing->shouldReceive('resolve')->once()->andReturn($this->makeRoutingResult());
        $this->cardCreator->shouldReceive('create')->once()
            ->andReturn(new CreatedCardResult('card-1', 'AbCd1234', 'https://trello.com/c/card-1'));
        $this->telegram->shouldReceive('sendMessage')->once();
        $this->repository->shouldReceive('markProcessed')->once()->with(1);

        $this->processor->process(1);
    }

    /**
     * Команда без текста, но с reply_to_message → карточка создаётся.
     */
    public function test_command_without_text_but_with_reply_creates_card(): void
    {
        $reply = new ReplyMessageDTO(
            text: 'Текст цитируемого поста',
            caption: null,
            photos: [],
        );
        $dto = $this->makeCommandDTO(text: '/bug', command: '/bug', replyToMessage: $reply);

        $this->repository->shouldReceive('getPayload')->once()->andReturn([]);
        $this->parser->shouldReceive('parse')->once()->andReturn($dto);
        $this->routing->shouldReceive('resolve')->once()->andReturn($this->makeRoutingResult());
        $this->cardCreator->shouldReceive('create')->once()
            ->andReturn(new CreatedCardResult('card-1', 'AbCd1234', 'https://trello.com/c/card-1'));
        $this->telegram->shouldReceive('sendMessage')->once();
        $this->repository->shouldReceive('markProcessed')->once()->with(1);

        $this->processor->process(1);
    }

    /**
     * Первый update медиагруппы без существующей карточки →
     * обычный флоу создания карточки.
     */
    public function test_first_group_update_without_existing_card_creates_card(): void
    {
        $dto = $this->makeMediaGroupDTO(photos: ['file-id-1']);

        $this->repository->shouldReceive('getPayload')->once()->andReturn([]);
        $this->parser->shouldReceive('parse')->once()->andReturn($dto);
        $this->repository->shouldReceive('findCardIdByMediaGroup')
            ->once()->with('group-123')->andReturn(null);

        $this->routing->shouldReceive('resolve')->once()->andReturn($this->makeRoutingResult());
        $this->cardCreator->shouldReceive('create')->once()
            ->andReturn(new CreatedCardResult('new-card', 'AbCd1234', 'https://trello.com/c/new-card'));
        $this->telegram->shouldReceive('sendMessage')->once();
        $this->repository->shouldReceive('findSkippedGroupParts')
            ->once()->with('group-123', 1)->andReturn([]);
        $this->repository->shouldReceive('markProcessed')->once()->with(1);

        $this->processor->process(1);
    }

    /**
     * Первый update медиагруппы, нет карточки, нет routing rule → markSkipped.
     */
    public function test_first_group_update_without_routing_marks_skipped(): void
    {
        $dto = $this->makeMediaGroupDTO(photos: ['file-id-1']);

        $this->repository->shouldReceive('getPayload')->once()->andReturn([]);
        $this->parser->shouldReceive('parse')->once()->andReturn($dto);
        $this->repository->shouldReceive('findCardIdByMediaGroup')
            ->once()->with('group-123')->andReturn(null);

        $this->routing->shouldReceive('resolve')->once()->andReturn(null);
        $this->cardCreator->shouldNotReceive('create');
        $this->repository->shouldReceive('markSkipped')
            ->once()->with(1, 'Правило маршрутизации не найдено');

        $this->processor->process(1);
    }

    /**
     * Первый update группы создаёт карточку и retroactively
     * прикрепляет файлы из ранее пришедших skipped частей группы.
     */
    public function test_retroactively_attaches_files_from_skipped_group_parts(): void
    {
        $dto = $this->makeMediaGroupDTO(photos: ['main-file-id']);

        $this->repository->shouldReceive('getPayload')->once()->andReturn([]);
        $this->parser->shouldReceive('parse')->once()->andReturn($dto);
        $this->repository->shouldReceive('findCardIdByMediaGroup')
            ->once()->with('group-123')->andReturn(null);

        $this->routing->shouldReceive('resolve')->once()->andReturn($this->makeRoutingResult());
        $this->cardCreator->shouldReceive('create')->once()
            ->andReturn(new CreatedCardResult('new-card', 'AbCd1234', 'https://trello.com/c/new-card'));
        $this->telegram->shouldReceive('sendMessage')->once();

        // Репозиторий возвращает одну skipped часть с файлом
        $this->repository->shouldReceive('findSkippedGroupParts')
            ->once()->with('group-123', 1)
            ->andReturn([['id' => 99, 'file_ids' => ['skipped-file-id']]]);

        $this->fileDownloader->shouldReceive('download')
            ->once()->with('skipped-file-id', 99)
            ->andReturn(new DownloadedFile('/tmp/skipped.jpg', 'image/jpeg'));

        $this->trello->shouldReceive('attachFile')
            ->once()->with('new-card', '/tmp/skipped.jpg', 'image/jpeg');

        $this->repository->shouldReceive('markProcessed')->once()->with(99);
        $this->repository->shouldReceive('markProcessed')->once()->with(1);

        $this->processor->process(1);
    }

    /**
     * Если skipped частей нет — никаких лишних вызовов.
     */
    public function test_no_retroactive_attach_when_no_skipped_parts(): void
    {
        $dto = $this->makeMediaGroupDTO(photos: ['main-file-id']);

        $this->repository->shouldReceive('getPayload')->once()->andReturn([]);
        $this->parser->shouldReceive('parse')->once()->andReturn($dto);
        $this->repository->shouldReceive('findCardIdByMediaGroup')->once()->andReturn(null);
        $this->routing->shouldReceive('resolve')->once()->andReturn($this->makeRoutingResult());
        $this->cardCreator->shouldReceive('create')->once()
            ->andReturn(new CreatedCardResult('new-card', 'AbCd1234', 'https://trello.com/c/new-card'));
        $this->telegram->shouldReceive('sendMessage')->once();

        $this->repository->shouldReceive('findSkippedGroupParts')
            ->once()->with('group-123', 1)->andReturn([]);

        $this->fileDownloader->shouldNotReceive('download');
        $this->trello->shouldNotReceive('attachFile');

        $this->repository->shouldReceive('markProcessed')->once()->with(1);

        $this->processor->process(1);
    }

    /**
     * После создания карточки bot_message_id сохраняется в cardLog.
     */
    public function test_bot_message_id_stored_after_card_creation(): void
    {
        $dto = $this->makeMessageDTO();

        $this->repository->shouldReceive('getPayload')->andReturn([]);
        $this->parser->shouldReceive('parse')->andReturn($dto);
        $this->routing->shouldReceive('resolve')->andReturn($this->makeRoutingResult());
        $this->cardCreator->shouldReceive('create')
            ->andReturn(new CreatedCardResult('card-1', 'AbCd1234', 'https://trello.com/c/card-1'));

        $this->telegram->shouldReceive('sendMessage')->once()->andReturn(7777);

        $this->cardLog
            ->shouldReceive('setBotMessageId')
            ->once()
            ->with(1, 7777);

        $this->repository->shouldReceive('markProcessed')->once()->with(1);

        $this->processor->process(1);
    }

    /**
     * Если sendMessage вернул null (ошибка), setBotMessageId не вызывается.
     */
    public function test_bot_message_id_not_stored_when_send_fails(): void
    {
        $dto = $this->makeMessageDTO();

        $this->repository->shouldReceive('getPayload')->andReturn([]);
        $this->parser->shouldReceive('parse')->andReturn($dto);
        $this->routing->shouldReceive('resolve')->andReturn($this->makeRoutingResult());
        $this->cardCreator->shouldReceive('create')
            ->andReturn(new CreatedCardResult('card-1', 'AbCd1234', 'https://trello.com/c/card-1'));

        $this->telegram->shouldReceive('sendMessage')->once()->andReturn(null);

        $this->cardLog->shouldNotReceive('setBotMessageId');

        $this->repository->shouldReceive('markProcessed')->once();

        $this->processor->process(1);
    }

    /**
     * Ответ на сообщение бота с текстом → добавляет комментарий в Trello.
     */
    public function test_reply_to_bot_message_with_text_adds_comment(): void
    {
        $dto = $this->makeReplyToBotDTO(text: 'Добавляю комментарий', replyToMessageId: 8888);

        $card = ['card_id' => 'card-abc', 'card_url' => 'https://trello.com/c/card-abc'];

        $this->repository->shouldReceive('getPayload')->andReturn([]);
        $this->parser->shouldReceive('parse')->andReturn($dto);
        $this->repository->shouldReceive('findCardByBotMessageId')
            ->once()->with('100', 8888)->andReturn($card);

        $this->trello->shouldReceive('addComment')
            ->once()->with('card-abc', 'Добавляю комментарий');

        $this->telegram->shouldReceive('sendMessage')->once();
        $this->repository->shouldReceive('markProcessed')->once()->with(1);

        $this->cardCreator->shouldNotReceive('create');
        $this->routing->shouldNotReceive('resolve');

        $this->processor->process(1);
    }

    /**
     * Ответ на сообщение бота с файлом → прикрепляет файл к карточке.
     */
    public function test_reply_to_bot_message_with_file_attaches_to_card(): void
    {
        $dto = $this->makeReplyToBotDTO(documents: ['doc-file-id'], replyToMessageId: 8888);

        $card = ['card_id' => 'card-abc', 'card_url' => 'https://trello.com/c/card-abc'];

        $this->repository->shouldReceive('getPayload')->andReturn([]);
        $this->parser->shouldReceive('parse')->andReturn($dto);
        $this->repository->shouldReceive('findCardByBotMessageId')
            ->once()->with('100', 8888)->andReturn($card);

        $this->fileDownloader->shouldReceive('download')
            ->once()->with('doc-file-id', 1)
            ->andReturn(new DownloadedFile('/tmp/doc.pdf', 'application/pdf'));

        $this->trello->shouldReceive('attachFile')
            ->once()->with('card-abc', '/tmp/doc.pdf', 'application/pdf');

        $this->telegram->shouldReceive('sendMessage')->once();
        $this->repository->shouldReceive('markProcessed')->once()->with(1);

        $this->cardCreator->shouldNotReceive('create');

        $this->processor->process(1);
    }

    /**
     * Ответ на сообщение бота с фото → прикрепляет фото к карточке.
     */
    public function test_reply_to_bot_message_with_photo_attaches_to_card(): void
    {
        $dto = $this->makeReplyToBotDTO(photos: ['photo-file-id'], replyToMessageId: 8888);

        $card = ['card_id' => 'card-abc', 'card_url' => 'https://trello.com/c/card-abc'];

        $this->repository->shouldReceive('getPayload')->andReturn([]);
        $this->parser->shouldReceive('parse')->andReturn($dto);
        $this->repository->shouldReceive('findCardByBotMessageId')
            ->once()->with('100', 8888)->andReturn($card);

        $this->fileDownloader->shouldReceive('download')
            ->once()->with('photo-file-id', 1)
            ->andReturn(new DownloadedFile('/tmp/photo.jpg', 'image/jpeg'));

        $this->trello->shouldReceive('attachFile')
            ->once()->with('card-abc', '/tmp/photo.jpg', 'image/jpeg');

        $this->telegram->shouldReceive('sendMessage')->once();
        $this->repository->shouldReceive('markProcessed')->once()->with(1);

        $this->cardCreator->shouldNotReceive('create');

        $this->processor->process(1);
    }

    /**
     * Команда в ответе на сообщение бота → обрабатывается как обычное создание карточки.
     */
    public function test_command_reply_to_bot_message_creates_card_normally(): void
    {
        $dto = $this->makeCommandDTO(text: '/bug новый баг', command: '/bug');
        // replyToMessageId не установлен → не проверяем findCardByBotMessageId
        $this->repository->shouldReceive('getPayload')->andReturn([]);
        $this->parser->shouldReceive('parse')->andReturn($dto);
        $this->repository->shouldNotReceive('findCardByBotMessageId');
        $this->routing->shouldReceive('resolve')->andReturn($this->makeRoutingResult());
        $this->cardCreator->shouldReceive('create')->once()
            ->andReturn(new CreatedCardResult('card-1', 'AbCd1234', 'https://trello.com/c/card-1'));
        $this->telegram->shouldReceive('sendMessage')->once()->andReturn(null);
        $this->repository->shouldReceive('markProcessed')->once();

        $this->processor->process(1);
    }

    /**
     * Ответ на неизвестное сообщение (не на сообщение бота) → обычный флоу.
     */
    public function test_reply_to_unknown_message_proceeds_normally(): void
    {
        $dto = new TelegramMessageDTO(
            messageType: 'text',
            text: 'просто текст',
            caption: null,
            photos: [],
            documents: [],
            userId: 42,
            chatId: '100',
            chatType: 'private',
            command: null,
            username: 'testuser',
            firstName: 'Test',
            sentAt: new DateTimeImmutable,
            replyToMessageId: 9999,
        );

        $this->repository->shouldReceive('getPayload')->andReturn([]);
        $this->parser->shouldReceive('parse')->andReturn($dto);
        $this->repository->shouldReceive('findCardByBotMessageId')
            ->once()->with('100', 9999)->andReturn(null);

        // Не найдено — идёт в обычный флоу
        $this->routing->shouldReceive('resolve')->once()->andReturn($this->makeRoutingResult());
        $this->cardCreator->shouldReceive('create')->once()
            ->andReturn(new CreatedCardResult('card-1', 'AbCd1234', 'https://trello.com/c/card-1'));
        $this->telegram->shouldReceive('sendMessage')->once()->andReturn(null);
        $this->repository->shouldReceive('markProcessed')->once();

        $this->processor->process(1);
    }

    // --- Fixtures ---

    private function makeMessageDTO(): TelegramMessageDTO
    {
        return new TelegramMessageDTO(
            messageType: 'text',
            text: 'Hello world',
            caption: null,
            photos: [],
            documents: [],
            userId: 42,
            chatId: '100',
            chatType: 'private',
            command: null,
            username: 'testuser',
            firstName: 'Test',
            sentAt: new DateTimeImmutable,
        );
    }

    private function makeMediaGroupDTO(array $photos = [], array $documents = []): TelegramMessageDTO
    {
        return new TelegramMessageDTO(
            messageType: empty($photos) ? 'text' : 'text_photo',
            text: null,
            caption: '/bug test',
            photos: $photos,
            documents: $documents,
            userId: 42,
            chatId: '100',
            chatType: 'private',
            command: '/bug',
            username: 'testuser',
            firstName: 'Test',
            sentAt: new DateTimeImmutable,
            mediaGroupId: 'group-123',
        );
    }

    /**
     * Пользователь с language_code=ru получает ответ на русском.
     */
    public function test_reply_is_in_russian_for_ru_user(): void
    {
        $dto = $this->makeCommandDTO(text: '/bug', command: '/bug', languageCode: 'ru');

        $this->repository->shouldReceive('getPayload')->once()->andReturn([]);
        $this->parser->shouldReceive('parse')->once()->andReturn($dto);
        $this->routing->shouldReceive('resolve')->once()->andReturn($this->makeRoutingResult());
        $this->cardCreator->shouldNotReceive('create');
        $this->telegram
            ->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text) => str_contains($text, 'описание'));
        $this->repository->shouldReceive('markSkipped')->once();

        $this->processor->process(1);
    }

    /**
     * Пользователь с language_code=uk получает ответ на украинском.
     */
    public function test_reply_is_in_ukrainian_for_uk_user(): void
    {
        $dto = $this->makeCommandDTO(text: '/bug', command: '/bug', languageCode: 'uk');

        $this->repository->shouldReceive('getPayload')->once()->andReturn([]);
        $this->parser->shouldReceive('parse')->once()->andReturn($dto);
        $this->routing->shouldReceive('resolve')->once()->andReturn($this->makeRoutingResult());
        $this->cardCreator->shouldNotReceive('create');
        $this->telegram
            ->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text) => str_contains($text, 'опис'));
        $this->repository->shouldReceive('markSkipped')->once();

        $this->processor->process(1);
    }

    /**
     * Пользователь с language_code=pl получает ответ на польском.
     */
    public function test_reply_is_in_polish_for_pl_user(): void
    {
        $dto = $this->makeCommandDTO(text: '/bug', command: '/bug', languageCode: 'pl');

        $this->repository->shouldReceive('getPayload')->once()->andReturn([]);
        $this->parser->shouldReceive('parse')->once()->andReturn($dto);
        $this->routing->shouldReceive('resolve')->once()->andReturn($this->makeRoutingResult());
        $this->cardCreator->shouldNotReceive('create');
        $this->telegram
            ->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text) => str_contains($text, 'opis'));
        $this->repository->shouldReceive('markSkipped')->once();

        $this->processor->process(1);
    }

    /**
     * Пользователь с language_code=en получает ответ на английском.
     */
    public function test_reply_is_in_english_for_en_user(): void
    {
        $dto = $this->makeCommandDTO(text: '/bug', command: '/bug', languageCode: 'en');

        $this->repository->shouldReceive('getPayload')->once()->andReturn([]);
        $this->parser->shouldReceive('parse')->once()->andReturn($dto);
        $this->routing->shouldReceive('resolve')->once()->andReturn($this->makeRoutingResult());
        $this->cardCreator->shouldNotReceive('create');
        $this->telegram
            ->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text) => str_contains($text, 'description'));
        $this->repository->shouldReceive('markSkipped')->once();

        $this->processor->process(1);
    }

    /**
     * Неизвестный language_code → fallback на украинский.
     */
    public function test_unknown_language_falls_back_to_ukrainian(): void
    {
        $dto = $this->makeCommandDTO(text: '/bug', command: '/bug', languageCode: 'de');

        $this->repository->shouldReceive('getPayload')->once()->andReturn([]);
        $this->parser->shouldReceive('parse')->once()->andReturn($dto);
        $this->routing->shouldReceive('resolve')->once()->andReturn($this->makeRoutingResult());
        $this->cardCreator->shouldNotReceive('create');
        $this->telegram
            ->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text) => str_contains($text, 'опис'));
        $this->repository->shouldReceive('markSkipped')->once();

        $this->processor->process(1);
    }

    /**
     * Карточка создана — сообщение успеха на языке пользователя (uk).
     */
    public function test_card_created_message_uses_user_language(): void
    {
        $dto = new TelegramMessageDTO(
            messageType: 'text',
            text: 'баг знайдено',
            caption: null,
            photos: [],
            documents: [],
            userId: 42,
            chatId: '100',
            chatType: 'supergroup',
            command: null,
            username: 'testuser',
            firstName: 'Test',
            sentAt: new DateTimeImmutable,
            languageCode: 'uk',
        );

        $this->repository->shouldReceive('getPayload')->once()->andReturn([]);
        $this->parser->shouldReceive('parse')->once()->andReturn($dto);
        $this->routing->shouldReceive('resolve')->once()->andReturn($this->makeRoutingResult());
        $this->cardCreator->shouldReceive('create')->once()
            ->andReturn(new CreatedCardResult('card-1', 'AbCd1234', 'https://trello.com/c/card-1'));
        $this->telegram
            ->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text) => str_contains($text, 'створено'));
        $this->repository->shouldReceive('markProcessed')->once();

        $this->processor->process(1);
    }

    private function makeCommandDTO(
        ?string $text,
        string $command,
        array $photos = [],
        ?ReplyMessageDTO $replyToMessage = null,
        ?string $languageCode = null,
    ): TelegramMessageDTO {
        return new TelegramMessageDTO(
            messageType: 'command',
            text: $text,
            caption: null,
            photos: $photos,
            documents: [],
            userId: 42,
            chatId: '100',
            chatType: 'supergroup',
            command: $command,
            username: 'testuser',
            firstName: 'Test',
            sentAt: new DateTimeImmutable,
            replyToMessage: $replyToMessage,
            languageCode: $languageCode,
        );
    }

    private function makeReplyToBotDTO(
        ?string $text = null,
        array $photos = [],
        array $documents = [],
        int $replyToMessageId = 8888,
    ): TelegramMessageDTO {
        return new TelegramMessageDTO(
            messageType: empty($photos) && empty($documents) ? 'text' : 'photo',
            text: $text,
            caption: null,
            photos: $photos,
            documents: $documents,
            userId: 42,
            chatId: '100',
            chatType: 'supergroup',
            command: null,
            username: 'testuser',
            firstName: 'Test',
            sentAt: new DateTimeImmutable,
            replyToMessageId: $replyToMessageId,
        );
    }

    private function makeRoutingResult(): RoutingResultDTO
    {
        return new RoutingResultDTO(
            listId: 'list-abc',
            listName: 'Backlog',
            memberIds: [],
            labelIds: [],
            cardTitleTemplate: '{{first_name}}: {{text_preview}}',
            cardDescriptionTemplate: '{{text}}',
        );
    }
}
