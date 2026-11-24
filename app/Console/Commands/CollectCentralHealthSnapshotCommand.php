<?php

namespace App\Console\Commands;

use App\Http\Controllers\CentralHealthController;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class CollectCentralHealthSnapshotCommand extends Command
{
    protected $signature = 'nadi:central-health-collect';

    protected $description = 'Collect and persist a Central Health snapshot.';

    public function handle(CentralHealthController $controller): int
    {
        try
        {
            $snapshot = $controller->collectSnapshot();
            $overall = $snapshot['overall'] ?? [];

            DB::table('nadi_central_health_snapshots')->insert([
                'sampled_at' => Carbon::parse($snapshot['sampled_at'])->utc(),
                'overall_status' => $overall['status'] ?? 'unknown',
                'overall_score' => $overall['score'] ?? null,
                'critical_count' => $overall['critical'] ?? 0,
                'warning_count' => $overall['warning'] ?? 0,
                'info_count' => $overall['info'] ?? 0,
                'network_rx_bytes' => $snapshot['network']['totals']['rx_bytes'] ?? null,
                'network_tx_bytes' => $snapshot['network']['totals']['tx_bytes'] ?? null,
                'snapshot_payload' => json_encode(
                    $snapshot,
                    JSON_THROW_ON_ERROR
                ),
                'created_at' => now('UTC'),
            ]);

            $this->info(
                'Central Health snapshot stored at '
                . $snapshot['sampled_at']
            );

            return self::SUCCESS;
        }
        catch (Throwable $exception)
        {
            report($exception);

            $this->error(
                'Central Health snapshot collection failed: '
                . $exception->getMessage()
            );

            return self::FAILURE;
        }
    }
}
