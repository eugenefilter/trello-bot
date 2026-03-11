<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_files', function (Blueprint $table) {
            $table->id();
            // FK на сообщение, к которому прикреплён файл
            $table->foreignId('telegram_message_id')->constrained('telegram_messages')->cascadeOnDelete();
            $table->string('file_id');         // ID файла в Telegram (используется для скачивания)
            $table->string('file_unique_id');  // стабильный уникальный ID (не меняется при переотправке)
            $table->string('file_path')->nullable();  // путь для скачивания через Telegram API (getFile)
            $table->string('file_type', 20);   // photo | document
            $table->string('local_path')->nullable();  // локальный путь после скачивания на сервер
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();  // размер в байтах
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_files');
    }
};
