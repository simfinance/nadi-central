<?php

namespace App\Http\Controllers;

use App\Services\Notification\GmailNotificationService;
use App\Services\Notification\NotificationTemplateRenderer;
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

        $templates = DB::table('nadi_notification_templates')
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'subject',
                'body',
            ]);

        return view('management.notifications', compact('deliveries', 'templates'));
    }

    public function store(
        Request $request,
        GmailNotificationService $mailService,
        NotificationTemplateRenderer $templateRenderer
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
            'placeholders' => [
                'nullable',
                'array',
            ],
            'placeholders.application_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'placeholders.server_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'placeholders.severity' => [
                'nullable',
                'string',
                'max:100',
            ],
            'placeholders.event_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'placeholders.event_time' => [
                'nullable',
                'string',
                'max:100',
            ],
            'placeholders.message' => [
                'nullable',
                'string',
                'max:10000',
            ],
        ]);

        $rendered = $templateRenderer->render(
            $validated['subject'],
            $validated['body'],
            $validated['recipient'],
            $validated['placeholders'] ?? []
        );

        try
        {
            $mailService->queue(
                $validated['recipient'],
                $rendered['subject'],
                $rendered['body']
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
