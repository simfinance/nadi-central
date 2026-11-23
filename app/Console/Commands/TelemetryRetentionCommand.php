<?php

namespace App\Console\Commands;

use App\Services\Nadi\TelemetryRetentionService;
use Illuminate\Console\Command;
use Throwable;

class TelemetryRetentionCommand extends Command
{
    protected $signature = 'nadi:telemetry:prune';

    protected $description = 'Remove expired NADI telemetry data according to retention policies.';

    public function handle(
        TelemetryRetentionService $service
    ): int
    {
        try
        {
            $result = $service->prune();

            if ($result['status'] === 'skipped')
            {
                $this->info($result['message']);

                return self::SUCCESS;
            }

            $this->info($result['message']);

            foreach ($result['deleted'] as $dataType => $count)
            {
                $this->line(
                    $dataType . ': ' . $count . ' rows deleted'
                );
            }

            return self::SUCCESS;
        }
        catch (Throwable $exception)
        {
            report($exception);

            $this->error(
                'Telemetry retention cleanup failed.'
            );

            return self::FAILURE;
        }
    }
}