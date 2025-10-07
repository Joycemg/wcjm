<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('honor_events', function (Blueprint $table) {
            $table->id();

            // Relación con usuario
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Puntos del evento (+ o -)
            $table->integer('points')->default(0);

            // Motivo estándar o personalizado (ej: "attendance:confirmed")
            $table->string('reason', 64)->index();

            // Metadatos arbitrarios (JSON liviano)
            $table->json('meta')->nullable();

            // Slug único (idempotencia)
            $table->string('slug', 120)->nullable();

            // Índice único compuesto (usuario + slug)
            $table->unique(['user_id', 'slug']);

            // Campos de timestamp
            $table->timestampsTz(precision: 0);

            // === Futuras extensiones (opcional) ===
            // $table->timestampTz('expires_at')->nullable()->index();
            // $table->string('source', 64)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('honor_events');
    }
};
