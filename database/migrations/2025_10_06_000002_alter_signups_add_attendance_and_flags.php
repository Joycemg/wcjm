<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('signups'))
            return;

        Schema::table('signups', function (Blueprint $table) {
            if (!Schema::hasColumn('signups', 'is_counted')) {
                $table->boolean('is_counted')->default(true)->after('user_id'); // cuenta cupo
            }
            if (!Schema::hasColumn('signups', 'is_manager')) {
                $table->boolean('is_manager')->default(false)->after('is_counted');
            }
            if (!Schema::hasColumn('signups', 'attended')) {
                $table->boolean('attended')->nullable()->after('is_manager'); // null=sin marcar
            }
            if (!Schema::hasColumn('signups', 'behavior')) {
                $table->string('behavior', 16)->nullable()->after('attended'); // good/regular/bad
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('signups'))
            return;

        Schema::table('signups', function (Blueprint $table) {
            foreach (['is_counted', 'is_manager', 'attended', 'behavior'] as $c) {
                if (Schema::hasColumn('signups', $c))
                    $table->dropColumn($c);
            }
        });
    }
};
