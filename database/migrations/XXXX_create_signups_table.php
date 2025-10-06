<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('signups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_table_id')->constrained('game_tables')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['game_table_id', 'user_id']);
            $table->index('created_at');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('signups');
    }
};
