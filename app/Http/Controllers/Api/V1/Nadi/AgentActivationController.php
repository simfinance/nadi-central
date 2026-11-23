<?php

namespace App\Http\Controllers\Api\V1\Nadi;

use App\Http\Controllers\Controller;
use App\Services\Activation\ActivationAcknowledgementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

class AgentActivationController extends Controller
{
    public function acknowledge(
        Request $request,
        string $grantId,
        ActivationAcknowledgementService $acknowledgementService
    ) {
        $agent = $request->attributes->get('nadi_agent');

        if (!$agent)
        {
            return response()->json([
                'success' => false,
                'message' => 'Agent authentication required.',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'request_id' => [
                'required',
                'uuid',
            ],
            'application_id' => [
                'required',
                'uuid',
            ],
            'result' => [
                'required',
                'in:success,failed',
            ],
            'error_code' => [
                'nullable',
                'string',
                'max:100',
            ],
            'message' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        if ($validator->fails())
        {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        if (
            $validated['result'] === 'failed'
            && empty($validated['error_code'])
        )
        {
            return response()->json([
                'success' => false,
                'message' => 'error_code is required when activation fails.',
            ], 422);
        }

        try
        {
            $result = $acknowledgementService->acknowledge(
                $agent->id,
                $validated['request_id'],
                $validated['application_id'],
                $grantId,
                $validated['result'],
                $validated['error_code'] ?? null,
                $validated['message'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        }
        catch (RuntimeException $exception)
        {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 409);
        }
        catch (Throwable $exception)
        {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Activation acknowledgement failed.',
            ], 500);
        }
    }
}
