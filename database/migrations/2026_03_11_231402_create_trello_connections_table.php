<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trello_connections', function (Blueprint $table) {
            $table->id();
            $table->string('name');                      // человекочитаемое название подключения
            $table->string('api_key');                   // Trello Power-Up API Key
            $table->string('api_token');                 // Trello User Token
            $table->string('board_id');                  // ID доски Trello
            $table->boolean('is_active')->default(true); // false = подключение отключено
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trello_connections');
    }
};
