<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());

        if ($schema->hasTable('signups')) {
            return;
        }

        $schema->create('signups', function (Blueprint $table) {
            $table->id();

            $table->foreignId('game_table_id')->constrained('game_tables')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->boolean('is_counted')->default(true);
            $table->boolean('is_manager')->default(false);
            $table->boolean('attended')->nullable();
            $table->string('behavior', 16)->nullable();

            $table->timestamp('attendance_confirmed_at')->nullable();
            $table->foreignId('attendance_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('no_show_at')->nullable();
            $table->foreignId('no_show_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('user_id', 'signups_user_id_index');
            $table->unique(['game_table_id', 'user_id'], 'signups_game_table_id_user_id_unique');
            $table->index(['game_table_id', 'created_at'], 'signups_game_table_id_created_at_index');
            $table->index(['game_table_id', 'attended'], 'signups_attendance_lookup_index');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('signups');
    }
};
