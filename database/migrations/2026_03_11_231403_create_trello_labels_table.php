<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trello_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('trello_connections')->cascadeOnDelete();
            $table->string('trello_label_id');           // ID метки в Trello API
            $table->string('board_id');
            $table->string('name')->nullable();          // метка может быть без названия (только цвет)
            $table->string('color', 30)->nullable();     // green | yellow | red | purple | blue | sky и т.д.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trello_labels');
    }
};
