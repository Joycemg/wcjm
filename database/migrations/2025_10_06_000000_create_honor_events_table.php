<?php declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());

        if ($schema->hasTable('honor_events')) {
            return;
        }

        $schema->create('honor_events', function (Blueprint $t) {
            $t->id();
            $t->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $t->integer('points');
            $t->string('reason')->nullable();
            $t->json('meta')->nullable();
            // Longitud 191 => compatible con índices UNIQUE en MySQL/MariaDB utf8mb4
            // sin depender de Schema::defaultStringLength().
            $t->string('slug', 191)->nullable(); // idempotencia por acción (único por user)
            $t->timestamps();

            $t->index('user_id');
            $t->unique(['user_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('honor_events');
    }
};
