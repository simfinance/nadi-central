<?php

namespace App\\Http\\Controllers\\Api\\V1\\Nadi;

use App\\Http\\Controllers\\Controller;
use Illuminate\\Http\\JsonResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Carbon;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Validator;
use Illuminate\\Support\\Str;
use Symfony\\Component\\HttpFoundation\\Response;
use Throwable;

class AlertmanagerWebhookController extends Controller
{
    private const MAX_PAYLOAD_BYTES = 1048576;

    private const MAX_ALERTS_PER_GROUP = 200;

    public function __invoke(Request $request): JsonResponse
    {
        $expectedToken = (string) config(
            'nadi.alertmanager.webhook_token',
            ''
        );

        if ($expectedToken === '')
        {
            return response()->json([
                'success' => false,
                'message' => 'Alertmanager webhook is not configured.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $providedToken = (string) $request->bearerToken();

        if (
            $providedToken === ''
            || !hash_equals($expectedToken, $providedToken)
        )
        {
            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook credential.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!$request->isJson())
        {
            return response()->json([
                'success' => false,
                'message' => 'Content-Type must be application/json.',
            ], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        $rawPayload = $request->getContent();

        if (strlen($rawPayload) > self::MAX_PAYLOAD_BYTES)
        {
            return response()->json([
                'success' => false,
                'message' => 'Alertmanager payload exceeds the maximum size.',
            ], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $payload = $request->json()->all();

        $validator = Validator::make(
            $payload,
            [
                'version' => [
                    'required',
                    'string',
                    'max:20',
                ],
                'groupKey' => [
                    'required',
                    'string',
                    'max:512',
                ],
                'status' => [
                    'required',
                    'string',
                    'in:firing,resolved',
                ],
                'receiver' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'groupLabels' => [
                    'nullable',
                    'array',
                ],
                'groupLabels.*' => [
                    'string',
                    'max:2048',
                ],
                'commonLabels' => [
                    'nullable',
                    'array',
                ],
                'commonLabels.*' => [
                    'string',
                    'max:2048',
                ],
                'commonAnnotations' => [
                    'nullable',
                    'array',
                ],
                'commonAnnotations.*' => [
                    'string',
                    'max:8192',
                ],
                'externalURL' => [
                    'nullable',
                    'url',
                    'max:2048',
                ],
                'alerts' => [
                    'required',
                    'array',
                    'min:1',
                    'max:' . self::MAX_ALERTS_PER_GROUP,
                ],
                'alerts.*.status' => [
                    'required',
                    'string',
                    'in:firing,resolved',
                ],
                'alerts.*.labels' => [
                    'required',
                    'array',
                ],
                'alerts.*.labels.alertname' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'alerts.*.labels.*' => [
                    'string',
                    'max:2048',
                ],
                'alerts.*.annotations' => [
                    'nullable',
                    'array',
                ],
                'alerts.*.annotations.*' => [
                    'string',
                    'max:8192',
                ],
                'alerts.*.startsAt' => [
                    'required',
                    'date',
                ],
                'alerts.*.endsAt' => [
                    'required',
                    'date',
                ],
                'alerts.*.generatorURL' => [
                    'nullable',
                    'url',
                    'max:2048',
                ],
                'alerts.*.fingerprint' => [
                    'required',
                    'string',
                    'max:128',
                ],
            ]
        );

        if ($validator->fails())
        {
            return response()->json([
                'success' => false,
                'message' => 'Alertmanager payload validation failed.',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $validator->validated();

        try
        {
            $result = DB::transaction(function () use ($validated)
            {
                $receivedAt = now('UTC');
                $acceptedCount = 0;
                $duplicateCount = 0;

                foreach ($validated['alerts'] as $alert)
                {
                    $labels = $alert['labels'];
                    $annotations = $alert['annotations'] ?? [];
                    $startsAt = $this->normalizeTimestamp($alert['startsAt']);
                    $endsAt = $this->normalizeTimestamp($alert['endsAt']);

                    $eventMaterial = [
                        'fingerprint' => $alert['fingerprint'],
                        'status' => $alert['status'],
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'labels' => $labels,
                        'annotations' => $annotations,
                    ];

                    $eventKey = hash(
                        'sha256',
                        json_encode(
                            $eventMaterial,
                            JSON_UNESCAPED_SLASHES
                                | JSON_UNESCAPED_UNICODE
                                | JSON_THROW_ON_ERROR
                        )
                    );

                    $eventInserted = DB::table('nadi_alert_events')
                        ->insertOrIgnore([
                            'id' => (string) Str::uuid(),
                            'event_key' => $eventKey,
                            'fingerprint' => $alert['fingerprint'],
                            'status' => $alert['status'],
                            'alertname' => $labels['alertname'],
                            'severity' => $labels['severity'] ?? null,
                            'summary' => $annotations['summary'] ?? null,
                            'description' => $annotations['description'] ?? null,
                            'labels' => json_encode(
                                $labels,
                                JSON_UNESCAPED_SLASHES
                                    | JSON_UNESCAPED_UNICODE
                                    | JSON_THROW_ON_ERROR
                            ),
                            'annotations' => json_encode(
                                $annotations,
                                JSON_UNESCAPED_SLASHES
                                    | JSON_UNESCAPED_UNICODE
                                    | JSON_THROW_ON_ERROR
                            ),
                            'group_key' => $validated['groupKey'],
                            'receiver' => $validated['receiver'],
                            'generator_url' => $alert['generatorURL'] ?? null,
                            'external_url' => $validated['externalURL'] ?? null,
                            'starts_at' => $startsAt,
                            'ends_at' => $endsAt,
                            'received_at' => $receivedAt->format('Y-m-d H:i:s.u'),
                            'created_at' => $receivedAt->format('Y-m-d H:i:s.u'),
                        ]);

                    if ($eventInserted === 0)
                    {
                        $duplicateCount++;

                        continue;
                    }

                    $currentAlert = [
                        'status' => $alert['status'],
                        'alertname' => $labels['alertname'],
                        'severity' => $labels['severity'] ?? null,
                        'summary' => $annotations['summary'] ?? null,
                        'description' => $annotations['description'] ?? null,
                        'labels' => json_encode(
                            $labels,
                            JSON_UNESCAPED_SLASHES
                                | JSON_UNESCAPED_UNICODE
                                | JSON_THROW_ON_ERROR
                        ),
                        'annotations' => json_encode(
                            $annotations,
                            JSON_UNESCAPED_SLASHES
                                | JSON_UNESCAPED_UNICODE
                                | JSON_THROW_ON_ERROR
                        ),
                        'group_key' => $validated['groupKey'],
                        'receiver' => $validated['receiver'],
                        'generator_url' => $alert['generatorURL'] ?? null,
                        'external_url' => $validated['externalURL'] ?? null,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'last_received_at' => $receivedAt->format('Y-m-d H:i:s.u'),
                        'updated_at' => $receivedAt->format('Y-m-d H:i:s.u'),
                    ];

                    DB::table('nadi_alerts')->insertOrIgnore([
                        'id' => (string) Str::uuid(),
                        'fingerprint' => $alert['fingerprint'],
                        ...$currentAlert,
                        'created_at' => $receivedAt->format('Y-m-d H:i:s.u'),
                    ]);

                    DB::table('nadi_alerts')
                        ->where('fingerprint', $alert['fingerprint'])
                        ->update($currentAlert);

                    $acceptedCount++;
                }

                return [
                    'accepted_count' => $acceptedCount,
                    'duplicate_count' => $duplicateCount,
                    'received_count' => count($validated['alerts']),
                    'received_at' => $receivedAt->toIso8601String(),
                ];
            }, 3);

            return response()->json([
                'success' => true,
                'data' => $result,
            ], Response::HTTP_OK);
        }
        catch (Throwable $exception)
        {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Alertmanager webhook processing failed.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function normalizeTimestamp(string $value): ?string
    {
        $timestamp = Carbon::parse($value)->utc();

        if ((int) $timestamp->format('Y') < 1000)
        {
            return null;
        }

        return $timestamp->format('Y-m-d H:i:s.u');
    }
}
