@extends('layouts.app')

@section('title', 'Email Templates - NADI Console')

@section('breadcrumb', 'Management / Email Templates')

@section('content')
<div class="template-page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Email Templates</h1>
            <p class="page-description">Create and maintain reusable email subjects and message bodies for Central notifications.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="template-alert template-alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="template-alert template-alert-error">
            <strong>Unable to save email template.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="template-panel">
        <div class="template-panel-heading">
            <h2>Create template</h2>
            <p>Templates can be selected from Management → Notifications when composing an email.</p>
        </div>

        <div class="template-placeholder-reference">
            <strong>Supported placeholders</strong>
            <p>Enter these tokens in the subject or body. Values are resolved when the notification is queued.</p>
            <div class="template-placeholder-group">
                <span>Automatic</span>
                <code>{{ '{{recipient_email}}' }}</code>
                <code>{{ '{{current_date}}' }}</code>
                <code>{{ '{{current_datetime}}' }}</code>
            </div>
            <div class="template-placeholder-group">
                <span>Filled in on Notifications</span>
                <code>{{ '{{application_name}}' }}</code>
                <code>{{ '{{server_name}}' }}</code>
                <code>{{ '{{severity}}' }}</code>
                <code>{{ '{{event_name}}' }}</code>
                <code>{{ '{{event_time}}' }}</code>
                <code>{{ '{{message}}' }}</code>
            </div>
        </div>

        <form method="POST" action="{{ route('management.notification-templates.store') }}" class="template-form">
            @csrf

            <label class="template-field">
                <span>Template name</span>
                <input type="text" name="name" value="{{ old('name') }}" maxlength="120" required>
            </label>

            <label class="template-field">
                <span>Email subject</span>
                <input type="text" name="subject" value="{{ old('subject') }}" maxlength="255" required>
            </label>

            <label class="template-field">
                <span>Message body</span>
                <textarea name="body" rows="7" maxlength="20000" required>{{ old('body') }}</textarea>
            </label>

            <label class="template-checkbox">
                <input type="checkbox" name="is_active" value="1" checked>
                <span>Active and available for composing notifications</span>
            </label>

            <div class="template-actions">
                <button type="submit" class="template-button">Create template</button>
            </div>
        </form>
    </section>

    <section class="template-panel">
        <div class="template-panel-heading">
            <h2>Saved templates</h2>
            <p>Inactive templates remain saved but are not offered in the notification composer.</p>
        </div>

        <div class="template-list">
            @forelse ($templates as $template)
                <article class="template-item">
                    <form method="POST" action="{{ route('management.notification-templates.update', $template->id) }}" class="template-form">
                        @csrf
                        @method('PUT')

                        <div class="template-item-heading">
                            <strong>{{ $template->name }}</strong>
                            <span class="template-status {{ $template->is_active ? 'template-status-active' : 'template-status-inactive' }}">
                                {{ $template->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>

                        <label class="template-field">
                            <span>Template name</span>
                            <input type="text" name="name" value="{{ $template->name }}" maxlength="120" required>
                        </label>

                        <label class="template-field">
                            <span>Email subject</span>
                            <input type="text" name="subject" value="{{ $template->subject }}" maxlength="255" required>
                        </label>

                        <label class="template-field">
                            <span>Message body</span>
                            <textarea name="body" rows="7" maxlength="20000" required>{{ $template->body }}</textarea>
                        </label>

                        <label class="template-checkbox">
                            <input type="checkbox" name="is_active" value="1" {{ $template->is_active ? 'checked' : '' }}>
                            <span>Active and available for composing notifications</span>
                        </label>

                        <div class="template-actions">
                            <button type="submit" class="template-button">Save changes</button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('management.notification-templates.destroy', $template->id) }}" onsubmit="return confirm('Delete this email template?');">
                        @csrf
                        @method('DELETE')

                        <button type="submit" class="template-delete-button">Delete template</button>
                    </form>
                </article>
            @empty
                <div class="template-empty">No email templates have been created.</div>
            @endforelse
        </div>

        <div class="template-pagination">
            {{ $templates->links() }}
        </div>
    </section>

    <div class="template-note">
        Template content is stored as plain text. It is copied into the compose form when selected; editing or deleting a template does not change notifications that have already been queued.
    </div>
</div>

<style>
    .template-page
    {
        display: grid;
        gap: 22px;
    }

    .template-panel
    {
        padding: 24px;
        border: 1px solid var(--line);
        border-radius: 18px;
        background: var(--surface);
        box-shadow: var(--shadow);
    }

    .template-panel-heading
    {
        margin-bottom: 20px;
    }

    .template-panel-heading h2
    {
        margin: 0;
        color: var(--ink);
        font-size: 15px;
        font-weight: 800;
    }

    .template-panel-heading p
    {
        margin: 7px 0 0;
        color: var(--muted);
        font-size: 11px;
        line-height: 1.7;
    }

    .template-form
    {
        display: grid;
        gap: 16px;
    }

    .template-field
    {
        display: grid;
        gap: 8px;
    }

    .template-field > span
    {
        color: var(--ink);
        font-size: 12px;
        font-weight: 700;
    }

    .template-field input,
    .template-field textarea
    {
        width: 100%;
        padding: 12px 13px;
        border: 1px solid var(--line);
        border-radius: 10px;
        background: var(--surface-2);
        color: var(--ink);
        font: 500 12px var(--font);
    }

    .template-field textarea
    {
        resize: vertical;
        line-height: 1.7;
    }

    .template-placeholder-reference
    {
        display: grid;
        gap: 10px;
        margin-bottom: 20px;
        padding: 15px;
        border: 1px solid var(--line);
        border-radius: 12px;
        background: var(--surface-2);
    }

    .template-placeholder-reference > strong
    {
        color: var(--ink);
        font-size: 12px;
    }

    .template-placeholder-reference p
    {
        margin: 0;
        color: var(--muted);
        font-size: 10px;
        line-height: 1.6;
    }

    .template-placeholder-group
    {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 7px;
    }

    .template-placeholder-group span
    {
        width: 100%;
        color: var(--muted);
        font-size: 10px;
        font-weight: 700;
    }

    .template-placeholder-group code
    {
        padding: 5px 8px;
        border: 1px solid var(--line);
        border-radius: 7px;
        background: var(--surface);
        color: var(--accent);
        font: 500 10px var(--mono);
    }

    .template-checkbox
    {
        display: flex;
        align-items: center;
        gap: 9px;
        color: var(--muted);
        font-size: 11px;
    }

    .template-checkbox input
    {
        width: 16px;
        height: 16px;
        accent-color: var(--accent);
    }

    .template-actions
    {
        display: flex;
        justify-content: flex-end;
    }

    .template-button,
    .template-delete-button
    {
        min-height: 40px;
        padding: 0 17px;
        border: 0;
        border-radius: 10px;
        color: #ffffff;
        font: 700 11px var(--font);
        cursor: pointer;
    }

    .template-button
    {
        background: var(--brand);
    }

    .template-delete-button
    {
        background: var(--danger);
    }

    .template-list
    {
        display: grid;
        gap: 16px;
    }

    .template-item
    {
        display: grid;
        gap: 16px;
        padding: 19px;
        border: 1px solid var(--line);
        border-radius: 14px;
        background: var(--surface-2);
    }

    .template-item-heading
    {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .template-item-heading strong
    {
        color: var(--ink);
        font-size: 13px;
    }

    .template-status
    {
        padding: 5px 9px;
        border-radius: 999px;
        font: 700 9px var(--mono);
    }

    .template-status-active
    {
        background: var(--ok-soft);
        color: var(--ok);
    }

    .template-status-inactive
    {
        background: var(--line);
        color: var(--muted);
    }

    .template-alert
    {
        padding: 13px 16px;
        border-radius: 12px;
        font-size: 12px;
        line-height: 1.7;
    }

    .template-alert ul
    {
        margin: 6px 0 0;
        padding-left: 20px;
    }

    .template-alert-success
    {
        color: var(--ok);
        background: var(--ok-soft);
    }

    .template-alert-error
    {
        color: var(--danger);
        background: color-mix(in srgb, var(--danger) 9%, transparent);
    }

    .template-empty
    {
        padding: 28px;
        color: var(--muted);
        text-align: center;
        font-size: 12px;
    }

    .template-pagination
    {
        margin-top: 18px;
    }

    .template-note
    {
        padding: 14px 18px;
        border: 1px solid var(--line);
        border-radius: 12px;
        background: var(--surface-2);
        color: var(--muted);
        font-size: 10px;
        line-height: 1.7;
    }

    @media (max-width: 700px)
    {
        .template-panel
        {
            padding: 19px 14px;
        }

        .template-actions
        {
            justify-content: stretch;
        }

        .template-button,
        .template-delete-button
        {
            width: 100%;
        }
    }
</style>
@endsection
