<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('game_tables', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('capacity');

            $table->string('image_path')->nullable();
            $table->string('image_url', 2048)->nullable();

            $table->boolean('is_open')->default(false)->index();
            $table->timestamp('opens_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable()->index();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('manager_counts_as_player')->default(true);
            $table->text('manager_note')->nullable();
            $table->string('join_url', 2048)->nullable();

            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('game_tables');
    }
};
