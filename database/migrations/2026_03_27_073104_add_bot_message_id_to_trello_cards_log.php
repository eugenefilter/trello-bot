<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('trello_cards_log', function (Blueprint $table) {
            // ID сообщения бота с подтверждением создания карточки.
            // Используется для определения, что пользователь отвечает на сообщение бота.
            $table->unsignedBigInteger('bot_message_id')->nullable()->after('trello_card_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trello_cards_log', function (Blueprint $table) {
            $table->dropColumn('bot_message_id');
        });
    }
};
