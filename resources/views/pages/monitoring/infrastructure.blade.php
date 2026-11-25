@extends('layouts.app')

@section('title', 'Infrastructure Monitoring - NADI Central')

@section('breadcrumb', 'Monitoring / Infrastructure')

@push('styles')
<style>
    .infra-page
    {
        display: flex;
        flex-direction: column;
        gap: 22px;
    }

    .infra-page .page-header
    {
        margin-bottom: 0;
    }

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
        background: var(--ok);
        box-shadow: 0 0 0 4px var(--ok-soft);
    }

    .infra-status[data-state="down"] .infra-status-dot
    {
        background: var(--danger);
    }

    .infra-summary
    {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
    }

    .infra-stat
    {
        min-width: 0;
        padding: 18px;
        border: 1px solid var(--line);
        border-radius: 14px;
        background: var(--surface);
        box-shadow: var(--shadow);
    }

    .infra-stat-label
    {
        color: var(--muted);
        font: 600 10px var(--mono);
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .infra-stat-value
    {
        margin-top: 10px;
        color: var(--ink);
        font-size: 28px;
        font-weight: 800;
        letter-spacing: -.03em;
    }

    .infra-stat-meta
    {
        margin-top: 5px;
        color: var(--muted);
        font: 500 10px var(--mono);
    }

    .infra-grid
    {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }

    .infra-panel
    {
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
        margin-bottom: 16px;
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

    .infra-chart
    {
        display: block;
        width: 100%;
        height: 190px;
    }

    .infra-empty
    {
        display: grid;
        min-height: 190px;
        place-items: center;
        color: var(--muted);
        font: 500 11px var(--mono);
        text-align: center;
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
        color: var(--ok);
    }

    .infra-target-state.down
    {
        color: var(--danger);
    }

    .infra-note
    {
        padding: 12px 14px;
        border-left: 3px solid var(--accent);
        border-radius: 8px;
        background: var(--accent-soft);
        color: var(--muted);
        font-size: 11px;
        line-height: 1.6;
    }

    @media (max-width: 1000px)
    {
        .infra-summary
        {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .infra-grid
        {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 600px)
    {
        .infra-summary
        {
            grid-template-columns: 1fr 1fr;
            gap: 9px;
        }

        .infra-stat
        {
            padding: 14px;
        }

        .infra-stat-value
        {
            font-size: 22px;
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

        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <div class="infra-status" id="infra-connection" data-state="unknown">
                <span class="infra-status-dot"></span>
                <span id="infra-connection-text">Checking Prometheus</span>
            </div>
            <button type="button" class="infra-refresh" id="infra-refresh">
                Refresh data
            </button>
        </div>
    </section>

    <section class="infra-summary">
        <article class="infra-stat">
            <div class="infra-stat-label">CPU Usage</div>
            <div class="infra-stat-value" id="infra-cpu-value">—</div>
            <div class="infra-stat-meta" id="infra-cpu-meta">Waiting for samples</div>
        </article>

        <article class="infra-stat">
            <div class="infra-stat-label">Memory Usage</div>
            <div class="infra-stat-value" id="infra-memory-value">—</div>
            <div class="infra-stat-meta" id="infra-memory-meta">Waiting for samples</div>
        </article>

        <article class="infra-stat">
            <div class="infra-stat-label">Disk Usage</div>
            <div class="infra-stat-value" id="infra-disk-value">—</div>
            <div class="infra-stat-meta" id="infra-disk-meta">Latest filesystem sample</div>
        </article>

        <article class="infra-stat">
            <div class="infra-stat-label">Network Receive</div>
            <div class="infra-stat-value" id="infra-network-value">—</div>
            <div class="infra-stat-meta">Current rate</div>
        </article>
    </section>

    <section class="infra-grid">
        <article class="infra-panel">
            <div class="infra-panel-header">
                <div>
                    <div class="infra-panel-title">CPU Utilization</div>
                    <div class="infra-panel-description">Percent · historical samples</div>
                </div>
            </div>
            <canvas class="infra-chart" id="chart-cpu" aria-label="CPU utilization chart"></canvas>
            <div class="infra-empty" hidden>No CPU samples in this range.</div>
        </article>

        <article class="infra-panel">
            <div class="infra-panel-header">
                <div>
                    <div class="infra-panel-title">Memory Utilization</div>
                    <div class="infra-panel-description">Percent · historical samples</div>
                </div>
            </div>
            <canvas class="infra-chart" id="chart-memory" aria-label="Memory utilization chart"></canvas>
            <div class="infra-empty" hidden>No memory samples in this range.</div>
        </article>

        <article class="infra-panel">
            <div class="infra-panel-header">
                <div>
                    <div class="infra-panel-title">Disk Utilization</div>
                    <div class="infra-panel-description">Percent · per mount point</div>
                </div>
            </div>
            <canvas class="infra-chart" id="chart-disk" aria-label="Disk utilization chart"></canvas>
            <div class="infra-empty" hidden>No disk samples in this range.</div>
        </article>

        <article class="infra-panel">
            <div class="infra-panel-header">
                <div>
                    <div class="infra-panel-title">Network Throughput</div>
                    <div class="infra-panel-description">Bytes per second · receive and transmit</div>
                </div>
            </div>
            <canvas class="infra-chart" id="chart-network" aria-label="Network throughput chart"></canvas>
            <div class="infra-empty" hidden>No network samples in this range.</div>
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
                <canvas class="infra-chart" id="chart-probe" aria-label="Application probe status chart"></canvas>
                <div class="infra-empty" hidden>No application probe samples in this range.</div>
            </div>
            <div>
                <canvas class="infra-chart" id="chart-latency" aria-label="Application probe latency chart"></canvas>
                <div class="infra-empty" hidden>No application latency samples in this range.</div>
            </div>
        </div>

        <div class="infra-note">
            Probe value 1 berarti status yang dilaporkan termasuk available/healthy. Nilai 0 berarti probe tidak melaporkan status sehat; ini tidak otomatis membuktikan aplikasi sedang down. Probe yang belum didukung atau belum tersedia perlu diperiksa pada detail telemetry Central.
        </div>
    </section>

    <section class="infra-panel">
        <div class="infra-panel-header">
            <div>
                <div class="infra-panel-title">Prometheus Scrape Targets</div>
                <div class="infra-panel-description">Status koneksi exporter dan target scrape aktif</div>
            </div>
        </div>
        <div class="infra-targets" id="infra-targets">
            <div class="infra-empty">Loading scrape targets...</div>
        </div>
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
        targets: document.getElementById('infra-targets')
    };

    const rangeSettings = {
        '1h': { seconds: 3600, step: 30 },
        '6h': { seconds: 21600, step: 60 },
        '24h': { seconds: 86400, step: 300 },
        '7d': { seconds: 604800, step: 3600 }
    };

    let activeRequest = false;

    function setConnection(state, message)
    {
        elements.connection.dataset.state = state;
        elements.connectionText.textContent = message;
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

            return amount.toFixed(1) + ' ' + units[index];
        }

        return value.toFixed(1);
    }

    function updateStat(id, value, unit, meta)
    {
        document.getElementById(id + '-value').textContent = formatValue(value, unit);
        document.getElementById(id + '-meta').textContent = meta || 'Latest sample';
    }

    function queryUrl(metric, start, end, step)
    {
        const url = new URL(root.dataset.queryUrl + '/' + metric, window.location.origin);

        if (elements.server.value)
        {
            url.searchParams.set('server_id', elements.server.value);
        }

        if (elements.application.value && metric.startsWith('application_'))
        {
            url.searchParams.set('application_id', elements.application.value);
        }

        url.searchParams.set('start', start);
        url.searchParams.set('end', end);
        url.searchParams.set('step', String(step));

        return url.toString();
    }

    async function fetchMetric(metric, start, end, step)
    {
        const response = await fetch(
            queryUrl(metric, start, end, step),
            {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            }
        );

        const payload = await response.json();

        if (!response.ok || payload.status !== 'ok')
        {
            throw new Error(payload.message || 'Metric query failed.');
        }

        return payload.result || [];
    }

    function seriesFromResult(result)
    {
        return result.map(function (item)
        {
            return {
                label: Object.entries(item.metric || {})
                    .filter(function ([key])
                    {
                        return !['__name__', 'agent_id', 'server_id', 'application_id'].includes(key);
                    })
                    .map(function ([key, value])
                    {
                        return key + ': ' + value;
                    })
                    .join(' · ') || 'Value',
                values: (item.values || []).map(function (point)
                {
                    return {
                        time: Number(point[0]),
                        value: Number(point[1])
                    };
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

    function drawChart(canvasId, series, unit)
    {
        const canvas = document.getElementById(canvasId);
        const context = canvas.getContext('2d');
        const rect = canvas.getBoundingClientRect();
        const ratio = window.devicePixelRatio || 1;
        const width = Math.max(320, rect.width);
        const height = 190;

        canvas.width = width * ratio;
        canvas.height = height * ratio;
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        context.clearRect(0, 0, width, height);

        const panel = canvas.closest('.infra-panel');
        const empty = panel.querySelector('.infra-empty');

        if (!series.length)
        {
            canvas.hidden = true;
            empty.hidden = false;
            return null;
        }

        canvas.hidden = false;
        empty.hidden = true;

        const allPoints = series.flatMap(function (item)
        {
            return item.values;
        });

        const maximum = Math.max(1, ...allPoints.map(point => point.value));
        const minimum = Math.min(0, ...allPoints.map(point => point.value));
        const range = Math.max(1, maximum - minimum);
        const left = 42;
        const right = width - 12;
        const top = 12;
        const bottom = height - 26;
        const colors = ['#2f6fed', '#08a6b5', '#12a150', '#d97706', '#8b5cf6', '#dc4c64'];

        context.font = '10px sans-serif';
        context.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--muted').trim() || '#64748b';
        context.strokeStyle = getComputedStyle(document.documentElement).getPropertyValue('--line').trim() || '#dce3f1';
        context.lineWidth = 1;

        for (let index = 0; index <= 4; index++)
        {
            const y = top + ((bottom - top) / 4) * index;

            context.beginPath();
            context.moveTo(left, y);
            context.lineTo(right, y);
            context.stroke();

            const value = maximum - (range / 4) * index;
            context.fillText(value.toFixed(unit === 'percent' ? 0 : 1), 2, y + 3);
        }

        series.forEach(function (item, seriesIndex)
        {
            const points = item.values;
            const color = colors[seriesIndex % colors.length];

            context.beginPath();

            points.forEach(function (point, index)
            {
                const x = points.length === 1
                    ? right
                    : left + ((right - left) * index / (points.length - 1));
                const y = bottom - ((point.value - minimum) / range) * (bottom - top);

                if (index === 0)
                {
                    context.moveTo(x, y);
                }
                else
                {
                    context.lineTo(x, y);
                }
            });

            context.strokeStyle = color;
            context.lineWidth = 2;
            context.stroke();
        });

        context.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--muted').trim() || '#64748b';
        context.fillText('Earlier', left, height - 7);
        context.textAlign = 'right';
        context.fillText('Latest', right, height - 7);
        context.textAlign = 'left';

        return series;
    }

    function latestValue(series)
    {
        const points = series.flatMap(item => item.values);

        if (!points.length)
        {
            return null;
        }

        points.sort((a, b) => a.time - b.time);

        return points[points.length - 1].value;
    }

    function renderTargets(targets)
    {
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
                ? 'Last scrape: ' + new Date(target.last_scrape).toLocaleString()
                : 'No scrape timestamp';

            const state = document.createElement('div');
            state.className = 'infra-target-state ' + (target.health === 'up' ? 'up' : 'down');
            state.textContent = target.health;

            details.append(name, meta);
            item.append(details, state);
            elements.targets.append(item);
        });
    }

    async function refresh()
    {
        if (activeRequest)
        {
            return;
        }

        activeRequest = true;
        elements.refresh.disabled = true;
        elements.refresh.textContent = 'Loading...';

        try
        {
            const overviewResponse = await fetch(root.dataset.overviewUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });

            const overview = await overviewResponse.json();

            if (!overviewResponse.ok || overview.status !== 'ok')
            {
                throw new Error(overview.message || 'Prometheus is unavailable.');
            }

            setConnection(
                overview.prometheus === 'reachable' && overview.exporter === 'up' ? 'up' : 'down',
                'Prometheus ' + overview.prometheus + ' · Exporter ' + overview.exporter
            );

            renderTargets(overview.targets || []);

            const range = rangeSettings[elements.range.value] || rangeSettings['24h'];
            const endDate = new Date();
            const startDate = new Date(endDate.getTime() - range.seconds * 1000);
            const start = startDate.toISOString();
            const end = endDate.toISOString();

            const serverMetrics = [
                ['server_cpu_usage', 'chart-cpu', 'percent'],
                ['server_memory_usage', 'chart-memory', 'percent'],
                ['server_disk_usage', 'chart-disk', 'percent'],
                ['server_network_receive', 'chart-network', 'bytes_per_second'],
                ['server_network_transmit', 'chart-network', 'bytes_per_second']
            ];

            const appMetrics = [
                ['application_probe_up', 'chart-probe', 'boolean'],
                ['application_probe_latency', 'chart-latency', 'milliseconds']
            ];

            const requests = [...serverMetrics, ...appMetrics].map(async function (item)
            {
                const result = await fetchMetric(item[0], start, end, range.step);
                return {
                    metric: item[0],
                    canvas: item[1],
                    unit: item[2],
                    series: seriesFromResult(result)
                };
            });

            const results = await Promise.all(requests);
            const networkSeries = [];

            results.forEach(function (item)
            {
                if (item.canvas === 'chart-network')
                {
                    networkSeries.push(...item.series);
                }
                else
                {
                    drawChart(item.canvas, item.series, item.unit);
                }
            });

            drawChart('chart-network', networkSeries, 'bytes_per_second');

            const cpu = results.find(item => item.metric === 'server_cpu_usage');
            const memory = results.find(item => item.metric === 'server_memory_usage');
            const disk = results.find(item => item.metric === 'server_disk_usage');
            const network = results.find(item => item.metric === 'server_network_receive');

            updateStat('infra-cpu', latestValue(cpu.series), 'percent', 'Latest Prometheus sample');
            updateStat('infra-memory', latestValue(memory.series), 'percent', 'Latest Prometheus sample');
            updateStat('infra-disk', latestValue(disk.series), 'percent', 'Latest filesystem sample');
            updateStat('infra-network', latestValue(network.series), 'bytes_per_second', 'Receive rate · 5-minute window');
        }
        catch (error)
        {
            setConnection('down', 'Monitoring data unavailable');
            elements.targets.textContent = error.message;
        }
        finally
        {
            activeRequest = false;
            elements.refresh.disabled = false;
            elements.refresh.textContent = 'Refresh data';
        }
    }

    elements.refresh.addEventListener('click', refresh);
    elements.server.addEventListener('change', refresh);
    elements.application.addEventListener('change', refresh);
    elements.range.addEventListener('change', refresh);

    refresh();
});
</script>
@endpush
