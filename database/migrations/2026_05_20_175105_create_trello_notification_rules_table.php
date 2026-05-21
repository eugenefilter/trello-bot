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
        Schema::create('trello_notification_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('trello_connections')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);

            // Триггер: ID целевой колонки
            $table->string('trigger_list_id');

            // Фильтр по меткам: массив trello_label_id + режим any/all
            $table->json('filter_label_ids')->nullable();
            $table->string('filter_label_mode')->default('any');

            // Фильтр по участникам: массив trello_member_bindings.id
            $table->json('filter_member_binding_ids')->nullable();

            // Уведомление
            $table->string('telegram_chat_id');
            $table->json('mention_member_binding_ids')->nullable();
            $table->text('message_template');

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trello_notification_rules');
    }
};
