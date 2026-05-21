<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trello_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('trello_connections')->cascadeOnDelete();
            $table->string('action_type', 64)->nullable();
            $table->json('payload');
            $table->timestamp('received_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trello_webhook_logs');
    }
};
