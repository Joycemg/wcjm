<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) Chequeo de duplicados antes de aplicar UNIQUE(user_id)
        $hasDuplicates = DB::table('signups')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new RuntimeException(
                "No se puede agregar UNIQUE(user_id) en signups: hay usuarios con más de una inscripción.\n" .
                "Solución: eliminá/mergeá duplicados y volvé a correr la migración."
            );
        }

        // 2) Crear índices
        Schema::table('signups', function (Blueprint $table) {
            // Unique: una mesa por usuario
            // Nombre explícito para poder revertir sin problemas
            $table->unique('user_id', 'signups_user_id_unique');

            // Índice compuesto para ordenar por llegada dentro de una mesa
            // Acelera queries tipo WHERE game_table_id = ? ORDER BY created_at
            $table->index(['game_table_id', 'created_at'], 'signups_table_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('signups', function (Blueprint $table) {
            // Bajar índices por nombre (evita problemas entre drivers)
            $table->dropUnique('signups_user_id_unique');
            $table->dropIndex('signups_table_created_idx');
        });
    }
};
