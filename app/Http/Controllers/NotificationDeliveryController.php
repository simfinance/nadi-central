<?php

namespace App\Http\Controllers;

use App\Services\Notification\GmailNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NotificationDeliveryController extends Controller
{
    public function index(): View
    {
        $deliveries = DB::table('nadi_notification_deliveries')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('management.notifications', compact('deliveries'));
    }

    public function store(
        Request $request,
        GmailNotificationService $mailService
    ): RedirectResponse
    {
        $validated = $request->validate([
            'recipient' => [
                'required',
                'email',
                'max:255',
            ],
            'subject' => [
                'required',
                'string',
                'max:255',
            ],
            'body' => [
                'required',
                'string',
                'max:20000',
            ],
        ]);

        try
        {
            $mailService->queue(
                $validated['recipient'],
                $validated['subject'],
                $validated['body']
            );
        }
        catch (\RuntimeException $exception)
        {
            return back()
                ->withInput()
                ->withErrors([
                    'notification' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route('management.notifications')
            ->with('success', 'Notification added to the delivery queue.');
    }
}
