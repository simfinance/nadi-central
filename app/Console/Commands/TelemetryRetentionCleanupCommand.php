<?php

namespace App\Console\Commands;

use App\Services\TelemetryRetentionCleanupService;
use Illuminate\Console\Command;

class TelemetryRetentionCleanupCommand extends Command
{
    protected $signature = 'nadi:telemetry-retention-cleanup
                            {--dry-run : Preview eligible records without deleting them}';

    protected $description = 'Clean up expired NADI telemetry data according to retention policies.';

    public function handle(
        TelemetryRetentionCleanupService $service
    ): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info(
            $dryRun
                ? 'NADI telemetry retention: DRY RUN'
                : 'NADI telemetry retention: EXECUTION'
        );

        try
        {
            $results = $service->run($dryRun);

            foreach ($results as $dataType => $result)
            {
                $this->line(sprintf(
                    '%s | %s | Eligible: %d | Deleted: %d',
                    $dataType,
                    $result['status'],
                    $result['eligible'] ?? 0,
                    $result['deleted'] ?? 0
                ));
            }

            $this->info('Telemetry retention cleanup finished.');

            return self::SUCCESS;
        }
        catch (\Throwable $exception)
        {
            report($exception);

            $this->error(
                'Telemetry retention cleanup failed: '
                . $exception->getMessage()
            );

            return self::FAILURE;
        }
    }
}
