<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TelemetryRetentionCleanupService
{
    private const CHUNK_SIZE = 1000;

    private const POLICIES = [
        'telemetry_records' => [
            'table' => 'nadi_telemetry_records',
            'column' => 'created_at',
        ],
        'http_request_events' => [
            'table' => 'nadi_http_request_events',
            'column' => 'created_at',
        ],
        'telemetry_record_keys' => [
            'table' => 'nadi_telemetry_record_keys',
            'column' => 'last_seen_at',
        ],
        'telemetry_batches' => [
            'table' => 'nadi_telemetry_batches',
            'column' => 'received_at',
        ],
        'telemetry_gaps' => [
            'table' => 'nadi_telemetry_gaps',
            'column' => 'gap_resolved_at',
            'resolved_only' => true,
        ],
        'central_health_snapshots' => [
            'table' => 'nadi_central_health_snapshots',
            'column' => 'sampled_at',
        ],
    ];

    public function run(bool $dryRun = false): array
    {
        $results = [];

        foreach (self::POLICIES as $dataType => $config)
        {
            $policy = DB::table('nadi_retention_policies')
                ->where('data_type', $dataType)
                ->where('enabled', true)
                ->first();

            if (!$policy)
            {
                $results[$dataType] = [
                    'status' => 'skipped',
                    'eligible' => 0,
                    'deleted' => 0,
                ];

                continue;
            }

            $retentionDays = filter_var(
                $policy->retention_days,
                FILTER_VALIDATE_INT
            );

            if ($retentionDays === false || $retentionDays < 1)
            {
                $results[$dataType] = [
                    'status' => 'invalid_policy',
                    'eligible' => 0,
                    'deleted' => 0,
                ];

                Log::warning('NADI telemetry retention skipped due to an invalid policy.', [
                    'data_type' => $dataType,
                    'retention_days' => $policy->retention_days,
                ]);

                continue;
            }

            $cutoff = now('UTC')->subDays($retentionDays);

            $table = $config['table'];

            $query = DB::table($table)
                ->where($config['column'], '<', $cutoff);

            if ($dataType === 'telemetry_record_keys')
            {
                $query
                    ->whereNotExists(function ($subquery)
                    {
                        $subquery
                            ->selectRaw('1')
                            ->from('nadi_telemetry_records')
                            ->whereColumn(
                                'nadi_telemetry_records.stream_id',
                                'nadi_telemetry_record_keys.stream_id'
                            )
                            ->whereColumn(
                                'nadi_telemetry_records.sequence',
                                'nadi_telemetry_record_keys.sequence'
                            );
                    })
                    ->whereNotExists(function ($subquery)
                    {
                        $subquery
                            ->selectRaw('1')
                            ->from('nadi_http_request_events')
                            ->whereColumn(
                                'nadi_http_request_events.stream_id',
                                'nadi_telemetry_record_keys.stream_id'
                            )
                            ->whereColumn(
                                'nadi_http_request_events.sequence',
                                'nadi_telemetry_record_keys.sequence'
                            );
                    });
            }

            if (!empty($config['resolved_only']))
            {
                $query->whereNotNull('gap_resolved_at');
            }

            if ($dataType === 'telemetry_batches')
            {
                $query
                    ->whereNotExists(function ($subquery)
                    {
                        $subquery
                            ->selectRaw('1')
                            ->from('nadi_telemetry_records')
                            ->whereColumn(
                                'nadi_telemetry_records.batch_id',
                                'nadi_telemetry_batches.id'
                            );
                    })
                    ->whereNotExists(function ($subquery)
                    {
                        $subquery
                            ->selectRaw('1')
                            ->from('nadi_http_request_events')
                            ->whereColumn(
                                'nadi_http_request_events.batch_id',
                                'nadi_telemetry_batches.id'
                            );
                    });
            }

            $eligibleCount = (clone $query)->count();

            $deletedCount = $dryRun
                ? 0
                : $this->deleteInChunks($query, $table);

            $results[$dataType] = [
                'status' => $dryRun ? 'dry_run' : 'completed',
                'eligible' => $eligibleCount,
                'deleted' => $deletedCount,
                'retention_days' => $retentionDays,
            ];

            Log::info('NADI telemetry retention processed.', [
                'data_type' => $dataType,
                'dry_run' => $dryRun,
                'eligible' => $eligibleCount,
                'deleted' => $deletedCount,
                'retention_days' => $retentionDays,
            ]);
        }

        return $results;
    }

    private function deleteInChunks($query, string $table): int
    {
        $totalDeleted = 0;

        do
        {
            $ids = (clone $query)
                ->orderBy('id')
                ->limit(self::CHUNK_SIZE)
                ->pluck('id');

            if ($ids->isEmpty())
            {
                break;
            }

            $deleted = DB::table($table)
                ->whereIn('id', $ids)
                ->delete();

            $totalDeleted += $deleted;
        }
        while ($ids->count() === self::CHUNK_SIZE);

        return $totalDeleted;
    }
}
