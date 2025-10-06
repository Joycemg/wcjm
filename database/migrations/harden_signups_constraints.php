<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Deshabilitamos FKs mientras limpiamos/alteramos
        Schema::disableForeignKeyConstraints();

        // 0) Limpieza de duplicados por usuario (deja el más reciente)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                DELETE s1 FROM signups s1
                INNER JOIN signups s2
                  ON s1.user_id = s2.user_id
                 AND s1.id < s2.id
            ");
        } else {
            // Fallback genérico
            $dupes = DB::table('signups')
                ->select('user_id')
                ->groupBy('user_id')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('user_id');

            foreach ($dupes as $uid) {
                $ids = DB::table('signups')->where('user_id', $uid)
                    ->orderByDesc('id')->pluck('id')->toArray();
                array_shift($ids); // conserva el más nuevo
                if ($ids)
                    DB::table('signups')->whereIn('id', $ids)->delete();
            }
        }

        // 1) Índices y únicos (sólo si no existen)
        Schema::table('signups', function (Blueprint $t) {
            if (!self::indexExists('signups', 'signups_game_table_id_created_at_index')) {
                $t->index(['game_table_id', 'created_at'], 'signups_game_table_id_created_at_index');
            }

            // Regla fuerte: un usuario en una sola mesa
            if (!self::indexExists('signups', 'signups_user_id_unique')) {
                $t->unique('user_id', 'signups_user_id_unique');
            }

            // (Opcional) Evitar duplicado en la misma mesa. Si USÁS el unique de user_id,
            // este es redundante; déjalo comentado si querés simplificar.
            // if (!self::indexExists('signups', 'signups_game_table_id_user_id_unique')) {
            //     $t->unique(['game_table_id','user_id'], 'signups_game_table_id_user_id_unique');
            // }
        });

        // 2) Foreign Keys (sólo si no existen)
        Schema::table('signups', function (Blueprint $t) {
            if (!self::fkExistsByName('signups', 'signups_user_id_foreign')) {
                $t->foreign('user_id', 'signups_user_id_foreign')
                    ->references('id')->on('users')
                    ->onDelete('cascade');
            }
            if (!self::fkExistsByName('signups', 'signups_game_table_id_foreign')) {
                $t->foreign('game_table_id', 'signups_game_table_id_foreign')
                    ->references('id')->on('game_tables')
                    ->onDelete('cascade');
            }
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('signups', function (Blueprint $t) {
            if (self::fkExistsByName('signups', 'signups_user_id_foreign')) {
                $t->dropForeign('signups_user_id_foreign');
            }
            if (self::fkExistsByName('signups', 'signups_game_table_id_foreign')) {
                $t->dropForeign('signups_game_table_id_foreign');
            }

            if (self::indexExists('signups', 'signups_user_id_unique')) {
                $t->dropUnique('signups_user_id_unique');
            }
            // if (self::indexExists('signups', 'signups_game_table_id_user_id_unique')) {
            //     $t->dropUnique('signups_game_table_id_user_id_unique');
            // }
            if (self::indexExists('signups', 'signups_game_table_id_created_at_index')) {
                $t->dropIndex('signups_game_table_id_created_at_index');
            }
        });

        Schema::enableForeignKeyConstraints();
    }

    /* ========= helpers sin DBAL ========= */

    private static function indexExists(string $table, string $indexName): bool
    {
        // SHOW INDEX funciona en MySQL/MariaDB
        try {
            $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
            return !empty($rows);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function fkExistsByName(string $table, string $fkName): bool
    {
        try {
            $db = DB::getDatabaseName();
            $rows = DB::select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_TYPE = 'FOREIGN KEY'
                  AND TABLE_SCHEMA = ?
                  AND TABLE_NAME   = ?
                  AND CONSTRAINT_NAME = ?
                LIMIT 1
            ", [$db, $table, $fkName]);
            return !empty($rows);
        } catch (\Throwable $e) {
            return false;
        }
    }
};
