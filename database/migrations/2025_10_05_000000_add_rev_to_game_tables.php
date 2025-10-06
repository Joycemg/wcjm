<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('game_tables', function (Blueprint $table) {
            if (!Schema::hasColumn('game_tables', 'rev')) {
                $table->unsignedBigInteger('rev')->default(1)->after('updated_at')->index();
            }
        });

        // Backfill seguro (MySQL/MariaDB en Hostinger)
        try {
            $driver = DB::getDriverName();
            if (in_array($driver, ['mysql', 'mariadb'])) {
                DB::statement("
                    UPDATE game_tables
                    SET rev = GREATEST(
                        1,
                        UNIX_TIMESTAMP(COALESCE(updated_at, created_at))
                    )
                ");
            } else {
                // Fallback genérico
                DB::table('game_tables')->update(['rev' => 1]);
            }
        } catch (\Throwable $e) {
            // Si algo falla, al menos aseguramos rev=1
            try {
                DB::table('game_tables')->update(['rev' => 1]);
            } catch (\Throwable $e2) {
            }
        }
    }

    public function down(): void
    {
        Schema::table('game_tables', function (Blueprint $table) {
            if (Schema::hasColumn('game_tables', 'rev')) {
                $table->dropColumn('rev');
            }
        });
    }
};
