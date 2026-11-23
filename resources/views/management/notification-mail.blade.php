@extends('layouts.app')

@section('title', 'Email Notifications - NADI Console')

@section('breadcrumb', 'Management / Email Notifications')

@section('content')
<div class="mail-settings-page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Email Notifications</h1>
            <p class="page-description">Configure the Google Gmail account used by NADI Central to send notifications.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="mail-alert mail-alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="mail-alert mail-alert-error">
            <strong>Unable to complete the request.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="mail-panel">
        <div class="mail-panel-heading">
            <div>
                <h2>Google Gmail SMTP</h2>
                <p>Credentials are stored encrypted in the Central database. The App Password is never displayed after saving.</p>
            </div>
            <span class="mail-status {{ ($settings?->enabled && $hasPassword) ? 'is-enabled' : 'is-disabled' }}">
                {{ ($settings?->enabled && $hasPassword) ? 'Enabled' : 'Not enabled' }}
            </span>
        </div>

        <form method="POST" action="{{ route('management.notification-mail.update') }}" autocomplete="off">
            @csrf
            @method('PUT')

            <div class="mail-grid">
                <label class="mail-field">
                    <span>Gmail account</span>
                    <input
                        type="email"
                        name="smtp_username"
                        value="{{ old('smtp_username', $settings?->smtp_username) }}"
                        placeholder="nadi.notifications@gmail.com"
                        maxlength="255"
                        required
                    >
                    <small>Use the Google account that will send NADI emails.</small>
                </label>

                <label class="mail-field">
                    <span>Google App Password</span>
                    <input
                        type="password"
                        name="smtp_password"
                        value=""
                        placeholder="{{ $hasPassword ? 'Leave blank to keep the saved password' : 'Enter Google App Password' }}"
                        minlength="16"
                        maxlength="255"
                        autocomplete="new-password"
                        {{ $hasPassword ? '' : 'required' }}
                    >
                    <small>{{ $hasPassword ? 'A password is already stored. Enter a new one only when rotating credentials.' : 'Create an App Password in your Google Account security settings.' }}</small>
                </label>

                <label class="mail-field">
                    <span>Sender name</span>
                    <input
                        type="text"
                        name="from_name"
                        value="{{ old('from_name', $settings?->from_name ?? 'NADI') }}"
                        maxlength="150"
                        required
                    >
                    <small>The display name shown to email recipients.</small>
                </label>
            </div>

            <div class="mail-provider">
                <div>
                    <strong>Google SMTP server</strong>
                    <span>smtp.gmail.com : 587 · STARTTLS</span>
                </div>
                <div>
                    <strong>Credential management</strong>
                    <span>Editable here without deployment or .env changes</span>
                </div>
            </div>

            <label class="mail-toggle">
                <input type="checkbox" name="enabled" value="1" {{ old('enabled', $settings?->enabled) ? 'checked' : '' }}>
                <span>
                    <strong>Enable email notifications</strong>
                    <small>When disabled, NADI will not use this account to send notifications.</small>
                </span>
            </label>

            <div class="mail-actions">
                <button type="submit" class="mail-button">Save email settings</button>
            </div>
        </form>
    </section>

    <section class="mail-panel">
        <div class="mail-panel-heading">
            <div>
                <h2>Send test email</h2>
                <p>Verify the saved Gmail credentials before using this account for notifications.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('management.notification-mail.test') }}" class="mail-test-form">
            @csrf

            <label class="mail-field">
                <span>Test recipient</span>
                <input
                    type="email"
                    name="test_recipient"
                    value="{{ old('test_recipient') }}"
                    placeholder="your-email@example.com"
                    maxlength="255"
                    required
                >
            </label>

            <button type="submit" class="mail-button mail-button-secondary" {{ (!$settings?->enabled || !$hasPassword) ? 'disabled' : '' }}>
                Send test email
            </button>
        </form>

        <div class="mail-last-tested">
            Last successful test:
            <strong>{{ $settings?->last_tested_at ? \Illuminate\Support\Carbon::parse($settings->last_tested_at)->format('d M Y H:i:s') : 'Never' }}</strong>
        </div>
    </section>

    <div class="mail-security-note">
        <strong>Security note</strong>
        <p>Use a Google App Password, not your normal Google account password. Two-Step Verification must be enabled for the Google account. The password is encrypted before it is stored. Only users who are permitted to access the Central Console should access this page.</p>
    </div>
</div>

<style>
    .mail-settings-page
    {
        display: grid;
        gap: 22px;
    }

    .mail-panel
    {
        padding: 26px;
        border: 1px solid var(--line);
        border-radius: 18px;
        background: var(--surface);
        box-shadow: var(--shadow);
    }

    .mail-panel-heading
    {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 24px;
    }

    .mail-panel-heading h2
    {
        margin: 0;
        color: var(--ink);
        font-size: 16px;
        font-weight: 800;
    }

    .mail-panel-heading p
    {
        margin: 8px 0 0;
        color: var(--muted);
        font-size: 12px;
        line-height: 1.7;
    }

    .mail-status
    {
        padding: 7px 11px;
        border-radius: 999px;
        font: 700 10px var(--mono);
        white-space: nowrap;
    }

    .mail-status.is-enabled
    {
        color: var(--ok);
        background: var(--ok-soft);
    }

    .mail-status.is-disabled
    {
        color: var(--muted);
        background: var(--surface-2);
    }

    .mail-grid
    {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 20px;
    }

    .mail-field
    {
        display: grid;
        gap: 8px;
        min-width: 0;
    }

    .mail-field > span
    {
        color: var(--ink);
        font-size: 12px;
        font-weight: 700;
    }

    .mail-field input
    {
        width: 100%;
        height: 44px;
        padding: 0 13px;
        border: 1px solid var(--line);
        border-radius: 10px;
        background: var(--surface-2);
        color: var(--ink);
        font: 500 12px var(--font);
    }

    .mail-field input:focus
    {
        outline: 2px solid var(--accent-soft);
        border-color: var(--accent);
    }

    .mail-field small,
    .mail-toggle small
    {
        color: var(--muted);
        font-size: 10.5px;
        line-height: 1.6;
    }

    .mail-provider
    {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        margin-top: 22px;
        padding: 16px;
        border: 1px solid var(--line);
        border-radius: 12px;
        background: var(--surface-2);
    }

    .mail-provider div
    {
        display: grid;
        gap: 6px;
    }

    .mail-provider strong
    {
        color: var(--ink);
        font-size: 11px;
    }

    .mail-provider span
    {
        color: var(--muted);
        font: 500 10px var(--mono);
        overflow-wrap: anywhere;
    }

    .mail-toggle
    {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        margin-top: 22px;
        cursor: pointer;
    }

    .mail-toggle input
    {
        width: 17px;
        height: 17px;
        margin: 2px 0 0;
        accent-color: var(--accent);
    }

    .mail-toggle span
    {
        display: grid;
        gap: 5px;
    }

    .mail-toggle strong
    {
        color: var(--ink);
        font-size: 12px;
    }

    .mail-actions
    {
        display: flex;
        justify-content: flex-end;
        margin-top: 24px;
    }

    .mail-button
    {
        min-height: 42px;
        padding: 0 20px;
        border: 0;
        border-radius: 10px;
        background: var(--brand);
        color: #ffffff;
        font: 700 12px var(--font);
        cursor: pointer;
    }

    .mail-button:disabled
    {
        opacity: .45;
        cursor: not-allowed;
    }

    .mail-button-secondary
    {
        flex-shrink: 0;
    }

    .mail-test-form
    {
        display: flex;
        align-items: flex-end;
        gap: 14px;
    }

    .mail-test-form .mail-field
    {
        flex: 1;
    }

    .mail-last-tested
    {
        margin-top: 16px;
        color: var(--muted);
        font-size: 11px;
    }

    .mail-last-tested strong
    {
        color: var(--ink);
    }

    .mail-alert
    {
        padding: 14px 16px;
        border-radius: 12px;
        font-size: 12px;
        line-height: 1.7;
    }

    .mail-alert ul
    {
        margin: 6px 0 0;
        padding-left: 20px;
    }

    .mail-alert-success
    {
        color: var(--ok);
        background: var(--ok-soft);
    }

    .mail-alert-error
    {
        color: var(--danger);
        background: color-mix(in srgb, var(--danger) 9%, transparent);
    }

    .mail-security-note
    {
        padding: 18px 20px;
        border: 1px solid var(--line);
        border-radius: 14px;
        background: var(--surface-2);
    }

    .mail-security-note strong
    {
        color: var(--ink);
        font-size: 12px;
    }

    .mail-security-note p
    {
        margin: 7px 0 0;
        color: var(--muted);
        font-size: 11px;
        line-height: 1.8;
    }

    @media (max-width: 700px)
    {
        .mail-panel
        {
            padding: 20px 16px;
        }

        .mail-grid,
        .mail-provider
        {
            grid-template-columns: 1fr;
        }

        .mail-test-form
        {
            align-items: stretch;
            flex-direction: column;
        }

        .mail-button
        {
            width: 100%;
        }
    }
</style>
@endsection
