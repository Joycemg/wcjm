<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /**
         * Si tu hosting no soporta/quieres evitar FKs (o usas tablas legacy),
         * pone en .env: DB_FOREIGN_KEYS=false
         */
        $useForeignKeys = (bool) env('DB_FOREIGN_KEYS', true);

        Schema::create('signups', function (Blueprint $table) use ($useForeignKeys) {
            $table->id();

            // Relaciones (FKs opcionales)
            if ($useForeignKeys) {
                $table->foreignId('game_table_id')
                    ->constrained('game_tables')
                    ->cascadeOnDelete(); // borra inscripciones al eliminar mesa

                $table->foreignId('user_id')
                    ->constrained()
                    ->cascadeOnDelete(); // borra inscripciones al eliminar usuario
            } else {
                $table->unsignedBigInteger('game_table_id')->index();
                $table->unsignedBigInteger('user_id')->index();
            }

            // Evitar duplicados por mesa/usuario (una inscripción activa por mesa)
            $table->unique(['game_table_id', 'user_id'], 'signups_table_user_unique');

            // Flags
            $table->boolean('is_counted')->default(true)->index();  // cuenta para capacidad/posición
            $table->boolean('is_manager')->default(false)->index(); // aparece como encargado en la mesa

            /**
             * attended: si tu esquema ya la usa como columna real.
             * Si no la usas, el modelo la derivará de attendance_confirmed_at/no_show_at.
             * La dejamos nullable para permitir "pendiente".
             */
            $table->boolean('attended')->nullable()->index();

            /**
             * behavior: 'good' | 'regular' | 'bad' | null
             * Usamos string corta (16) para compatibilidad; validación dura se hace en app.
             */
            $table->string('behavior', 16)->nullable()->index();

            // Marcas de honor (compatibles con tu MesaHonorController)
            $table->timestamp('attendance_confirmed_at')->nullable()->index();
            $table->unsignedBigInteger('attendance_confirmed_by')->nullable()->index();

            $table->timestamp('no_show_at')->nullable()->index();
            $table->unsignedBigInteger('no_show_by')->nullable()->index();

            // Extras opcionales por si luego extendés (dejar nullable e indexar si ayuda)
            $table->string('note', 500)->nullable();                 // pequeña nota interna del manager
            $table->json('meta')->nullable();                        // payload extensible (clave/valor)

            $table->timestamps();

            /**
             * Índices para patrones de consulta:
             *  - position(): (game_table_id, is_counted, created_at, id)
             *  - recentForTable(): (game_table_id, created_at desc)
             */
            $table->index(['game_table_id', 'is_counted', 'created_at', 'id'], 'signups_pos_idx');
            $table->index(['game_table_id', 'created_at', 'id'], 'signups_recent_idx');

            // Índices auxiliares ya puestos: is_manager, behavior, *_at, *_by
        });

        // (Opcional) CHECK en DB modernas para behavior: 'good'|'regular'|'bad'
        // Lo envolvemos en try/catch por compatibilidad (MySQL < 8 ignora/enlaza mal CHECK).
        try {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'pgsql') {
                DB::statement(
                    "ALTER TABLE signups
                     ADD CONSTRAINT signups_behavior_chk
                     CHECK (behavior IN ('good','regular','bad') OR behavior IS NULL)"
                );
            } elseif ($driver === 'mysql') {
                // MySQL 8.0+ lo respeta; versiones viejas lo ignoran silenciosamente.
                DB::statement(
                    "ALTER TABLE signups
                     ADD CONSTRAINT signups_behavior_chk
                     CHECK (behavior IN ('good','regular','bad') OR behavior IS NULL)"
                );
            }
        } catch (\Throwable $e) {
            // Silenciar para hosting compartido; la app ya valida en el modelo/capa de dominio.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('signups');
    }
};
