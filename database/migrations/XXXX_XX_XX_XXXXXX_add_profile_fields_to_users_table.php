<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('email');
            $table->string('name')->nullable()->change(); // por si venía null
            $table->string('username')->nullable()->unique()->after('name'); // opcional
            $table->string('role')->default('user')->change(); // asegúrate de tener role
            $table->text('bio')->nullable()->after('avatar_path');
        });
    }
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_path', 'bio', 'username']);
        });
    }
};
