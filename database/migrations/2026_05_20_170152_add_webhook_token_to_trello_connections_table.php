<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('trello_connections', 'webhook_token')) {
            Schema::table('trello_connections', function (Blueprint $table) {
                $table->string('webhook_token')->nullable()->unique()->after('is_active');
            });
        }

        // Генерируем токены для существующих записей без токена
        DB::table('trello_connections')
            ->whereNull('webhook_token')
            ->orderBy('id')
            ->each(function ($row): void {
                DB::table('trello_connections')
                    ->where('id', $row->id)
                    ->update(['webhook_token' => Str::uuid()->toString()]);
            });
    }

    public function down(): void
    {
        Schema::table('trello_connections', function (Blueprint $table) {
            $table->dropColumn('webhook_token');
        });
    }
};
