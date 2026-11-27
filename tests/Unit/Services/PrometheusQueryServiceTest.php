<?php

namespace Tests\Unit\Services;

use App\Services\PrometheusQueryService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class PrometheusQueryServiceTest extends TestCase
{
    public function test_database_probe_metrics_are_available_in_query_catalog(): void
    {
        $metrics = app(PrometheusQueryService::class)->metrics();
        $keys = array_column($metrics, 'key');

        foreach ([
            'database_connection_status',
            'database_connection_duration',
            'database_last_success',
            'database_probe_failures',
            'database_query_success',
        ] as $key)
        {
            $this->assertContains($key, $keys);
        }
    }

    public function test_database_metric_queries_are_gated_by_stream_freshness(): void
    {
        Http::fake([
            'https://prometheus.test/api/v1/query*' => Http::response([
                'status' => 'success',
                'data' => [
                    'resultType' => 'vector',
                    'result' => [],
                ],
            ]),
        ]);

        config([
            'nadi.prometheus.base_url' => 'https://prometheus.test',
            'nadi.prometheus.cache_seconds' => 1,
        ]);

        $agentId = '00000000-0000-4000-8000-000000000001';
        $applicationId = '00000000-0000-4000-8000-000000000002';

        app(PrometheusQueryService::class)->instantQuery(
            'database_connection_status',
            [
                'agent_id' => $agentId,
                'application_id' => $applicationId,
            ]
        );

        Http::assertSent(function ($request) use (
            $agentId,
            $applicationId
        ): bool {
            $expectedQuery = '(laravel_db_connection_status{agent_id="'
                . $agentId
                . '",application_id="'
                . $applicationId
                . '"}) and on(agent_id,application_id) '
                . '(nadi_telemetry_stream_up{stream="database_metrics"} == 1)';

            return str_starts_with(
                    $request->url(),
                    'https://prometheus.test/api/v1/query?'
                )
                && $request['query'] === $expectedQuery;
        });
    }

    public function test_database_metric_query_rejects_invalid_identity_filters(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'A Prometheus identity filter is invalid.'
        );

        app(PrometheusQueryService::class)->instantQuery(
            'database_connection_status',
            [
                'application_id' => 'not-a-uuid',
            ]
        );
    }
}
