<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('signups', function (Blueprint $t) {
            if (!Schema::hasColumn('signups', 'attended')) {
                $t->boolean('attended')->default(false)->after('is_manager');
            }
            if (!Schema::hasColumn('signups', 'behavior')) {
                $t->string('behavior', 16)->default('regular')->after('attended');
            }
        });
    }

    public function down(): void
    {
        Schema::table('signups', function (Blueprint $t) {
            if (Schema::hasColumn('signups', 'behavior'))
                $t->dropColumn('behavior');
            if (Schema::hasColumn('signups', 'attended'))
                $t->dropColumn('attended');
        });
    }
};
