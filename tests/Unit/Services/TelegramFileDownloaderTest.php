<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use TelegramBot\Contracts\TelegramAdapterInterface;
use TelegramBot\Contracts\TelegramFileRepositoryInterface;
use TelegramBot\DTOs\DownloadedFile;
use TelegramBot\DTOs\TelegramFileInfo;
use TelegramBot\Exceptions\TelegramFileException;
use TelegramBot\Services\TelegramFileDownloader;

/**
 * Unit-тест TelegramFileDownloader.
 *
 * TelegramAdapterInterface и TelegramFileRepositoryInterface мокируются.
 * Для проверки MIME-типа создаётся реальный временный файл.
 */
class TelegramFileDownloaderTest extends TestCase
{
    private MockInterface $telegram;

    private MockInterface $fileRepository;

    private TelegramFileDownloader $downloader;

    private string $tmpFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->telegram = Mockery::mock(TelegramAdapterInterface::class);
        $this->fileRepository = Mockery::mock(TelegramFileRepositoryInterface::class);
        $this->downloader = new TelegramFileDownloader($this->telegram, $this->fileRepository);

        // Создаём временный файл с JPEG-заголовком для корректного определения MIME
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'tg_test_').'.jpg';
        file_put_contents($this->tmpFile, "\xFF\xD8\xFF\xE0".str_repeat("\x00", 100));
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
        Mockery::close();
        parent::tearDown();
    }

    /**
     * getFile вызывается с переданным fileId.
     */
    public function test_calls_get_file_with_file_id(): void
    {
        $this->telegram
            ->shouldReceive('getFile')
            ->once()
            ->with('file-id-abc')
            ->andReturn(new TelegramFileInfo('file-id-abc', 'photos/file.jpg', 5000));

        $this->telegram->shouldReceive('downloadFile')->andReturn($this->tmpFile);
        $this->fileRepository->shouldReceive('updateLocalPath')->once();

        $this->downloader->download('file-id-abc', telegramMessageId: 1);
    }

    /**
     * downloadFile вызывается с file_path из TelegramFileInfo.
     */
    public function test_calls_download_file_with_path_from_file_info(): void
    {
        $this->telegram->shouldReceive('getFile')->andReturn(
            new TelegramFileInfo('file-id-abc', 'photos/file_5.jpg', 5000)
        );

        $this->telegram
            ->shouldReceive('downloadFile')
            ->once()
            ->with('photos/file_5.jpg')
            ->andReturn($this->tmpFile);

        $this->fileRepository->shouldReceive('updateLocalPath')->once();

        $this->downloader->download('file-id-abc', telegramMessageId: 1);
    }

    /**
     * Возвращает DownloadedFile с localPath из downloadFile.
     */
    public function test_returns_downloaded_file_with_local_path(): void
    {
        $this->telegram->shouldReceive('getFile')->andReturn(
            new TelegramFileInfo('file-id-abc', 'photos/file.jpg', 5000)
        );
        $this->telegram->shouldReceive('downloadFile')->andReturn($this->tmpFile);
        $this->fileRepository->shouldReceive('updateLocalPath')->once();

        $result = $this->downloader->download('file-id-abc', telegramMessageId: 1);

        $this->assertInstanceOf(DownloadedFile::class, $result);
        $this->assertSame($this->tmpFile, $result->localPath);
    }

    /**
     * Возвращает DownloadedFile с MIME-типом, определённым из содержимого файла.
     */
    public function test_returns_mime_type_from_file_content(): void
    {
        $this->telegram->shouldReceive('getFile')->andReturn(
            new TelegramFileInfo('file-id-abc', 'photos/file.jpg', 5000)
        );
        $this->telegram->shouldReceive('downloadFile')->andReturn($this->tmpFile);
        $this->fileRepository->shouldReceive('updateLocalPath')->once();

        $result = $this->downloader->download('file-id-abc', telegramMessageId: 1);

        $this->assertNotEmpty($result->mimeType);
        $this->assertStringContainsString('image', $result->mimeType);
    }

    /**
     * updateLocalPath вызывается с правильными аргументами.
     */
    public function test_updates_telegram_file_record_with_local_path(): void
    {
        $this->telegram->shouldReceive('getFile')->andReturn(
            new TelegramFileInfo('file-id-abc', 'photos/file.jpg', 5000)
        );
        $this->telegram->shouldReceive('downloadFile')->andReturn($this->tmpFile);

        $this->fileRepository
            ->shouldReceive('updateLocalPath')
            ->once()
            ->with('file-id-abc', 42, $this->tmpFile);

        $this->downloader->download('file-id-abc', telegramMessageId: 42);
    }

    /**
     * Если getFile выбрасывает исключение — оно пробрасывается выше.
     */
    public function test_throws_exception_when_file_not_found(): void
    {
        $this->telegram
            ->shouldReceive('getFile')
            ->andThrow(new TelegramFileException('File not found: invalid-id'));

        $this->fileRepository->shouldNotReceive('updateLocalPath');

        $this->expectException(TelegramFileException::class);

        $this->downloader->download('invalid-id', telegramMessageId: 1);
    }
}
