@extends('layouts.app')

@section('title', 'Activation Grants - NADI Console')

@section('breadcrumb', 'Management / Activation Grants')

@section('content')

@php
    $activeGrantCount = $grants->filter(function ($grant)
    {
        return $grant->grant_status === 'active'
            && $grant->expires_at
            && $grant->expires_at->isFuture();
    })->count();

    $revokedGrantCount = $grants->where('grant_status', 'revoked')->count();

    $grantBase = max($grants->count(), 1);
    $activeGrantPercent = round(($activeGrantCount / $grantBase) * 100);
    $revokedGrantPercent = round(($revokedGrantCount / $grantBase) * 100);
@endphp

<div class="activation-page">

    <div class="activation-header page-header">
        <div>
            <h1 class="page-title">Activation Grants</h1>
            <p class="page-description">
                Manage signed activation grants for approved applications.
            </p>
        </div>
    </div>

    <div class="activation-summary">

        <div class="activation-stat">
            <div class="activation-stat-top">
                <div class="activation-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="16" rx="3"/>
                        <path d="M3 9h18M7 14h4"/>
                    </svg>
                </div>
                <span class="stat-label">Approved Applications</span>
            </div>
            <strong>{{ number_format($registrations->count()) }}</strong>
            <div class="activation-stat-description">Eligible for grant issuance</div>
            <div class="activation-stat-meter">
                <div class="activation-stat-meter-fill" style="width: 100%;"></div>
            </div>
        </div>

        <div class="activation-stat">
            <div class="activation-stat-top">
                <div class="activation-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="10" width="18" height="11" rx="2"/>
                        <path d="M7 10V7a5 5 0 0 1 10 0v3"/>
                        <circle cx="12" cy="15" r="1"/>
                    </svg>
                </div>
                <span class="stat-label">Total Grants</span>
            </div>
            <strong>{{ number_format($grants->count()) }}</strong>
            <div class="activation-stat-description">All issued activation grants</div>
            <div class="activation-stat-meter">
                <div class="activation-stat-meter-fill" style="width: 100%;"></div>
            </div>
        </div>

        <div class="activation-stat">
            <div class="activation-stat-top">
                <div class="activation-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M8 12.5l3 3 5-6"/>
                    </svg>
                </div>
                <span class="stat-label">Active Grants</span>
            </div>
            <strong>
                {{ number_format($activeGrantCount) }}
                <span class="activation-stat-percent">{{ $activeGrantPercent }}%</span>
            </strong>
            <div class="activation-stat-description">Active and not yet expired</div>
            <div class="activation-stat-meter">
                <div class="activation-stat-meter-fill" style="width: {{ $activeGrantPercent }}%;"></div>
            </div>
        </div>

        <div class="activation-stat">
            <div class="activation-stat-top">
                <div class="activation-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M5.7 5.7l12.6 12.6"/>
                    </svg>
                </div>
                <span class="stat-label">Revoked Grants</span>
            </div>
            <strong>
                {{ number_format($revokedGrantCount) }}
                <span class="activation-stat-percent">{{ $revokedGrantPercent }}%</span>
            </strong>
            <div class="activation-stat-description">Access withdrawn</div>
            <div class="activation-stat-meter">
                <div class="activation-stat-meter-fill" style="width: {{ $revokedGrantPercent }}%;"></div>
            </div>
        </div>

    </div>

    <div class="activation-card">

        <div class="activation-card-header">
            <div>
                <h2>
                    Approved Applications
                    <span class="activation-card-live">Live</span>
                </h2>
                <p>
                    Applications eligible for activation grant issuance.
                </p>
            </div>
        </div>

        <div class="activation-table-wrapper">

            <table class="activation-table">

                <thead>
                    <tr>
                        <th>Application</th>
                        <th>Server</th>
                        <th>Environment</th>
                        <th>Grant</th>
                        <th>Version</th>
                        <th>Expiry</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse($registrations as $registration)

                        @php
                            $application = $registration->application;
                            $agent = $registration->agent;
                            $server = $agent?->server;

                            $grant = $grantsByRegistration->get(
                                $registration->id
                            );

                            $grantStatus = 'not_activated';

                            if ($grant)
                            {
                                $grantStatus = $grant->grant_status;

                                if (
                                    $grantStatus === 'active'
                                    && $grant->expires_at
                                    && $grant->expires_at->isPast()
                                )
                                {
                                    $grantStatus = 'expired';
                                }
                            }
                        @endphp

                        <tr>

                            <td>
                                <div class="activation-main">
                                    <div class="activation-main-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="4" width="18" height="16" rx="3"/>
                                            <path d="M3 9h18M7 14h4"/>
                                        </svg>
                                    </div>

                                    <div>
                                        <div class="application-name">
                                            {{ $application->name ?? 'Unknown Application' }}
                                        </div>

                                        <div class="application-meta application-key">
                                            {{ $application->application_key ?? $application->id }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div class="server-name">
                                    {{ $server->hostname ?? 'Unknown Server' }}
                                </div>

                                <div class="application-meta">
                                    <span class="activation-chip">
                                        {{ $server->ip_address ?? $server->ip ?? '-' }}
                                    </span>
                                </div>
                            </td>

                            <td>
                                <span class="activation-environment">
                                    {{ $application->environment ?? '-' }}
                                </span>
                            </td>

                            <td>
                                <span class="grant-status status-{{ $grantStatus }}">
                                    <span class="grant-status-dot"></span>
                                    @switch($grantStatus)
                                        @case('active')
                                            Active
                                            @break

                                        @case('expired')
                                            Expired
                                            @break

                                        @case('revoked')
                                            Revoked
                                            @break

                                        @default
                                            Not Activated
                                    @endswitch
                                </span>
                            </td>

                            <td>
                                <span class="activation-version">
                                    {{ $grant?->grant_version ?? '-' }}
                                </span>
                            </td>

                            <td>
                                @if($grant)
                                    <div class="activation-date">
                                        {{ $grant->expires_at?->format('d M Y H:i') ?? '-' }}
                                    </div>

                                    @if($grant->expires_at)
                                        <div class="application-meta">
                                            {{ $grant->expires_at->diffForHumans() }}
                                        </div>
                                    @endif
                                @else
                                    <span class="application-meta">
                                        -
                                    </span>
                                @endif
                            </td>

                            <td>

                                @if(!$grant || in_array($grantStatus, ['expired', 'revoked', 'not_activated']))

                                    <form
                                        method="POST"
                                        action="{{ route('management.activation-grants.issue', $registration->id) }}"
                                        data-confirm-title="Issue activation grant for this application?"
                                        data-confirm-detail="{{ $application->name ?? 'Unknown Application' }} · {{ $server->hostname ?? 'Unknown Server' }}"
                                        data-confirm-button="{{ $grant ? 'Renew Grant' : 'Issue Grant' }}"
                                        data-confirm-variant="primary"
                                    >
                                        @csrf

                                        <button
                                            type="submit"
                                            class="activation-button issue"
                                        >
                                            {{ $grant ? 'Renew Grant' : 'Issue Grant' }}
                                        </button>
                                    </form>

                                @elseif($grantStatus === 'active')

                                    <form
                                        method="POST"
                                        action="{{ route('management.activation-grants.revoke', $grant->id) }}"
                                        class="revoke-form"
                                        data-confirm-title="Revoke this activation grant?"
                                        data-confirm-detail="{{ $application->name ?? 'Unknown Application' }} · {{ $server->hostname ?? 'Unknown Server' }}"
                                        data-confirm-button="Revoke"
                                        data-confirm-variant="danger"
                                    >
                                        @csrf

                                        <input
                                            type="text"
                                            name="reason"
                                            placeholder="Reason for revoke (optional)"
                                            maxlength="1000"
                                        >

                                        <button
                                            type="submit"
                                            class="activation-button revoke"
                                        >
                                            Revoke
                                        </button>
                                    </form>

                                @endif

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="7" class="activation-empty">
                                <div class="activation-empty-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="10" width="18" height="11" rx="2"/>
                                        <path d="M7 10V7a5 5 0 0 1 10 0v3"/>
                                        <circle cx="12" cy="15" r="1"/>
                                    </svg>
                                </div>

                                No approved applications available.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function ()
{
    const flash = {
        success: @json(session('success')),
        error: @json(session('error')),
        validation: @json($errors->any() ? $errors->first() : null)
    };

    /*
     * Tanpa SweetAlert, aksi issue dan revoke tidak boleh berjalan tanpa konfirmasi.
     * Form diblokir dan penyebabnya dicatat di konsol.
     */
    if (typeof Swal === 'undefined')
    {
        document.addEventListener('submit', function (event)
        {
            if (event.target.hasAttribute && event.target.hasAttribute('data-confirm-title'))
            {
                event.preventDefault();

                console.error('SweetAlert2 failed to load. The activation grant action was blocked.');
            }
        });

        return;
    }

    function dialogClass(variant)
    {
        return {
            popup: 'nadi-swal-popup',
            title: 'nadi-swal-title',
            htmlContainer: 'nadi-swal-text',
            actions: 'nadi-swal-actions',
            confirmButton: 'nadi-swal-btn nadi-swal-btn-' + (variant || 'primary'),
            cancelButton: 'nadi-swal-btn nadi-swal-btn-secondary'
        };
    }

    const modal = Swal.mixin({
        buttonsStyling: false,
        reverseButtons: true,
        customClass: dialogClass('primary')
    });

    const toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true,
        customClass: {
            popup: 'nadi-swal-toast',
            title: 'nadi-swal-toast-title'
        },
        didOpen: function (element)
        {
            element.addEventListener('mouseenter', Swal.stopTimer);
            element.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    /* ---------- Pesan dari server ---------- */
    if (flash.success)
    {
        toast.fire({
            icon: 'success',
            title: flash.success
        });
    }

    if (flash.error)
    {
        modal.fire({
            icon: 'error',
            title: 'Action failed',
            text: flash.error,
            confirmButtonText: 'OK',
            customClass: dialogClass('danger')
        });
    }
    else if (flash.validation)
    {
        modal.fire({
            icon: 'warning',
            title: 'Please check your input',
            text: flash.validation,
            confirmButtonText: 'OK'
        });
    }

    function excerpt(value, limit)
    {
        const text = value.trim();

        return text.length > limit ? text.slice(0, limit) + '…' : text;
    }

    function setActionButtonsDisabled(disabled)
    {
        document.querySelectorAll('.activation-button').forEach(function (button)
        {
            button.disabled = disabled;
        });
    }

    /* ---------- Konfirmasi issue dan revoke ---------- */
    document.addEventListener('submit', async function (event)
    {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm-title'))
        {
            return;
        }

        event.preventDefault();

        if (form.dataset.submitting === '1')
        {
            return;
        }

        const variant = form.dataset.confirmVariant || 'primary';
        const reasonField = form.querySelector('input[name="reason"]');

        let detail = form.dataset.confirmDetail || '';

        if (reasonField && reasonField.value.trim() !== '')
        {
            detail = detail + '\nReason: ' + excerpt(reasonField.value, 200);
        }

        const result = await modal.fire({
            icon: variant === 'danger' ? 'warning' : 'question',
            title: form.dataset.confirmTitle,
            text: detail,
            showCancelButton: true,
            confirmButtonText: form.dataset.confirmButton || 'Confirm',
            cancelButtonText: 'Cancel',
            focusCancel: variant === 'danger',
            customClass: dialogClass(variant)
        });

        if (!result.isConfirmed)
        {
            return;
        }

        form.dataset.submitting = '1';

        setActionButtonsDisabled(true);

        modal.fire({
            title: 'Processing...',
            text: 'Please wait.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function ()
            {
                Swal.showLoading();
            }
        });

        form.submit();
    });

    /* Kembali lewat tombol Back: tutup dialog proses dan aktifkan lagi tombolnya. */
    window.addEventListener('pageshow', function (event)
    {
        if (!event.persisted)
        {
            return;
        }

        Swal.close();

        document.querySelectorAll('form[data-submitting]').forEach(function (form)
        {
            delete form.dataset.submitting;
        });

        setActionButtonsDisabled(false);
    });
});
</script>

@endsection

@push('styles')

<style>

    .activation-page,
    .swal2-container
    {
        --status-ok: #16a34a;
        --status-warn: #d97706;
        --status-bad: #dc2626;
    }

    .activation-page
    {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .activation-page .page-header
    {
        margin-bottom: 0;
    }

    .activation-header
    {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
    }

    /* ---------- Ringkasan ---------- */
    .activation-summary
    {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 18px;
    }

    .activation-stat
    {
        position: relative;
        padding: 22px 24px;
        border: 1px solid var(--line);
        border-radius: 16px;
        background-color: var(--surface);
        background-image: radial-gradient(circle at 100% 0%, color-mix(in srgb, var(--accent) 14%, transparent), transparent 55%), linear-gradient(color-mix(in srgb, var(--accent) 5%, transparent) 1px, transparent 1px), linear-gradient(90deg, color-mix(in srgb, var(--accent) 5%, transparent) 1px, transparent 1px);
        background-size: auto, 22px 22px, 22px 22px;
        box-shadow: var(--shadow);
        overflow: hidden;
        transition: transform .25s ease, border-color .25s ease, box-shadow .25s ease;
    }

    .activation-stat:hover
    {
        transform: translateY(-3px);
        border-color: color-mix(in srgb, var(--accent) 45%, var(--line));
        box-shadow: var(--shadow), 0 18px 40px -22px var(--accent);
    }

    .activation-stat::before
    {
        content: '';
        position: absolute;
        top: -1px;
        left: 24px;
        width: 44px;
        height: 2px;
        border-radius: 0 0 3px 3px;
        background: var(--brand);
    }

    /* Siku HUD di pojok kanan bawah */
    .activation-stat::after
    {
        content: '';
        position: absolute;
        right: 12px;
        bottom: 12px;
        width: 14px;
        height: 14px;
        border-right: 2px solid var(--accent);
        border-bottom: 2px solid var(--accent);
        border-radius: 0 0 4px 0;
        opacity: .5;
        pointer-events: none;
    }

    .activation-stat-top
    {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
    }

    .activation-stat-icon
    {
        width: 40px;
        height: 40px;
        display: grid;
        place-items: center;
        flex-shrink: 0;
        border: 1px solid color-mix(in srgb, var(--accent) 40%, transparent);
        border-radius: 12px;
        background: var(--accent-soft);
        color: var(--accent);
        box-shadow: 0 0 22px -6px var(--accent), inset 0 0 12px -6px var(--accent);
    }

    .activation-stat-icon svg
    {
        width: 19px;
        height: 19px;
    }

    .activation-page .stat-label
    {
        display: block;
        font: 600 11.5px var(--mono);
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .activation-stat strong
    {
        display: flex;
        align-items: baseline;
        gap: 10px;
        font-size: 34px;
        font-weight: 800;
        letter-spacing: -.03em;
        line-height: 1.1;
        color: var(--ink);
    }

    .activation-stat-percent
    {
        padding: 3px 9px;
        border: 1px solid color-mix(in srgb, var(--accent) 35%, transparent);
        border-radius: 999px;
        background: var(--accent-soft);
        color: var(--accent);
        font: 600 11px var(--mono);
        letter-spacing: 0;
    }

    .activation-stat-description
    {
        margin-top: 8px;
        font: 500 11.5px var(--mono);
        color: var(--muted);
    }

    .activation-stat-meter
    {
        height: 4px;
        margin-top: 16px;
        border-radius: 999px;
        background: var(--surface-2);
        overflow: hidden;
    }

    .activation-stat-meter-fill
    {
        height: 100%;
        border-radius: 999px;
        background: var(--brand);
        box-shadow: 0 0 12px var(--accent);
        transition: width .8s cubic-bezier(.2, .8, .2, 1);
    }

    /* ---------- Panel ---------- */
    .activation-card
    {
        position: relative;
        border: 1px solid var(--line);
        border-radius: 18px;
        background: var(--surface);
        box-shadow: var(--shadow);
        overflow: hidden;
    }

    .activation-card::before
    {
        content: '';
        position: absolute;
        top: 0;
        left: 28px;
        right: 28px;
        height: 3px;
        border-radius: 0 0 4px 4px;
        background: var(--brand);
        box-shadow: 0 0 18px var(--accent);
    }

    .activation-card-header
    {
        padding: 26px 24px 20px;
        border-bottom: 1px solid var(--line);
        background-image: linear-gradient(180deg, color-mix(in srgb, var(--accent) 6%, transparent), transparent);
    }

    .activation-card-header h2
    {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
        font-size: 16px;
        font-weight: 800;
        color: var(--ink);
    }

    .activation-card-header p
    {
        margin: 5px 0 0;
        font: 500 12px var(--mono);
        color: var(--muted);
    }

    .activation-card-live
    {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 9px;
        border: 1px solid color-mix(in srgb, var(--accent) 35%, transparent);
        border-radius: 999px;
        background: var(--accent-soft);
        color: var(--accent);
        font: 600 10.5px var(--mono);
        letter-spacing: .1em;
        text-transform: uppercase;
    }

    .activation-card-live::before
    {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
        animation: activation-pulse 2s ease-out infinite;
    }

    /* ---------- Tabel ---------- */
    .activation-table-wrapper
    {
        overflow-x: auto;
    }

    .activation-table
    {
        width: 100%;
        border-collapse: collapse;
        white-space: nowrap;
    }

    .activation-table th
    {
        padding: 13px 20px;
        background: var(--surface-2);
        border-bottom: 1px solid var(--line);
        text-align: left;
        font: 600 11px var(--mono);
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .activation-table td
    {
        padding: 16px 20px;
        border-bottom: 1px solid var(--line);
        font-size: 13px;
        color: var(--muted);
        vertical-align: middle;
    }

    .activation-table tbody tr:last-child td
    {
        border-bottom: 0;
    }

    .activation-table tbody tr
    {
        transition: background .2s;
    }

    .activation-table tbody tr:hover
    {
        background: var(--accent-soft);
    }

    .activation-table tbody tr td:first-child
    {
        box-shadow: inset 0 0 0 var(--accent);
        transition: box-shadow .2s;
    }

    .activation-table tbody tr:hover td:first-child
    {
        box-shadow: inset 3px 0 0 var(--accent);
    }

    .activation-main
    {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .activation-main-icon
    {
        width: 34px;
        height: 34px;
        display: grid;
        place-items: center;
        flex-shrink: 0;
        border: 1px solid color-mix(in srgb, var(--accent) 30%, transparent);
        border-radius: 10px;
        background: var(--accent-soft);
        color: var(--accent);
    }

    .activation-main-icon svg
    {
        width: 16px;
        height: 16px;
    }

    .activation-page .application-name,
    .activation-page .server-name
    {
        font-weight: 700;
        color: var(--ink);
    }

    .activation-page .application-meta
    {
        margin-top: 4px;
        font: 500 11px var(--mono);
        color: var(--muted);
    }

    .activation-page .application-key
    {
        color: var(--accent);
    }

    .activation-chip
    {
        display: inline-block;
        padding: 2px 8px;
        border: 1px solid var(--line);
        border-radius: 6px;
        background: var(--surface-2);
        color: var(--muted);
        font: 500 11px var(--mono);
    }

    .activation-environment
    {
        display: inline-block;
        padding: 4px 10px;
        border: 1px solid var(--line);
        border-radius: 8px;
        background: var(--surface-2);
        color: var(--ink);
        font: 600 11.5px var(--mono);
    }

    .activation-version
    {
        display: inline-block;
        padding: 4px 10px;
        border: 1px dashed color-mix(in srgb, var(--accent) 45%, var(--line));
        border-radius: 8px;
        color: var(--accent);
        font: 600 11.5px var(--mono);
    }

    .activation-date
    {
        color: var(--ink);
        font: 600 12px var(--mono);
    }

    /* ---------- Status ---------- */
    .activation-page .grant-status
    {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 5px 11px;
        border: 1px solid var(--line);
        border-radius: 999px;
        background: var(--surface-2);
        color: var(--muted);
        font: 600 11.5px var(--mono);
    }

    .grant-status-dot
    {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
    }

    .activation-page .status-active
    {
        border-color: color-mix(in srgb, var(--status-ok) 40%, transparent);
        background: color-mix(in srgb, var(--status-ok) 14%, transparent);
        color: var(--status-ok);
        box-shadow: 0 0 18px -8px var(--status-ok);
    }

    .activation-page .status-active .grant-status-dot
    {
        animation: activation-pulse 2s ease-out infinite;
    }

    .activation-page .status-expired
    {
        border-color: color-mix(in srgb, var(--status-warn) 40%, transparent);
        background: color-mix(in srgb, var(--status-warn) 14%, transparent);
        color: var(--status-warn);
    }

    .activation-page .status-revoked
    {
        border-color: color-mix(in srgb, var(--status-bad) 40%, transparent);
        background: color-mix(in srgb, var(--status-bad) 14%, transparent);
        color: var(--status-bad);
    }

    .activation-page .status-not_activated
    {
        border-color: var(--line);
        background: var(--surface-2);
        color: var(--muted);
    }

    /* ---------- Tombol dan form ---------- */
    .activation-button
    {
        height: 38px;
        padding: 0 16px;
        border: 1.5px solid transparent;
        border-radius: 10px;
        font: 700 12.5px var(--font);
        cursor: pointer;
        transition: filter .2s, transform .2s, background .2s, box-shadow .2s;
    }

    .activation-button:hover
    {
        transform: translateY(-1px);
    }

    .activation-button:disabled
    {
        opacity: .6;
        cursor: not-allowed;
        transform: none;
    }

    .activation-button.issue
    {
        border: 0;
        background: var(--brand);
        color: #ffffff;
        box-shadow: 0 10px 22px -12px rgba(47, 76, 240, .9), inset 0 1px 0 rgba(255, 255, 255, .3);
    }

    .activation-button.issue:hover
    {
        filter: brightness(1.08);
    }

    .activation-button.revoke
    {
        border-color: color-mix(in srgb, var(--status-bad) 40%, transparent);
        background: color-mix(in srgb, var(--status-bad) 14%, transparent);
        color: var(--status-bad);
    }

    .activation-button.revoke:hover
    {
        background: color-mix(in srgb, var(--status-bad) 24%, transparent);
        box-shadow: 0 0 22px -8px var(--status-bad);
    }

    .activation-page .revoke-form
    {
        display: flex;
        flex-direction: column;
        gap: 8px;
        min-width: 200px;
    }

    .activation-page .revoke-form input
    {
        width: 100%;
        box-sizing: border-box;
        height: 38px;
        border: 1.5px solid var(--line);
        border-radius: 10px;
        padding: 0 11px;
        background: var(--surface-2);
        color: var(--ink);
        font: 500 12px var(--font);
        outline: none;
        transition: border-color .2s, box-shadow .2s, background .2s;
    }

    .activation-page .revoke-form input::placeholder
    {
        color: var(--muted);
        opacity: .8;
    }

    .activation-page .revoke-form input:focus
    {
        background: var(--surface);
        border-color: var(--status-bad);
        box-shadow: 0 0 0 4px color-mix(in srgb, var(--status-bad) 14%, transparent);
    }

    /* ---------- Kosong ---------- */
    .activation-table td.activation-empty
    {
        padding: 64px 24px;
        text-align: center;
        color: var(--muted);
        font-size: 13px;
    }

    .activation-empty-icon
    {
        width: 56px;
        height: 56px;
        display: grid;
        place-items: center;
        margin: 0 auto 18px;
        border: 1px solid color-mix(in srgb, var(--accent) 40%, transparent);
        border-radius: 16px;
        background: var(--accent-soft);
        color: var(--accent);
        box-shadow: 0 0 30px -8px var(--accent);
    }

    .activation-empty-icon svg
    {
        width: 24px;
        height: 24px;
    }

    /* ---------- SweetAlert (mengikuti tema terang dan gelap) ---------- */
    .swal2-container.swal2-backdrop-show,
    .swal2-container.swal2-noanimation
    {
        background: rgba(8, 12, 24, .6);
        backdrop-filter: blur(4px);
    }

    .swal2-popup.nadi-swal-popup
    {
        position: relative;
        width: 26em;
        max-width: 92vw;
        padding: 30px 24px 22px;
        border: 1px solid var(--line);
        border-radius: 18px;
        background: var(--surface);
        color: var(--ink);
        font-family: var(--font);
        box-shadow: var(--shadow), 0 30px 60px -30px var(--accent);
    }

    .swal2-popup.nadi-swal-popup::before
    {
        content: '';
        position: absolute;
        top: 0;
        left: 28px;
        right: 28px;
        height: 3px;
        border-radius: 0 0 4px 4px;
        background: var(--brand);
        box-shadow: 0 0 18px var(--accent);
    }

    .nadi-swal-title
    {
        padding: 0;
        font: 800 18px var(--font);
        color: var(--ink);
    }

    .nadi-swal-text
    {
        margin: 10px 0 0;
        font: 500 12.5px var(--mono);
        line-height: 1.6;
        color: var(--muted);
        white-space: pre-line;
        word-break: break-word;
    }

    .nadi-swal-actions
    {
        gap: 10px;
        margin-top: 22px;
    }

    .nadi-swal-btn
    {
        height: 40px;
        padding: 0 22px;
        border: 1.5px solid transparent;
        border-radius: 10px;
        font: 700 13px var(--font);
        cursor: pointer;
        transition: filter .2s, transform .2s, background .2s, box-shadow .2s, border-color .2s, color .2s;
    }

    .nadi-swal-btn:hover
    {
        transform: translateY(-1px);
    }

    .nadi-swal-btn:focus-visible
    {
        outline: none;
        box-shadow: 0 0 0 4px var(--accent-soft);
    }

    .nadi-swal-btn-primary
    {
        border: 0;
        background: var(--brand);
        color: #ffffff;
        box-shadow: 0 10px 22px -12px rgba(47, 76, 240, .9), inset 0 1px 0 rgba(255, 255, 255, .3);
    }

    .nadi-swal-btn-success
    {
        border-color: color-mix(in srgb, var(--status-ok) 45%, transparent);
        background: color-mix(in srgb, var(--status-ok) 16%, transparent);
        color: var(--status-ok);
    }

    .nadi-swal-btn-success:hover
    {
        background: color-mix(in srgb, var(--status-ok) 26%, transparent);
        box-shadow: 0 0 22px -8px var(--status-ok);
    }

    .nadi-swal-btn-danger
    {
        border-color: color-mix(in srgb, var(--status-bad) 45%, transparent);
        background: color-mix(in srgb, var(--status-bad) 16%, transparent);
        color: var(--status-bad);
    }

    .nadi-swal-btn-danger:hover
    {
        background: color-mix(in srgb, var(--status-bad) 26%, transparent);
        box-shadow: 0 0 22px -8px var(--status-bad);
    }

    .nadi-swal-btn-secondary
    {
        border-color: var(--line);
        background: transparent;
        color: var(--muted);
    }

    .nadi-swal-btn-secondary:hover
    {
        border-color: var(--accent);
        color: var(--accent);
    }

    .nadi-swal-popup .swal2-icon.swal2-question
    {
        border-color: color-mix(in srgb, var(--accent) 45%, transparent);
        color: var(--accent);
    }

    .nadi-swal-popup .swal2-icon.swal2-warning
    {
        border-color: color-mix(in srgb, var(--status-warn) 45%, transparent);
        color: var(--status-warn);
    }

    .nadi-swal-popup .swal2-icon.swal2-error
    {
        border-color: color-mix(in srgb, var(--status-bad) 45%, transparent);
        color: var(--status-bad);
    }

    .nadi-swal-popup .swal2-icon.swal2-error [class^='swal2-x-mark-line']
    {
        background-color: var(--status-bad);
    }

    .nadi-swal-popup .swal2-loader
    {
        border-color: var(--accent) transparent var(--accent) transparent;
    }

    .swal2-popup.nadi-swal-toast
    {
        padding: 12px 16px;
        border: 1px solid var(--line);
        border-left: 3px solid var(--status-ok);
        border-radius: 12px;
        background: var(--surface);
        color: var(--ink);
        font-family: var(--font);
        box-shadow: var(--shadow), 0 18px 40px -22px var(--status-ok);
    }

    .nadi-swal-toast-title
    {
        margin: 0 0 0 10px;
        font: 500 12.5px var(--mono);
        color: var(--ink);
    }

    .nadi-swal-toast .swal2-icon.swal2-success
    {
        border-color: color-mix(in srgb, var(--status-ok) 40%, transparent);
        color: var(--status-ok);
    }

    .nadi-swal-toast .swal2-timer-progress-bar
    {
        background: var(--status-ok);
    }

    .nadi-swal-toast .swal2-success-ring
    {
        border-color: color-mix(in srgb, var(--status-ok) 30%, transparent);
    }

    .nadi-swal-toast [class^='swal2-success-line']
    {
        background-color: var(--status-ok);
    }

    .nadi-swal-toast [class^='swal2-success-circular-line'],
    .nadi-swal-toast .swal2-success-fix
    {
        background: transparent;
    }

    /* ---------- Animasi ---------- */
    @keyframes activation-pulse
    {
        0%
        {
            box-shadow: 0 0 0 0 color-mix(in srgb, currentColor 55%, transparent);
        }

        100%
        {
            box-shadow: 0 0 0 8px transparent;
        }
    }

    @media (prefers-reduced-motion: reduce)
    {
        .activation-card-live::before,
        .activation-page .status-active .grant-status-dot
        {
            animation: none;
        }

        .activation-stat,
        .activation-stat-meter-fill,
        .activation-button,
        .nadi-swal-btn
        {
            transition: none;
        }
    }

    @media (max-width: 1000px)
    {
        .activation-summary
        {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 600px)
    {
        .activation-summary
        {
            grid-template-columns: 1fr;
        }

        .activation-card-header
        {
            padding: 22px 16px 16px;
        }
    }

</style>

@endpush