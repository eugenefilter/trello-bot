<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trello_cards_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_message_id')->constrained('telegram_messages')->cascadeOnDelete();
            $table->string('trello_card_id')->nullable();  // null если карточка не была создана
            $table->string('trello_card_url')->nullable();
            $table->string('trello_list_id')->nullable();  // в какой список пытались создать
            $table->string('status', 20);                  // success | error
            $table->text('error_message')->nullable();     // детали ошибки при status = error
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trello_cards_log');
    }
};
