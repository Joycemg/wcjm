<?php
// database/migrations/2025_10_05_000001_add_closed_at_to_game_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('game_tables', function (Blueprint $table) {
            if (!Schema::hasColumn('game_tables', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->index()->after('opens_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('game_tables', function (Blueprint $table) {
            if (Schema::hasColumn('game_tables', 'closed_at')) {
                $table->dropColumn('closed_at');
            }
        });
    }
};
