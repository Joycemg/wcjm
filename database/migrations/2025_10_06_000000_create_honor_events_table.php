<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('honor_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->integer('points');
            $t->string('reason')->nullable();
            $t->json('meta')->nullable();
            $t->string('slug')->nullable(); // idempotencia por acción (único por user)
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
