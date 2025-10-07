<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('game_tables', function (Blueprint $table) {
            $table->id();

            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('capacity');

            $table->string('image_path')->nullable();
            $table->string('image_url', 2048)->nullable();

            $table->boolean('is_open')->default(false)->index();
            $table->timestamp('opens_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable()->index();

            $table->foreignIdFor(User::class, 'created_by')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class, 'manager_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('manager_counts_as_player')->default(true);
            $table->text('manager_note')->nullable();
            $table->string('join_url', 2048)->nullable();

            $table->timestamps();

            $table->index(['is_open', 'opens_at'], 'game_tables_opening_window_index');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('game_tables');
    }
};
