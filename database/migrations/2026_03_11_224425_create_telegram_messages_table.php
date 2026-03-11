<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_messages', function (Blueprint $table): void {
            $table->id();
            $table->bigInteger('update_id')->unique();  // уникальный индекс — гарантия idempotency
            $table->bigInteger('message_id')->nullable();
            $table->bigInteger('chat_id');
            $table->string('chat_type', 20);            // private | group | supergroup | channel
            $table->bigInteger('user_id')->nullable();
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->text('text')->nullable();
            $table->text('caption')->nullable();         // текст под фото/документом
            $table->json('payload_json');                // полный сырой update для отладки и retry
            $table->timestamp('received_at');            // момент получения webhook
            $table->timestamp('processed_at')->nullable(); // null = ещё не обработан Job-ом
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_messages');
    }
};
