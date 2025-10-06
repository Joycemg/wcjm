<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('users'))
            return;

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 32)->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users'))
            return;

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'phone'))
                $table->dropColumn('phone');
        });
    }
};
