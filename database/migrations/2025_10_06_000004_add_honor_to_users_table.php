<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // No regresamos si falta la tabla "users": si este archivo se ejecuta
        // antes de crearla (p. ej. usando --path), la migración quedaría
        // marcada como corrida sin agregar la columna y no volvería a intentar.
        if (Schema::hasColumn('users', 'honor')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'honor')) {
                $table->integer('honor')->default(0);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'honor')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'honor')) {
                $table->dropColumn('honor');
            }
        });
    }
};
