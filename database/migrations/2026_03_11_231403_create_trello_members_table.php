<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trello_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('trello_connections')->cascadeOnDelete();
            $table->string('trello_member_id');          // ID участника в Trello API
            $table->string('username');                  // @username в Trello
            $table->string('full_name');                 // отображаемое имя
            $table->boolean('is_active')->default(true); // false = участник покинул доску
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trello_members');
    }
};
