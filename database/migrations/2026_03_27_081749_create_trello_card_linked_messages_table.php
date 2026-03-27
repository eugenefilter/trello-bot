<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trello_card_linked_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('chat_id', 20);
            $table->bigInteger('telegram_message_id');   // Telegram message_id пользователя
            $table->string('trello_card_id');
            $table->string('trello_card_url');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['chat_id', 'telegram_message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trello_card_linked_messages');
    }
};
