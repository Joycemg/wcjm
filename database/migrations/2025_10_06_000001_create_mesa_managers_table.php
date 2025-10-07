<?php

// database/migrations/2025_10_06_000001_create_mesa_managers_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mesa_managers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('mesa_id')->constrained('game_tables')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->timestamps();

            $t->unique(['mesa_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mesa_managers');
    }
};
