<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('users') || Schema::hasColumn('users', 'honor')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->integer('honor')->default(0);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'honor')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('honor');
        });
    }
};
