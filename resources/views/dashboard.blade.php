@extends('layouts.app')

@section('title', 'Dashboard - NADI Central')
@section('breadcrumb', 'Dashboard')

@push('styles')
<style>
    .nadi-dashboard {
        display: grid;
        gap: 24px;
    }

    .dashboard-heading {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .dashboard-heading h1 {
        margin: 0 0 6px;
        font-size: 25px;
        font-weight: 800;
    }

    .dashboard-heading p {
        margin: 0;
        color: var(--muted);
        font-size: 13px;
    }

    .dashboard-clock {
        color: var(--muted);
        font: 12px var(--mono);
    }

    .hero-panel {
        position: relative;
        overflow: hidden;
        padding: 28px;
        border: 1px solid var(--line);
        border-radius: 20px;
        background:
            radial-gradient(circle at 90% 10%, rgba(8, 181, 196, .13), transparent 34%),
            linear-gradient(135deg, var(--surface), var(--surface-2));
        box-shadow: var(--shadow);
    }

    .hero-panel::after {
        position: absolute;
        right: -70px;
        bottom: -120px;
        width: 300px;
        height: 300px;
        border: 1px solid rgba(8, 181, 196, .18);
        border-radius: 50%;
        content: "";
        box-shadow: 0 0 0 28px rgba(8, 181, 196, .035), 0 0 0 58px rgba(8, 181, 196, .025);
        pointer-events: none;
    }

    .hero-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
        color: var(--accent);
        font: 700 11px var(--mono);
        letter-spacing: .12em;
        text-transform: uppercase;
    }

    .hero-panel h2 {
        position: relative;
        z-index: 1;
        margin: 0 0 10px;
        font-size: clamp(22px, 3vw, 30px);
        font-weight: 800;
    }

    .hero-panel p {
        position: relative;
        z-index: 1;
        max-width: 680px;
        margin: 0;
        color: var(--muted);
        font-size: 13px;
        line-height: 1.75;
    }

    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
    }

    .kpi-card {
        min-width: 0;
        padding: 20px;
        border: 1px solid var(--line);
        border-radius: 16px;
        background: var(--surface);
        box-shadow: var(--shadow);
    }

    .kpi-label {
        color: var(--muted);
        font-size: 12px;
    }

    .kpi-value {
        display: block;
        margin: 9px 0 5px;
        font-size: 29px;
        font-weight: 800;
        line-height: 1;
        font-variant-numeric: tabular-nums;
    }

    .kpi-note {
        color: var(--muted);
        font-size: 11px;
    }

    .panel {
        min-width: 0;
        padding: 22px;
        border: 1px solid var(--line);
        border-radius: 16px;
        background: var(--surface);
        box-shadow: var(--shadow);
    }

    .panel-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 18px;
    }

    .panel-heading h2 {
        margin: 0;
        font-size: 15px;
        font-weight: 800;
    }

    .panel-heading a {
        color: var(--accent);
        font-size: 12px;
        text-decoration: none;
    }

    .resource-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .resource-card {
        padding: 17px;
        border: 1px solid var(--line);
        border-radius: 13px;
        background: var(--surface-2);
    }

    .resource-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
    }

    .resource-name {
        overflow-wrap: anywhere;
        font-size: 14px;
        font-weight: 800;
    }

    .resource-meta {
        margin-top: 5px;
        color: var(--muted);
        font: 11px var(--mono);
        overflow-wrap: anywhere;
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 9px;
        border-radius: 999px;
        background: var(--surface);
        color: var(--muted);
        font-size: 10px;
        font-weight: 700;
        white-space: nowrap;
    }

    .status-pill::before {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
        content: "";
    }

    .status-pill.fresh {
        background: var(--ok-soft);
        color: var(--ok);
    }

    .status-pill.stale,
    .status-pill.no-data,
    .status-pill.unavailable,
    .status-pill.unsupported {
        color: #c58b32;
    }

    .resource-details {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin-top: 16px;
    }

    .detail-item span {
        display: block;
        color: var(--muted);
        font-size: 10px;
    }

    .detail-item strong {
        display: block;
        margin-top: 4px;
        font-size: 12px;
        overflow-wrap: anywhere;
    }

    .metric-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .metric-card {
        padding: 18px;
        border: 1px solid var(--line);
        border-radius: 13px;
        background: var(--surface-2);
    }

    .metric-card span {
        color: var(--muted);
        font-size: 12px;
    }

    .metric-card strong {
        display: block;
        margin-top: 8px;
        font-size: 25px;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
    }

    .metric-track {
        height: 6px;
        overflow: hidden;
        margin-top: 12px;
        border-radius: 999px;
        background: var(--line);
    }

    .metric-track span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, var(--brand), var(--cyan));
    }

    .empty-state {
        padding: 22px;
        border: 1px dashed var(--line);
        border-radius: 12px;
        color: var(--muted);
        font-size: 12px;
        text-align: center;
    }

    .application-list {
        display: grid;
        gap: 12px;
    }

    .application-row {
        padding: 16px;
        border: 1px solid var(--line);
        border-radius: 13px;
    }

    .application-row-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    .application-row h3 {
        margin: 0;
        font-size: 13px;
    }

    .application-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 8px;
    }

    .application-tags span {
        padding: 4px 7px;
        border: 1px solid var(--line);
        border-radius: 6px;
        color: var(--muted);
        font-size: 10px;
    }

    .application-status-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        margin-top: 14px;
    }

    .application-status-grid div {
        min-width: 0;
        padding: 10px;
        border-radius: 9px;
        background: var(--surface-2);
    }

    .application-status-grid small {
        display: block;
        color: var(--muted);
        font-size: 10px;
    }

    .application-status-grid strong {
        display: block;
        margin-top: 5px;
        font-size: 11px;
        overflow-wrap: anywhere;
    }

    .dashboard-footer {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        color: var(--muted);
        font: 10px var(--mono);
    }

    @media (max-width: 1000px) {
        .kpi-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 700px) {
        .resource-grid,
        .metric-grid {
            grid-template-columns: 1fr;
        }

        .application-status-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .panel,
        .hero-panel {
            padding: 18px;
        }
    }

    @media (max-width: 420px) {
        .kpi-grid {
            grid-template-columns: 1fr 1fr;
        }

        .kpi-card {
            padding: 14px;
        }

        .kpi-value {
            font-size: 24px;
        }
    }
</style>
@endpush

@section('content')
@php
    $formatMetric = function ($value) {
        return is_numeric($value) ? number_format((float) $value, 1) . '%' : 'N/A';
    };

    $formatTime = function ($value) {
        return $value ? \Illuminate\Support\Carbon::parse($value)->format('d M Y H:i:s') : 'Belum tersedia';
    };

    $bridgeState = function ($bridge, $key) {
        $status = data_get($bridge, $key . '.status');

        if ($status === 'unavailable') {
            return 'Unavailable';
        }

        if ($status === 'unsupported') {
            return 'Unsupported';
        }

        if ($status === 'healthy' || $status === 'ok') {
            return 'Reported OK';
        }

        return 'No data';
    };
@endphp

<div class="nadi-dashboard">
    <header class="dashboard-heading">
        <div>
            <h1>Dashboard Intelligence</h1>
            <p>Ringkasan kondisi infrastruktur, Agent, aplikasi, dan jalur telemetry NADI.</p>
        </div>

        <div class="dashboard-clock">
            DATA SNAPSHOT · {{ now()->format('d M Y H:i:s') }}
        </div>
    </header>

    <section class="hero-panel">
        <div class="hero-eyebrow">NADI CENTRAL / OBSERVABILITY</div>

        <h2>Infrastructure at a glance.</h2>

        <p>
            Dashboard ini menampilkan status registrasi dan aktivasi secara terpisah dari
            penerimaan telemetry. Data yang belum tersedia atau endpoint Bridge yang belum
            mendukung pemeriksaan tidak akan ditandai sebagai kondisi sehat.
        </p>
    </section>

    <section class="kpi-grid">
        <article class="kpi-card">
            <span class="kpi-label">Registered Servers</span>
            <strong class="kpi-value">{{ $serverCount }}</strong>
            <span class="kpi-note">{{ $freshServerCount }} menerima telemetry ≤ 90 detik</span>
        </article>

        <article class="kpi-card">
            <span class="kpi-label">Installed Agents</span>
            <strong class="kpi-value">{{ $agentCount }}</strong>
            <span class="kpi-note">Identitas Agent terpasang pada server</span>
        </article>

        <article class="kpi-card">
            <span class="kpi-label">Applications</span>
            <strong class="kpi-value">{{ $applicationCount }}</strong>
            <span class="kpi-note">{{ $approvedApplicationCount }} approved · {{ $activeApplicationCount }} activation active</span>
        </article>

        <article class="kpi-card">
            <span class="kpi-label">Accepted Telemetry Batches</span>
            <strong class="kpi-value">{{ number_format((int) ($batchStats->accepted_batches ?? 0)) }}</strong>
            <span class="kpi-note">{{ number_format((int) ($batchStats->accepted_records ?? 0)) }} accepted records</span>
        </article>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <h2>Server Intelligence</h2>
            <a href="{{ route('monitoring.servers') }}">Open server monitoring →</a>
        </div>

        <div class="resource-grid">
            @forelse ($servers as $server)
                <article class="resource-card">
                    <div class="resource-top">
                        <div>
                            <div class="resource-name">{{ $server->hostname }}</div>
                            <div class="resource-meta">
                                {{ $server->os_name ?: 'OS unknown' }}
                                {{ $server->os_version ?: '' }}
                                · {{ $server->architecture ?: 'Architecture unknown' }}
                            </div>
                        </div>

                        <span class="status-pill {{ $server->telemetry_state }}">
                            @if ($server->telemetry_state === 'fresh')
                                Telemetry Fresh
                            @elseif ($server->telemetry_state === 'stale')
                                Telemetry Stale
                            @else
                                No Telemetry
                            @endif
                        </span>
                    </div>

                    <div class="resource-details">
                        <div class="detail-item">
                            <span>Server registry</span>
                            <strong>{{ ucfirst($server->server_status) }}</strong>
                        </div>

                        <div class="detail-item">
                            <span>Agent installation</span>
                            <strong>{{ $server->installation_state ?: 'No Agent' }}</strong>
                        </div>

                        <div class="detail-item">
                            <span>Agent version</span>
                            <strong>{{ $server->agent_version ?: 'Unknown' }}</strong>
                        </div>

                        <div class="detail-item">
                            <span>Last telemetry received</span>
                            <strong>{{ $formatTime($server->telemetry_received_at) }}</strong>
                        </div>

                        <div class="detail-item">
                            <span>Last control contact</span>
                            <strong>{{ $formatTime($server->last_control_at) }}</strong>
                        </div>

                        <div class="detail-item">
                            <span>IP address</span>
                            <strong>{{ $server->ip_address ?: 'Not recorded' }}</strong>
                        </div>
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada server terdaftar.</div>
            @endforelse
        </div>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <h2>Latest Server Metrics</h2>
            <span class="status-pill {{ $latestServerObservedAt ? 'fresh' : 'no-data' }}">
                {{ $latestServerObservedAt ? 'Telemetry available' : 'No metric data' }}
            </span>
        </div>

        <div class="metric-grid">
            <div class="metric-card">
                <span>CPU Usage</span>
                <strong>{{ $formatMetric($cpuUsage) }}</strong>
                <div class="metric-track">
                    <span style="width: {{ is_numeric($cpuUsage) ? min(100, max(0, $cpuUsage)) : 0 }}%;"></span>
                </div>
            </div>

            <div class="metric-card">
                <span>Memory Usage</span>
                <strong>{{ $formatMetric($memoryUsage) }}</strong>
                <div class="metric-track">
                    <span style="width: {{ is_numeric($memoryUsage) ? min(100, max(0, $memoryUsage)) : 0 }}%;"></span>
                </div>
            </div>
        </div>

        <div class="dashboard-footer" style="margin-top: 14px;">
            <span>Observed at: {{ $formatTime($latestServerObservedAt) }}</span>
            <span>Metrics are shown only when present in telemetry payload.</span>
        </div>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <h2>Application Intelligence</h2>
            <a href="{{ route('monitoring.applications') }}">Open application monitoring →</a>
        </div>

        <div class="application-list">
            @forelse ($applications as $application)
                <article class="application-row">
                    <div class="application-row-header">
                        <div>
                            <h3>{{ $application->application_name }}</h3>

                            <div class="resource-meta">
                                {{ $application->application_key }} · {{ $application->hostname }}
                            </div>

                            <div class="application-tags">
                                <span>{{ strtoupper($application->environment) }}</span>
                                <span>{{ ucfirst($application->framework) }}</span>
                                <span>Framework {{ $application->framework_version ?: 'unknown' }}</span>
                                <span>PHP {{ $application->php_version ?: 'unknown' }}</span>
                            </div>
                        </div>

                        <span class="status-pill {{ $application->telemetry_state }}">
                            @if ($application->telemetry_state === 'fresh')
                                Telemetry Fresh
                            @elseif ($application->telemetry_state === 'stale')
                                Telemetry Stale
                            @else
                                No Telemetry
                            @endif
                        </span>
                    </div>

                    <div class="application-status-grid">
                        <div>
                            <small>Registration</small>
                            <strong>{{ ucfirst($application->registration_status) }}</strong>
                        </div>

                        <div>
                            <small>Activation</small>
                            <strong>{{ ucfirst($application->activation_status) }}</strong>
                        </div>

                        <div>
                            <small>Monitoring</small>
                            <strong>{{ ucfirst($application->monitoring_status) }}</strong>
                        </div>

                        <div>
                            <small>Telemetry received</small>
                            <strong>{{ $formatTime($application->telemetry_received_at) }}</strong>
                        </div>
                    </div>

                    <div class="application-status-grid">
                        <div>
                            <small>Bridge Health</small>
                            <strong>{{ $bridgeState($application->bridge, 'health') }}</strong>
                        </div>

                        <div>
                            <small>Database Probe</small>
                            <strong>{{ $bridgeState($application->bridge, 'database') }}</strong>
                        </div>

                        <div>
                            <small>Queue Probe</small>
                            <strong>{{ $bridgeState($application->bridge, 'queue') }}</strong>
                        </div>

                        <div>
                            <small>Scheduler Probe</small>
                            <strong>{{ $bridgeState($application->bridge, 'scheduler') }}</strong>
                        </div>
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada aplikasi terdaftar.</div>
            @endforelse
        </div>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <h2>Telemetry Pipeline</h2>
            <span class="status-pill fresh">Ingestion counters</span>
        </div>

        <div class="metric-grid">
            <div class="metric-card">
                <span>Accepted Records</span>
                <strong>{{ number_format((int) ($batchStats->accepted_records ?? 0)) }}</strong>
            </div>

            <div class="metric-card">
                <span>Duplicate Records</span>
                <strong>{{ number_format((int) ($batchStats->duplicate_records ?? 0)) }}</strong>
            </div>

            <div class="metric-card">
                <span>Rejected Records</span>
                <strong>{{ number_format((int) ($batchStats->rejected_records ?? 0)) }}</strong>
            </div>

            <div class="metric-card">
                <span>HTTP Request Events</span>
                <strong>{{ number_format($httpEventCount) }}</strong>
            </div>
        </div>

        <p class="dashboard-footer" style="margin-top: 14px;">
            HTTP event count menunjukkan jumlah event yang tersimpan, bukan estimasi request.
            Jika nol, dashboard tidak menyimpulkan bahwa trafik aplikasi juga nol.
        </p>
    </section>

    <footer class="dashboard-footer">
        <span>NADI CENTRAL · OBSERVABILITY ONLY</span>
        <span>Snapshot generated {{ now()->format('Y-m-d H:i:s') }}</span>
    </footer>
</div>
@endsection
