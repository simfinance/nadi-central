<?php

namespace App\Jobs;

use App\Services\Notification\GmailNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class DeliverNadiNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly string $deliveryId
    )
    {
        $this->onQueue('notifications');
    }

    public function handle(GmailNotificationService $mailService): void
    {
        $delivery = DB::table('nadi_notification_deliveries')
            ->where('id', $this->deliveryId)
            ->first();

        if (!$delivery || $delivery->status === 'sent')
        {
            return;
        }

        DB::table('nadi_notification_deliveries')
            ->where('id', $this->deliveryId)
            ->update([
                'status' => 'sending',
                'attempts' => DB::raw('attempts + 1'),
                'last_error' => null,
                'updated_at' => now(),
            ]);

        try
        {
            $mailService->sendQueuedDelivery($this->deliveryId);

            DB::table('nadi_notification_deliveries')
                ->where('id', $this->deliveryId)
                ->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'last_error' => null,
                    'updated_at' => now(),
                ]);
        }
        catch (Throwable $exception)
        {
            DB::table('nadi_notification_deliveries')
                ->where('id', $this->deliveryId)
                ->update([
                    'status' => 'queued',
                    'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                    'updated_at' => now(),
                ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        DB::table('nadi_notification_deliveries')
            ->where('id', $this->deliveryId)
            ->where('status', '!=', 'sent')
            ->update([
                'status' => 'failed',
                'last_error' => $exception
                    ? mb_substr($exception->getMessage(), 0, 2000)
                    : 'Notification delivery failed after all retry attempts.',
                'updated_at' => now(),
            ]);
    }
}
