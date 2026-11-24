<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TelemetryRetentionPolicyController extends Controller
{
    private const POLICIES = [
        'telemetry_records' => [
            'label' => 'Telemetry Records',
            'description' => 'Server and application metrics and telemetry records.',
        ],
        'http_request_events' => [
            'label' => 'HTTP Request Events',
            'description' => 'HTTP request and response metadata events.',
        ],
        'telemetry_record_keys' => [
            'label' => 'Telemetry Record Keys',
            'description' => 'Idempotency keys used to prevent duplicate ingestion.',
        ],
        'telemetry_batches' => [
            'label' => 'Telemetry Batches',
            'description' => 'Ingestion batch acknowledgements and metadata.',
        ],
        'telemetry_gaps' => [
            'label' => 'Telemetry Gaps',
            'description' => 'Resolved telemetry sequence gaps. Unresolved gaps are preserved.',
        ],
        'central_health_snapshots' => [
            'label' => 'Central Health Snapshots',
            'description' => 'Historical snapshots of Central host, services, database, PHP, Laravel, and system health.',
        ],
    ];

    public function index(): View
    {
        $storedPolicies = DB::table('nadi_retention_policies')
            ->whereIn('data_type', array_keys(self::POLICIES))
            ->get()
            ->keyBy('data_type');

        $policies = collect(self::POLICIES)->map(
            function ($definition, $dataType) use ($storedPolicies)
            {
                $stored = $storedPolicies->get($dataType);

                return [
                    'data_type' => $dataType,
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'retention_days' => $stored?->retention_days,
                    'enabled' => (bool) ($stored?->enabled ?? false),
                    'configured' => $stored !== null,
                ];
            }
        );

        return view(
            'management.telemetry-retention',
            compact('policies')
        );
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'policies' => [
                'required',
                'array',
            ],
            'policies.*.retention_days' => [
                'nullable',
                'integer',
                'min:1',
                'max:36500',
            ],
            'policies.*.enabled' => [
                'nullable',
                'boolean',
            ],
        ]);

        $allowedTypes = array_keys(self::POLICIES);
        $submittedTypes = array_keys($validated['policies']);

        if (array_diff($submittedTypes, $allowedTypes))
        {
            abort(422, 'Invalid retention policy type.');
        }

        DB::transaction(function () use ($validated, $request)
        {
            foreach ($validated['policies'] as $dataType => $values)
            {
                $retentionDays = $values['retention_days'] ?? null;

                $existing = DB::table('nadi_retention_policies')
                    ->where('data_type', $dataType)
                    ->lockForUpdate()
                    ->first();

                if ($retentionDays === null || $retentionDays === '')
                {
                    if ($existing)
                    {
                        DB::table('nadi_retention_policies')
                            ->where('data_type', $dataType)
                            ->update([
                                'enabled' => 0,
                                'updated_by' => $request->user()?->getAuthIdentifier(),
                            ]);
                    }

                    continue;
                }

                $enabled = (bool) ($values['enabled'] ?? false);

                $data = [
                    'retention_days' => (int) $retentionDays,
                    'enabled' => $enabled,
                    'description' => self::POLICIES[$dataType]['description'],
                    'updated_by' => $request->user()?->getAuthIdentifier(),
                ];

                if ($existing)
                {
                    DB::table('nadi_retention_policies')
                        ->where('data_type', $dataType)
                        ->update($data);
                }
                else
                {
                    DB::table('nadi_retention_policies')->insert([
                        'id' => (string) Str::uuid(),
                        'data_type' => $dataType,
                        ...$data,
                        'created_at' => now(),
                    ]);
                }
            }
        });

        return redirect()
            ->route('management.telemetry-retention')
            ->with('success', 'Telemetry retention policies updated successfully.');
    }
}
