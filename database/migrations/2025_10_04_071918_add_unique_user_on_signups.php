<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('signups', function (Blueprint $table) {
            if (!collect(Schema::getColumnListing('signups'))->contains('user_id'))
                return;
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes('signups');
            if (!array_key_exists('signups_user_id_unique', $indexes)) {
                $table->unique('user_id');
            }
        });
    }
    public function down(): void
    {
        Schema::table('signups', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
        });
    }
};
