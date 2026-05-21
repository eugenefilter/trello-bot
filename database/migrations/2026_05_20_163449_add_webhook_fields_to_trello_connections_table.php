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
        Schema::table('trello_connections', function (Blueprint $table) {
            $table->string('webhook_id')->nullable()->after('is_active');
            $table->timestamp('webhook_registered_at')->nullable()->after('webhook_id');
        });
    }

    public function down(): void
    {
        Schema::table('trello_connections', function (Blueprint $table) {
            $table->dropColumn(['webhook_id', 'webhook_registered_at']);
        });
    }
};
