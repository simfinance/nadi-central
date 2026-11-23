@extends('layouts.app')

@section('title', 'Approval Workflow - NADI Console')

@section('breadcrumb', 'Management / Approval Workflow')

@section('content')

@php
    $statsBase = max((int) $stats['total'], 1);
    $pendingPercent = round(((int) $stats['pending'] / $statsBase) * 100);
    $approvedPercent = round(((int) $stats['approved'] / $statsBase) * 100);
    $rejectedPercent = round(((int) $stats['rejected'] / $statsBase) * 100);
@endphp

<div class="approval-page">

    <div class="page-header">
        <div>
            <h1 class="page-title">Approval Workflow</h1>
            <p class="page-description text-muted">
                Review and manage application registration requests.
            </p>
        </div>
    </div>

    <div class="approval-stats">
        <div class="approval-stat-card">
            <div class="approval-stat-top">
                <div class="approval-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="5" y="3" width="14" height="18" rx="2"/>
                        <path d="M9 8h6M9 12h6M9 16h3"/>
                    </svg>
                </div>
                <div class="approval-stat-label">Total Registrations</div>
            </div>
            <div class="approval-stat-value">{{ number_format($stats['total']) }}</div>
            <div class="approval-stat-description">Requests submitted to Central</div>
            <div class="approval-stat-meter">
                <div class="approval-stat-meter-fill" style="width: 100%;"></div>
            </div>
        </div>

        <div class="approval-stat-card">
            <div class="approval-stat-top">
                <div class="approval-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M12 7.5V12l3 2"/>
                    </svg>
                </div>
                <div class="approval-stat-label">Pending Review</div>
            </div>
            <div class="approval-stat-value">
                {{ number_format($stats['pending']) }}
                <span class="approval-stat-percent">{{ $pendingPercent }}%</span>
            </div>
            <div class="approval-stat-description">Waiting for review</div>
            <div class="approval-stat-meter">
                <div class="approval-stat-meter-fill" style="width: {{ $pendingPercent }}%;"></div>
            </div>
        </div>

        <div class="approval-stat-card">
            <div class="approval-stat-top">
                <div class="approval-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M8 12.5l3 3 5-6"/>
                    </svg>
                </div>
                <div class="approval-stat-label">Approved</div>
            </div>
            <div class="approval-stat-value">
                {{ number_format($stats['approved']) }}
                <span class="approval-stat-percent">{{ $approvedPercent }}%</span>
            </div>
            <div class="approval-stat-description">Status: approved</div>
            <div class="approval-stat-meter">
                <div class="approval-stat-meter-fill" style="width: {{ $approvedPercent }}%;"></div>
            </div>
        </div>

        <div class="approval-stat-card">
            <div class="approval-stat-top">
                <div class="approval-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M9 9l6 6M15 9l-6 6"/>
                    </svg>
                </div>
                <div class="approval-stat-label">Rejected</div>
            </div>
            <div class="approval-stat-value">
                {{ number_format($stats['rejected']) }}
                <span class="approval-stat-percent">{{ $rejectedPercent }}%</span>
            </div>
            <div class="approval-stat-description">Status: rejected</div>
            <div class="approval-stat-meter">
                <div class="approval-stat-meter-fill" style="width: {{ $rejectedPercent }}%;"></div>
            </div>
        </div>
    </div>

    <div class="approval-panel">

        <div class="approval-panel-header">
            <div class="approval-panel-title">
                Registration Review
                <span class="approval-panel-live">Live</span>
            </div>
            <div class="approval-panel-subtitle">
                {{ $registrations->total() }} registration(s) found
            </div>
        </div>

        <div class="approval-toolbar">
            <form method="GET" action="{{ route('management.approval-workflow') }}">
                <div class="approval-filter-group">
                    <div class="approval-search-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="11" cy="11" r="7"/>
                            <path d="M20 20l-3.5-3.5"/>
                        </svg>

                        <input
                            type="text"
                            name="search"
                            value="{{ $search }}"
                            placeholder="Search application, agent, registration..."
                            class="approval-search"
                        >
                    </div>

                    <select name="status" class="approval-status-filter">
                        <option value="">All Status</option>
                        <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>
                            Pending
                        </option>
                        <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>
                            Approved
                        </option>
                        <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>
                            Rejected
                        </option>
                    </select>

                    <button type="submit" class="approval-btn approval-btn-secondary">
                        Filter
                    </button>

                    <a
                        href="{{ route('management.approval-workflow') }}"
                        class="approval-btn approval-btn-secondary"
                    >
                        Reset
                    </a>
                </div>
            </form>
        </div>

        @if($registrations->count() > 0)

            <div class="approval-table-wrapper">
                <table class="approval-table">
                    <thead>
                        <tr>
                            <th>Application</th>
                            <th>Server / Agent</th>
                            <th>Registration</th>
                            <th>Requested</th>
                            <th>Status</th>
                            <th>Review Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($registrations as $registration)

                            @php
                                $application = $registration->application;
                                $agent = $registration->agent;
                                $server = $agent?->server;
                            @endphp

                            <tr>
                                <td>
                                    <div class="approval-main">
                                        <div class="approval-main-icon">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="5" y="3" width="14" height="18" rx="2"/>
                                                <path d="M9 8h6M9 12h6M9 16h3"/>
                                            </svg>
                                        </div>

                                        <div>
                                            <div class="approval-app-name">
                                                {{ $application?->name ?? 'Unknown Application' }}
                                            </div>

                                            <div class="approval-subtext approval-key">
                                                {{ $application?->application_key ?? '-' }}
                                            </div>

                                            <div class="approval-subtext">
                                                {{ $application?->framework ?? '-' }}
                                                @if($application?->php_version)
                                                    <span class="approval-chip">PHP {{ $application->php_version }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <div class="approval-server-name">
                                        {{ $server?->hostname ?? '-' }}
                                    </div>

                                    <div class="approval-subtext">
                                        <span class="approval-chip">
                                            {{ $server?->ip_address ?? '-' }}
                                        </span>
                                    </div>

                                    <div class="approval-subtext">
                                        Agent:
                                        {{ $agent?->agent_instance_id ?? '-' }}
                                    </div>

                                    <div class="approval-subtext">
                                        Version {{ $agent?->agent_version ?? '-' }}
                                    </div>
                                </td>

                                <td>
                                    <div class="approval-id">
                                        {{ $registration->id }}
                                    </div>
                                </td>

                                <td>
                                    <div class="approval-date">
                                        {{ $registration->requested_at?->format('d M Y') ?? '-' }}
                                    </div>

                                    <div class="approval-subtext">
                                        {{ $registration->requested_at?->format('H:i:s') ?? '' }}
                                        @if($registration->requested_at)
                                            · {{ $registration->requested_at->diffForHumans() }}
                                        @endif
                                    </div>
                                </td>

                                <td>
                                    @if($registration->registration_status === 'pending')
                                        <span class="approval-badge approval-badge-pending">
                                            <span class="approval-badge-dot"></span>
                                            Pending
                                        </span>
                                    @elseif($registration->registration_status === 'approved')
                                        <span class="approval-badge approval-badge-approved">
                                            <span class="approval-badge-dot"></span>
                                            Approved
                                        </span>
                                    @elseif($registration->registration_status === 'rejected')
                                        <span class="approval-badge approval-badge-rejected">
                                            <span class="approval-badge-dot"></span>
                                            Rejected
                                        </span>
                                    @else
                                        <span class="approval-badge">
                                            <span class="approval-badge-dot"></span>
                                            {{ ucfirst($registration->registration_status) }}
                                        </span>
                                    @endif

                                    @if($registration->reviewed_at)
                                        <div class="approval-subtext approval-review-info">
                                            Reviewed {{ $registration->reviewed_at->format('d M Y H:i') }}
                                        </div>

                                        <div class="approval-subtext">
                                            By {{ $registration->reviewer?->name ?? '-' }}
                                        </div>
                                    @endif

                                    @if($registration->registration_status === 'rejected' && $registration->rejection_reason)
                                        <div class="approval-rejection-reason">
                                            {{ $registration->rejection_reason }}
                                        </div>
                                    @endif
                                </td>

                                <td>
                                    @if($registration->registration_status === 'pending')

                                        <div class="approval-actions">

                                            <form
                                                method="POST"
                                                action="{{ route('management.approval-workflow.approve', $registration->id) }}"
                                                data-confirm-title="Approve this registration?"
                                                data-confirm-detail="{{ $application?->name ?? 'Unknown Application' }} · {{ $server?->hostname ?? '-' }}"
                                                data-confirm-button="Approve"
                                                data-confirm-variant="success"
                                            >
                                                @csrf

                                                <button
                                                    type="submit"
                                                    class="approval-btn approval-btn-success"
                                                >
                                                    Approve
                                                </button>
                                            </form>

                                            <form
                                                method="POST"
                                                action="{{ route('management.approval-workflow.reject', $registration->id) }}"
                                                class="approval-reject-form"
                                                novalidate
                                                data-confirm-title="Reject this registration?"
                                                data-confirm-detail="{{ $application?->name ?? 'Unknown Application' }} · {{ $server?->hostname ?? '-' }}"
                                                data-confirm-button="Reject"
                                                data-confirm-variant="danger"
                                            >
                                                @csrf

                                                <textarea
                                                    name="reason"
                                                    placeholder="Reason for rejection..."
                                                    required
                                                    minlength="5"
                                                    maxlength="5000"
                                                    rows="2"
                                                    class="approval-reject-reason"
                                                ></textarea>

                                                <button
                                                    type="submit"
                                                    class="approval-btn approval-btn-danger"
                                                >
                                                    Reject
                                                </button>
                                            </form>

                                        </div>

                                    @else

                                        <span class="approval-action-completed">
                                            Review completed
                                        </span>

                                    @endif
                                </td>
                            </tr>

                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="approval-pagination">
                {{ $registrations->links() }}
            </div>

        @else

            <div class="approval-empty">
                <div class="approval-empty-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="5" y="3" width="14" height="18" rx="2"/>
                        <path d="M9 8h6M9 12h6M9 16h3"/>
                    </svg>
                </div>

                <div class="approval-empty-title">
                    No registrations found
                </div>

                <div class="approval-empty-description">
                    No registration matches the current search or filter.
                </div>
            </div>

        @endif

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
     * Tanpa SweetAlert, aksi persetujuan tidak boleh berjalan tanpa konfirmasi.
     * Form diblokir dan penyebabnya dicatat di konsol.
     */
    if (typeof Swal === 'undefined')
    {
        document.addEventListener('submit', function (event)
        {
            if (event.target.hasAttribute && event.target.hasAttribute('data-confirm-title'))
            {
                event.preventDefault();

                console.error('SweetAlert2 failed to load. The approval action was blocked.');
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

    /* ---------- Validasi alasan penolakan ---------- */
    function validateReason(field)
    {
        const reason = field.value.trim();
        const min = Number(field.getAttribute('minlength')) || 0;
        const max = Number(field.getAttribute('maxlength')) || 0;

        if (reason.length === 0)
        {
            return 'Please provide a reason for rejection.';
        }

        if (reason.length < min)
        {
            return 'The reason must be at least ' + min + ' characters.';
        }

        if (max && reason.length > max)
        {
            return 'The reason must not exceed ' + max + ' characters.';
        }

        return '';
    }

    function excerpt(value, limit)
    {
        const text = value.trim();

        return text.length > limit ? text.slice(0, limit) + '…' : text;
    }

    /* ---------- Konfirmasi approve dan reject ---------- */
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
        const reasonField = form.querySelector('textarea[name="reason"]');

        let detail = form.dataset.confirmDetail || '';

        if (reasonField)
        {
            const problem = validateReason(reasonField);

            if (problem)
            {
                await modal.fire({
                    icon: 'warning',
                    title: 'Reason required',
                    text: problem,
                    confirmButtonText: 'OK'
                });

                reasonField.focus();

                return;
            }

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

        document.querySelectorAll('.approval-actions button').forEach(function (button)
        {
            button.disabled = true;
        });

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

        document.querySelectorAll('.approval-actions button').forEach(function (button)
        {
            button.disabled = false;
        });
    });
});
</script>

@endsection

@push('styles')

<style>
    .approval-page,
    .swal2-container
    {
        --status-ok: #16a34a;
        --status-warn: #d97706;
        --status-bad: #dc2626;
    }

    .approval-page
    {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .approval-page .page-header
    {
        margin-bottom: 0;
    }

    /* ---------- Ringkasan ---------- */
    .approval-stats
    {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 18px;
    }

    .approval-stat-card
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

    .approval-stat-card:hover
    {
        transform: translateY(-3px);
        border-color: color-mix(in srgb, var(--accent) 45%, var(--line));
        box-shadow: var(--shadow), 0 18px 40px -22px var(--accent);
    }

    .approval-stat-card::before
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
    .approval-stat-card::after
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

    .approval-stat-top
    {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
    }

    .approval-stat-icon
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

    .approval-stat-icon svg
    {
        width: 19px;
        height: 19px;
    }

    .approval-stat-label
    {
        font: 600 11.5px var(--mono);
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .approval-stat-value
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

    .approval-stat-percent
    {
        padding: 3px 9px;
        border: 1px solid color-mix(in srgb, var(--accent) 35%, transparent);
        border-radius: 999px;
        background: var(--accent-soft);
        color: var(--accent);
        font: 600 11px var(--mono);
        letter-spacing: 0;
    }

    .approval-stat-description
    {
        margin-top: 8px;
        font: 500 11.5px var(--mono);
        color: var(--muted);
    }

    .approval-stat-meter
    {
        height: 4px;
        margin-top: 16px;
        border-radius: 999px;
        background: var(--surface-2);
        overflow: hidden;
    }

    .approval-stat-meter-fill
    {
        height: 100%;
        border-radius: 999px;
        background: var(--brand);
        box-shadow: 0 0 12px var(--accent);
        transition: width .8s cubic-bezier(.2, .8, .2, 1);
    }

    /* ---------- Panel ---------- */
    .approval-panel
    {
        position: relative;
        border: 1px solid var(--line);
        border-radius: 18px;
        background: var(--surface);
        box-shadow: var(--shadow);
        overflow: hidden;
    }

    .approval-panel::before
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

    .approval-panel-header
    {
        padding: 26px 24px 20px;
        border-bottom: 1px solid var(--line);
        background-image: linear-gradient(180deg, color-mix(in srgb, var(--accent) 6%, transparent), transparent);
    }

    .approval-panel-title
    {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 16px;
        font-weight: 800;
        color: var(--ink);
    }

    .approval-panel-subtitle
    {
        margin-top: 5px;
        font: 500 12px var(--mono);
        color: var(--muted);
    }

    .approval-panel-live
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

    .approval-panel-live::before
    {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
        animation: approval-pulse 2s ease-out infinite;
    }

    /* ---------- Toolbar ---------- */
    .approval-toolbar
    {
        padding: 18px 24px;
        border-bottom: 1px solid var(--line);
    }

    .approval-filter-group
    {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .approval-search-wrap
    {
        position: relative;
        flex: 1;
        min-width: 240px;
    }

    .approval-search-wrap svg
    {
        position: absolute;
        top: 50%;
        left: 12px;
        width: 16px;
        height: 16px;
        transform: translateY(-50%);
        color: var(--muted);
        pointer-events: none;
        transition: color .2s;
    }

    .approval-search-wrap:focus-within svg
    {
        color: var(--accent);
    }

    .approval-search,
    .approval-status-filter
    {
        height: 40px;
        border: 1.5px solid var(--line);
        border-radius: 10px;
        padding: 0 12px;
        background: var(--surface-2);
        color: var(--ink);
        font: 500 13px var(--font);
        outline: none;
        transition: border-color .2s, box-shadow .2s, background .2s;
    }

    .approval-search
    {
        width: 100%;
        box-sizing: border-box;
        padding-left: 36px;
    }

    .approval-search::placeholder
    {
        color: var(--muted);
        opacity: .8;
    }

    .approval-status-filter
    {
        min-width: 150px;
    }

    .approval-search:focus,
    .approval-status-filter:focus
    {
        background: var(--surface);
        border-color: var(--accent);
        box-shadow: 0 0 0 4px var(--accent-soft), 0 0 22px -8px var(--accent);
    }

    /* ---------- Tombol ---------- */
    .approval-btn
    {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 40px;
        padding: 0 18px;
        border: 1.5px solid transparent;
        border-radius: 10px;
        font: 700 13px var(--font);
        text-decoration: none;
        cursor: pointer;
        transition: filter .2s, transform .2s, color .2s, border-color .2s, background .2s;
    }

    .approval-btn:hover
    {
        transform: translateY(-1px);
    }

    .approval-btn:disabled
    {
        opacity: .6;
        cursor: not-allowed;
        transform: none;
    }

    .approval-btn-secondary
    {
        border-color: var(--line);
        background: transparent;
        color: var(--muted);
    }

    .approval-btn-secondary:hover
    {
        border-color: var(--accent);
        color: var(--accent);
    }

    .approval-btn-success
    {
        border-color: color-mix(in srgb, var(--status-ok) 40%, transparent);
        background: color-mix(in srgb, var(--status-ok) 14%, transparent);
        color: var(--status-ok);
    }

    .approval-btn-success:hover
    {
        background: color-mix(in srgb, var(--status-ok) 24%, transparent);
        box-shadow: 0 0 22px -8px var(--status-ok);
    }

    .approval-btn-danger
    {
        border-color: color-mix(in srgb, var(--status-bad) 40%, transparent);
        background: color-mix(in srgb, var(--status-bad) 14%, transparent);
        color: var(--status-bad);
    }

    .approval-btn-danger:hover
    {
        background: color-mix(in srgb, var(--status-bad) 24%, transparent);
        box-shadow: 0 0 22px -8px var(--status-bad);
    }

    /* ---------- Tabel ---------- */
    .approval-table-wrapper
    {
        margin-top: 16px;
        overflow-x: auto;
    }

    .approval-table
    {
        width: 100%;
        border-collapse: collapse;
        min-width: 1100px;
    }

    .approval-table th,
    .approval-table td
    {
        padding: 16px 20px;
        border-bottom: 1px solid var(--line);
        text-align: left;
        vertical-align: top;
    }

    .approval-table th
    {
        background: var(--surface-2);
        font: 600 11px var(--mono);
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--muted);
        white-space: nowrap;
    }

    .approval-table td
    {
        color: var(--muted);
        font-size: 13px;
    }

    .approval-table tbody tr:last-child td
    {
        border-bottom: 0;
    }

    .approval-table tbody tr
    {
        transition: background .2s;
    }

    .approval-table tbody tr:hover
    {
        background: var(--accent-soft);
    }

    .approval-table tbody tr td:first-child
    {
        box-shadow: inset 0 0 0 var(--accent);
        transition: box-shadow .2s;
    }

    .approval-table tbody tr:hover td:first-child
    {
        box-shadow: inset 3px 0 0 var(--accent);
    }

    .approval-main
    {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .approval-main-icon
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

    .approval-main-icon svg
    {
        width: 16px;
        height: 16px;
    }

    .approval-app-name,
    .approval-server-name
    {
        margin-bottom: 4px;
        color: var(--ink);
        font-weight: 700;
    }

    .approval-subtext
    {
        margin-top: 4px;
        font: 500 11px var(--mono);
        color: var(--muted);
        word-break: break-word;
    }

    .approval-key
    {
        color: var(--accent);
    }

    .approval-chip
    {
        display: inline-block;
        padding: 2px 8px;
        border: 1px solid var(--line);
        border-radius: 6px;
        background: var(--surface-2);
        color: var(--muted);
        font: 500 11px var(--mono);
    }

    .approval-id
    {
        display: inline-block;
        max-width: 180px;
        padding: 4px 10px;
        border: 1px dashed color-mix(in srgb, var(--accent) 45%, var(--line));
        border-radius: 8px;
        color: var(--accent);
        font: 600 11px var(--mono);
        word-break: break-all;
    }

    .approval-date
    {
        color: var(--ink);
        font: 600 12px var(--mono);
    }

    /* ---------- Badge ---------- */
    .approval-badge
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

    .approval-badge-dot
    {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
    }

    .approval-badge-pending
    {
        border-color: color-mix(in srgb, var(--status-warn) 40%, transparent);
        background: color-mix(in srgb, var(--status-warn) 14%, transparent);
        color: var(--status-warn);
    }

    .approval-badge-pending .approval-badge-dot
    {
        animation: approval-pulse 2s ease-out infinite;
    }

    .approval-badge-approved
    {
        border-color: color-mix(in srgb, var(--status-ok) 40%, transparent);
        background: color-mix(in srgb, var(--status-ok) 14%, transparent);
        color: var(--status-ok);
        box-shadow: 0 0 18px -8px var(--status-ok);
    }

    .approval-badge-rejected
    {
        border-color: color-mix(in srgb, var(--status-bad) 40%, transparent);
        background: color-mix(in srgb, var(--status-bad) 14%, transparent);
        color: var(--status-bad);
    }

    .approval-review-info
    {
        margin-top: 8px;
    }

    .approval-rejection-reason
    {
        max-width: 240px;
        margin-top: 8px;
        padding: 6px 10px;
        border-left: 2px solid var(--status-bad);
        border-radius: 0 8px 8px 0;
        background: color-mix(in srgb, var(--status-bad) 8%, transparent);
        color: var(--muted);
        font: 500 11px var(--mono);
        line-height: 1.5;
        white-space: pre-wrap;
        word-break: break-word;
    }

    /* ---------- Aksi ---------- */
    .approval-actions
    {
        display: flex;
        flex-direction: column;
        gap: 12px;
        min-width: 200px;
    }

    .approval-actions form
    {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .approval-reject-reason
    {
        width: 100%;
        box-sizing: border-box;
        resize: vertical;
        border: 1.5px solid var(--line);
        border-radius: 10px;
        padding: 9px 11px;
        background: var(--surface-2);
        color: var(--ink);
        font: 500 12px var(--font);
        outline: none;
        transition: border-color .2s, box-shadow .2s, background .2s;
    }

    .approval-reject-reason::placeholder
    {
        color: var(--muted);
        opacity: .8;
    }

    .approval-reject-reason:focus
    {
        background: var(--surface);
        border-color: var(--status-bad);
        box-shadow: 0 0 0 4px color-mix(in srgb, var(--status-bad) 14%, transparent);
    }

    .approval-action-completed
    {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border: 1px dashed var(--line);
        border-radius: 8px;
        color: var(--muted);
        font: 500 11.5px var(--mono);
    }

    /* ---------- Pagination ---------- */
    .approval-pagination
    {
        padding: 18px 24px;
        border-top: 1px solid var(--line);
        font-size: 13px;
        color: var(--muted);
    }

    .approval-pagination svg
    {
        width: 16px;
        height: 16px;
    }

    .approval-pagination p
    {
        margin: 0;
        color: var(--muted);
    }

    .approval-pagination a
    {
        color: var(--accent);
        text-decoration: none;
    }

    /* ---------- Kosong ---------- */
    .approval-empty
    {
        padding: 64px 24px;
        text-align: center;
    }

    .approval-empty-icon
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

    .approval-empty-icon svg
    {
        width: 24px;
        height: 24px;
    }

    .approval-empty-title
    {
        margin-bottom: 8px;
        color: var(--ink);
        font-size: 16px;
        font-weight: 800;
    }

    .approval-empty-description
    {
        color: var(--muted);
        font-size: 13px;
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
    @keyframes approval-pulse
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
        .approval-panel-live::before,
        .approval-badge-pending .approval-badge-dot
        {
            animation: none;
        }

        .approval-stat-card,
        .approval-stat-meter-fill,
        .approval-btn,
        .nadi-swal-btn
        {
            transition: none;
        }
    }

    @media (max-width: 900px)
    {
        .approval-stats
        {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 600px)
    {
        .approval-stats
        {
            grid-template-columns: 1fr;
        }

        .approval-panel-header
        {
            padding: 22px 16px 16px;
        }

        .approval-toolbar
        {
            padding: 12px 16px;
        }

        .approval-search-wrap
        {
            min-width: 100%;
        }

        .approval-filter-group
        {
            flex-direction: column;
            align-items: stretch;
        }

        .approval-filter-group > *
        {
            width: 100%;
        }
    }
</style>

@endpush