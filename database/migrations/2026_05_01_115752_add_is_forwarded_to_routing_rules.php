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
        Schema::table('routing_rules', function (Blueprint $table) {
            $table->boolean('is_forwarded')->nullable()->default(null)->after('has_photo');
        });
    }

    public function down(): void
    {
        Schema::table('routing_rules', function (Blueprint $table) {
            $table->dropColumn('is_forwarded');
        });
    }
};
