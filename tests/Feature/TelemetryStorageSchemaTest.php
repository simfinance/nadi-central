<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TelemetryStorageSchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_all_telemetry_storage_tables_exist(): void
    {
        $tables = [
            'nadi_telemetry_batches',
            'nadi_telemetry_streams',
            'nadi_telemetry_record_keys',
            'nadi_telemetry_records',
            'nadi_http_request_events',
            'nadi_telemetry_gaps',
        ];

        foreach ($tables as $table)
        {
            $this->assertTrue(
                Schema::hasTable($table),
                'Missing telemetry storage table: ' . $table
            );
        }
    }

    public function test_http_request_events_contains_sequence_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn(
                'nadi_http_request_events',
                'sequence'
            )
        );
    }

    public function test_telemetry_storage_has_all_expected_foreign_keys(): void
    {
        $result = DB::select(
            'SELECT COUNT(*) AS aggregate
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
                AND REFERENCED_TABLE_NAME IS NOT NULL
                AND TABLE_NAME IN (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )',
            [
                'nadi_telemetry_batches',
                'nadi_telemetry_streams',
                'nadi_telemetry_record_keys',
                'nadi_telemetry_records',
                'nadi_http_request_events',
                'nadi_telemetry_gaps',
            ]
        );

        $this->assertSame(
            15,
            (int) $result[0]->aggregate
        );
    }

    public function test_telemetry_storage_tables_use_expected_mysql_collation(): void
    {
        $result = DB::select(
            'SELECT COUNT(*) AS aggregate
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME IN (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
                AND TABLE_COLLATION = ?',
            [
                'nadi_telemetry_batches',
                'nadi_telemetry_streams',
                'nadi_telemetry_record_keys',
                'nadi_telemetry_records',
                'nadi_http_request_events',
                'nadi_telemetry_gaps',
                'utf8mb4_0900_ai_ci',
            ]
        );

        $this->assertSame(
            6,
            (int) $result[0]->aggregate
        );
    }
}
