<?php

namespace App\Services\Nadi;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TelemetryRetentionService
{
    private const CHUNK_SIZE = 500;

    private const POLICIES = [
        'telemetry_records' => [
            'table' => 'nadi_telemetry_records',
            'column' => 'observed_at',
        ],
        'http_request_events' => [
            'table' => 'nadi_http_request_events',
            'column' => 'observed_at',
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
        ],
    ];

    public function prune(): array
    {
        $deleted = [
            'telemetry_records' => 0,
            'http_request_events' => 0,
            'telemetry_record_keys' => 0,
            'telemetry_batches' => 0,
            'telemetry_gaps' => 0,
        ];

        $policies = $this->getEnabledPolicies();

        if ($policies->isEmpty())
        {
            return [
                'status' => 'skipped',
                'message' => 'No enabled retention policies found.',
                'deleted' => $deleted,
            ];
        }

        /*
         * Delete child records before parent batches.
         */

        foreach ([
            'telemetry_records',
            'http_request_events',
            'telemetry_record_keys',
        ] as $dataType)
        {
            if (!$policies->has($dataType))
            {
                continue;
            }

            $deleted[$dataType] = $this->deleteExpiredRows(
                $dataType,
                $policies->get($dataType)
            );
        }

        /*
         * Delete batches only when no dependent records remain.
         */

        if ($policies->has('telemetry_batches'))
        {
            $deleted['telemetry_batches'] = $this->deleteExpiredBatches(
                $policies->get('telemetry_batches')
            );
        }

        /*
         * Only resolved gaps are eligible for deletion.
         * Unresolved gaps are always preserved.
         */

        if ($policies->has('telemetry_gaps'))
        {
            $deleted['telemetry_gaps'] = $this->deleteExpiredRows(
                'telemetry_gaps',
                $policies->get('telemetry_gaps')
            );
        }

        return [
            'status' => 'completed',
            'message' => 'Telemetry retention cleanup completed.',
            'deleted' => $deleted,
        ];
    }

    private function getEnabledPolicies(): Collection
    {
        $rows = DB::table('nadi_retention_policies')
            ->where('enabled', 1)
            ->whereIn(
                'data_type',
                array_keys(self::POLICIES)
            )
            ->get();

        $policies = collect();

        foreach ($rows as $row)
        {
            if (!isset(self::POLICIES[$row->data_type]))
            {
                continue;
            }

            $retentionDays = (int) $row->retention_days;

            if ($retentionDays < 1)
            {
                continue;
            }

            $policies->put(
                $row->data_type,
                [
                    'retention_days' => $retentionDays,
                    'cutoff' => Carbon::now()
                        ->subDays($retentionDays),
                ]
            );
        }

        return $policies;
    }

    private function deleteExpiredRows(
        string $dataType,
        array $policy
    ): int
    {
        $definition = self::POLICIES[$dataType];

        $query = DB::table($definition['table'])
            ->where(
                $definition['column'],
                '<',
                $policy['cutoff']
            );

        if ($dataType === 'telemetry_gaps')
        {
            $query->whereNotNull('gap_resolved_at');
        }

        return $this->deleteInChunks($query);
    }

    private function deleteExpiredBatches(
        array $policy
    ): int
    {
        $query = DB::table('nadi_telemetry_batches')
            ->where(
                'received_at',
                '<',
                $policy['cutoff']
            )
            ->whereNotExists(function ($subQuery)
            {
                $subQuery->selectRaw('1')
                    ->from('nadi_telemetry_records')
                    ->whereColumn(
                        'nadi_telemetry_records.batch_id',
                        'nadi_telemetry_batches.id'
                    );
            })
            ->whereNotExists(function ($subQuery)
            {
                $subQuery->selectRaw('1')
                    ->from('nadi_http_request_events')
                    ->whereColumn(
                        'nadi_http_request_events.batch_id',
                        'nadi_telemetry_batches.id'
                    );
            });

        return $this->deleteInChunks($query);
    }

    private function deleteInChunks(
        string $table,
        $query
    ): int
    {
        $deleted = 0;

        $query->chunkById(
            self::CHUNK_SIZE,
            function (Collection $rows) use (&$deleted, $table)
            {
                $ids = $rows->pluck('id');

                if ($ids->isEmpty())
                {
                    return;
                }

                $deleted += DB::table($table)
                    ->whereIn('id', $ids)
                    ->delete();
            },
            'id'
        );

        return $deleted;
    }
}