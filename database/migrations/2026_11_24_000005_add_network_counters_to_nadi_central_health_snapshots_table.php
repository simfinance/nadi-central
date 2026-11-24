<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nadi_central_health_snapshots', function (Blueprint $table) {
            $table->unsignedBigInteger('network_rx_bytes')->nullable()->after('info_count');
            $table->unsignedBigInteger('network_tx_bytes')->nullable()->after('network_rx_bytes');
        });

        DB::table('nadi_central_health_snapshots')
            ->select(['id', 'snapshot_payload'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row)
                {
                    $snapshot = json_decode($row->snapshot_payload, true);

                    if (! is_array($snapshot))
                    {
                        continue;
                    }

                    $totals = $snapshot['network']['totals'] ?? [];

                    DB::table('nadi_central_health_snapshots')
                        ->where('id', $row->id)
                        ->update([
                            'network_rx_bytes' => isset($totals['rx_bytes'])
                                ? (int) $totals['rx_bytes']
                                : null,
                            'network_tx_bytes' => isset($totals['tx_bytes'])
                                ? (int) $totals['tx_bytes']
                                : null,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('nadi_central_health_snapshots', function (Blueprint $table) {
            $table->dropColumn([
                'network_rx_bytes',
                'network_tx_bytes',
            ]);
        });
    }
};
