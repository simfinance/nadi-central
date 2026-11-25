<?php

namespace App\Http\Controllers;

use App\Services\PrometheusQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class PrometheusQueryController extends Controller
{
    public function __construct(
        private readonly PrometheusQueryService $prometheus
    ) {
    }

    public function overview(): JsonResponse
    {
        try
        {
            $targets = $this->prometheus->targets();
            $exporter = $this->prometheus->instantQuery('exporter_up');

            $targetsUp = count(array_filter(
                $targets,
                static fn (array $target): bool => $target['health'] === 'up'
            ));

            $exporterUp = collect($exporter['result'] ?? [])
                ->contains(
                    static fn (array $sample): bool => (float) (
                        $sample['value'][1] ?? 0
                    ) === 1.0
                );

            return response()->json([
                'status' => 'ok',
                'prometheus' => 'reachable',
                'exporter' => $exporterUp ? 'up' : 'down',
                'target_count' => count($targets),
                'targets_up' => $targetsUp,
                'targets_down' => count($targets) - $targetsUp,
                'targets' => $targets,
            ]);
        }
        catch (Throwable $exception)
        {
            Log::warning(
                'NADI Prometheus overview request failed.',
                ['exception' => $exception::class]
            );

            return response()->json([
                'status' => 'unavailable',
                'prometheus' => 'unavailable',
                'message' => 'Prometheus is unavailable or returned an invalid response.',
            ], 503);
        }
    }

    public function metrics(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'metrics' => $this->prometheus->metrics(),
        ]);
    }

    public function query(
        Request $request,
        string $metric
    ): JsonResponse {
        $validator = Validator::make(
            $request->query(),
            [
                'server_id' => ['sometimes', 'uuid'],
                'agent_id' => ['sometimes', 'uuid'],
                'application_id' => ['sometimes', 'uuid'],
                'start' => ['required_with:end', 'date'],
                'end' => ['required_with:start', 'date'],
                'at' => ['sometimes', 'date'],
                'step' => ['sometimes', 'integer', 'between:15,300'],
            ]
        );

        if ($validator->fails())
        {
            return response()->json([
                'status' => 'invalid_request',
                'errors' => $validator->errors(),
            ], 422);
        }

        $filters = $request->only([
            'server_id',
            'agent_id',
            'application_id',
        ]);

        try
        {
            if ($request->filled('start') && $request->filled('end'))
            {
                $start = CarbonImmutable::parse(
                    $request->query('start')
                )->utc();

                $end = CarbonImmutable::parse(
                    $request->query('end')
                )->utc();

                $maximumHours = max(
                    1,
                    (int) config('nadi.prometheus.max_range_hours', 168)
                );

                $maximumPoints = max(
                    100,
                    (int) config('nadi.prometheus.max_data_points', 11000)
                );

                if (
                    $end->lessThanOrEqualTo($start)
                    || $end->greaterThan(CarbonImmutable::now('UTC')->addMinute())
                    || $start->diffInSeconds($end) > $maximumHours * 3600
                )
                {
                    return response()->json([
                        'status' => 'invalid_request',
                        'message' => 'The requested time range is outside the allowed bounds.',
                    ], 422);
                }

                $step = (int) $request->query('step', 30);
                $pointCount = (int) ceil(
                    $start->diffInSeconds($end) / $step
                );

                if ($pointCount > $maximumPoints)
                {
                    return response()->json([
                        'status' => 'invalid_request',
                        'message' => 'The requested range produces too many data points. Increase the step interval.',
                    ], 422);
                }

                $data = $this->prometheus->rangeQuery(
                    $metric,
                    $filters,
                    $start,
                    $end,
                    $step
                );

                return response()->json([
                    'status' => 'ok',
                    'mode' => 'range',
                    'metric' => $metric,
                    'start' => $start->toIso8601String(),
                    'end' => $end->toIso8601String(),
                    'step_seconds' => $step,
                    'result_type' => $data['resultType'] ?? null,
                    'result' => $data['result'] ?? [],
                ]);
            }

            $at = $request->filled('at')
                ? CarbonImmutable::parse($request->query('at'))->utc()
                : null;

            if (
                $at !== null
                && $at->greaterThan(CarbonImmutable::now('UTC')->addMinute())
            )
            {
                return response()->json([
                    'status' => 'invalid_request',
                    'message' => 'The requested instant cannot be in the future.',
                ], 422);
            }

            $data = $this->prometheus->instantQuery(
                $metric,
                $filters,
                $at
            );

            return response()->json([
                'status' => 'ok',
                'mode' => 'instant',
                'metric' => $metric,
                'at' => $at?->toIso8601String(),
                'result_type' => $data['resultType'] ?? null,
                'result' => $data['result'] ?? [],
            ]);
        }
        catch (Throwable $exception)
        {
            $status = str_contains(
                $exception->getMessage(),
                'query catalog'
            ) ? 404 : 503;

            Log::warning(
                'NADI Prometheus metric query failed.',
                [
                    'metric' => $metric,
                    'exception' => $exception::class,
                ]
            );

            return response()->json([
                'status' => $status === 404
                    ? 'unknown_metric'
                    : 'unavailable',
                'message' => $status === 404
                    ? 'The requested metric is not in the NADI query catalog.'
                    : 'Prometheus is unavailable or could not process the query.',
            ], $status);
        }
    }
}
