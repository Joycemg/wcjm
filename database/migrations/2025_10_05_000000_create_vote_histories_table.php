<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Evita error en entornos compartidos donde ya exista la tabla
        if (Schema::hasTable('vote_histories')) {
            return;
        }

        Schema::create('vote_histories', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('game_table_id')->index();

            // Datos descriptivos
            $table->string('game_title', 180);
            $table->string('kind', 40)->nullable();

            // Fechas principales
            $table->timestamp('happened_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable()->index();

            // Timestamps Laravel (pueden omitirse si querés más liviano)
            $table->timestamps();

            // Índice compuesto más usado
            $table->index(['user_id', 'game_table_id', 'happened_at'], 'vh_user_table_date_idx');

            // (Opcional) FK si tu hosting soporta constraints
            if (config('database.connections.mysql.foreign_keys', true)) {
                $table->foreign('user_id')
                    ->references('id')->on('users')
                    ->onDelete('cascade');
                $table->foreign('game_table_id')
                    ->references('id')->on('game_tables')
                    ->onDelete('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vote_histories');
    }
};
