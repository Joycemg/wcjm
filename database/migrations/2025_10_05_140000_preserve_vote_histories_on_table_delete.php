<?php
// database/migrations/2025_10_05_140000_preserve_vote_histories_on_table_delete.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vote_histories', function (Blueprint $table) {
            // 1) Soltar FK actual (probablemente CASCADE)
            try {
                $table->dropForeign(['game_table_id']);
            } catch (\Throwable $e) {
                // Algunas bases tienen nombre distinto; si querés, podés loguear $e->getMessage()
            }

            // 2) Permitir NULL en game_table_id
            $table->unsignedBigInteger('game_table_id')->nullable()->change();

            // 3) Re-crear la FK con SET NULL (o comenta esto si preferís SIN FK)
            $table->foreign('game_table_id')
                ->references('id')->on('game_tables')
                ->nullOnDelete(); // ON DELETE SET NULL
        });
    }

    public function down(): void
    {
        Schema::table('vote_histories', function (Blueprint $table) {
            // Revertir a CASCADE (opcional)
            try {
                $table->dropForeign(['game_table_id']);
            } catch (\Throwable $e) {
            }

            // Si querés volver a NOT NULL, asegurate de no tener filas con NULL primero
            // \DB::table('vote_histories')->whereNull('game_table_id')->delete();

            $table->unsignedBigInteger('game_table_id')->nullable(false)->change();

            $table->foreign('game_table_id')
                ->references('id')->on('game_tables')
                ->cascadeOnDelete(); // vuelve a borrar historiales al borrar mesa (no recomendado)
        });
    }
};
