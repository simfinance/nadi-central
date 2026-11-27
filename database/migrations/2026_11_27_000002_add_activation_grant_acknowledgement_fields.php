<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('nadi_activation_grants'))
        {
            return;
        }

        Schema::table('nadi_activation_grants', function (Blueprint $table)
        {
            if (!Schema::hasColumn('nadi_activation_grants', 'acknowledgement_status'))
            {
                $table->string('acknowledgement_status', 20)
                    ->default('pending')
                    ->after('grant_status');
            }

            if (!Schema::hasColumn('nadi_activation_grants', 'acknowledgement_request_id'))
            {
                $table->char('acknowledgement_request_id', 36)
                    ->charset('ascii')
                    ->collation('ascii_bin')
                    ->nullable()
                    ->after('acknowledgement_status');
            }

            if (!Schema::hasColumn('nadi_activation_grants', 'acknowledged_at'))
            {
                $table->dateTime('acknowledged_at', 6)
                    ->nullable()
                    ->after('acknowledgement_request_id');
            }

            if (!Schema::hasColumn('nadi_activation_grants', 'acknowledgement_error_code'))
            {
                $table->string('acknowledgement_error_code', 100)
                    ->nullable()
                    ->after('acknowledged_at');
            }

            if (!Schema::hasColumn('nadi_activation_grants', 'acknowledgement_message'))
            {
                $table->text('acknowledgement_message')
                    ->nullable()
                    ->after('acknowledgement_error_code');
            }
        });

        $index = DB::selectOne(
            'SELECT COUNT(*) AS aggregate
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND INDEX_NAME = ?',
            [
                'nadi_activation_grants',
                'uq_nadi_grant_ack_request_id',
            ]
        );

        if ((int) $index->aggregate === 0)
        {
            Schema::table('nadi_activation_grants', function (Blueprint $table)
            {
                $table->unique(
                    'acknowledgement_request_id',
                    'uq_nadi_grant_ack_request_id'
                );
            });
        }
    }

    public function down(): void
    {
        /*
         * Forward-only migration.
         * Acknowledgement history must not be removed by rollback.
         */
    }
};
