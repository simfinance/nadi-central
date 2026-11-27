<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class NotificationTemplateController extends Controller
{
    public function index(): View
    {
        $templates = DB::table('nadi_notification_templates')
            ->orderBy('name')
            ->paginate(20);

        return view('management.notification-templates', compact('templates'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                'unique:nadi_notification_templates,name',
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
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        DB::table('nadi_notification_templates')->insert([
            'name' => trim($validated['name']),
            'subject' => trim($validated['subject']),
            'body' => $validated['body'],
            'is_active' => $request->boolean('is_active'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('management.notification-templates')
            ->with('success', 'Email template created successfully.');
    }

    public function update(Request $request, int $templateId): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('nadi_notification_templates', 'name')->ignore($templateId),
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
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        $updated = DB::table('nadi_notification_templates')
            ->where('id', $templateId)
            ->update([
                'name' => trim($validated['name']),
                'subject' => trim($validated['subject']),
                'body' => $validated['body'],
                'is_active' => $request->boolean('is_active'),
                'updated_at' => now(),
            ]);

        if (!$updated && !DB::table('nadi_notification_templates')->where('id', $templateId)->exists())
        {
            abort(404);
        }

        return redirect()
            ->route('management.notification-templates')
            ->with('success', 'Email template updated successfully.');
    }

    public function destroy(int $templateId): RedirectResponse
    {
        $deleted = DB::table('nadi_notification_templates')
            ->where('id', $templateId)
            ->delete();

        if (!$deleted)
        {
            abort(404);
        }

        return redirect()
            ->route('management.notification-templates')
            ->with('success', 'Email template deleted successfully.');
    }
}
