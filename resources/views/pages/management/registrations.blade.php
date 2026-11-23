@extends('layouts.app')

@section('title', 'Registrations - NADI Console')

@section('breadcrumb', 'Management / Registrations')

@section('content')

@php
    $totalBase = max((int) $totalRegistrations, 1);
    $pendingPercent = round(((int) $pendingRegistrations / $totalBase) * 100);
    $approvedPercent = round(((int) $approvedRegistrations / $totalBase) * 100);
    $rejectedPercent = round(((int) $rejectedRegistrations / $totalBase) * 100);
@endphp

<div class="registrations-page">

    <div class="page-header">
        <div>
            <h1 class="page-title">Registrations</h1>
            <p class="page-description">Review application registration requests submitted to NADI Central.</p>
        </div>
    </div>

    <div class="registration-summary">

        <div class="registration-summary-card">
            <div class="registration-summary-top">
                <div class="registration-summary-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="5" y="3" width="14" height="18" rx="2"/>
                        <path d="M9 8h6M9 12h6M9 16h3"/>
                    </svg>
                </div>
                <div class="registration-summary-label">Total Registrations</div>
            </div>
            <div class="registration-summary-value">
                {{ number_format($totalRegistrations) }}
            </div>
            <div class="registration-summary-description">Requests submitted to Central</div>
            <div class="registration-summary-meter">
                <div class="registration-summary-meter-fill" style="width: 100%;"></div>
            </div>
        </div>

        <div class="registration-summary-card">
            <div class="registration-summary-top">
                <div class="registration-summary-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M12 7.5V12l3 2"/>
                    </svg>
                </div>
                <div class="registration-summary-label">Pending Review</div>
            </div>
            <div class="registration-summary-value">
                {{ number_format($pendingRegistrations) }}
                <span class="registration-summary-percent">{{ $pendingPercent }}%</span>
            </div>
            <div class="registration-summary-description">Waiting for review</div>
            <div class="registration-summary-meter">
                <div class="registration-summary-meter-fill" style="width: {{ $pendingPercent }}%;"></div>
            </div>
        </div>

        <div class="registration-summary-card">
            <div class="registration-summary-top">
                <div class="registration-summary-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M8 12.5l3 3 5-6"/>
                    </svg>
                </div>
                <div class="registration-summary-label">Approved</div>
            </div>
            <div class="registration-summary-value">
                {{ number_format($approvedRegistrations) }}
                <span class="registration-summary-percent">{{ $approvedPercent }}%</span>
            </div>
            <div class="registration-summary-description">Status: approved</div>
            <div class="registration-summary-meter">
                <div class="registration-summary-meter-fill" style="width: {{ $approvedPercent }}%;"></div>
            </div>
        </div>

        <div class="registration-summary-card">
            <div class="registration-summary-top">
                <div class="registration-summary-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M9 9l6 6M15 9l-6 6"/>
                    </svg>
                </div>
                <div class="registration-summary-label">Rejected</div>
            </div>
            <div class="registration-summary-value">
                {{ number_format($rejectedRegistrations) }}
                <span class="registration-summary-percent">{{ $rejectedPercent }}%</span>
            </div>
            <div class="registration-summary-description">Status: rejected</div>
            <div class="registration-summary-meter">
                <div class="registration-summary-meter-fill" style="width: {{ $rejectedPercent }}%;"></div>
            </div>
        </div>

    </div>

    <div class="registration-panel">

        <div class="registration-panel-header">
            <div>
                <h2>
                    Registration Requests
                    <span class="registration-panel-live">Live</span>
                </h2>
                <p>Application registration history and review status.</p>
            </div>
        </div>

        <form
            method="GET"
            action="{{ route('management.registrations') }}"
            class="registration-filter-form"
        >

            <div class="registration-search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/>
                    <path d="M20 20l-3.5-3.5"/>
                </svg>

                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Search application, server, IP, or ID..."
                >
            </div>

            <select name="status">
                <option value="">All Statuses</option>

                @foreach ($statuses as $status)
                    <option
                        value="{{ $status }}"
                        {{ request('status') === $status ? 'selected' : '' }}
                    >
                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                    </option>
                @endforeach
            </select>

            <button type="submit" class="registration-filter-button">
                Filter
            </button>

            @if (request()->hasAny(['search', 'status']))
                <a
                    href="{{ route('management.registrations') }}"
                    class="registration-reset-button"
                >
                    Reset
                </a>
            @endif

        </form>

        <div class="registration-table-wrapper">

            <table class="registration-table">

                <thead>
                    <tr>
                        <th>Application</th>
                        <th>Server / Agent</th>
                        <th>Registration</th>
                        <th>Requested At</th>
                        <th>Reviewed By</th>
                        <th>Reviewed At</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse ($registrations as $registration)

                        <tr>

                            <td>
                                <div class="registration-main">
                                    <div class="registration-main-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="5" y="3" width="14" height="18" rx="2"/>
                                            <path d="M9 8h6M9 12h6M9 16h3"/>
                                        </svg>
                                    </div>

                                    <div>
                                        <div class="registration-primary">
                                            {{ $registration->application?->application_name ?? 'Unknown Application' }}
                                        </div>

                                        <div class="registration-secondary registration-key">
                                            {{ $registration->application?->application_key ?? '—' }}
                                        </div>

                                        <div class="registration-secondary">
                                            Framework:
                                            <span class="registration-chip">
                                                {{ $registration->application?->framework ?? '—' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div class="registration-primary">
                                    {{ $registration->agent?->server?->hostname ?? 'Unknown Server' }}
                                </div>

                                <div class="registration-secondary">
                                    <span class="registration-chip">
                                        {{ $registration->agent?->server?->ip_address ?? 'No IP address' }}
                                    </span>
                                </div>

                                <div class="registration-secondary">
                                    Agent:
                                    {{ $registration->agent?->agent_version ?? '—' }}
                                </div>
                            </td>

                            <td>
                                <div class="registration-id" title="{{ $registration->id }}">
                                    {{ $registration->id }}
                                </div>
                            </td>

                            <td>
                                @if ($registration->requested_at)
                                    <div class="registration-primary registration-date">
                                        {{ $registration->requested_at->format('d M Y') }}
                                    </div>

                                    <div class="registration-secondary">
                                        {{ $registration->requested_at->format('H:i:s') }}
                                        · {{ $registration->requested_at->diffForHumans() }}
                                    </div>
                                @else
                                    <span class="registration-secondary">
                                        —
                                    </span>
                                @endif
                            </td>

                            <td>
                                @if ($registration->reviewer)
                                    <div class="registration-primary">
                                        {{ $registration->reviewer->name }}
                                    </div>

                                    <div class="registration-secondary">
                                        {{ $registration->reviewer->username }}
                                    </div>
                                @else
                                    <span class="registration-secondary">
                                        Not reviewed
                                    </span>
                                @endif
                            </td>

                            <td>
                                @if ($registration->reviewed_at)
                                    <div class="registration-primary registration-date">
                                        {{ $registration->reviewed_at->format('d M Y') }}
                                    </div>

                                    <div class="registration-secondary">
                                        {{ $registration->reviewed_at->format('H:i:s') }}
                                    </div>
                                @else
                                    <span class="registration-secondary">
                                        —
                                    </span>
                                @endif
                            </td>

                            <td>
                                <span class="registration-status status-{{ strtolower($registration->registration_status) }}">
                                    <span class="registration-status-dot"></span>
                                    {{ ucfirst(str_replace('_', ' ', $registration->registration_status)) }}
                                </span>

                                @if ($registration->registration_status === 'rejected' && $registration->rejection_reason)
                                    <div class="registration-rejection">
                                        {{ $registration->rejection_reason }}
                                    </div>
                                @endif
                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="7">
                                <div class="registration-empty-state">

                                    <div class="registration-empty-icon">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.7"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        >
                                            <rect x="5" y="3" width="14" height="18" rx="2"/>
                                            <path d="M9 8h6M9 12h6M9 16h3"/>
                                        </svg>
                                    </div>

                                    <h3>No registrations found</h3>

                                    <p>
                                        Registration requests will appear here when an agent submits an application.
                                    </p>

                                </div>
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

        @if ($registrations->hasPages())
            <div class="registration-pagination">
                {{ $registrations->links() }}
            </div>
        @endif

    </div>

</div>

@endsection

@push('styles')

<style>
    .registrations-page
    {
        --status-ok: #16a34a;
        --status-warn: #d97706;
        --status-bad: #dc2626;
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .registrations-page .page-header
    {
        margin-bottom: 0;
    }

    /* ---------- Ringkasan ---------- */
    .registration-summary
    {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 18px;
    }

    .registration-summary-card
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

    .registration-summary-card:hover
    {
        transform: translateY(-3px);
        border-color: color-mix(in srgb, var(--accent) 45%, var(--line));
        box-shadow: var(--shadow), 0 18px 40px -22px var(--accent);
    }

    .registration-summary-card::before
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
    .registration-summary-card::after
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

    .registration-summary-top
    {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
    }

    .registration-summary-icon
    {
        width: 40px;
        height: 40px;
        display: grid;
        place-items: center;
        border: 1px solid color-mix(in srgb, var(--accent) 40%, transparent);
        border-radius: 12px;
        background: var(--accent-soft);
        color: var(--accent);
        box-shadow: 0 0 22px -6px var(--accent), inset 0 0 12px -6px var(--accent);
    }

    .registration-summary-icon svg
    {
        width: 19px;
        height: 19px;
    }

    .registration-summary-label
    {
        font: 600 11.5px var(--mono);
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .registration-summary-value
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

    .registration-summary-percent
    {
        padding: 3px 9px;
        border: 1px solid color-mix(in srgb, var(--accent) 35%, transparent);
        border-radius: 999px;
        background: var(--accent-soft);
        color: var(--accent);
        font: 600 11px var(--mono);
        letter-spacing: 0;
    }

    .registration-summary-description
    {
        margin-top: 8px;
        font: 500 11.5px var(--mono);
        color: var(--muted);
    }

    .registration-summary-meter
    {
        height: 4px;
        margin-top: 16px;
        border-radius: 999px;
        background: var(--surface-2);
        overflow: hidden;
    }

    .registration-summary-meter-fill
    {
        height: 100%;
        border-radius: 999px;
        background: var(--brand);
        box-shadow: 0 0 12px var(--accent);
        transition: width .8s cubic-bezier(.2, .8, .2, 1);
    }

    /* ---------- Panel ---------- */
    .registration-panel
    {
        position: relative;
        border: 1px solid var(--line);
        border-radius: 18px;
        background: var(--surface);
        box-shadow: var(--shadow);
        overflow: hidden;
    }

    .registration-panel::before
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

    .registration-panel-header
    {
        padding: 26px 24px 20px;
        border-bottom: 1px solid var(--line);
        background-image: linear-gradient(180deg, color-mix(in srgb, var(--accent) 6%, transparent), transparent);
    }

    .registration-panel-header h2
    {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
        font-size: 16px;
        font-weight: 800;
        color: var(--ink);
    }

    .registration-panel-header p
    {
        margin: 5px 0 0;
        font: 500 12px var(--mono);
        color: var(--muted);
    }

    .registration-panel-live
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

    .registration-panel-live::before
    {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
        animation: registration-pulse 2s ease-out infinite;
    }

    /* ---------- Filter ---------- */
    .registration-filter-form
    {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        padding: 18px 24px;
        border-bottom: 1px solid var(--line);
    }

    .registration-search
    {
        position: relative;
        flex: 1;
        min-width: 220px;
    }

    .registration-search svg
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

    .registration-search:focus-within svg
    {
        color: var(--accent);
    }

    .registration-filter-form input,
    .registration-filter-form select
    {
        height: 40px;
        width: 100%;
        border: 1.5px solid var(--line);
        border-radius: 10px;
        padding: 0 12px;
        background: var(--surface-2);
        color: var(--ink);
        font: 500 13px var(--font);
        outline: none;
        transition: border-color .2s, box-shadow .2s, background .2s;
    }

    .registration-filter-form input
    {
        padding-left: 36px;
    }

    .registration-filter-form input::placeholder
    {
        color: var(--muted);
        opacity: .8;
    }

    .registration-filter-form select
    {
        width: auto;
        min-width: 160px;
    }

    .registration-filter-form input:focus,
    .registration-filter-form select:focus
    {
        background: var(--surface);
        border-color: var(--accent);
        box-shadow: 0 0 0 4px var(--accent-soft), 0 0 22px -8px var(--accent);
    }

    .registration-filter-button,
    .registration-reset-button
    {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 40px;
        padding: 0 18px;
        border-radius: 10px;
        font: 700 13px var(--font);
        text-decoration: none;
        cursor: pointer;
        transition: filter .2s, transform .2s, color .2s, border-color .2s;
    }

    .registration-filter-button
    {
        border: 0;
        background: var(--brand);
        color: #ffffff;
        box-shadow: 0 10px 22px -12px rgba(47, 76, 240, .9), inset 0 1px 0 rgba(255, 255, 255, .3);
    }

    .registration-filter-button:hover
    {
        filter: brightness(1.08);
        transform: translateY(-1px);
    }

    .registration-reset-button
    {
        border: 1.5px solid var(--line);
        background: transparent;
        color: var(--muted);
    }

    .registration-reset-button:hover
    {
        border-color: var(--accent);
        color: var(--accent);
    }

    /* ---------- Tabel ---------- */
    .registration-table-wrapper
    {
        width: 100%;
        overflow-x: auto;
    }

    .registration-table
    {
        width: 100%;
        border-collapse: collapse;
        white-space: nowrap;
    }

    .registration-table th
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

    .registration-table td
    {
        padding: 16px 20px;
        border-bottom: 1px solid var(--line);
        font-size: 13px;
        color: var(--muted);
        vertical-align: middle;
    }

    .registration-table tbody tr:last-child td
    {
        border-bottom: 0;
    }

    .registration-table tbody tr
    {
        transition: background .2s;
    }

    .registration-table tbody tr:hover
    {
        background: var(--accent-soft);
    }

    .registration-table tbody tr td:first-child
    {
        box-shadow: inset 0 0 0 var(--accent);
        transition: box-shadow .2s;
    }

    .registration-table tbody tr:hover td:first-child
    {
        box-shadow: inset 3px 0 0 var(--accent);
    }

    .registration-main
    {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .registration-main-icon
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

    .registration-main-icon svg
    {
        width: 16px;
        height: 16px;
    }

    .registration-primary
    {
        color: var(--ink);
        font-size: 13px;
        font-weight: 700;
    }

    .registration-date
    {
        font: 600 12px var(--mono);
    }

    .registration-secondary
    {
        margin-top: 5px;
        font: 500 11px var(--mono);
        color: var(--muted);
    }

    .registration-key
    {
        color: var(--accent);
    }

    .registration-chip
    {
        display: inline-block;
        padding: 2px 8px;
        border: 1px solid var(--line);
        border-radius: 6px;
        background: var(--surface-2);
        color: var(--muted);
        font: 500 11px var(--mono);
    }

    .registration-id
    {
        display: inline-block;
        max-width: 220px;
        overflow: hidden;
        text-overflow: ellipsis;
        vertical-align: middle;
        padding: 4px 10px;
        border: 1px dashed color-mix(in srgb, var(--accent) 45%, var(--line));
        border-radius: 8px;
        color: var(--accent);
        font: 600 11px var(--mono);
    }

    /* ---------- Status ---------- */
    .registration-status
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

    .registration-status-dot
    {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
    }

    .registration-status.status-approved
    {
        border-color: color-mix(in srgb, var(--status-ok) 40%, transparent);
        background: color-mix(in srgb, var(--status-ok) 14%, transparent);
        color: var(--status-ok);
        box-shadow: 0 0 18px -8px var(--status-ok);
    }

    .registration-status.status-approved .registration-status-dot
    {
        animation: registration-pulse 2s ease-out infinite;
    }

    .registration-status.status-pending
    {
        border-color: color-mix(in srgb, var(--status-warn) 40%, transparent);
        background: color-mix(in srgb, var(--status-warn) 14%, transparent);
        color: var(--status-warn);
    }

    .registration-status.status-rejected
    {
        border-color: color-mix(in srgb, var(--status-bad) 40%, transparent);
        background: color-mix(in srgb, var(--status-bad) 14%, transparent);
        color: var(--status-bad);
    }

    .registration-rejection
    {
        max-width: 260px;
        margin-top: 8px;
        padding: 6px 10px;
        border-left: 2px solid var(--status-bad);
        border-radius: 0 8px 8px 0;
        background: color-mix(in srgb, var(--status-bad) 8%, transparent);
        white-space: normal;
        color: var(--muted);
        font: 500 11px var(--mono);
        line-height: 1.5;
    }

    /* ---------- Kosong ---------- */
    .registration-empty-state
    {
        padding: 64px 24px;
        text-align: center;
    }

    .registration-empty-icon
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

    .registration-empty-icon svg
    {
        width: 24px;
        height: 24px;
    }

    .registration-empty-state h3
    {
        margin: 0 0 8px;
        font-size: 16px;
        font-weight: 800;
        color: var(--ink);
    }

    .registration-empty-state p
    {
        margin: 0;
        font-size: 13px;
        color: var(--muted);
    }

    /* ---------- Pagination ---------- */
    .registration-pagination
    {
        padding: 18px 24px;
        border-top: 1px solid var(--line);
        font-size: 13px;
        color: var(--muted);
    }

    .registration-pagination svg
    {
        width: 16px;
        height: 16px;
    }

    .registration-pagination p
    {
        margin: 0;
        color: var(--muted);
    }

    .registration-pagination a
    {
        color: var(--accent);
        text-decoration: none;
    }

    /* ---------- Animasi ---------- */
    @keyframes registration-pulse
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
        .registration-panel-live::before,
        .registration-status.status-approved .registration-status-dot
        {
            animation: none;
        }

        .registration-summary-card,
        .registration-summary-meter-fill
        {
            transition: none;
        }
    }

    @media (max-width: 1100px)
    {
        .registration-summary
        {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 700px)
    {
        .registration-filter-form
        {
            flex-direction: column;
            align-items: stretch;
        }

        .registration-filter-form select
        {
            width: 100%;
        }

        .registration-search
        {
            min-width: 0;
        }

        .registration-filter-button,
        .registration-reset-button
        {
            width: 100%;
        }

        .registration-panel-header
        {
            padding: 22px 16px 16px;
        }
    }

    @media (max-width: 480px)
    {
        .registration-summary
        {
            grid-template-columns: 1fr;
        }
    }
</style>

@endpush