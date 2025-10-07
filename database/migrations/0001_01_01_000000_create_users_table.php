<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();

            // Datos básicos
            $table->string('name', 120)->nullable();
            $table->string('username', 60)->unique()->nullable();
            $table->string('email', 190)->unique();
            $table->string('password');

            // Rol y estado
            $table->string('role', 50)->default('user')->index();
            $table->boolean('is_superadmin')->default(false)->index();

            // Perfil visual
            $table->string('avatar_path', 255)->nullable();
            $table->text('bio')->nullable();

            // Verificación / auditoría
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();

            // Preferencias / metadatos (JSON flexible)
            $table->json('settings')->nullable(); // idioma, notificaciones, etc.
            $table->json('social_links')->nullable(); // { "twitter": "...", "discord": "..." }

            // Seguridad extra (opcional si activás 2FA / API tokens)
            $table->string('api_token', 80)->nullable()->unique();
            $table->string('two_factor_secret', 255)->nullable();
            $table->string('two_factor_recovery_codes', 255)->nullable();

            // Auditoría interna (quién creó o actualizó)
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();

            // Tokens de sesión
            $table->rememberToken();

            // Fechas con zona horaria
            $table->timestampsTz();

            // Relaciones de auditoría (opcionales, no obligatorias)
            // Se pueden activar cuando tu hosting soporte FK sin costo de rendimiento:
            /*
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            */
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
