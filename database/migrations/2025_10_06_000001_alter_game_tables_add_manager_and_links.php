<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('game_tables'))
            return;

        Schema::table('game_tables', function (Blueprint $table) {
            // Campos nuevos (opcionales, tolerantes)
            if (!Schema::hasColumn('game_tables', 'manager_id')) {
                $table->unsignedBigInteger('manager_id')->nullable()->after('created_by');
                // Si querés FK real y tu hosting la banca:
                // $table->foreign('manager_id')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('game_tables', 'manager_counts_as_player')) {
                $table->boolean('manager_counts_as_player')->default(true)->after('manager_id');
            }
            if (!Schema::hasColumn('game_tables', 'manager_note')) {
                $table->text('manager_note')->nullable()->after('manager_counts_as_player');
            }
            if (!Schema::hasColumn('game_tables', 'join_url')) {
                $table->string('join_url', 2048)->nullable()->after('manager_note');
            }
            if (!Schema::hasColumn('game_tables', 'image_url')) {
                $table->string('image_url', 2048)->nullable()->after('image_path');
            }

            // Remover "rev" si existiera (era para polling)
            if (Schema::hasColumn('game_tables', 'rev')) {
                $table->dropColumn('rev');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('game_tables'))
            return;

        Schema::table('game_tables', function (Blueprint $table) {
            foreach (['manager_id', 'manager_counts_as_player', 'manager_note', 'join_url', 'image_url'] as $c) {
                if (Schema::hasColumn('game_tables', $c))
                    $table->dropColumn($c);
            }
        });
    }
};
