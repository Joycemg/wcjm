<?php

// database/migrations/2025_10_06_000001_create_mesa_managers_table.php
use App\Models\GameTable;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());

        if ($schema->hasTable('mesa_managers')) {
            return;
        }

        $schema->create('mesa_managers', function (Blueprint $t) {
            $t->id();
            $t->foreignIdFor(GameTable::class, 'mesa_id')->constrained('game_tables')->cascadeOnDelete();
            $t->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $t->timestamps();

            $t->unique(['mesa_id', 'user_id']);
            $t->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mesa_managers');
    }
};
