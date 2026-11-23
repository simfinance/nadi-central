<?php

namespace App\Http\Controllers\Api\V1\Nadi;

use App\Http\Controllers\Controller;
use App\Services\Telemetry\TelemetryIngestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class AgentTelemetryController extends Controller
{
    public function __construct(
        protected TelemetryIngestionService $ingestion
    )
    {
    }

    public function store(Request $request)
    {
        $agent = $request->attributes->get('nadi_agent');

        if (!$agent)
        {
            return response()->json([
                'success' => false,
                'message' => 'Agent authentication required.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'protocol_version' => [
                    'required',
                    'string',
                    'max:30',
                ],

                'batch_id' => [
                    'required',
                    'uuid',
                ],

                'agent_instance_id' => [
                    'required',
                    'uuid',
                ],

                'sent_at' => [
                    'required',
                    'date',
                ],

                'records' => [
                    'required',
                    'array',
                    'min:1',
                    'max:500',
                ],

                'records.*.record_id' => [
                    'required',
                    'uuid',
                ],

                'records.*.stream' => [
                    'required',
                    'string',
                    Rule::in([
                        'server_metrics',
                        'nginx_metrics',
                        'nginx_vhost_metrics',
                        'nginx_access_logs',
                        'nginx_error_logs',
                        'application_metrics',
                        'application_logs',
                        'application_events',
                        'nginx_http_requests',
                        'application_http_requests',
                        'application_http_errors',
                    ]),
                ],

                'records.*.sequence' => [
                    'required',
                    'integer',
                    'min:1',
                ],

                'records.*.observed_at' => [
                    'required',
                    'date',
                ],

                'records.*.collected_at' => [
                    'required',
                    'date',
                ],

                'records.*.application_id' => [
                    'nullable',
                    'uuid',
                ],

                'records.*.metric_set' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'records.*.data' => [
                    'required',
                    'array',
                ],
            ]
        );

        if ($validator->fails())
        {
            return response()->json([
                'success' => false,
                'message' => 'Telemetry payload validation failed.',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try
        {
            $ack = $this->ingestion->ingest(
                $validator->validated(),
                $agent
            );

            return response()->json([
                'success' => true,
                'data' => $ack,
            ], Response::HTTP_OK);
        }
        catch (HttpException $exception)
        {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], $exception->getStatusCode());
        }
        catch (Throwable $exception)
        {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Telemetry ingestion failed.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
