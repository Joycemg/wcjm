<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // nullable para poder backfillear y no romper usuarios existentes
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username', 30)->nullable()->unique('users_username_unique')->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'username')) {
                // bajar el índice por nombre explícito y luego la columna
                $table->dropUnique('users_username_unique');
                $table->dropColumn('username');
            }
        });
    }
};
