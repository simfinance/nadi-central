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
                <code>@{{recipient_email}}</code>
                <code>@{{current_date}}</code>
                <code>@{{current_datetime}}</code>
            </div>
            <div class="template-placeholder-group">
                <span>Filled in on Notifications</span>
                <code>@{{application_name}}</code>
                <code>@{{server_name}}</code>
                <code>@{{severity}}</code>
                <code>@{{event_name}}</code>
                <code>@{{event_time}}</code>
                <code>@{{message}}</code>
            </div>
        </div>

        <div class="template-placeholder-palette">
            <strong>Drag placeholders into any Subject or Message field</strong>
            <p>These tokens are available in the create form and every saved template. You can also click a token to insert it at the cursor.</p>
            <div class="template-placeholder-token-list">
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

        <form method="POST" action="{{ route('management.notification-templates.store') }}" class="template-form">
            @csrf

            <label class="template-field">
                <span>Template name</span>
                <input type="text" name="name" value="{{ old('name') }}" maxlength="120" required>
            </label>

            <label class="template-field">
                <span>Email subject</span>
                <input type="text" name="subject" data-placeholder-target value="{{ old('subject') }}" maxlength="255" required>
            </label>

            <label class="template-field">
                <span>Message body</span>
                <textarea name="body" data-placeholder-target rows="7" maxlength="20000" required>{{ old('body') }}</textarea>
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
                            <input type="text" name="subject" data-placeholder-target value="{{ $template->subject }}" maxlength="255" required>
                        </label>

                        <label class="template-field">
                            <span>Message body</span>
                            <textarea name="body" data-placeholder-target rows="7" maxlength="20000" required>{{ $template->body }}</textarea>
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


    .template-placeholder-palette
    {
        display: grid;
        gap: 10px;
        margin-bottom: 18px;
        padding: 15px;
        border: 1px solid var(--line);
        border-radius: 12px;
        background: var(--surface-2);
    }

    .template-placeholder-palette > strong
    {
        color: var(--ink);
        font-size: 12px;
    }

    .template-placeholder-palette p
    {
        margin: 0;
        color: var(--muted);
        font-size: 10px;
        line-height: 1.6;
    }

    .template-placeholder-token-list
    {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
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
