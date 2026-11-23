<?php

namespace App\Http\Controllers;

use App\Services\Notification\GmailNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class NotificationMailSettingsController extends Controller
{
    public function __construct(
        private readonly GmailNotificationService $mailService
    )
    {
    }

    public function index(): View
    {
        $settings = $this->mailService->settings();

        return view('management.notification-mail', [
            'settings' => $settings,
            'hasPassword' => !empty($settings?->smtp_password),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'smtp_username' => [
                'required',
                'email',
                'max:255',
            ],
            'smtp_password' => [
                'nullable',
                'string',
                'min:16',
                'max:255',
            ],
            'from_name' => [
                'required',
                'string',
                'max:150',
            ],
            'enabled' => [
                'nullable',
                'boolean',
            ],
        ]);

        $existing = $this->mailService->settings();
        $password = $validated['smtp_password'] ?? null;
        $accountChanged = $existing
            && strtolower((string) $existing->smtp_username) !== strtolower($validated['smtp_username']);

        if ((!$existing || $accountChanged) && (!$password || trim($password) === ''))
        {
            return back()
                ->withInput($request->except('smtp_password'))
                ->withErrors([
                    'smtp_password' => 'A Gmail App Password is required when configuring or changing the Gmail account.',
                ]);
        }

        $this->mailService->saveSettings(
            $validated['smtp_username'],
            $password,
            $validated['from_name'],
            $request->boolean('enabled')
        );

        return redirect()
            ->route('management.notification-mail')
            ->with('success', 'Gmail notification settings saved.');
    }

    public function sendTest(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'test_recipient' => [
                'required',
                'email',
                'max:255',
            ],
        ]);

        try
        {
            $this->mailService->sendTestMessage($validated['test_recipient']);
        }
        catch (Throwable $exception)
        {
            return back()
                ->withInput($request->only('test_recipient'))
                ->withErrors([
                    'test_recipient' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route('management.notification-mail')
            ->with('success', 'Test email sent successfully.');
    }
}
