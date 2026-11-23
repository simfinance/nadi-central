@extends('layouts.app')

@section('title', 'Servers - NADI Central')

@section('breadcrumb', 'Monitoring / Servers')

@push('styles')
<style>
    .servers-page
    {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .servers-page .page-header
    {
        margin-bottom: 0;
    }

    /* ---------- Ringkasan ---------- */
    .servers-summary
    {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
    }

    .server-summary-card
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

    .server-summary-card:hover
    {
        transform: translateY(-3px);
        border-color: color-mix(in srgb, var(--accent) 45%, var(--line));
        box-shadow: var(--shadow), 0 18px 40px -22px var(--accent);
    }

    .server-summary-card::before
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
    .server-summary-card::after
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

    .server-summary-top
    {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
    }

    .server-summary-icon
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

    .server-summary-icon svg
    {
        width: 19px;
        height: 19px;
    }

    .server-summary-label
    {
        font: 600 11.5px var(--mono);
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .server-summary-value
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

    .server-summary-percent
    {
        padding: 3px 9px;
        border: 1px solid color-mix(in srgb, var(--accent) 35%, transparent);
        border-radius: 999px;
        background: var(--accent-soft);
        color: var(--accent);
        font: 600 11px var(--mono);
        letter-spacing: 0;
    }

    .server-summary-description
    {
        margin-top: 8px;
        font: 500 11.5px var(--mono);
        color: var(--muted);
    }

    .server-summary-meter
    {
        height: 4px;
        margin-top: 16px;
        border-radius: 999px;
        background: var(--surface-2);
        overflow: hidden;
    }

    .server-summary-meter-fill
    {
        height: 100%;
        border-radius: 999px;
        background: var(--brand);
        box-shadow: 0 0 12px var(--accent);
        transition: width .8s cubic-bezier(.2, .8, .2, 1);
    }

    /* ---------- Panel inventaris ---------- */
    .servers-panel
    {
        position: relative;
        border: 1px solid var(--line);
        border-radius: 18px;
        background: var(--surface);
        box-shadow: var(--shadow);
        overflow: hidden;
    }

    .servers-panel::before
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

    .servers-panel-header
    {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
        padding: 26px 24px 20px;
        border-bottom: 1px solid var(--line);
        background-image: linear-gradient(180deg, color-mix(in srgb, var(--accent) 6%, transparent), transparent);
    }

    .servers-panel-title
    {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 16px;
        font-weight: 800;
    }

    .servers-panel-live
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

    .servers-panel-live::before
    {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
        animation: servers-pulse 2s ease-out infinite;
    }

    .servers-panel-subtitle
    {
        margin-top: 5px;
        font: 500 12px var(--mono);
        color: var(--muted);
    }

    .servers-filter
    {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .servers-search
    {
        position: relative;
    }

    .servers-search svg
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

    .servers-search:focus-within svg
    {
        color: var(--accent);
    }

    .servers-filter input,
    .servers-filter select
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

    .servers-filter input
    {
        width: 250px;
        padding-left: 36px;
    }

    .servers-filter input::placeholder
    {
        color: var(--muted);
        opacity: .8;
    }

    .servers-filter input:focus,
    .servers-filter select:focus
    {
        background: var(--surface);
        border-color: var(--accent);
        box-shadow: 0 0 0 4px var(--accent-soft), 0 0 22px -8px var(--accent);
    }

    .servers-filter button
    {
        height: 40px;
        border: 0;
        border-radius: 10px;
        padding: 0 18px;
        background: var(--brand);
        color: #ffffff;
        font: 700 13px var(--font);
        cursor: pointer;
        box-shadow: 0 10px 22px -12px rgba(47, 76, 240, .9), inset 0 1px 0 rgba(255, 255, 255, .3);
        transition: filter .2s, transform .2s;
    }

    .servers-filter button:hover
    {
        filter: brightness(1.08);
        transform: translateY(-1px);
    }

    .servers-reset
    {
        padding: 0 4px;
        font-size: 13px;
        font-weight: 600;
        color: var(--muted);
        text-decoration: none;
    }

    .servers-reset:hover
    {
        color: var(--accent);
    }

    /* ---------- Tabel ---------- */
    .servers-table-wrapper
    {
        overflow-x: auto;
    }

    .servers-table
    {
        width: 100%;
        border-collapse: collapse;
        white-space: nowrap;
    }

    .servers-table th
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

    .servers-table td
    {
        padding: 16px 20px;
        border-bottom: 1px solid var(--line);
        font-size: 13px;
        color: var(--muted);
        vertical-align: middle;
    }

    .servers-table tbody tr:last-child td
    {
        border-bottom: 0;
    }

    .servers-table tbody tr
    {
        transition: background .2s;
    }

    .servers-table tbody tr:hover
    {
        background: var(--accent-soft);
    }

    .servers-table tbody tr td:first-child
    {
        box-shadow: inset 0 0 0 var(--accent);
        transition: box-shadow .2s;
    }

    .servers-table tbody tr:hover td:first-child
    {
        box-shadow: inset 3px 0 0 var(--accent);
    }

    .server-host
    {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .server-host-icon
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

    .server-host-icon svg
    {
        width: 16px;
        height: 16px;
    }

    .server-hostname
    {
        font-weight: 700;
        color: var(--ink);
    }

    .server-ip
    {
        display: inline-block;
        padding: 4px 10px;
        border: 1px solid var(--line);
        border-radius: 8px;
        background: var(--surface-2);
        font: 500 12px var(--mono);
        color: var(--ink);
    }

    .server-os
    {
        font-weight: 600;
        color: var(--ink);
    }

    .server-os-version
    {
        margin-top: 3px;
        font: 500 11.5px var(--mono);
        color: var(--muted);
    }

    .server-arch
    {
        display: inline-block;
        padding: 4px 10px;
        border: 1px dashed color-mix(in srgb, var(--accent) 45%, var(--line));
        border-radius: 8px;
        font: 600 11.5px var(--mono);
        color: var(--accent);
    }

    .server-status
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

    .server-status.registered
    {
        border-color: color-mix(in srgb, var(--accent) 40%, transparent);
        background: var(--accent-soft);
        color: var(--accent);
        box-shadow: 0 0 18px -8px var(--accent);
    }

    .server-status-dot
    {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
    }

    .server-status.registered .server-status-dot
    {
        animation: servers-pulse 2s ease-out infinite;
    }

    .server-last-seen
    {
        display: block;
        font: 500 12px var(--mono);
        color: var(--ink);
    }

    .server-last-seen-ago
    {
        display: block;
        margin-top: 3px;
        font: 500 11px var(--mono);
        color: var(--muted);
    }

    /* ---------- Kosong ---------- */
    .servers-empty
    {
        padding: 64px 24px;
        text-align: center;
    }

    .servers-empty-icon
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

    .servers-empty h3
    {
        margin: 0 0 8px;
        font-size: 16px;
        font-weight: 800;
    }

    .servers-empty p
    {
        margin: 0;
        font-size: 13px;
        color: var(--muted);
    }

    /* ---------- Pagination ---------- */
    .servers-pagination
    {
        padding: 18px 24px;
        border-top: 1px solid var(--line);
        font-size: 13px;
        color: var(--muted);
    }

    .servers-pagination svg
    {
        width: 16px;
        height: 16px;
    }

    .servers-pagination p
    {
        margin: 0;
        color: var(--muted);
    }

    .servers-pagination a
    {
        color: var(--accent);
        text-decoration: none;
    }

    /* ---------- Animasi ---------- */
    @keyframes servers-pulse
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
        .servers-panel-live::before,
        .server-status.registered .server-status-dot
        {
            animation: none;
        }

        .server-summary-card,
        .server-summary-meter-fill
        {
            transition: none;
        }
    }

    @media (max-width: 900px)
    {
        .servers-summary
        {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 600px)
    {
        .servers-summary
        {
            grid-template-columns: 1fr;
        }

        .servers-filter,
        .servers-search
        {
            width: 100%;
        }

        .servers-filter input
        {
            width: 100%;
        }

        .servers-filter select
        {
            flex: 1;
        }

        .servers-panel-header
        {
            padding: 22px 16px 16px;
        }
    }
</style>
@endpush

@section('content')
@php
    $totalBase = max((int) $totalServers, 1);
    $registeredPercent = round(((int) $registeredServers / $totalBase) * 100);
    $otherPercent = round(((int) $otherStatusServers / $totalBase) * 100);
@endphp
<div class="servers-page">

    <div class="page-header">
        <h1 class="page-title">Servers</h1>
        <p class="page-description">Monitor and manage registered server infrastructure.</p>
    </div>

    <div class="servers-summary">

        <div class="server-summary-card">
            <div class="server-summary-top">
                <div class="server-summary-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="6" rx="2"/>
                        <rect x="3" y="14" width="18" height="6" rx="2"/>
                        <path d="M7 7h.01M7 17h.01"/>
                    </svg>
                </div>
                <div class="server-summary-label">Total Servers</div>
            </div>
            <div class="server-summary-value">{{ number_format($totalServers) }}</div>
            <div class="server-summary-description">Servers registered in NADI</div>
            <div class="server-summary-meter">
                <div class="server-summary-meter-fill" style="width: 100%;"></div>
            </div>
        </div>

        <div class="server-summary-card">
            <div class="server-summary-top">
                <div class="server-summary-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M8 12.5l3 3 5-6"/>
                    </svg>
                </div>
                <div class="server-summary-label">Registered</div>
            </div>
            <div class="server-summary-value">
                {{ number_format($registeredServers) }}
                <span class="server-summary-percent">{{ $registeredPercent }}%</span>
            </div>
            <div class="server-summary-description">Status: registered</div>
            <div class="server-summary-meter">
                <div class="server-summary-meter-fill" style="width: {{ $registeredPercent }}%;"></div>
            </div>
        </div>

        <div class="server-summary-card">
            <div class="server-summary-top">
                <div class="server-summary-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M12 8v5M12 16.5v.01"/>
                    </svg>
                </div>
                <div class="server-summary-label">Other Status</div>
            </div>
            <div class="server-summary-value">
                {{ number_format($otherStatusServers) }}
                <span class="server-summary-percent">{{ $otherPercent }}%</span>
            </div>
            <div class="server-summary-description">Servers with different status</div>
            <div class="server-summary-meter">
                <div class="server-summary-meter-fill" style="width: {{ $otherPercent }}%;"></div>
            </div>
        </div>

    </div>

    <div class="servers-panel">

        <div class="servers-panel-header">
            <div>
                <div class="servers-panel-title">
                    Server Inventory
                    <span class="servers-panel-live">Live</span>
                </div>
                <div class="servers-panel-subtitle">
                    {{ $servers->total() }} server(s) found
                </div>
            </div>

            <form method="GET" action="{{ route('monitoring.servers') }}" class="servers-filter">
                <div class="servers-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/>
                        <path d="M20 20l-3.5-3.5"/>
                    </svg>

                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Search hostname, IP, OS..."
                    >
                </div>

                <select name="status">
                    <option value="">All Status</option>

                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                            {{ ucfirst($status) }}
                        </option>
                    @endforeach
                </select>

                <button type="submit">Filter</button>

                @if (request()->hasAny(['search', 'status']))
                    <a href="{{ route('monitoring.servers') }}" class="servers-reset">
                        Reset
                    </a>
                @endif
            </form>
        </div>

        @if ($servers->count())
            <div class="servers-table-wrapper">
                <table class="servers-table">
                    <thead>
                        <tr>
                            <th>Hostname</th>
                            <th>IP Address</th>
                            <th>Operating System</th>
                            <th>Architecture</th>
                            <th>Status</th>
                            <th>Last Seen</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($servers as $server)
                            <tr>
                                <td>
                                    <div class="server-host">
                                        <div class="server-host-icon">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="3" y="4" width="18" height="6" rx="2"/>
                                                <rect x="3" y="14" width="18" height="6" rx="2"/>
                                                <path d="M7 7h.01M7 17h.01"/>
                                            </svg>
                                        </div>
                                        <div class="server-hostname">
                                            {{ $server->hostname }}
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="server-ip">
                                        {{ $server->ip_address ?: '—' }}
                                    </span>
                                </td>

                                <td>
                                    <div class="server-os">
                                        {{ $server->os_name ?: '—' }}
                                    </div>
                                    <div class="server-os-version">
                                        {{ $server->os_version ? 'v' . ltrim($server->os_version, 'vV') : '—' }}
                                    </div>
                                </td>

                                <td>
                                    <span class="server-arch">
                                        {{ $server->architecture ?: '—' }}
                                    </span>
                                </td>

                                <td>
                                    <span class="server-status {{ $server->status === 'registered' ? 'registered' : '' }}">
                                        <span class="server-status-dot"></span>
                                        {{ ucfirst($server->status) }}
                                    </span>
                                </td>

                                <td>
                                    <span class="server-last-seen">
                                        {{ $server->last_seen_at ? $server->last_seen_at->format('d M Y, H:i:s') : 'Never' }}
                                    </span>
                                    @if ($server->last_seen_at)
                                        <span class="server-last-seen-ago">
                                            {{ $server->last_seen_at->diffForHumans() }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($servers->hasPages())
                <div class="servers-pagination">
                    {{ $servers->links() }}
                </div>
            @endif

        @else
            <div class="servers-empty">
                <div class="servers-empty-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="6" rx="2"/>
                        <rect x="3" y="14" width="18" height="6" rx="2"/>
                        <path d="M7 7h.01M7 17h.01"/>
                    </svg>
                </div>

                @if (request()->hasAny(['search', 'status']))
                    <h3>No servers found</h3>
                    <p>No servers match your search or selected filter.</p>
                @else
                    <h3>No servers registered yet</h3>
                    <p>Servers will appear here when an NADI Agent registers with Central.</p>
                @endif
            </div>
        @endif

    </div>

</div>
@endsection