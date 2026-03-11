<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trello_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('trello_connections')->cascadeOnDelete();
            $table->string('trello_list_id');            // ID списка в Trello API
            $table->string('board_id');                  // ID доски (денормализовано для быстрых запросов)
            $table->string('name');                      // название списка (To Do, In Progress и т.д.)
            $table->boolean('is_active')->default(true); // false = список удалён на стороне Trello
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trello_lists');
    }
};
