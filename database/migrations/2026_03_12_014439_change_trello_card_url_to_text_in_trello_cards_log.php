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
            $table->text('trello_card_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('trello_cards_log', function (Blueprint $table) {
            $table->string('trello_card_url')->nullable()->change();
        });
    }
};
