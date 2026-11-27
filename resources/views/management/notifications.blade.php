@extends('layouts.app')

@section('title', 'Notifications - NADI Console')

@section('breadcrumb', 'Management / Notifications')

@section('content')
<div class="notification-page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Notifications</h1>
            <p class="page-description">Compose Central email notifications and review delivery status.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="notification-alert notification-alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="notification-alert notification-alert-error">
            <strong>Unable to queue notification.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="notification-panel">
        <div class="notification-panel-heading">
            <h2>Compose email</h2>
            <p>Messages are queued for asynchronous delivery through the configured Gmail account.</p>
        </div>

        <form method="POST" action="{{ route('management.notifications.store') }}" class="notification-form">
            @csrf

            <label class="notification-field">
                <span>Recipient email</span>
                <input
                    type="email"
                    name="recipient"
                    value="{{ old('recipient') }}"
                    maxlength="255"
                    required
                >
            </label>

            <label class="notification-field">
                <span>Use email template</span>
                <select id="notificationTemplate">
                    <option value="">Compose without a template</option>
                    @foreach ($templates as $template)
                        <option
                            value="{{ $template->id }}"
                            data-subject="{{ $template->subject }}"
                            data-body="{{ $template->body }}"
                        >
                            {{ $template->name }}
                        </option>
                    @endforeach
                </select>
                <small>Selecting a template copies its subject and body into the fields below.</small>
            </label>

            <label class="notification-field">
                <span>Subject</span>
                <input
                    type="text"
                    name="subject"
                    data-placeholder-target
                    value="{{ old('subject') }}"
                    maxlength="255"
                    required
                >
            </label>

            <label class="notification-field">
                <span>Message</span>
                <textarea
                    name="body"
                    data-placeholder-target
                    rows="7"
                    maxlength="20000"
                    required
                >{{ old('body') }}</textarea>
                <small>Plain text email. Maximum 20,000 characters.</small>
            </label>

            <div class="notification-placeholder-heading">
                <strong>Drag placeholders into Subject or Message</strong>
                <p>Drag a token into either field, or click a token to insert it at the cursor.</p>
                <div class="notification-placeholder-palette">
                    <button type="button" class="placeholder-token" draggable="true" data-placeholder="@{{recipient_email}}">@{{recipient_email}}</button>
                    <button type="button" class="placeholder-token" draggable="true" data-placeholder="@{{current_date}}">@{{current_date}}</button>
                    <button type="button" class="placeholder-token" draggable="true" data-placeholder="@{{current_datetime}}">@{{current_datetime}}</button>
                    <button type="button" class="placeholder-token" draggable="true" data-placeholder="@{{application_name}}">@{{application_name}}</button>
                    <button type="button" class="placeholder-token" draggable="true" data-placeholder="@{{server_name}}">@{{server_name}}</button>
                    <button type="button" class="placeholder-token" draggable="true" data-placeholder="@{{severity}}">@{{severity}}</button>
                    <button type="button" class="placeholder-token" draggable="true" data-placeholder="@{{event_name}}">@{{event_name}}</button>
                    <button type="button" class="placeholder-token" draggable="true" data-placeholder="@{{event_time}}">@{{event_time}}</button>
                    <button type="button" class="placeholder-token" draggable="true" data-placeholder="@{{message}}">@{{message}}</button>
                </div>
            </div>

            <div class="notification-placeholder-heading">
                <strong>Placeholder values</strong>
                <p>Fill only the values used by the selected template. System placeholders are filled automatically.</p>
            </div>

            <div class="notification-placeholder-grid">
                <label class="notification-field">
                    <span>Application name</span>
                    <input
                        type="text"
                        name="placeholders[application_name]"
                        value="{{ old('placeholders.application_name', config('app.name', 'NADI')) }}"
                        maxlength="255"
                    >
                    <small>@{{application_name}}</small>
                </label>

                <label class="notification-field">
                    <span>Server name</span>
                    <input
                        type="text"
                        name="placeholders[server_name]"
                        value="{{ old('placeholders.server_name') }}"
                        maxlength="255"
                    >
                    <small>@{{server_name}}</small>
                </label>

                <label class="notification-field">
                    <span>Severity</span>
                    <input
                        type="text"
                        name="placeholders[severity]"
                        value="{{ old('placeholders.severity') }}"
                        maxlength="100"
                        placeholder="Critical, Warning, Info"
                    >
                    <small>@{{severity}}</small>
                </label>

                <label class="notification-field">
                    <span>Event name</span>
                    <input
                        type="text"
                        name="placeholders[event_name]"
                        value="{{ old('placeholders.event_name') }}"
                        maxlength="255"
                    >
                    <small>@{{event_name}}</small>
                </label>

                <label class="notification-field">
                    <span>Event time</span>
                    <input
                        type="text"
                        name="placeholders[event_time]"
                        value="{{ old('placeholders.event_time') }}"
                        maxlength="100"
                        placeholder="Example: 24-11-2026 14:30:00"
                    >
                    <small>@{{event_time}}</small>
                </label>

                <label class="notification-field notification-placeholder-message">
                    <span>Additional message / details</span>
                    <textarea
                        name="placeholders[message]"
                        rows="4"
                        maxlength="10000"
                    >{{ old('placeholders.message') }}</textarea>
                    <small>@{{message}}</small>
                </label>
            </div>

            <div class="notification-placeholder-reference">
                Automatic: @{{recipient_email}}, @{{current_date}}, @{{current_datetime}}.
                Unrecognized placeholders are rejected before the email enters the queue.
            </div>

            <div class="notification-form-actions">
                <button type="submit" class="notification-button">Add to delivery queue</button>
            </div>
        </form>
    </section>

    <section class="notification-panel">
        <div class="notification-panel-heading">
            <h2>Delivery history</h2>
            <p>Latest 20 deliveries per page. Failed deliveries retain a short diagnostic message.</p>
        </div>

        <div class="notification-table-wrapper">
            <table class="notification-table">
                <thead>
                    <tr>
                        <th>Recipient / Subject</th>
                        <th>Status</th>
                        <th>Attempts</th>
                        <th>Queued</th>
                        <th>Sent</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($deliveries as $delivery)
                        <tr>
                            <td>
                                <strong>{{ $delivery->recipient }}</strong>
                                <small>{{ $delivery->subject }}</small>
                                @if ($delivery->last_error)
                                    <small class="notification-error-detail">{{ $delivery->last_error }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="notification-status status-{{ $delivery->status }}">
                                    {{ ucfirst($delivery->status) }}
                                </span>
                            </td>
                            <td>{{ number_format($delivery->attempts) }}</td>
                            <td>{{ $delivery->queued_at ? \Illuminate\Support\Carbon::parse($delivery->queued_at)->format('d M Y H:i') : '—' }}</td>
                            <td>{{ $delivery->sent_at ? \Illuminate\Support\Carbon::parse($delivery->sent_at)->format('d M Y H:i') : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="notification-empty">No notification deliveries yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="notification-pagination">
            {{ $deliveries->links() }}
        </div>
    </section>

    <div class="notification-security-note">
        Message bodies are encrypted in the Central notification outbox. Queue payloads contain only the delivery identifier.
    </div>
</div>

<style>
    .notification-page
    {
        display: grid;
        gap: 22px;
    }

    .notification-panel
    {
        padding: 24px;
        border: 1px solid var(--line);
        border-radius: 18px;
        background: var(--surface);
        box-shadow: var(--shadow);
    }

    .notification-panel-heading
    {
        margin-bottom: 20px;
    }

    .notification-panel-heading h2
    {
        margin: 0;
        color: var(--ink);
        font-size: 15px;
        font-weight: 800;
    }

    .notification-panel-heading p
    {
        margin: 7px 0 0;
        color: var(--muted);
        font-size: 11px;
        line-height: 1.7;
    }

    .notification-form
    {
        display: grid;
        gap: 17px;
    }

    .notification-field
    {
        display: grid;
        gap: 8px;
    }

    .notification-field > span
    {
        color: var(--ink);
        font-size: 12px;
        font-weight: 700;
    }

    .notification-field input,
    .notification-field textarea
    {
        width: 100%;
        padding: 12px 13px;
        border: 1px solid var(--line);
        border-radius: 10px;
        background: var(--surface-2);
        color: var(--ink);
        font: 500 12px var(--font);
    }

    .notification-field textarea
    {
        resize: vertical;
        line-height: 1.7;
    }

    .notification-field select
    {
        width: 100%;
        padding: 12px 13px;
        border: 1px solid var(--line);
        border-radius: 10px;
        background: var(--surface-2);
        color: var(--ink);
        font: 500 12px var(--font);
    }

    .notification-placeholder-heading
    {
        display: grid;
        gap: 6px;
        padding-top: 8px;
    }

    .notification-placeholder-heading strong
    {
        color: var(--ink);
        font-size: 12px;
    }

    .notification-placeholder-heading p
    {
        margin: 0;
        color: var(--muted);
        font-size: 10px;
        line-height: 1.6;
    }

    .notification-placeholder-grid
    {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }

    .notification-placeholder-message
    {
        grid-column: 1 / -1;
    }


    .notification-placeholder-palette
    {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 10px;
    }

    .placeholder-token
    {
        padding: 7px 10px;
        border: 1px dashed var(--accent);
        border-radius: 8px;
        background: var(--surface);
        color: var(--accent);
        font: 600 10px var(--mono);
        cursor: grab;
        user-select: none;
    }

    .placeholder-token:active
    {
        cursor: grabbing;
    }

    [data-placeholder-target]
    {
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    [data-placeholder-target].placeholder-drop-active
    {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 16%, transparent);
        outline: none;
    }

    .notification-placeholder-reference
    {
        padding: 12px 14px;
        border: 1px solid var(--line);
        border-radius: 10px;
        background: var(--surface-2);
        color: var(--muted);
        font-size: 10px;
        line-height: 1.7;
        overflow-wrap: anywhere;
    }

    .notification-field small
    {
        color: var(--muted);
        font-size: 10px;
    }

    .notification-form-actions
    {
        display: flex;
        justify-content: flex-end;
    }

    .notification-button
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

    .notification-table-wrapper
    {
        overflow-x: auto;
    }

    .notification-table
    {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    .notification-table th
    {
        padding: 12px;
        border-bottom: 1px solid var(--line);
        color: var(--muted);
        font: 500 10px var(--mono);
        white-space: nowrap;
    }

    .notification-table td
    {
        padding: 14px 12px;
        border-bottom: 1px solid var(--line);
        color: var(--ink);
        font-size: 11px;
        vertical-align: top;
    }

    .notification-table td:first-child
    {
        min-width: 220px;
    }

    .notification-table td strong,
    .notification-table td small
    {
        display: block;
    }

    .notification-table td small
    {
        margin-top: 5px;
        color: var(--muted);
        line-height: 1.5;
    }

    .notification-table .notification-error-detail
    {
        color: var(--danger);
        overflow-wrap: anywhere;
    }

    .notification-status
    {
        display: inline-block;
        padding: 5px 9px;
        border-radius: 999px;
        background: var(--surface-2);
        color: var(--muted);
        font: 700 9px var(--mono);
    }

    .status-sent
    {
        background: var(--ok-soft);
        color: var(--ok);
    }

    .status-failed
    {
        background: color-mix(in srgb, var(--danger) 12%, transparent);
        color: var(--danger);
    }

    .status-sending
    {
        background: var(--accent-soft);
        color: var(--accent);
    }

    .notification-empty
    {
        padding: 30px !important;
        color: var(--muted) !important;
        text-align: center;
    }

    .notification-pagination
    {
        margin-top: 18px;
    }

    .notification-alert
    {
        padding: 13px 16px;
        border-radius: 12px;
        font-size: 12px;
        line-height: 1.7;
    }

    .notification-alert ul
    {
        margin: 6px 0 0;
        padding-left: 20px;
    }

    .notification-alert-success
    {
        color: var(--ok);
        background: var(--ok-soft);
    }

    .notification-alert-error
    {
        color: var(--danger);
        background: color-mix(in srgb, var(--danger) 9%, transparent);
    }

    .notification-security-note
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
        .notification-panel
        {
            padding: 19px 14px;
        }

        .notification-form-actions
        {
            justify-content: stretch;
        }

        .notification-button
        {
            width: 100%;
        }

        .notification-placeholder-grid
        {
            grid-template-columns: 1fr;
        }

        .notification-placeholder-message
        {
            grid-column: auto;
        }
    }
</style>

<script>
    (function () {
        var lastFocusedTarget = null;
        var placeholderTokens = document.querySelectorAll('.placeholder-token');

        function insertPlaceholder(target, placeholder)
        {
            if (!target || !placeholder)
            {
                return;
            }

            target.focus();

            var start = typeof target.selectionStart === 'number'
                ? target.selectionStart
                : target.value.length;
            var end = typeof target.selectionEnd === 'number'
                ? target.selectionEnd
                : target.value.length;

            if (typeof target.setRangeText === 'function')
            {
                target.setRangeText(placeholder, start, end, 'end');
            }
            else
            {
                target.value = target.value.slice(0, start)
                    + placeholder
                    + target.value.slice(end);
            }

            target.dispatchEvent(new Event('input', { bubbles: true }));
        }

        document.addEventListener('focusin', function (event) {
            if (event.target.matches('[data-placeholder-target]'))
            {
                lastFocusedTarget = event.target;
            }
        });

        placeholderTokens.forEach(function (token) {
            token.addEventListener('dragstart', function (event) {
                event.dataTransfer.setData('text/plain', token.dataset.placeholder || '');
                event.dataTransfer.effectAllowed = 'copy';
            });

            token.addEventListener('click', function () {
                insertPlaceholder(lastFocusedTarget, token.dataset.placeholder);
            });
        });

        document.querySelectorAll('[data-placeholder-target]').forEach(function (target) {
            target.addEventListener('dragover', function (event) {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'copy';
                target.classList.add('placeholder-drop-active');
            });

            target.addEventListener('dragleave', function () {
                target.classList.remove('placeholder-drop-active');
            });

            target.addEventListener('drop', function (event) {
                event.preventDefault();
                target.classList.remove('placeholder-drop-active');

                var placeholder = event.dataTransfer.getData('text/plain');

                if (/^\{\{[a-zA-Z0-9_]+\}\}$/.test(placeholder))
                {
                    var selectionPosition = target.selectionStart;

                    if (typeof selectionPosition !== 'number')
                    {
                        selectionPosition = target.value.length;
                    }

                    target.focus();

                    if (typeof target.setRangeText === 'function')
                    {
                        target.setRangeText(placeholder, selectionPosition, selectionPosition, 'end');
                    }
                    else
                    {
                        target.value += placeholder;
                    }

                    target.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
        });
    })();
</script>
@endsection
