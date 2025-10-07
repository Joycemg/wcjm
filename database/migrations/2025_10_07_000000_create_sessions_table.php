<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            // Identificador primario de la sesión
            $table->string('id')->primary();

            // Usuario autenticado (si existe)
            $table->foreignId('user_id')
                ->nullable()
                ->index()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // Información de red y agente
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Contenido serializado de la sesión
            $table->text('payload');

            // Última actividad (timestamp UNIX)
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
