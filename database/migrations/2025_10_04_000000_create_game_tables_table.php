<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('game_tables', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Básicos
            $table->string('title', 120);
            $table->text('description')->nullable();

            // Capacidad
            $table->unsignedInteger('capacity')->default(4);

            // Estado / horarios
            $table->boolean('is_open')->default(false)->index();
            $table->timestampTz('opens_at')->nullable()->index();
            $table->timestampTz('closed_at')->nullable()->index();

            // FKs (¡sin ->index()!)
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('manager_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->boolean('manager_counts_as_player')->default(true);

            // Imagen / links
            $table->string('image_path', 512)->nullable()->index();
            $table->string('image_url', 1024)->nullable();
            $table->string('join_url', 1024)->nullable();

            // Notas / extras
            $table->text('manager_note')->nullable();
            $table->string('tags', 512)->nullable();
            $table->json('extras')->nullable();

            // Flags
            $table->boolean('is_archived')->default(false)->index();
            $table->boolean('is_featured')->default(false)->index();

            // Auditoría
            $table->timestampsTz();

            // Índices compuestos útiles
            $table->index(['is_open', 'opens_at']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_tables');
    }
};
