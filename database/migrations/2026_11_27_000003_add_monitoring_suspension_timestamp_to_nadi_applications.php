<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('nadi_applications')
            && !Schema::hasColumn('nadi_applications', 'monitoring_suspended_at')
        )
        {
            Schema::table('nadi_applications', function (Blueprint $table)
            {
                $table->dateTime('monitoring_suspended_at', 6)
                    ->nullable()
                    ->after('monitoring_status');
            });
        }
    }

    public function down(): void
    {
        /*
         * Forward-only migration.
         * Monitoring suspension history must not be altered by rollback.
         */
    }
};
