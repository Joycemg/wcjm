<?php
// database/migrations/2025_10_05_000000_create_vote_histories_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vote_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_table_id')->nullable()->constrained('game_tables')->nullOnDelete();

            $table->string('game_title', 191);
            $table->string('kind', 12)->default('close')->index(); // 'close'
            $table->timestamp('happened_at')->nullable()->index(); // fecha mostrada

            $table->timestamps();

            // Un cierre por usuario/mesa
            $table->unique(['user_id', 'game_table_id', 'kind'], 'vh_user_table_kind_unique');
            $table->index(['game_table_id', 'kind'], 'vh_table_kind_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vote_histories');
    }
};
