<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // человекочитаемое название правила

            // --- Условия совпадения (все nullable — правило может не иметь условия) ---
            $table->bigInteger('telegram_chat_id')->nullable(); // совпадение по конкретному чату
            $table->string('chat_type', 20)->nullable();        // private | group | supergroup
            $table->string('command', 50)->nullable();          // /bug | /task | /new и т.д.
            $table->string('keyword')->nullable();              // ключевое слово в тексте (зарезервировано)
            $table->boolean('has_photo')->nullable();           // true = только сообщения с фото

            // --- Действие при совпадении ---
            // FK на trello_lists.id — в какой список создавать карточку
            $table->foreignId('target_list_id')->constrained('trello_lists')->restrictOnDelete();
            $table->json('label_ids')->default('[]');           // Trello label IDs для карточки
            $table->json('member_ids')->default('[]');          // Trello member IDs для карточки
            $table->string('card_title_template');              // шаблон заголовка: "{{first_name}}: {{text_preview}}"
            $table->text('card_description_template');          // шаблон описания карточки

            // --- Управление ---
            // Чем больше priority, тем раньше проверяется правило при нескольких совпадениях
            $table->integer('priority')->default(0)->index();
            $table->boolean('is_active')->default(true);

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routing_rules');
    }
};
