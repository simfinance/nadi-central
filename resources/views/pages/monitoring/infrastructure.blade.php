@extends('layouts.app')

@section('title', 'Infrastructure Monitoring - NADI Central')

@section('breadcrumb', 'Monitoring / Infrastructure')

@push('styles')
<style>
    .infra-page
    {
        --infra-ok: var(--ok, #12a150);
        --infra-ok-soft: var(--ok-soft, rgba(18, 161, 80, .12));
        --infra-warn: #d97706;
        --infra-warn-soft: rgba(217, 119, 6, .13);
        --infra-bad: var(--danger, #dc4c64);
        --infra-bad-soft: rgba(220, 76, 100, .13);
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 22px;
    }

    .infra-page .page-header
    {
        margin-bottom: 0;
    }

    .infra-progress
    {
        position: fixed;
        top: 0;
        left: 0;
        z-index: 60;
        width: 100%;
        height: 2px;
        background: linear-gradient(90deg, transparent, var(--accent), transparent);
        background-size: 40% 100%;
        background-repeat: no-repeat;
        opacity: 0;
        transition: opacity .2s ease;
        pointer-events: none;
    }

    .infra-page.is-loading .infra-progress
    {
        opacity: 1;
        animation: infra-sweep 1.1s linear infinite;
    }

    @keyframes infra-sweep
    {
        from
        {
            background-position: -40% 0;
        }

        to
        {
            background-position: 140% 0;
        }
    }

    /* Toolbar */

    .infra-toolbar
    {
        display: flex;
        align-items: end;
        justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
        padding: 18px;
        border: 1px solid var(--line);
        border-radius: 16px;
        background: var(--surface);
        box-shadow: var(--shadow);
    }

    .infra-filters
    {
        display: flex;
        align-items: end;
        gap: 12px;
        flex-wrap: wrap;
    }

    .infra-field
    {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 190px;
    }

    .infra-field label
    {
        color: var(--muted);
        font: 600 10px var(--mono);
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .infra-field select
    {
        min-width: 190px;
        height: 40px;
        padding: 0 12px;
        border: 1px solid var(--line);
        border-radius: 10px;
        background: var(--surface-2);
        color: var(--ink);
        font: 600 12px var(--font);
    }

    .infra-actions
    {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .infra-status
    {
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--muted);
        font: 500 11px var(--mono);
    }

    .infra-status-dot
    {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--muted);
    }

    .infra-status[data-state="up"] .infra-status-dot
    {
        background: var(--infra-ok);
        box-shadow: 0 0 0 4px var(--infra-ok-soft);
        animation: infra-pulse 2s ease-in-out infinite;
    }

    .infra-status[data-state="down"] .infra-status-dot
    {
        background: var(--infra-bad);
        box-shadow: 0 0 0 4px var(--infra-bad-soft);
    }

    @keyframes infra-pulse
    {
        50%
        {
            box-shadow: 0 0 0 7px transparent;
        }
    }

    .infra-auto
    {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: var(--muted);
        font: 600 11px var(--mono);
        cursor: pointer;
        user-select: none;
    }

    .infra-auto input
    {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .infra-switch
    {
        position: relative;
        width: 30px;
        height: 17px;
        border: 1px solid var(--line);
        border-radius: 999px;
        background: var(--surface-2);
        transition: background .2s ease;
    }

    .infra-switch::after
    {
        content: "";
        position: absolute;
        top: 2px;
        left: 2px;
        width: 11px;
        height: 11px;
        border-radius: 50%;
        background: var(--muted);
        transition: transform .2s ease, background .2s ease;
    }

    .infra-auto input:checked + .infra-switch
    {
        border-color: var(--accent);
        background: var(--accent-soft);
    }

    .infra-auto input:checked + .infra-switch::after
    {
        background: var(--accent);
        transform: translateX(13px);
    }

    .infra-auto input:focus-visible + .infra-switch
    {
        outline: 2px solid var(--accent);
        outline-offset: 2px;
    }

    .infra-refresh
    {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 40px;
        padding: 0 14px;
        border: 1px solid var(--accent);
        border-radius: 10px;
        background: var(--accent-soft);
        color: var(--accent);
        font: 700 12px var(--font);
        cursor: pointer;
    }

    .infra-refresh:disabled
    {
        opacity: .6;
        cursor: progress;
    }

    /* Panels */

    .infra-panel
    {
        position: relative;
        min-width: 0;
        padding: 20px;
        border: 1px solid var(--line);
        border-radius: 16px;
        background: var(--surface);
        box-shadow: var(--shadow);
    }

    .infra-panel-header
    {
        display: flex;
        align-items: start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 14px;
    }

    .infra-panel-title
    {
        color: var(--ink);
        font-size: 14px;
        font-weight: 800;
    }

    .infra-panel-description
    {
        margin-top: 5px;
        color: var(--muted);
        font: 500 10px var(--mono);
    }

    .infra-chip
    {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 8px;
        border: 1px solid var(--line);
        border-radius: 999px;
        background: var(--surface-2);
        color: var(--muted);
        font: 600 10px var(--mono);
        white-space: nowrap;
    }

    .infra-chip b
    {
        color: var(--ink);
        font-weight: 700;
    }

    .infra-chart-stats
    {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    /* Hero */

    .infra-hero
    {
        display: grid;
        grid-template-columns: minmax(0, 5fr) minmax(0, 7fr);
        gap: 16px;
    }

    .infra-score
    {
        display: grid;
        grid-template-columns: 150px minmax(0, 1fr);
        align-items: center;
        gap: 20px;
        overflow: hidden;
    }

    .infra-score::before
    {
        content: "";
        position: absolute;
        top: -70px;
        left: -70px;
        width: 220px;
        height: 220px;
        border-radius: 50%;
        background: radial-gradient(circle, var(--score-glow, var(--accent-soft)), transparent 70%);
        pointer-events: none;
    }

    .infra-ring
    {
        position: relative;
        width: 150px;
        height: 150px;
    }

    .infra-ring svg
    {
        width: 100%;
        height: 100%;
        transform: rotate(-90deg);
    }

    .infra-ring-track
    {
        fill: none;
        stroke: var(--line);
        stroke-width: 9;
    }

    .infra-ring-bar
    {
        fill: none;
        stroke: var(--accent);
        stroke-width: 9;
        stroke-linecap: round;
        stroke-dasharray: 326.7;
        stroke-dashoffset: 326.7;
        transition: stroke-dashoffset .9s cubic-bezier(.2, .8, .2, 1), stroke .3s ease;
    }

    .infra-ring-text
    {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .infra-ring-value
    {
        color: var(--ink);
        font-size: 34px;
        font-weight: 800;
        letter-spacing: -.04em;
        line-height: 1;
    }

    .infra-ring-label
    {
        margin-top: 6px;
        color: var(--muted);
        font: 700 9px var(--mono);
        letter-spacing: .12em;
        text-transform: uppercase;
    }

    .infra-factors
    {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 7px;
        min-width: 0;
    }

    .infra-factor
    {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 7px 10px;
        border: 1px solid var(--line);
        border-radius: 9px;
        background: var(--surface-2);
        font: 600 11px var(--mono);
    }

    .infra-factor-name
    {
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--ink);
    }

    .infra-factor-text
    {
        color: var(--muted);
        font-weight: 500;
        text-align: right;
    }

    .infra-dot
    {
        flex: none;
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--muted);
    }

    .infra-dot.ok
    {
        background: var(--infra-ok);
    }

    .infra-dot.warn
    {
        background: var(--infra-warn);
    }

    .infra-dot.bad
    {
        background: var(--infra-bad);
    }

    .infra-insights-list
    {
        display: flex;
        flex-direction: column;
        gap: 8px;
        max-height: 330px;
        overflow: auto;
    }

    .infra-insight
    {
        display: grid;
        grid-template-columns: 28px minmax(0, 1fr);
        gap: 12px;
        padding: 11px 12px;
        border: 1px solid var(--line);
        border-left-width: 3px;
        border-radius: 10px;
        background: var(--surface-2);
    }

    .infra-insight.ok
    {
        border-left-color: var(--infra-ok);
    }

    .infra-insight.warn
    {
        border-left-color: var(--infra-warn);
    }

    .infra-insight.bad
    {
        border-left-color: var(--infra-bad);
    }

    .infra-insight.info
    {
        border-left-color: var(--accent);
    }

    .infra-insight-icon
    {
        display: grid;
        width: 28px;
        height: 28px;
        place-items: center;
        border-radius: 8px;
        font: 800 13px var(--mono);
    }

    .infra-insight.ok .infra-insight-icon
    {
        background: var(--infra-ok-soft);
        color: var(--infra-ok);
    }

    .infra-insight.warn .infra-insight-icon
    {
        background: var(--infra-warn-soft);
        color: var(--infra-warn);
    }

    .infra-insight.bad .infra-insight-icon
    {
        background: var(--infra-bad-soft);
        color: var(--infra-bad);
    }

    .infra-insight.info .infra-insight-icon
    {
        background: var(--accent-soft);
        color: var(--accent);
    }

    .infra-insight-title
    {
        color: var(--ink);
        font-size: 12px;
        font-weight: 700;
    }

    .infra-insight-detail
    {
        margin-top: 3px;
        color: var(--muted);
        font-size: 11px;
        line-height: 1.55;
    }

    /* KPI */

    .infra-summary
    {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 14px;
    }

    .infra-stat
    {
        position: relative;
        min-width: 0;
        padding: 16px 16px 14px;
        overflow: hidden;
        border: 1px solid var(--line);
        border-radius: 14px;
        background: var(--surface);
        box-shadow: var(--shadow);
    }

    .infra-stat::before
    {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 2px;
        background: var(--tone, var(--accent));
        opacity: .85;
    }

    .infra-stat[data-tone="ok"]
    {
        --tone: var(--infra-ok);
    }

    .infra-stat[data-tone="warn"]
    {
        --tone: var(--infra-warn);
    }

    .infra-stat[data-tone="bad"]
    {
        --tone: var(--infra-bad);
    }

    .infra-stat-top
    {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }

    .infra-stat-label
    {
        color: var(--muted);
        font: 600 10px var(--mono);
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .infra-delta
    {
        color: var(--muted);
        font: 700 10px var(--mono);
    }

    .infra-delta.up
    {
        color: var(--infra-warn);
    }

    .infra-delta.down
    {
        color: var(--infra-ok);
    }

    .infra-stat-value
    {
        margin-top: 8px;
        color: var(--ink);
        font-size: 28px;
        font-weight: 800;
        letter-spacing: -.03em;
        line-height: 1.1;
    }

    .infra-stat-meta
    {
        margin-top: 5px;
        color: var(--muted);
        font: 500 10px var(--mono);
    }

    .infra-spark
    {
        height: 34px;
        margin-top: 10px;
    }

    .infra-spark svg
    {
        display: block;
        width: 100%;
        height: 100%;
    }

    .infra-meter
    {
        height: 4px;
        margin-top: 10px;
        overflow: hidden;
        border-radius: 99px;
        background: var(--line);
    }

    .infra-meter span
    {
        display: block;
        width: 0;
        height: 100%;
        border-radius: 99px;
        background: var(--tone, var(--accent));
        transition: width .7s cubic-bezier(.2, .8, .2, 1);
    }

    .infra-stat-stats
    {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 4px;
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px dashed var(--line);
    }

    .infra-mini
    {
        min-width: 0;
        color: var(--ink);
        font: 700 10px var(--mono);
    }

    .infra-mini small
    {
        display: block;
        margin-bottom: 2px;
        color: var(--muted);
        font: 600 8px var(--mono);
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    /* Charts */

    .infra-grid
    {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }

    .infra-grid > div
    {
        min-width: 0;
    }

    .infra-legend
    {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        min-height: 4px;
        margin-bottom: 10px;
    }

    .infra-legend-item
    {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        max-width: 100%;
        padding: 3px 9px;
        border: 1px solid var(--line);
        border-radius: 999px;
        background: var(--surface-2);
        color: var(--ink);
        font: 600 10px var(--mono);
        cursor: pointer;
    }

    .infra-legend-item span:nth-child(2)
    {
        overflow: hidden;
        max-width: 190px;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .infra-legend-item em
    {
        color: var(--muted);
        font-style: normal;
        font-weight: 500;
    }

    .infra-legend-item.is-off
    {
        opacity: .4;
    }

    .infra-swatch
    {
        flex: none;
        width: 8px;
        height: 8px;
        border-radius: 3px;
    }

    .infra-chart-wrap
    {
        position: relative;
    }

    .infra-chart
    {
        display: block;
        width: 100%;
        height: 210px;
        cursor: crosshair;
        touch-action: pan-y;
    }

    .infra-empty[hidden],
    .infra-chart[hidden]
    {
        display: none;
    }

    .infra-empty
    {
        display: grid;
        min-height: 210px;
        padding: 0 12px;
        place-items: center;
        color: var(--muted);
        font: 500 11px var(--mono);
        text-align: center;
    }

    .infra-tip
    {
        position: absolute;
        top: 6px;
        left: 0;
        z-index: 5;
        display: none;
        min-width: 120px;
        max-width: 280px;
        padding: 8px 10px;
        border: 1px solid var(--line);
        border-radius: 9px;
        background: var(--surface);
        box-shadow: var(--shadow);
        color: var(--ink);
        font: 600 10px var(--mono);
        pointer-events: none;
    }

    .infra-tip-time
    {
        margin-bottom: 5px;
        color: var(--muted);
        font-weight: 500;
    }

    .infra-tip-row
    {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 3px;
    }

    .infra-tip-row span:nth-child(2)
    {
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Boards */

    .infra-section-gap
    {
        margin-top: 18px;
    }

    .infra-table-wrap
    {
        overflow: auto;
        border: 1px solid var(--line);
        border-radius: 12px;
    }

    .infra-table
    {
        width: 100%;
        border-collapse: collapse;
        font-size: 11px;
    }

    .infra-table th
    {
        padding: 9px 12px;
        border-bottom: 1px solid var(--line);
        background: var(--surface-2);
        color: var(--muted);
        font: 600 9px var(--mono);
        letter-spacing: .08em;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .infra-table td
    {
        padding: 9px 12px;
        border-bottom: 1px solid var(--line);
        color: var(--ink);
        font: 500 11px var(--mono);
        white-space: nowrap;
    }

    .infra-table tr:last-child td
    {
        border-bottom: 0;
    }

    .infra-table td.infra-cell-name
    {
        max-width: 320px;
        overflow: hidden;
        font-weight: 700;
        text-overflow: ellipsis;
    }

    .infra-table .infra-spark
    {
        width: 96px;
        height: 24px;
        margin: 0;
    }

    .infra-pill
    {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 2px 8px;
        border-radius: 999px;
        background: var(--surface-2);
        color: var(--muted);
        font: 700 9px var(--mono);
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .infra-pill.ok
    {
        background: var(--infra-ok-soft);
        color: var(--infra-ok);
    }

    .infra-pill.warn
    {
        background: var(--infra-warn-soft);
        color: var(--infra-warn);
    }

    .infra-pill.bad
    {
        background: var(--infra-bad-soft);
        color: var(--infra-bad);
    }

    .infra-bar
    {
        display: inline-block;
        width: 54px;
        height: 4px;
        margin-right: 8px;
        overflow: hidden;
        border-radius: 99px;
        background: var(--line);
        vertical-align: middle;
    }

    .infra-bar span
    {
        display: block;
        height: 100%;
        background: var(--infra-ok);
    }

    .infra-note
    {
        margin-top: 14px;
        padding: 12px 14px;
        border-left: 3px solid var(--accent);
        border-radius: 8px;
        background: var(--accent-soft);
        color: var(--muted);
        font-size: 11px;
        line-height: 1.6;
    }

    /* Targets and coverage */

    .infra-duo
    {
        display: grid;
        grid-template-columns: minmax(0, 7fr) minmax(0, 5fr);
        gap: 16px;
    }

    .infra-targets
    {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .infra-target
    {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 14px;
        border: 1px solid var(--line);
        border-radius: 10px;
        background: var(--surface-2);
    }

    .infra-target-name
    {
        color: var(--ink);
        font: 700 11px var(--mono);
    }

    .infra-target-meta
    {
        margin-top: 4px;
        color: var(--muted);
        font: 500 9px var(--mono);
    }

    .infra-target-state
    {
        color: var(--muted);
        font: 700 10px var(--mono);
        text-transform: uppercase;
    }

    .infra-target-state.up
    {
        color: var(--infra-ok);
    }

    .infra-target-state.down
    {
        color: var(--infra-bad);
    }

    .infra-coverage
    {
        display: flex;
        flex-direction: column;
        gap: 7px;
    }

    .infra-coverage-row
    {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 8px 10px;
        border: 1px solid var(--line);
        border-radius: 9px;
        background: var(--surface-2);
        font: 600 10px var(--mono);
    }

    .infra-coverage-row > span:first-child
    {
        display: flex;
        align-items: center;
        gap: 8px;
        min-width: 0;
        overflow: hidden;
        color: var(--ink);
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .infra-coverage-row > span:last-child
    {
        color: var(--muted);
        font-weight: 500;
        white-space: nowrap;
    }

    @media (max-width: 1000px)
    {
        .infra-hero,
        .infra-grid,
        .infra-duo
        {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 600px)
    {
        .infra-score
        {
            grid-template-columns: 1fr;
            justify-items: center;
        }

        .infra-factors
        {
            width: 100%;
        }

        .infra-summary
        {
            grid-template-columns: 1fr 1fr;
            gap: 9px;
        }

        .infra-stat-value
        {
            font-size: 22px;
        }

        .infra-stat-stats
        {
            grid-template-columns: 1fr 1fr;
        }

        .infra-targets
        {
            grid-template-columns: 1fr;
        }

        .infra-field,
        .infra-field select
        {
            width: 100%;
        }

        .infra-panel
        {
            padding: 16px;
        }
    }

    @media (prefers-reduced-motion: reduce)
    {
        .infra-page *,
        .infra-page *::before
        {
            animation: none !important;
            transition: none !important;
        }
    }
</style>
@endpush

@section('content')
<div
    class="infra-page"
    id="infrastructure-monitoring"
    data-overview-url="{{ route('monitoring.prometheus.overview') }}"
    data-query-url="{{ url('/monitoring/prometheus/query') }}"
>
    <div class="infra-progress"></div>

    <div class="page-header">
        <h1 class="page-title">Infrastructure Monitoring</h1>
        <p class="page-description">
            Telemetry historis server dan aplikasi dari Prometheus, dengan NADI Central sebagai gateway.
        </p>
    </div>

    <section class="infra-toolbar">
        <div class="infra-filters">
            <div class="infra-field">
                <label for="infra-server">Server</label>
                <select id="infra-server">
                    <option value="">All servers</option>
                    @foreach ($servers as $server)
                        <option value="{{ $server->id }}">
                            {{ $server->hostname }}{{ $server->ip_address ? ' · ' . $server->ip_address : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="infra-field">
                <label for="infra-application">Application</label>
                <select id="infra-application">
                    <option value="">All applications</option>
                    @foreach ($applications as $application)
                        <option value="{{ $application->id }}">
                            {{ $application->application_name }} · {{ $application->hostname }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="infra-field">
                <label for="infra-range">Time range</label>
                <select id="infra-range">
                    <option value="1h">Last 1 hour</option>
                    <option value="6h">Last 6 hours</option>
                    <option value="24h" selected>Last 24 hours</option>
                    <option value="7d">Last 7 days</option>
                </select>
            </div>
        </div>

        <div class="infra-actions">
            <div class="infra-status" id="infra-connection" data-state="unknown">
                <span class="infra-status-dot"></span>
                <span id="infra-connection-text">Checking Prometheus</span>
            </div>
            <span class="infra-chip" id="infra-updated">Updated <b>—</b></span>
            <label class="infra-auto" for="infra-auto">
                <input type="checkbox" id="infra-auto" checked>
                <span class="infra-switch"></span>
                <span id="infra-auto-text">Auto 30s</span>
            </label>
            <button type="button" class="infra-refresh" id="infra-refresh">
                Refresh data
            </button>
        </div>
    </section>

    <section class="infra-hero">
        <article class="infra-panel infra-score" id="infra-score-panel">
            <div class="infra-ring">
                <svg viewBox="0 0 120 120" aria-hidden="true">
                    <circle class="infra-ring-track" cx="60" cy="60" r="52"></circle>
                    <circle class="infra-ring-bar" id="infra-score-bar" cx="60" cy="60" r="52"></circle>
                </svg>
                <div class="infra-ring-text">
                    <div class="infra-ring-value" id="infra-score-value">—</div>
                    <div class="infra-ring-label" id="infra-score-label">Analyzing</div>
                </div>
            </div>
            <div class="infra-factors" id="infra-score-factors">
                <div class="infra-factor">
                    <span class="infra-factor-name"><span class="infra-dot"></span>Collecting signals</span>
                    <span class="infra-factor-text">...</span>
                </div>
            </div>
        </article>

        <article class="infra-panel">
            <div class="infra-panel-header">
                <div>
                    <div class="infra-panel-title">Smart Insights</div>
                    <div class="infra-panel-description">Analisis otomatis dari data pada rentang waktu terpilih</div>
                </div>
                <span class="infra-chip" id="infra-insight-count">—</span>
            </div>
            <div class="infra-insights-list" id="infra-insights">
                <div class="infra-empty">Analyzing telemetry...</div>
            </div>
        </article>
    </section>

    <section class="infra-summary">
        <article class="infra-stat" id="infra-cpu-card">
            <div class="infra-stat-top">
                <div class="infra-stat-label">CPU Usage</div>
                <div class="infra-delta" id="infra-cpu-delta"></div>
            </div>
            <div class="infra-stat-value" id="infra-cpu-value">—</div>
            <div class="infra-stat-meta" id="infra-cpu-meta">Waiting for samples</div>
            <div class="infra-spark" id="infra-cpu-spark"></div>
            <div class="infra-meter"><span id="infra-cpu-meter"></span></div>
            <div class="infra-stat-stats" id="infra-cpu-stats"></div>
        </article>

        <article class="infra-stat" id="infra-memory-card">
            <div class="infra-stat-top">
                <div class="infra-stat-label">Memory Usage</div>
                <div class="infra-delta" id="infra-memory-delta"></div>
            </div>
            <div class="infra-stat-value" id="infra-memory-value">—</div>
            <div class="infra-stat-meta" id="infra-memory-meta">Waiting for samples</div>
            <div class="infra-spark" id="infra-memory-spark"></div>
            <div class="infra-meter"><span id="infra-memory-meter"></span></div>
            <div class="infra-stat-stats" id="infra-memory-stats"></div>
        </article>

        <article class="infra-stat" id="infra-disk-card">
            <div class="infra-stat-top">
                <div class="infra-stat-label">Disk Usage</div>
                <div class="infra-delta" id="infra-disk-delta"></div>
            </div>
            <div class="infra-stat-value" id="infra-disk-value">—</div>
            <div class="infra-stat-meta" id="infra-disk-meta">Latest filesystem sample</div>
            <div class="infra-spark" id="infra-disk-spark"></div>
            <div class="infra-meter"><span id="infra-disk-meter"></span></div>
            <div class="infra-stat-stats" id="infra-disk-stats"></div>
        </article>

        <article class="infra-stat" id="infra-network-card">
            <div class="infra-stat-top">
                <div class="infra-stat-label">Network Receive</div>
                <div class="infra-delta" id="infra-network-delta"></div>
            </div>
            <div class="infra-stat-value" id="infra-network-value">—</div>
            <div class="infra-stat-meta" id="infra-network-meta">Current rate</div>
            <div class="infra-spark" id="infra-network-spark"></div>
            <div class="infra-stat-stats" id="infra-network-stats"></div>
        </article>

        <article class="infra-stat" id="infra-transmit-card">
            <div class="infra-stat-top">
                <div class="infra-stat-label">Network Transmit</div>
                <div class="infra-delta" id="infra-transmit-delta"></div>
            </div>
            <div class="infra-stat-value" id="infra-transmit-value">—</div>
            <div class="infra-stat-meta" id="infra-transmit-meta">Current rate</div>
            <div class="infra-spark" id="infra-transmit-spark"></div>
            <div class="infra-stat-stats" id="infra-transmit-stats"></div>
        </article>

        <article class="infra-stat" id="infra-probe-card">
            <div class="infra-stat-top">
                <div class="infra-stat-label">Probe Success</div>
                <div class="infra-delta" id="infra-probe-delta"></div>
            </div>
            <div class="infra-stat-value" id="infra-probe-value">—</div>
            <div class="infra-stat-meta" id="infra-probe-meta">Application probes</div>
            <div class="infra-spark" id="infra-probe-spark"></div>
            <div class="infra-meter"><span id="infra-probe-meter"></span></div>
            <div class="infra-stat-stats" id="infra-probe-stats"></div>
        </article>
    </section>

    <section class="infra-grid">
        <article class="infra-panel">
            <div class="infra-panel-header">
                <div>
                    <div class="infra-panel-title">CPU Utilization</div>
                    <div class="infra-panel-description">Percent · garis putus-putus = batas warning/critical</div>
                </div>
                <div class="infra-chart-stats" data-stats="chart-cpu"></div>
            </div>
            <div class="infra-legend" data-legend="chart-cpu"></div>
            <div class="infra-chart-wrap">
                <canvas class="infra-chart" id="chart-cpu" aria-label="CPU utilization chart"></canvas>
                <div class="infra-empty" data-default="No CPU samples in this range." hidden>No CPU samples in this range.</div>
            </div>
        </article>

        <article class="infra-panel">
            <div class="infra-panel-header">
                <div>
                    <div class="infra-panel-title">Memory Utilization</div>
                    <div class="infra-panel-description">Percent · garis putus-putus = batas warning/critical</div>
                </div>
                <div class="infra-chart-stats" data-stats="chart-memory"></div>
            </div>
            <div class="infra-legend" data-legend="chart-memory"></div>
            <div class="infra-chart-wrap">
                <canvas class="infra-chart" id="chart-memory" aria-label="Memory utilization chart"></canvas>
                <div class="infra-empty" data-default="No memory samples in this range." hidden>No memory samples in this range.</div>
            </div>
        </article>

        <article class="infra-panel">
            <div class="infra-panel-header">
                <div>
                    <div class="infra-panel-title">Disk Utilization</div>
                    <div class="infra-panel-description">Percent · per mount point</div>
                </div>
                <div class="infra-chart-stats" data-stats="chart-disk"></div>
            </div>
            <div class="infra-legend" data-legend="chart-disk"></div>
            <div class="infra-chart-wrap">
                <canvas class="infra-chart" id="chart-disk" aria-label="Disk utilization chart"></canvas>
                <div class="infra-empty" data-default="No disk samples in this range." hidden>No disk samples in this range.</div>
            </div>
        </article>

        <article class="infra-panel">
            <div class="infra-panel-header">
                <div>
                    <div class="infra-panel-title">Network Throughput</div>
                    <div class="infra-panel-description">Bytes per second · receive and transmit</div>
                </div>
                <div class="infra-chart-stats" data-stats="chart-network"></div>
            </div>
            <div class="infra-legend" data-legend="chart-network"></div>
            <div class="infra-chart-wrap">
                <canvas class="infra-chart" id="chart-network" aria-label="Network throughput chart"></canvas>
                <div class="infra-empty" data-default="No network samples in this range." hidden>No network samples in this range.</div>
            </div>
        </article>
    </section>

    <section class="infra-panel">
        <div class="infra-panel-header">
            <div>
                <div class="infra-panel-title">Application Observability</div>
                <div class="infra-panel-description">Probe status and response latency</div>
            </div>
        </div>

        <div class="infra-grid">
            <div>
                <div class="infra-legend" data-legend="chart-probe"></div>
                <div class="infra-chart-wrap">
                    <canvas class="infra-chart" id="chart-probe" aria-label="Application probe status chart"></canvas>
                    <div class="infra-empty" data-default="No application probe samples in this range." hidden>No application probe samples in this range.</div>
                </div>
            </div>
            <div>
                <div class="infra-legend" data-legend="chart-latency"></div>
                <div class="infra-chart-wrap">
                    <canvas class="infra-chart" id="chart-latency" aria-label="Application probe latency chart"></canvas>
                    <div class="infra-empty" data-default="No application latency samples in this range." hidden>No application latency samples in this range.</div>
                </div>
            </div>
        </div>

        <div class="infra-table-wrap infra-section-gap">
            <table class="infra-table">
                <thead>
                    <tr>
                        <th>Probe</th>
                        <th>Status</th>
                        <th>Availability</th>
                        <th>Avg latency</th>
                        <th>P95 latency</th>
                        <th>Trend</th>
                    </tr>
                </thead>
                <tbody id="infra-probe-board">
                    <tr>
                        <td colspan="6">Loading probes...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="infra-note">
            Probe value 1 berarti status yang dilaporkan termasuk available/healthy. Nilai 0 berarti probe tidak melaporkan status sehat; ini tidak otomatis membuktikan aplikasi sedang down. Probe yang belum didukung atau belum tersedia perlu diperiksa pada detail telemetry Central.
        </div>
    </section>

    <section class="infra-panel">
        <div class="infra-panel-header">
            <div>
                <div class="infra-panel-title">Telemetry Freshness</div>
                <div class="infra-panel-description">Age and availability of the latest telemetry stream samples</div>
            </div>
        </div>

        <div class="infra-grid">
            <div>
                <div class="infra-legend" data-legend="chart-stream-age"></div>
                <div class="infra-chart-wrap">
                    <canvas class="infra-chart" id="chart-stream-age" aria-label="Telemetry stream age chart"></canvas>
                    <div class="infra-empty" data-default="No telemetry freshness samples in this range." hidden>No telemetry freshness samples in this range.</div>
                </div>
            </div>
            <div>
                <div class="infra-legend" data-legend="chart-stream-up"></div>
                <div class="infra-chart-wrap">
                    <canvas class="infra-chart" id="chart-stream-up" aria-label="Telemetry stream availability chart"></canvas>
                    <div class="infra-empty" data-default="No telemetry availability samples in this range." hidden>No telemetry availability samples in this range.</div>
                </div>
            </div>
        </div>

        <div class="infra-table-wrap infra-section-gap">
            <table class="infra-table">
                <thead>
                    <tr>
                        <th>Stream</th>
                        <th>State</th>
                        <th>Age now</th>
                        <th>Max age</th>
                        <th>Uptime</th>
                        <th>Trend</th>
                    </tr>
                </thead>
                <tbody id="infra-stream-board">
                    <tr>
                        <td colspan="6">Loading streams...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="infra-duo">
        <article class="infra-panel">
            <div class="infra-panel-header">
                <div>
                    <div class="infra-panel-title">Prometheus Scrape Targets</div>
                    <div class="infra-panel-description">Status koneksi exporter dan target scrape aktif</div>
                </div>
                <span class="infra-chip" id="infra-target-count">—</span>
            </div>
            <div class="infra-targets" id="infra-targets">
                <div class="infra-empty">Loading scrape targets...</div>
            </div>
        </article>

        <article class="infra-panel">
            <div class="infra-panel-header">
                <div>
                    <div class="infra-panel-title">Query Health</div>
                    <div class="infra-panel-description">Status tiap metric yang diambil dari catalog</div>
                </div>
                <span class="infra-chip" id="infra-query-time">—</span>
            </div>
            <div class="infra-coverage" id="infra-coverage">
                <div class="infra-empty">Waiting for queries...</div>
            </div>
        </article>
    </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function ()
{
    const root = document.getElementById('infrastructure-monitoring');

    if (!root)
    {
        return;
    }

    const elements = {
        server: document.getElementById('infra-server'),
        application: document.getElementById('infra-application'),
        range: document.getElementById('infra-range'),
        refresh: document.getElementById('infra-refresh'),
        connection: document.getElementById('infra-connection'),
        connectionText: document.getElementById('infra-connection-text'),
        targets: document.getElementById('infra-targets'),
        auto: document.getElementById('infra-auto'),
        autoText: document.getElementById('infra-auto-text'),
        updated: document.getElementById('infra-updated')
    };

    /* Step maksimum 300 detik mengikuti validasi PrometheusQueryController (between:15,300). */
    const rangeSettings = {
        '1h': { seconds: 3600, step: 30 },
        '6h': { seconds: 21600, step: 60 },
        '24h': { seconds: 86400, step: 300 },
        '7d': { seconds: 604800, step: 300 }
    };

    const AUTO_SECONDS = 30;
    const palette = ['#2f6fed', '#08a6b5', '#8b5cf6', '#f59e0b', '#12a150', '#dc4c64', '#ec4899', '#64748b'];

    const metrics = [
        { key: 'cpu', name: 'server_cpu_usage', chart: 'chart-cpu', unit: 'percent', scoped: false },
        { key: 'memory', name: 'server_memory_usage', chart: 'chart-memory', unit: 'percent', scoped: false },
        { key: 'disk', name: 'server_disk_usage', chart: 'chart-disk', unit: 'percent', scoped: false },
        { key: 'rx', name: 'server_network_receive', chart: 'chart-network', unit: 'bytes_per_second', prefix: 'Receive', scoped: false },
        { key: 'tx', name: 'server_network_transmit', chart: 'chart-network', unit: 'bytes_per_second', prefix: 'Transmit', scoped: false },
        { key: 'probe', name: 'application_probe_up', chart: 'chart-probe', unit: 'boolean', scoped: true },
        { key: 'latency', name: 'application_probe_latency', chart: 'chart-latency', unit: 'milliseconds', scoped: true },
        { key: 'age', name: 'telemetry_stream_age', chart: 'chart-stream-age', unit: 'seconds', scoped: true },
        { key: 'up', name: 'telemetry_stream_up', chart: 'chart-stream-up', unit: 'boolean', scoped: true }
    ];

    const thresholds = {
        cpu: [75, 90],
        memory: [80, 90],
        disk: [80, 90]
    };

    const store = {};
    const charts = {};
    let overview = null;
    let overviewError = '';
    let windowInfo = { start: 0, end: 0, step: 60 };
    let controller = null;
    let generation = 0;
    let countdown = AUTO_SECONDS;
    let queryMs = 0;
    let lastUpdate = null;

    /* ---------- Helpers ---------- */

    function esc(value)
    {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatValue(value, unit)
    {
        if (!Number.isFinite(value))
        {
            return '—';
        }

        if (unit === 'percent')
        {
            return value.toFixed(1) + '%';
        }

        if (unit === 'bytes_per_second')
        {
            const units = ['B/s', 'KB/s', 'MB/s', 'GB/s'];
            let amount = value;
            let index = 0;

            while (amount >= 1024 && index < units.length - 1)
            {
                amount /= 1024;
                index++;
            }

            return amount.toFixed(amount >= 100 ? 0 : 1) + ' ' + units[index];
        }

        if (unit === 'milliseconds')
        {
            return value >= 1000 ? (value / 1000).toFixed(2) + ' s' : Math.round(value) + ' ms';
        }

        if (unit === 'seconds')
        {
            return duration(value);
        }

        if (unit === 'boolean')
        {
            return value >= 0.5 ? 'Up' : 'Down';
        }

        return value.toFixed(1);
    }

    function duration(seconds)
    {
        if (!Number.isFinite(seconds))
        {
            return '—';
        }

        const total = Math.max(0, Math.round(seconds));

        if (total < 60)
        {
            return total + 's';
        }

        if (total < 3600)
        {
            return Math.floor(total / 60) + 'm ' + (total % 60) + 's';
        }

        if (total < 86400)
        {
            return Math.floor(total / 3600) + 'h ' + Math.floor((total % 3600) / 60) + 'm';
        }

        return Math.floor(total / 86400) + 'd ' + Math.floor((total % 86400) / 3600) + 'h';
    }

    function clock(seconds, withDate)
    {
        const date = new Date(seconds * 1000);
        const time = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        if (!withDate)
        {
            return time;
        }

        return date.toLocaleDateString([], { day: '2-digit', month: 'short' }) + ' ' + time;
    }

    function ago(value)
    {
        const time = new Date(value).getTime();

        if (!Number.isFinite(time))
        {
            return 'No scrape timestamp';
        }

        return duration((Date.now() - time) / 1000) + ' ago';
    }

    function quantile(sorted, q)
    {
        if (!sorted.length)
        {
            return null;
        }

        const position = (sorted.length - 1) * q;
        const base = Math.floor(position);
        const rest = position - base;

        return sorted[base + 1] !== undefined
            ? sorted[base] + rest * (sorted[base + 1] - sorted[base])
            : sorted[base];
    }

    function reduceList(list, mode)
    {
        if (mode === 'sum')
        {
            return list.reduce((total, item) => total + item, 0);
        }

        if (mode === 'min')
        {
            return Math.min(...list);
        }

        if (mode === 'avg')
        {
            return list.reduce((total, item) => total + item, 0) / list.length;
        }

        return Math.max(...list);
    }

    function aggregate(series, mode)
    {
        const map = new Map();

        series.forEach(function (item)
        {
            item.values.forEach(function (point)
            {
                const list = map.get(point.time) || [];
                list.push(point.value);
                map.set(point.time, list);
            });
        });

        return Array.from(map.entries())
            .sort((a, b) => a[0] - b[0])
            .map(function (entry)
            {
                return { time: entry[0], value: reduceList(entry[1], mode) };
            });
    }

    function statsOf(points)
    {
        if (!points.length)
        {
            return null;
        }

        const values = points.map(point => point.value).sort((a, b) => a - b);

        return {
            min: values[0],
            max: values[values.length - 1],
            avg: values.reduce((total, item) => total + item, 0) / values.length,
            p95: quantile(values, 0.95),
            last: points[points.length - 1].value,
            count: values.length
        };
    }

    function lastPoint(item)
    {
        return item.values[item.values.length - 1];
    }

    function latestOf(series, mode)
    {
        const lasts = series.filter(item => item.values.length).map(item => lastPoint(item).value);

        return lasts.length ? reduceList(lasts, mode) : null;
    }

    function trendOf(points)
    {
        if (points.length < 8)
        {
            return null;
        }

        const size = Math.max(2, Math.floor(points.length / 4));
        const recent = points.slice(-size).reduce((total, item) => total + item.value, 0) / size;
        const prior = points.slice(-size * 2, -size).reduce((total, item) => total + item.value, 0) / size;

        return { recent: recent, prior: prior, diff: recent - prior };
    }

    function regression(points)
    {
        const count = points.length;

        if (count < 12)
        {
            return null;
        }

        const origin = points[0].time;
        let sumX = 0;
        let sumY = 0;
        let sumXY = 0;
        let sumXX = 0;
        let sumYY = 0;

        points.forEach(function (point)
        {
            const x = (point.time - origin) / 86400;
            sumX += x;
            sumY += point.value;
            sumXY += x * point.value;
            sumXX += x * x;
            sumYY += point.value * point.value;
        });

        const denominator = count * sumXX - sumX * sumX;
        const spread = count * sumYY - sumY * sumY;

        if (denominator === 0 || spread === 0)
        {
            return null;
        }

        const slope = (count * sumXY - sumX * sumY) / denominator;
        const correlation = (count * sumXY - sumX * sumY) / Math.sqrt(denominator * spread);

        return { slope: slope, r2: correlation * correlation };
    }

    function sparkline(points, color, area)
    {
        if (points.length < 2)
        {
            return '';
        }

        const limit = 60;
        const stride = Math.max(1, Math.ceil(points.length / limit));
        const data = points.filter((point, index) => index % stride === 0 || index === points.length - 1);
        const values = data.map(point => point.value);
        const min = Math.min(...values);
        const max = Math.max(...values);
        const span = max - min || 1;
        const width = 120;
        const height = 34;
        const coords = data.map(function (point, index)
        {
            const x = (index / (data.length - 1)) * width;
            const y = height - 3 - ((point.value - min) / span) * (height - 6);

            return x.toFixed(1) + ',' + y.toFixed(1);
        });

        const line = 'M' + coords.join(' L');
        const fill = line + ' L' + width + ',' + height + ' L0,' + height + ' Z';

        return '<svg viewBox="0 0 120 34" preserveAspectRatio="none" aria-hidden="true">'
            + (area === false ? '' : '<path d="' + fill + '" fill="' + color + '" opacity=".14"></path>')
            + '<path d="' + line + '" fill="none" stroke="' + color + '" stroke-width="1.6" vector-effect="non-scaling-stroke" stroke-linejoin="round"></path>'
            + '</svg>';
    }

    function toneFor(value, limits)
    {
        if (!Number.isFinite(value))
        {
            return '';
        }

        if (value >= limits[1])
        {
            return 'bad';
        }

        if (value >= limits[0])
        {
            return 'warn';
        }

        return 'ok';
    }

    function hexToRgba(hex, alpha)
    {
        const value = hex.replace('#', '');
        const red = parseInt(value.substring(0, 2), 16);
        const green = parseInt(value.substring(2, 4), 16);
        const blue = parseInt(value.substring(4, 6), 16);

        return 'rgba(' + red + ',' + green + ',' + blue + ',' + alpha + ')';
    }

    function niceCeil(value)
    {
        if (!(value > 0))
        {
            return 1;
        }

        const power = Math.pow(10, Math.floor(Math.log10(value)));
        const normalized = value / power;
        const step = normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 5 ? 5 : 10;

        return step * power;
    }

    function cssVar(name, fallback)
    {
        return getComputedStyle(root).getPropertyValue(name).trim() || fallback;
    }

    /* ---------- Data ---------- */

    function seriesFromResult(result, prefix)
    {
        return result.map(function (item)
        {
            let label = Object.entries(item.metric || {})
                .filter(function ([key])
                {
                    return !['__name__', 'agent_id', 'server_id', 'application_id'].includes(key);
                })
                .map(function ([key, value])
                {
                    return key + ': ' + value;
                })
                .join(' · ');

            if (prefix)
            {
                label = prefix + (label ? ' · ' + label : '');
            }

            return {
                label: label || 'Value',
                values: (item.values || []).map(function (point)
                {
                    return { time: Number(point[0]), value: Number(point[1]) };
                }).filter(function (point)
                {
                    return Number.isFinite(point.time) && Number.isFinite(point.value);
                })
            };
        }).filter(function (series)
        {
            return series.values.length > 0;
        });
    }

    function queryUrl(metric, start, end, step)
    {
        const url = new URL(root.dataset.queryUrl + '/' + metric.name, window.location.origin);

        if (elements.server.value)
        {
            url.searchParams.set('server_id', elements.server.value);
        }

        if (elements.application.value && metric.scoped)
        {
            url.searchParams.set('application_id', elements.application.value);
        }

        url.searchParams.set('start', start);
        url.searchParams.set('end', end);
        url.searchParams.set('step', String(step));

        return url.toString();
    }

    async function getJson(url, signal)
    {
        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            signal: signal
        });

        const payload = await response.json().catch(function ()
        {
            return {};
        });

        if (!response.ok || payload.status !== 'ok')
        {
            throw new Error(payload.message || 'Request failed (' + response.status + ').');
        }

        return payload;
    }

    /* ---------- Charts ---------- */

    function visibleSeries(chart)
    {
        return chart.series.filter((item, index) => !chart.hidden.has(index));
    }

    function setChart(id, series, options)
    {
        const previous = charts[id];

        charts[id] = Object.assign({
            id: id,
            series: series,
            hidden: previous && previous.series.length === series.length ? previous.hidden : new Set(),
            hoverX: null
        }, options);

        renderLegend(id);
        renderChartStats(id);
        drawChart(id);
    }

    function renderLegend(id)
    {
        const container = document.querySelector('[data-legend="' + id + '"]');
        const chart = charts[id];

        if (!container || !chart)
        {
            return;
        }

        const limit = 8;
        const items = chart.series.slice(0, limit).map(function (item, index)
        {
            const last = lastPoint(item);

            return '<button type="button" class="infra-legend-item' + (chart.hidden.has(index) ? ' is-off' : '') + '" data-index="' + index + '" title="' + esc(item.label) + '">'
                + '<span class="infra-swatch" style="background:' + palette[index % palette.length] + '"></span>'
                + '<span>' + esc(item.label) + '</span>'
                + '<em>' + esc(formatValue(last.value, chart.unit)) + '</em>'
                + '</button>';
        });

        if (chart.series.length > limit)
        {
            items.push('<span class="infra-chip">+' + (chart.series.length - limit) + ' more</span>');
        }

        container.innerHTML = items.join('');
    }

    function renderChartStats(id)
    {
        const container = document.querySelector('[data-stats="' + id + '"]');
        const chart = charts[id];

        if (!container)
        {
            return;
        }

        const points = chart ? aggregate(chart.series, chart.mode || 'max') : [];
        const stats = statsOf(points);

        if (!stats)
        {
            container.innerHTML = '';
            return;
        }

        container.innerHTML = '<span class="infra-chip">AVG <b>' + esc(formatValue(stats.avg, chart.unit)) + '</b></span>'
            + '<span class="infra-chip">P95 <b>' + esc(formatValue(stats.p95, chart.unit)) + '</b></span>'
            + '<span class="infra-chip">MAX <b>' + esc(formatValue(stats.max, chart.unit)) + '</b></span>';
    }

    function drawChart(id)
    {
        const chart = charts[id];
        const canvas = document.getElementById(id);

        if (!chart || !canvas)
        {
            return;
        }

        const wrap = canvas.parentElement;
        const empty = wrap.querySelector('.infra-empty');
        const tip = tipFor(wrap);

        if (!chart.series.length)
        {
            canvas.hidden = true;
            empty.hidden = false;
            empty.textContent = chart.message || empty.dataset.default;
            tip.style.display = 'none';
            return;
        }

        canvas.hidden = false;
        empty.hidden = true;

        const context = canvas.getContext('2d');
        const rect = canvas.getBoundingClientRect();
        const ratio = window.devicePixelRatio || 1;
        const width = Math.max(240, rect.width);
        const height = 210;

        canvas.width = width * ratio;
        canvas.height = height * ratio;
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        context.clearRect(0, 0, width, height);

        const muted = cssVar('--muted', '#64748b');
        const lineColor = cssVar('--line', '#dce3f1');
        const surface = cssVar('--surface', '#ffffff');
        const right = width - 14;
        const top = 12;
        const bottom = height - 26;
        const t0 = windowInfo.start;
        const t1 = Math.max(windowInfo.end, t0 + 1);
        const active = visibleSeries(chart);
        const pool = (active.length ? active : chart.series).flatMap(item => item.values);
        const peak = Math.max(0, ...pool.map(point => point.value));
        let yMax;

        if (chart.unit === 'percent')
        {
            yMax = peak > 50 ? 100 : peak > 20 ? 50 : 20;
        }
        else if (chart.unit === 'boolean')
        {
            yMax = 1;
        }
        else if (chart.unit === 'bytes_per_second')
        {
            const scale = Math.pow(1024, Math.max(0, Math.min(3, Math.floor(Math.log(Math.max(peak, 1)) / Math.log(1024)))));

            yMax = niceCeil((peak * 1.08) / scale) * scale;
        }
        else if (chart.unit === 'seconds')
        {
            const steps = [10, 30, 60, 120, 300, 600, 900, 1800, 3600, 7200, 21600, 43200, 86400];

            yMax = steps.find(step => step >= peak * 1.05) || niceCeil(peak * 1.05);
        }
        else
        {
            yMax = niceCeil(peak * 1.08);
        }

        context.font = '10px ui-monospace, SFMono-Regular, Menlo, monospace';

        const ticks = chart.unit === 'boolean' ? 1 : 4;
        const axisLabel = function (value)
        {
            if (chart.unit === 'percent')
            {
                return Math.round(value) + '%';
            }

            if (chart.unit === 'boolean')
            {
                return value >= 1 ? 'UP' : 'DOWN';
            }

            return formatValue(value, chart.unit).replace(/\.0(?= )/, '');
        };
        let left = 44;

        for (let index = 0; index <= ticks; index++)
        {
            left = Math.max(left, context.measureText(axisLabel((yMax / ticks) * index)).width + 16);
        }

        const xOf = time => left + ((Math.min(Math.max(time, t0), t1) - t0) / (t1 - t0)) * (right - left);
        const yOf = value => bottom - (Math.min(value, yMax) / yMax) * (bottom - top);
        context.textBaseline = 'middle';
        context.lineWidth = 1;

        for (let index = 0; index <= ticks; index++)
        {
            const value = (yMax / ticks) * index;
            const y = yOf(value);

            context.strokeStyle = lineColor;
            context.beginPath();
            context.moveTo(left, y);
            context.lineTo(right, y);
            context.stroke();

            context.fillStyle = muted;
            context.textAlign = 'right';

            context.fillText(axisLabel(value), left - 6, y);
        }

        context.textBaseline = 'alphabetic';
        context.textAlign = 'center';

        const withDate = (t1 - t0) > 86400 * 1.5;

        for (let index = 0; index <= 4; index++)
        {
            const time = t0 + ((t1 - t0) / 4) * index;
            const x = left + ((right - left) / 4) * index;

            context.fillStyle = muted;
            context.textAlign = index === 0 ? 'left' : index === 4 ? 'right' : 'center';
            context.fillText(clock(time, withDate), index === 0 ? left : index === 4 ? right : x, height - 8);
        }

        (chart.thresholds || []).forEach(function (limit, index)
        {
            if (limit > yMax)
            {
                return;
            }

            const y = yOf(limit);

            context.save();
            context.setLineDash([4, 4]);
            context.strokeStyle = index === 0 ? '#d97706' : '#dc4c64';
            context.globalAlpha = .55;
            context.beginPath();
            context.moveTo(left, y);
            context.lineTo(right, y);
            context.stroke();
            context.restore();
        });

        const gap = windowInfo.step * 2.5;
        const fillable = active.length <= 2 && chart.unit !== 'boolean';

        chart.series.forEach(function (item, index)
        {
            if (chart.hidden.has(index))
            {
                return;
            }

            const color = palette[index % palette.length];
            let segment = [];
            const segments = [];

            item.values.forEach(function (point, position)
            {
                if (position > 0 && point.time - item.values[position - 1].time > gap)
                {
                    segments.push(segment);
                    segment = [];
                }

                segment.push(point);
            });

            segments.push(segment);

            segments.forEach(function (points)
            {
                if (points.length === 1)
                {
                    context.fillStyle = color;
                    context.beginPath();
                    context.arc(xOf(points[0].time), yOf(points[0].value), 2.5, 0, Math.PI * 2);
                    context.fill();
                    return;
                }

                context.beginPath();

                points.forEach(function (point, position)
                {
                    const x = xOf(point.time);
                    const y = yOf(point.value);

                    if (position === 0)
                    {
                        context.moveTo(x, y);
                    }
                    else if (chart.step)
                    {
                        context.lineTo(x, yOf(points[position - 1].value));
                        context.lineTo(x, y);
                    }
                    else
                    {
                        context.lineTo(x, y);
                    }
                });

                context.strokeStyle = color;
                context.lineWidth = 1.8;
                context.lineJoin = 'round';
                context.stroke();

                if (fillable)
                {
                    context.lineTo(xOf(points[points.length - 1].time), bottom);
                    context.lineTo(xOf(points[0].time), bottom);
                    context.closePath();

                    const gradient = context.createLinearGradient(0, top, 0, bottom);
                    gradient.addColorStop(0, hexToRgba(color, .24));
                    gradient.addColorStop(1, hexToRgba(color, 0));
                    context.fillStyle = gradient;
                    context.fill();
                }
            });
        });

        if (chart.hoverX !== null && chart.hoverX >= left && chart.hoverX <= right)
        {
            drawHover(chart, context, tip, { left: left, right: right, top: top, bottom: bottom, width: width, t0: t0, t1: t1, xOf: xOf, yOf: yOf, surface: surface, muted: muted });
        }
        else
        {
            tip.style.display = 'none';
        }
    }

    function nearestPoint(values, time)
    {
        let low = 0;
        let high = values.length - 1;

        while (low < high)
        {
            const middle = Math.floor((low + high) / 2);

            if (values[middle].time < time)
            {
                low = middle + 1;
            }
            else
            {
                high = middle;
            }
        }

        const candidate = values[low];
        const before = values[low - 1];

        if (before && Math.abs(before.time - time) < Math.abs(candidate.time - time))
        {
            return before;
        }

        return candidate;
    }

    function drawHover(chart, context, tip, geometry)
    {
        const time = geometry.t0 + ((chart.hoverX - geometry.left) / (geometry.right - geometry.left)) * (geometry.t1 - geometry.t0);
        const rows = [];
        let anchor = null;

        chart.series.forEach(function (item, index)
        {
            if (chart.hidden.has(index))
            {
                return;
            }

            const point = nearestPoint(item.values, time);

            if (!point || Math.abs(point.time - time) > windowInfo.step * 2)
            {
                return;
            }

            if (anchor === null || Math.abs(point.time - time) < Math.abs(anchor - time))
            {
                anchor = point.time;
            }

            rows.push({ color: palette[index % palette.length], label: item.label, point: point });
        });

        if (anchor === null)
        {
            tip.style.display = 'none';
            return;
        }

        const x = geometry.xOf(anchor);

        context.save();
        context.strokeStyle = geometry.muted;
        context.globalAlpha = .6;
        context.setLineDash([3, 3]);
        context.beginPath();
        context.moveTo(x, geometry.top);
        context.lineTo(x, geometry.bottom);
        context.stroke();
        context.restore();

        rows.forEach(function (row)
        {
            context.fillStyle = geometry.surface;
            context.strokeStyle = row.color;
            context.lineWidth = 2;
            context.beginPath();
            context.arc(geometry.xOf(row.point.time), geometry.yOf(row.point.value), 3.5, 0, Math.PI * 2);
            context.fill();
            context.stroke();
        });

        tip.innerHTML = '<div class="infra-tip-time">' + esc(clock(anchor, true)) + '</div>'
            + rows.slice(0, 8).map(function (row)
            {
                return '<div class="infra-tip-row">'
                    + '<span class="infra-swatch" style="background:' + row.color + '"></span>'
                    + '<span>' + esc(row.label) + '</span>'
                    + '<span>' + esc(formatValue(row.point.value, chart.unit)) + '</span>'
                    + '</div>';
            }).join('');

        tip.style.display = 'block';

        const tipWidth = tip.offsetWidth;
        const place = x + 14 + tipWidth > geometry.width ? x - tipWidth - 14 : x + 14;
        tip.style.left = Math.max(4, place) + 'px';
    }

    function tipFor(wrap)
    {
        let tip = wrap.querySelector('.infra-tip');

        if (!tip)
        {
            tip = document.createElement('div');
            tip.className = 'infra-tip';
            wrap.appendChild(tip);
        }

        return tip;
    }

    function redrawAll()
    {
        Object.keys(charts).forEach(drawChart);
    }

    let redrawFrame = 0;

    function scheduleRedraw()
    {
        cancelAnimationFrame(redrawFrame);
        redrawFrame = requestAnimationFrame(function ()
        {
            redrawAll();
            renderKpis();
        });
    }

    function bindChart(id)
    {
        const canvas = document.getElementById(id);
        let frame = 0;

        canvas.addEventListener('pointermove', function (event)
        {
            const chart = charts[id];

            if (!chart)
            {
                return;
            }

            chart.hoverX = event.clientX - canvas.getBoundingClientRect().left;
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(function ()
            {
                drawChart(id);
            });
        });

        canvas.addEventListener('pointerleave', function ()
        {
            const chart = charts[id];

            if (chart)
            {
                chart.hoverX = null;
                drawChart(id);
            }
        });
    }

    document.querySelectorAll('[data-legend]').forEach(function (container)
    {
        container.addEventListener('click', function (event)
        {
            const button = event.target.closest('.infra-legend-item');
            const chart = charts[container.dataset.legend];

            if (!button || !chart)
            {
                return;
            }

            const index = Number(button.dataset.index);

            if (chart.hidden.has(index))
            {
                chart.hidden.delete(index);
            }
            else
            {
                chart.hidden.add(index);
            }

            renderLegend(chart.id);
            drawChart(chart.id);
        });
    });

    ['chart-cpu', 'chart-memory', 'chart-disk', 'chart-network', 'chart-probe', 'chart-latency', 'chart-stream-age', 'chart-stream-up'].forEach(bindChart);

    /* ---------- KPI ---------- */

    const kpis = [
        { id: 'infra-cpu', metric: 'cpu', mode: 'avg', unit: 'percent', limits: thresholds.cpu, meter: true, label: 'CPU' },
        { id: 'infra-memory', metric: 'memory', mode: 'avg', unit: 'percent', limits: thresholds.memory, meter: true, label: 'Memory' },
        { id: 'infra-disk', metric: 'disk', mode: 'avg', unit: 'percent', limits: thresholds.disk, meter: true, label: 'Disk' },
        { id: 'infra-network', metric: 'rx', mode: 'sum', unit: 'bytes_per_second', meter: false, label: 'Receive' },
        { id: 'infra-transmit', metric: 'tx', mode: 'sum', unit: 'bytes_per_second', meter: false, label: 'Transmit' }
    ];

    function setKpi(id, data)
    {
        const card = document.getElementById(id + '-card');

        card.dataset.tone = data.tone || '';
        document.getElementById(id + '-value').textContent = data.value;
        document.getElementById(id + '-meta').textContent = data.meta;
        document.getElementById(id + '-spark').innerHTML = data.spark || '';

        const delta = document.getElementById(id + '-delta');
        delta.textContent = data.delta || '';
        delta.className = 'infra-delta' + (data.deltaTone ? ' ' + data.deltaTone : '');

        const meter = document.getElementById(id + '-meter');

        if (meter)
        {
            meter.style.width = Math.max(0, Math.min(100, data.meter || 0)) + '%';
        }

        document.getElementById(id + '-stats').innerHTML = (data.stats || []).map(function (entry)
        {
            return '<div class="infra-mini"><small>' + esc(entry[0]) + '</small>' + esc(entry[1]) + '</div>';
        }).join('');
    }

    function renderKpis()
    {
        kpis.forEach(function (kpi)
        {
            const entry = store[kpi.metric];
            const series = entry && entry.ok ? entry.series : [];

            if (!series.length)
            {
                setKpi(kpi.id, {
                    value: '—',
                    meta: entry && !entry.ok ? 'Metric unavailable' : 'No samples in this range',
                    tone: ''
                });
                return;
            }

            const points = aggregate(series, kpi.mode);
            const stats = statsOf(points);
            const latest = latestOf(series, kpi.mode);
            const trend = trendOf(points);
            const isPercent = kpi.unit === 'percent';
            const color = kpi.limits ? ({ ok: '#12a150', warn: '#d97706', bad: '#dc4c64' }[toneFor(latest, kpi.limits)] || palette[0]) : palette[0];
            let delta = '';
            let deltaTone = '';

            if (trend)
            {
                const arrow = trend.diff > 0 ? '▲ ' : trend.diff < 0 ? '▼ ' : '';

                if (isPercent)
                {
                    delta = arrow + Math.abs(trend.diff).toFixed(1) + ' pp';
                    deltaTone = trend.diff >= 5 ? 'up' : trend.diff <= -5 ? 'down' : '';
                }
                else
                {
                    const base = Math.max(trend.prior, 1);
                    const percent = (trend.diff / base) * 100;
                    delta = arrow + Math.min(999, Math.abs(percent)).toFixed(0) + '%';
                }
            }

            setKpi(kpi.id, {
                value: formatValue(latest, kpi.unit),
                meta: series.length > 1
                    ? (kpi.mode === 'max' ? 'Highest of ' : 'Total of ') + series.length + ' series'
                    : 'Latest sample · ' + clock(lastPoint(series[0]).time),
                tone: kpi.limits ? toneFor(latest, kpi.limits) : '',
                spark: sparkline(points, color),
                delta: delta,
                deltaTone: deltaTone,
                meter: isPercent ? latest : 0,
                stats: [
                    ['Min', formatValue(stats.min, kpi.unit)],
                    ['Avg', formatValue(stats.avg, kpi.unit)],
                    ['P95', formatValue(stats.p95, kpi.unit)],
                    ['Max', formatValue(stats.max, kpi.unit)]
                ]
            });
        });

        const probeEntry = store.probe;
        const probeSeries = probeEntry && probeEntry.ok ? probeEntry.series : [];

        if (!probeSeries.length)
        {
            setKpi('infra-probe', {
                value: '—',
                meta: probeEntry && !probeEntry.ok ? 'Metric unavailable' : 'No probe samples in this range'
            });
            return;
        }

        const ratioPoints = aggregate(probeSeries, 'avg').map(point => ({ time: point.time, value: point.value * 100 }));
        const total = probeSeries.reduce((sum, item) => sum + item.values.length, 0);
        const okCount = probeSeries.reduce((sum, item) => sum + item.values.filter(point => point.value >= 1).length, 0);
        const availability = (okCount / total) * 100;
        const nowOk = probeSeries.filter(item => lastPoint(item).value >= 1).length;
        const tone = availability >= 99 ? 'ok' : availability >= 50 ? 'warn' : 'bad';
        const color = { ok: '#12a150', warn: '#d97706', bad: '#dc4c64' }[tone];

        setKpi('infra-probe', {
            value: availability.toFixed(1) + '%',
            meta: nowOk + ' of ' + probeSeries.length + ' probes reporting healthy now',
            tone: tone,
            spark: sparkline(ratioPoints, color),
            meter: availability,
            stats: [
                ['Probes', String(probeSeries.length)],
                ['Healthy', String(nowOk)],
                ['Failing', String(probeSeries.length - nowOk)],
                ['Samples', String(total)]
            ]
        });
    }

    /* ---------- Score and insights ---------- */

    function buildScore()
    {
        let score = 100;
        const factors = [];

        if (!overview)
        {
            factors.push({ tone: 'bad', name: 'Prometheus', text: 'unreachable' });
            return { score: null, factors: factors };
        }

        factors.push({ tone: 'ok', name: 'Prometheus', text: 'reachable' });

        if (overview.exporter === 'up')
        {
            factors.push({ tone: 'ok', name: 'Exporter', text: 'up' });
        }
        else
        {
            score -= 25;
            factors.push({ tone: 'bad', name: 'Exporter', text: 'down' });
        }

        const targetTotal = Number(overview.target_count || 0);
        const targetsDown = Number(overview.targets_down || 0);

        if (targetTotal > 0)
        {
            score -= (targetsDown / targetTotal) * 20;
            factors.push({
                tone: targetsDown === 0 ? 'ok' : 'warn',
                name: 'Scrape targets',
                text: (targetTotal - targetsDown) + '/' + targetTotal + ' up'
            });
        }

        const resources = [['cpu', 'CPU', thresholds.cpu], ['memory', 'Memory', thresholds.memory], ['disk', 'Disk', thresholds.disk]];
        const worst = { tone: 'ok', text: '' };
        const rank = { ok: 0, warn: 1, bad: 2 };
        let resourceSeen = false;

        resources.forEach(function (resource)
        {
            const entry = store[resource[0]];

            if (!entry || !entry.ok || !entry.series.length)
            {
                return;
            }

            resourceSeen = true;

            const latest = latestOf(entry.series, 'max');
            const tone = toneFor(latest, resource[2]);

            if (tone === 'bad')
            {
                score -= 12;
            }
            else if (tone === 'warn')
            {
                score -= 6;
            }

            if (rank[tone] > rank[worst.tone] || !worst.text)
            {
                worst.tone = tone;
                worst.text = resource[1] + ' ' + latest.toFixed(0) + '%';
            }
        });

        if (resourceSeen)
        {
            factors.push({ tone: worst.tone, name: 'Resources', text: 'peak ' + worst.text });
        }

        const probeEntry = store.probe;

        if (probeEntry && probeEntry.ok && probeEntry.series.length)
        {
            const healthy = probeEntry.series.filter(item => lastPoint(item).value >= 1).length;
            const ratio = healthy / probeEntry.series.length;

            score -= (1 - ratio) * 10;
            factors.push({
                tone: ratio === 1 ? 'ok' : ratio >= 0.5 ? 'warn' : 'bad',
                name: 'App probes',
                text: healthy + '/' + probeEntry.series.length + ' healthy'
            });
        }

        const upEntry = store.up;
        const ageEntry = store.age;

        if (upEntry && upEntry.ok && upEntry.series.length)
        {
            const live = upEntry.series.filter(item => lastPoint(item).value >= 1).length;
            const ratio = live / upEntry.series.length;
            const stale = ageEntry && ageEntry.ok
                ? ageEntry.series.filter(item => lastPoint(item).value > 300).length
                : 0;

            score -= (1 - ratio) * 10 + Math.min(5, stale);
            factors.push({
                tone: ratio === 1 && stale === 0 ? 'ok' : ratio >= 0.5 ? 'warn' : 'bad',
                name: 'Telemetry streams',
                text: live + '/' + upEntry.series.length + ' live' + (stale ? ' · ' + stale + ' stale' : '')
            });
        }

        return { score: Math.max(0, Math.min(100, Math.round(score))), factors: factors };
    }

    function renderScore()
    {
        const result = buildScore();
        const bar = document.getElementById('infra-score-bar');
        const panel = document.getElementById('infra-score-panel');
        const circumference = 326.7;
        let label = 'No data';
        let color = '#64748b';

        if (result.score !== null)
        {
            const score = result.score;

            if (score >= 90)
            {
                label = 'Optimal';
                color = '#12a150';
            }
            else if (score >= 75)
            {
                label = 'Stable';
                color = '#2f6fed';
            }
            else if (score >= 50)
            {
                label = 'Degraded';
                color = '#d97706';
            }
            else
            {
                label = 'Critical';
                color = '#dc4c64';
            }

            bar.style.strokeDashoffset = String(circumference * (1 - score / 100));
        }
        else
        {
            bar.style.strokeDashoffset = String(circumference);
        }

        bar.style.stroke = color;
        panel.style.setProperty('--score-glow', hexToRgba(color, .18));
        document.getElementById('infra-score-value').textContent = result.score === null ? '—' : String(result.score);
        document.getElementById('infra-score-label').textContent = label;

        document.getElementById('infra-score-factors').innerHTML = result.factors.map(function (factor)
        {
            return '<div class="infra-factor">'
                + '<span class="infra-factor-name"><span class="infra-dot ' + factor.tone + '"></span>' + esc(factor.name) + '</span>'
                + '<span class="infra-factor-text">' + esc(factor.text) + '</span>'
                + '</div>';
        }).join('');
    }

    function buildInsights()
    {
        const list = [];
        const add = function (tone, title, detail)
        {
            list.push({ tone: tone, title: title, detail: detail });
        };

        if (!overview)
        {
            add('bad', 'Prometheus tidak dapat dijangkau', overviewError || 'Gateway tidak mendapat respons valid dari Prometheus.');
        }
        else
        {
            if (overview.exporter !== 'up')
            {
                add('bad', 'Exporter NADI tidak melaporkan status up', 'Metric exporter_up tidak bernilai 1, sehingga data di halaman ini bisa tidak lengkap.');
            }

            if (Number(overview.targets_down) > 0)
            {
                const names = (overview.targets || [])
                    .filter(target => target.health !== 'up')
                    .map(target => target.job)
                    .join(', ');

                add('warn', overview.targets_down + ' dari ' + overview.target_count + ' scrape target down', names || 'Periksa halaman Prometheus Targets.');
            }
        }

        [
            ['cpu', 'CPU', thresholds.cpu],
            ['memory', 'Memory', thresholds.memory],
            ['disk', 'Disk', thresholds.disk]
        ].forEach(function (resource)
        {
            const entry = store[resource[0]];

            if (!entry || !entry.ok || !entry.series.length)
            {
                return;
            }

            const crest = entry.series
                .map(item => ({ label: item.label, value: lastPoint(item).value }))
                .sort((a, b) => b.value - a.value)[0];
            const tone = toneFor(crest.value, resource[2]);
            const stats = statsOf(aggregate(entry.series, 'max'));

            if (tone === 'bad' || tone === 'warn')
            {
                add(tone, resource[1] + ' berada di ' + crest.value.toFixed(1) + '%', 'Batas ' + (tone === 'bad' ? 'critical ' + resource[2][1] : 'warning ' + resource[2][0]) + '%. Series: ' + crest.label + '.');
            }
            else if (stats.max >= resource[2][1])
            {
                add('info', resource[1] + ' sempat mencapai ' + stats.max.toFixed(1) + '%', 'Saat ini sudah turun ke ' + crest.value.toFixed(1) + '%.');
            }

            const trend = trendOf(aggregate(entry.series, 'max'));

            if (trend && trend.diff >= 10 && tone === 'ok')
            {
                add('info', resource[1] + ' naik ' + trend.diff.toFixed(1) + ' poin', 'Rata-rata periode terbaru ' + trend.recent.toFixed(1) + '% dibanding ' + trend.prior.toFixed(1) + '% sebelumnya.');
            }
        });

        const disk = store.disk;

        if (disk && disk.ok)
        {
            disk.series.forEach(function (item)
            {
                const fit = regression(item.values);
                const last = lastPoint(item).value;

                if (!fit || fit.r2 < 0.6 || fit.slope <= 0.05 || last >= 100)
                {
                    return;
                }

                const days = (100 - last) / fit.slope;

                if (days <= 60)
                {
                    add(days <= 7 ? 'bad' : 'warn', 'Disk diperkirakan penuh dalam ±' + Math.max(1, Math.round(days)) + ' hari', item.label + ' naik ±' + fit.slope.toFixed(2) + ' poin/hari (regresi linear, estimasi kasar).');
                }
            });
        }

        const rx = store.rx;

        if (rx && rx.ok && rx.series.length)
        {
            const stats = statsOf(aggregate(rx.series, 'sum'));

            if (stats && stats.avg > 0 && stats.max > stats.avg * 4 && stats.max > 1048576)
            {
                add('info', 'Lonjakan trafik masuk ' + formatValue(stats.max, 'bytes_per_second'), 'Sekitar ' + (stats.max / stats.avg).toFixed(1) + 'x dari rata-rata ' + formatValue(stats.avg, 'bytes_per_second') + '.');
            }
        }

        const probe = store.probe;

        if (probe && probe.ok && probe.series.length)
        {
            const failing = probe.series.filter(item => lastPoint(item).value < 1);

            if (failing.length === probe.series.length)
            {
                add('warn', 'Semua probe aplikasi melaporkan status tidak sehat', 'Nilai 0 tidak otomatis berarti aplikasi down; bisa juga probe belum didukung aplikasi.');
            }
            else if (failing.length)
            {
                add('warn', failing.length + ' probe aplikasi tidak sehat', failing.slice(0, 4).map(item => item.label).join(' · '));
            }
        }

        const latency = store.latency;

        if (latency && latency.ok && latency.series.length)
        {
            const values = latency.series.flatMap(item => item.values.map(point => point.value)).sort((a, b) => a - b);
            const p95 = quantile(values, 0.95);

            if (p95 > 1000)
            {
                add('warn', 'Latency probe P95 ' + formatValue(p95, 'milliseconds'), 'Di atas 1 detik pada rentang waktu terpilih.');
            }
        }

        const age = store.age;

        if (age && age.ok)
        {
            const stale = age.series.filter(item => lastPoint(item).value > 300);

            if (stale.length)
            {
                add('warn', stale.length + ' telemetry stream tidak segar', 'Umur sampel terakhir > 5 menit: ' + stale.slice(0, 3).map(item => item.label + ' (' + duration(lastPoint(item).value) + ')').join(' · '));
            }
        }

        const up = store.up;

        if (up && up.ok)
        {
            const down = up.series.filter(item => lastPoint(item).value < 1);

            if (down.length)
            {
                add('bad', down.length + ' telemetry stream down', down.slice(0, 3).map(item => item.label).join(' · '));
            }
        }

        const broken = metrics.filter(metric => store[metric.key] && !store[metric.key].ok);

        if (broken.length)
        {
            add('warn', broken.length + ' metric gagal dibaca', broken.map(metric => metric.name).join(', '));
        }

        if (!list.some(item => item.tone === 'bad' || item.tone === 'warn') && overview)
        {
            list.push({ tone: 'ok', title: 'Semua indikator utama dalam batas normal', detail: 'Tidak ada pelanggaran batas CPU, memory, disk, probe, maupun kesegaran telemetry.' });
        }

        const order = { bad: 0, warn: 1, info: 2, ok: 3 };

        return list.sort((a, b) => order[a.tone] - order[b.tone]);
    }

    function renderInsights()
    {
        const list = buildInsights();
        const icons = { bad: '!', warn: '!', info: 'i', ok: '✓' };
        const issues = list.filter(item => item.tone === 'bad' || item.tone === 'warn').length;

        document.getElementById('infra-insight-count').innerHTML = issues
            ? '<b>' + issues + '</b> perlu perhatian'
            : '<b>0</b> masalah';

        document.getElementById('infra-insights').innerHTML = list.map(function (item)
        {
            return '<div class="infra-insight ' + item.tone + '">'
                + '<div class="infra-insight-icon">' + icons[item.tone] + '</div>'
                + '<div><div class="infra-insight-title">' + esc(item.title) + '</div>'
                + '<div class="infra-insight-detail">' + esc(item.detail) + '</div></div>'
                + '</div>';
        }).join('');
    }

    /* ---------- Boards ---------- */

    function renderProbeBoard()
    {
        const body = document.getElementById('infra-probe-board');
        const probe = store.probe;
        const latency = store.latency;

        if (!probe || !probe.ok || !probe.series.length)
        {
            body.innerHTML = '<tr><td colspan="6">' + esc(probe && !probe.ok ? probe.error : 'No application probe samples in this range.') + '</td></tr>';
            return;
        }

        const latencyMap = new Map();

        if (latency && latency.ok)
        {
            latency.series.forEach(item => latencyMap.set(item.label, item));
        }

        body.innerHTML = probe.series.map(function (item)
        {
            const healthy = lastPoint(item).value >= 1;
            const okCount = item.values.filter(point => point.value >= 1).length;
            const availability = (okCount / item.values.length) * 100;
            const related = latencyMap.get(item.label);
            const stats = related ? statsOf(related.values) : null;

            return '<tr>'
                + '<td class="infra-cell-name" title="' + esc(item.label) + '">' + esc(item.label) + '</td>'
                + '<td><span class="infra-pill ' + (healthy ? 'ok' : 'bad') + '">' + (healthy ? 'Healthy' : 'Unhealthy') + '</span></td>'
                + '<td><span class="infra-bar"><span style="width:' + availability.toFixed(0) + '%;background:' + (availability >= 99 ? 'var(--infra-ok)' : availability >= 50 ? 'var(--infra-warn)' : 'var(--infra-bad)') + '"></span></span>' + availability.toFixed(1) + '%</td>'
                + '<td>' + (stats ? esc(formatValue(stats.avg, 'milliseconds')) : '—') + '</td>'
                + '<td>' + (stats ? esc(formatValue(stats.p95, 'milliseconds')) : '—') + '</td>'
                + '<td><div class="infra-spark">' + (related ? sparkline(related.values, palette[0], false) : '') + '</div></td>'
                + '</tr>';
        }).join('');
    }

    function renderStreamBoard()
    {
        const body = document.getElementById('infra-stream-board');
        const up = store.up;
        const age = store.age;
        const rows = new Map();

        if (up && up.ok)
        {
            up.series.forEach(item => rows.set(item.label, { up: item, age: null }));
        }

        if (age && age.ok)
        {
            age.series.forEach(function (item)
            {
                const row = rows.get(item.label) || { up: null, age: null };
                row.age = item;
                rows.set(item.label, row);
            });
        }

        if (!rows.size)
        {
            const failed = (up && !up.ok && up.error) || (age && !age.ok && age.error);

            body.innerHTML = '<tr><td colspan="6">' + esc(failed || 'No telemetry stream samples in this range.') + '</td></tr>';
            return;
        }

        body.innerHTML = Array.from(rows.entries()).map(function (entry)
        {
            const label = entry[0];
            const row = entry[1];
            const live = row.up ? lastPoint(row.up).value >= 1 : null;
            const ageNow = row.age ? lastPoint(row.age).value : null;
            const ageStats = row.age ? statsOf(row.age.values) : null;
            const uptime = row.up ? (row.up.values.filter(point => point.value >= 1).length / row.up.values.length) * 100 : null;
            const stale = ageNow !== null && ageNow > 300;
            let tone = 'ok';
            let text = 'Live';

            if (live === false)
            {
                tone = 'bad';
                text = 'Down';
            }
            else if (stale)
            {
                tone = 'warn';
                text = 'Stale';
            }
            else if (live === null)
            {
                tone = '';
                text = 'Unknown';
            }

            return '<tr>'
                + '<td class="infra-cell-name" title="' + esc(label) + '">' + esc(label) + '</td>'
                + '<td><span class="infra-pill ' + tone + '">' + text + '</span></td>'
                + '<td>' + (ageNow !== null ? esc(duration(ageNow)) : '—') + '</td>'
                + '<td>' + (ageStats ? esc(duration(ageStats.max)) : '—') + '</td>'
                + '<td>' + (uptime !== null ? uptime.toFixed(1) + '%' : '—') + '</td>'
                + '<td><div class="infra-spark">' + (row.age ? sparkline(row.age.values, palette[1], false) : '') + '</div></td>'
                + '</tr>';
        }).join('');
    }

    function renderTargets()
    {
        const count = document.getElementById('infra-target-count');

        if (!overview)
        {
            count.textContent = 'unavailable';
            elements.targets.innerHTML = '<div class="infra-empty">' + esc(overviewError || 'Prometheus is unavailable.') + '</div>';
            return;
        }

        const targets = overview.targets || [];

        count.innerHTML = '<b>' + Number(overview.targets_up || 0) + '</b>/' + targets.length + ' up';

        if (!targets.length)
        {
            elements.targets.innerHTML = '<div class="infra-empty">No active Prometheus targets.</div>';
            return;
        }

        elements.targets.innerHTML = '';

        targets.forEach(function (target)
        {
            const item = document.createElement('div');
            item.className = 'infra-target';

            const details = document.createElement('div');
            const name = document.createElement('div');
            name.className = 'infra-target-name';
            name.textContent = target.job;

            const meta = document.createElement('div');
            meta.className = 'infra-target-meta';
            meta.textContent = target.last_scrape
                ? 'Last scrape: ' + ago(target.last_scrape) + ' · ' + new Date(target.last_scrape).toLocaleString()
                : 'No scrape timestamp';

            const state = document.createElement('div');
            state.className = 'infra-target-state ' + (target.health === 'up' ? 'up' : 'down');
            state.textContent = target.health;

            details.append(name, meta);
            item.append(details, state);
            elements.targets.append(item);
        });
    }

    function renderCoverage()
    {
        document.getElementById('infra-query-time').innerHTML = '<b>' + Math.round(queryMs) + '</b> ms';

        document.getElementById('infra-coverage').innerHTML = metrics.map(function (metric)
        {
            const entry = store[metric.key];
            let tone = 'warn';
            let text = 'pending';

            if (entry && entry.ok)
            {
                const points = entry.series.reduce((sum, item) => sum + item.values.length, 0);

                tone = entry.series.length ? 'ok' : 'warn';
                text = entry.series.length ? entry.series.length + ' series · ' + points + ' pts' : 'no samples';
            }
            else if (entry)
            {
                tone = 'bad';
                text = entry.error || 'failed';
            }

            return '<div class="infra-coverage-row" title="' + esc(text) + '">'
                + '<span><span class="infra-dot ' + tone + '"></span>' + esc(metric.name) + '</span>'
                + '<span>' + esc(text.length > 34 ? text.slice(0, 34) + '…' : text) + '</span>'
                + '</div>';
        }).join('');
    }

    function renderCharts()
    {
        const grouped = {};

        metrics.forEach(function (metric)
        {
            const entry = store[metric.key];

            grouped[metric.chart] = grouped[metric.chart] || { series: [], errors: [], metric: metric };

            if (entry && entry.ok)
            {
                grouped[metric.chart].series.push(...entry.series);
            }
            else if (entry)
            {
                grouped[metric.chart].errors.push(entry.error);
            }
        });

        Object.keys(grouped).forEach(function (id)
        {
            const group = grouped[id];
            const key = group.metric.key;

            setChart(id, group.series, {
                unit: group.metric.unit,
                mode: 'avg',
                step: group.metric.unit === 'boolean',
                thresholds: thresholds[key] || null,
                message: group.errors.length && !group.series.length ? group.errors[0] : ''
            });
        });
    }

    function renderUpdated()
    {
        elements.updated.innerHTML = lastUpdate
            ? 'Updated <b>' + esc(lastUpdate.toLocaleTimeString()) + '</b>'
            : 'Updated <b>—</b>';
    }

    function setConnection(state, message)
    {
        elements.connection.dataset.state = state;
        elements.connectionText.textContent = message;
    }

    /* ---------- Refresh cycle ---------- */

    async function refresh()
    {
        if (controller)
        {
            controller.abort();
        }

        controller = new AbortController();

        const signal = controller.signal;
        const current = ++generation;
        const range = rangeSettings[elements.range.value] || rangeSettings['24h'];
        const endDate = new Date();
        const startDate = new Date(endDate.getTime() - range.seconds * 1000);
        const started = performance.now();

        root.classList.add('is-loading');
        elements.refresh.disabled = true;
        elements.refresh.textContent = 'Loading...';
        countdown = AUTO_SECONDS;

        const tasks = [
            getJson(root.dataset.overviewUrl, signal)
        ].concat(metrics.map(function (metric)
        {
            return getJson(queryUrl(metric, startDate.toISOString(), endDate.toISOString(), range.step), signal);
        }));

        const settled = await Promise.allSettled(tasks);

        if (current !== generation)
        {
            return;
        }

        queryMs = performance.now() - started;
        windowInfo = {
            start: Math.floor(startDate.getTime() / 1000),
            end: Math.floor(endDate.getTime() / 1000),
            step: range.step
        };

        const overviewResult = settled[0];

        if (overviewResult.status === 'fulfilled')
        {
            overview = overviewResult.value;
            overviewError = '';
            setConnection(
                overview.prometheus === 'reachable' && overview.exporter === 'up' ? 'up' : 'down',
                'Prometheus ' + overview.prometheus + ' · Exporter ' + overview.exporter
            );
        }
        else
        {
            overview = null;
            overviewError = overviewResult.reason && overviewResult.reason.message ? overviewResult.reason.message : 'Prometheus is unavailable.';
            setConnection('down', 'Monitoring data unavailable');
        }

        metrics.forEach(function (metric, index)
        {
            const result = settled[index + 1];

            if (result.status === 'fulfilled')
            {
                store[metric.key] = {
                    ok: true,
                    series: seriesFromResult(result.value.result || [], metric.prefix)
                };
            }
            else
            {
                store[metric.key] = {
                    ok: false,
                    series: [],
                    error: result.reason && result.reason.message ? result.reason.message : 'Metric query failed.'
                };
            }
        });

        lastUpdate = new Date();

        renderCharts();
        renderKpis();
        renderScore();
        renderInsights();
        renderProbeBoard();
        renderStreamBoard();
        renderTargets();
        renderCoverage();
        renderUpdated();

        root.classList.remove('is-loading');
        elements.refresh.disabled = false;
        elements.refresh.textContent = 'Refresh data';
    }

    function readAutoPreference()
    {
        try
        {
            return window.localStorage.getItem('nadi.infra.auto') !== '0';
        }
        catch (error)
        {
            return true;
        }
    }

    function writeAutoPreference(value)
    {
        try
        {
            window.localStorage.setItem('nadi.infra.auto', value ? '1' : '0');
        }
        catch (error)
        {
            /* Browser storage unavailable; preference stays in memory only. */
        }
    }

    function tick()
    {
        if (!elements.auto.checked)
        {
            elements.autoText.textContent = 'Auto off';
            return;
        }

        if (document.hidden)
        {
            elements.autoText.textContent = 'Auto paused';
            return;
        }

        countdown--;
        elements.autoText.textContent = 'Auto ' + Math.max(0, countdown) + 's';

        if (countdown <= 0)
        {
            refresh();
        }
    }

    elements.auto.checked = readAutoPreference();
    elements.auto.addEventListener('change', function ()
    {
        writeAutoPreference(elements.auto.checked);
        countdown = AUTO_SECONDS;
        tick();
    });

    elements.refresh.addEventListener('click', refresh);
    elements.server.addEventListener('change', refresh);
    elements.application.addEventListener('change', refresh);
    elements.range.addEventListener('change', refresh);

    if (window.ResizeObserver)
    {
        new ResizeObserver(scheduleRedraw).observe(root);
    }
    else
    {
        window.addEventListener('resize', scheduleRedraw);
    }

    /* Gambar ulang saat tema berubah (class/atribut pada html atau body). */
    const themeObserver = new MutationObserver(scheduleRedraw);

    themeObserver.observe(document.documentElement, { attributes: true });
    themeObserver.observe(document.body, { attributes: true });

    setInterval(tick, 1000);
    setInterval(function ()
    {
        if (overview)
        {
            renderTargets();
        }
    }, 15000);

    refresh();
});
</script>
@endpush