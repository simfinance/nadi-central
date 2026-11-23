@extends('layouts.app')

@section('title', 'Telemetry - NADI Console')

@section('breadcrumb', 'Management / Telemetry')

@section('content')

<div class="tele-page" id="teleRoot">

    <div class="page-header tele-header">
        <div>
            <h1 class="page-title">
                Telemetry Overview
                <span class="tele-state" id="teleState" data-state="live">
                    <span class="tele-state-dot"></span>
                    <span id="teleStateText">Live</span>
                </span>
            </h1>
            <p class="page-description">Health of every registered server and application. Select one to inspect its detailed telemetry.</p>
        </div>

        <div class="tele-controls">
            <div class="tele-seg" id="teleRange" role="group" aria-label="Time range">
                @foreach ($ranges as $key => $label)
                    <button type="button" data-range="{{ $key }}" title="{{ $label }}">{{ strtoupper($key) }}</button>
                @endforeach
            </div>

            <button type="button" class="tele-toggle is-on" id="teleAuto" aria-pressed="true" title="Auto refresh every 30 seconds">
                <span class="tele-toggle-dot"></span> Auto
            </button>

            <button type="button" class="token-filter-button tele-refresh" id="teleRefresh">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 11a8 8 0 1 0-2.3 5.7"/>
                    <path d="M20 4v7h-7"/>
                </svg>
                Refresh
            </button>
        </div>
    </div>

    <div class="token-list-error" id="teleError" role="alert" hidden></div>

    <div class="token-summary tele-summary" id="teleSummary"></div>

    <div class="token-panel tele-analysis" id="teleAlertsPanel">
        <div class="token-panel-header">
            <h2>
                Attention Required
                <span class="token-panel-live">Fleet</span>
            </h2>
            <p>Critical and warning findings across all servers and applications.</p>
        </div>
        <div class="tele-insights" id="teleAlerts"></div>
    </div>

    <div class="tele-toolbar">
        <div class="token-search tele-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="11" cy="11" r="7"/>
                <path d="M20 20l-3.5-3.5"/>
            </svg>
            <input type="text" id="teleSearch" placeholder="Search server, application, environment, or IP..." autocomplete="off">
        </div>

        <div class="tele-seg" id="teleFilter" role="group" aria-label="Status filter">
            <button type="button" data-filter="all" class="is-active">All</button>
            <button type="button" data-filter="issues">Issues</button>
            <button type="button" data-filter="offline">Offline</button>
        </div>
    </div>

    <div class="token-panel">
        <div class="token-panel-header">
            <h2>
                Servers
                <span class="token-panel-live" id="teleServerCount">0</span>
            </h2>
            <p>Resource usage per registered agent. Click a card to open its detail.</p>
        </div>
        <div class="tele-fleet" id="teleServers"></div>
    </div>

    <div class="token-panel">
        <div class="token-panel-header">
            <h2>
                Applications
                <span class="token-panel-live" id="teleAppCount">0</span>
            </h2>
            <p>Probe health of each registered application. Click a row to open its server.</p>
        </div>
        <div class="token-table-wrapper">
            <table class="token-table" id="teleApps"></table>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function ()
{
    const DATA_URL = @json(route('management.telemetry.overview'));
    const SHOW_URL = @json(route('management.telemetry.show', ['agent' => '00000000-0000-0000-0000-000000000000']));
    const REFRESH_MS = 30000;

    let data = @json($payload);

    const state = { range: data.range.key, filter: 'all', search: '', auto: true, timer: null, controller: null };

    const $ = function (id) { return document.getElementById(id); };
    const showUrl = function (id) { return SHOW_URL.replace('00000000-0000-0000-0000-000000000000', encodeURIComponent(id)); };

    /* ---------- Helper ---------- */

    function esc(v)
    {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function num(v, d)
    {
        if (v === null || v === undefined || isNaN(v)) { return '—'; }
        return Number(v).toLocaleString('en-US', { minimumFractionDigits: d || 0, maximumFractionDigits: d || 0 });
    }

    function bytes(v, d)
    {
        if (v === null || v === undefined || isNaN(v)) { return '—'; }
        const u = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        let i = 0, n = Math.abs(Number(v));
        while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
        return n.toFixed(d === undefined ? (n >= 100 || i === 0 ? 0 : 1) : d) + ' ' + u[i];
    }

    function dur(sec)
    {
        if (sec === null || sec === undefined) { return '—'; }
        sec = Math.max(0, Math.floor(sec));
        const d = Math.floor(sec / 86400), h = Math.floor(sec % 86400 / 3600), m = Math.floor(sec % 3600 / 60), s = sec % 60;
        if (d) { return d + 'd ' + h + 'h'; }
        if (h) { return h + 'h ' + m + 'm'; }
        if (m) { return m + 'm ' + s + 's'; }
        return s + 's';
    }

    function ago(iso)
    {
        if (!iso) { return 'never'; }
        const s = (Date.now() - Date.parse(iso)) / 1000;
        return s < 5 ? 'just now' : dur(s) + ' ago';
    }

    function level(v, warn, crit)
    {
        if (v === null || v === undefined) { return 'idle'; }
        return v >= crit ? 'crit' : v >= warn ? 'warn' : 'ok';
    }

    function icon(name)
    {
        const p = {
            server: '<rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><path d="M7 7.5h.01M7 16.5h.01"/>',
            app: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M7 6.5h.01M10 6.5h.01"/>',
            cpu: '<rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v3M15 2v3M9 19v3M15 19v3M2 9h3M2 15h3M19 9h3M19 15h3"/>',
            alert: '<path d="M12 3l10 18H2L12 3z"/><path d="M12 10v5M12 18h.01"/>',
            ingest: '<path d="M12 4v11"/><path d="M7.5 11l4.5 4.5 4.5-4.5"/><path d="M5 19h14"/>'
        };
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + (p[name] || '') + '</svg>';
    }

    function sparkline(values, color)
    {
        const v = (values || []).filter(function (x) { return x !== null; });
        if (v.length < 2) { return '<div class="tele-spark tele-spark-empty">no data in window</div>'; }
        const W = 100, H = 30, min = Math.min.apply(null, v), max = Math.max.apply(null, v), span = (max - min) || 1;
        const pts = [];
        values.forEach(function (y, i) {
            if (y === null) { return; }
            pts.push((i / (values.length - 1) * W).toFixed(1) + ',' + (H - 3 - (y - min) / span * (H - 6)).toFixed(1));
        });
        return '<svg class="tele-spark" viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="none" aria-hidden="true">' +
            '<polygon points="0,' + H + ' ' + pts.join(' ') + ' ' + W + ',' + H + '" style="fill:' + color + '" opacity="0.12"/>' +
            '<polyline points="' + pts.join(' ') + '" fill="none" style="stroke:' + color + '" stroke-width="1.6" vector-effect="non-scaling-stroke" stroke-linejoin="round"/></svg>';
    }

    const stateLabel = { live: 'Live', stale: 'Delayed', offline: 'Offline', pending: 'No data' };
    const stateLv = { live: 'ok', stale: 'warn', offline: 'crit', pending: 'idle' };

    function pill(lv, text) { return '<span class="tele-pill lv-' + lv + '">' + esc(text) + '</span>'; }

    function matches(text)
    {
        return !state.search || text.toLowerCase().indexOf(state.search) >= 0;
    }

    function passes(item)
    {
        if (state.filter === 'issues') { return item.level !== 'ok'; }
        if (state.filter === 'offline') { return item.state === 'offline'; }
        return true;
    }

    /* ---------- Render ---------- */

    function card(o)
    {
        return '<div class="token-summary-card tele-card" data-lv="' + o.lv + '">' +
            '<div class="token-summary-top"><div class="token-summary-icon">' + icon(o.icon) + '</div><div class="token-summary-label">' + esc(o.label) + '</div></div>' +
            '<div class="token-summary-value">' + o.value + (o.chip ? '<span class="token-summary-percent">' + esc(o.chip) + '</span>' : '') + '</div>' +
            '<div class="token-summary-description">' + esc(o.desc) + '</div>' +
            '<div class="token-summary-meter"><div class="token-summary-meter-fill" style="width:' + Math.min(100, Math.max(0, o.meter || 0)) + '%"></div></div></div>';
    }

    function renderSummary()
    {
        const t = data.totals;

        $('teleSummary').innerHTML =
            card({ icon: 'server', label: 'Servers', lv: t.servers_offline ? 'crit' : (t.servers_stale ? 'warn' : 'ok'),
                value: num(t.servers_live) + '<small>/ ' + num(t.servers) + '</small>', chip: t.servers_offline ? t.servers_offline + ' offline' : 'all live',
                desc: t.servers_stale ? t.servers_stale + ' delayed' : 'reporting normally', meter: t.servers ? t.servers_live / t.servers * 100 : 0 }) +
            card({ icon: 'app', label: 'Applications', lv: t.apps_down ? 'crit' : (t.apps_warn ? 'warn' : 'ok'),
                value: num(t.apps_ok) + '<small>/ ' + num(t.apps) + '</small>', chip: (t.apps_warn + t.apps_down) ? (t.apps_warn + t.apps_down) + ' degraded' : 'healthy',
                desc: t.apps_pending ? t.apps_pending + ' awaiting telemetry' : 'all probes reporting', meter: t.apps ? t.apps_ok / t.apps * 100 : 0 }) +
            card({ icon: 'cpu', label: 'Fleet CPU', lv: level(t.cpu_avg, 70, 90), value: t.cpu_avg === null ? '—' : num(t.cpu_avg, 1) + '<small>%</small>',
                chip: 'mem ' + (t.mem_avg === null ? '—' : num(t.mem_avg, 0) + '%'), desc: 'average of live servers', meter: t.cpu_avg }) +
            card({ icon: 'alert', label: 'Findings', lv: t.crit ? 'crit' : (t.warn ? 'warn' : 'ok'), value: num(t.crit + t.warn),
                chip: t.crit + ' crit · ' + t.warn + ' warn', desc: (t.crit + t.warn) ? 'see Attention Required' : 'no active findings', meter: Math.min(100, (t.crit * 2 + t.warn) * 10) }) +
            card({ icon: 'ingest', label: 'Ingest', lv: t.rejected ? 'crit' : 'ok', value: num(t.records),
                chip: t.rejected ? num(t.rejected) + ' rejected' : '0 rejected', desc: 'records in selected window', meter: t.records ? (1 - t.rejected / t.records) * 100 : 0 });

        const st = t.servers_offline ? 'offline' : (t.servers_stale || t.apps_down ? 'stale' : 'live');
        $('teleState').dataset.state = st;
        $('teleStateText').textContent = st === 'live' ? 'All Live' : st === 'stale' ? 'Degraded' : 'Incident';
    }

    function renderAlerts()
    {
        const list = data.alerts;
        $('teleAlerts').innerHTML = list.length ? list.map(function (a) {
            return '<a class="tele-insight lv-' + a.level + ' tele-link" href="' + showUrl(a.server_id) + '">' +
                pill(a.level, a.level === 'crit' ? 'Critical' : 'Warning') +
                '<div><div class="tele-insight-title">' + esc(a.title) + ' <span class="tele-where">' + esc(a.server) + '</span></div>' +
                '<div class="tele-insight-detail">' + esc(a.detail) + '</div></div></a>';
        }).join('') : '<div class="tele-insight lv-ok tele-allclear">' + pill('ok', 'Healthy') + '<div><div class="tele-insight-title">All systems nominal</div>' +
            '<div class="tele-insight-detail">No critical or warning findings across ' + data.totals.servers + ' server(s) and ' + data.totals.apps + ' application(s).</div></div></div>';
    }

    function meterRow(label, v, warn, crit, extra)
    {
        const lv = level(v, warn, crit);
        return '<div class="tele-mrow"><span>' + label + '</span><div class="tele-bar"><div class="tele-bar-fill lv-' + lv + '" style="width:' + Math.min(100, v || 0) + '%"></div></div>' +
            '<b>' + (v === null || v === undefined ? '—' : num(v, 1) + '%') + '</b><em>' + esc(extra || '') + '</em></div>';
    }

    function renderServers()
    {
        const list = data.servers.filter(function (s) {
            return passes(s) && matches([s.hostname, s.os, s.ip, s.arch].concat(s.apps).join(' '));
        });

        $('teleServerCount').textContent = list.length + (list.length !== data.servers.length ? ' / ' + data.servers.length : '');

        if (!data.servers.length) {
            $('teleServers').innerHTML = '<div class="tele-chart-empty">No server registered yet. Install an agent with an enrollment token first.</div>';
            return;
        }

        if (!list.length) {
            $('teleServers').innerHTML = '<div class="tele-chart-empty">No server matches the current filter.</div>';
            return;
        }

        $('teleServers').innerHTML = list.map(function (s) {
            const issues = (s.issues.crit ? pill('crit', s.issues.crit + ' critical') : '') + (s.issues.warn ? pill('warn', s.issues.warn + ' warning') : '') +
                (!s.issues.crit && !s.issues.warn && s.state === 'live' ? pill('ok', 'no issues') : '');

            return '<a class="tele-server lv-' + s.level + '" href="' + showUrl(s.id) + '">' +
                '<div class="tele-server-head"><div class="token-main"><div class="token-main-icon">' + icon('server') + '</div><div>' +
                '<div class="token-primary">' + esc(s.hostname) + '</div><div class="token-secondary">' + esc(s.os || '—') + (s.arch ? ' · ' + esc(s.arch) : '') + (s.ip ? ' · ' + esc(s.ip) : '') + '</div></div></div>' +
                pill(stateLv[s.state], stateLabel[s.state]) + '</div>' +
                '<div class="tele-meters">' +
                meterRow('CPU', s.cpu, 70, 90, s.cores ? s.cores + ' cores' : '') +
                meterRow('MEM', s.mem, 80, 90, s.mem_total ? bytes(s.mem_used, 1) + ' / ' + bytes(s.mem_total, 1) : '') +
                meterRow('DISK', s.disk, 75, 90, s.disk_free !== null ? bytes(s.disk_free, 1) + ' free' + (s.disk_eta ? ' · ~' + num(s.disk_eta, 0) + 'd' : '') : '') +
                '</div>' +
                (s.spark ? '<div class="tele-spark-wrap">' + sparkline(s.spark.cpu, 'var(--tc1)') + '<span>CPU · ' + esc(data.range.key) + '</span></div>' : '') +
                '<div class="tele-server-meta"><span>load <b>' + (s.load === null ? '—' : num(s.load, 2)) + '</b></span><span>uptime <b>' + dur(s.uptime) + '</b></span><span>seen <b>' + esc(ago(s.last_seen)) + '</b></span></div>' +
                '<div class="tele-server-foot"><div class="tele-chips">' + (s.apps.length ? s.apps.map(function (a) { return '<span class="token-environment">' + esc(a) + '</span>'; }).join('') : '<span class="token-secondary">no applications</span>') + '</div>' +
                '<div class="tele-chips">' + issues + '</div></div>' +
                (s.top_issue ? '<div class="tele-topissue">' + esc(s.top_issue) + '</div>' : '') +
                '</a>';
        }).join('');
    }

    function probeDots(probes)
    {
        const names = Object.keys(probes);
        if (!names.length) { return '<span class="token-secondary">—</span>'; }
        const cls = function (st) { return ['available', 'ok', 'healthy', 'up'].indexOf(st) >= 0 ? 'ok' : st === 'unsupported' ? 'info' : st === 'unknown' ? 'idle' : 'warn'; };
        return '<div class="tele-dots">' + names.map(function (n) {
            return '<span class="tele-pdot lv-' + cls(probes[n]) + '" title="' + esc(n + ': ' + probes[n]) + '"></span>';
        }).join('') + '</div>';
    }

    function renderApps()
    {
        const list = data.apps.filter(function (a) {
            return passes(a) && matches([a.name, a.key, a.environment, a.server, a.framework].join(' '));
        });

        $('teleAppCount').textContent = list.length + (list.length !== data.apps.length ? ' / ' + data.apps.length : '');

        const head = '<thead><tr><th>Application</th><th>Environment</th><th>Server</th><th>Monitoring</th><th>Probes</th><th>Availability</th><th>Health Latency</th><th>Last Probe</th><th>State</th></tr></thead>';

        if (!list.length) {
            $('teleApps').innerHTML = head + '<tbody><tr><td colspan="9"><div class="tele-chart-empty">' +
                (data.apps.length ? 'No application matches the current filter.' : 'No application registered yet.') + '</div></td></tr></tbody>';
            return;
        }

        $('teleApps').innerHTML = head + '<tbody>' + list.map(function (a) {
            return '<tr class="tele-app-row" data-href="' + showUrl(a.server_id) + '">' +
                '<td><div class="token-main"><div class="token-main-icon">' + icon('app') + '</div><div><div class="token-primary">' + esc(a.name) + '</div>' +
                '<div class="token-secondary">' + esc(a.key) + ' · ' + esc(a.framework || '') + '</div></div></div></td>' +
                '<td><span class="token-environment">' + esc(a.environment || '—') + '</span></td>' +
                '<td><span class="token-date">' + esc(a.server) + '</span></td>' +
                '<td>' + pill(a.monitoring === 'active' ? 'ok' : 'warn', a.monitoring || '—') + '</td>' +
                '<td>' + probeDots(a.probes) + '<div class="token-secondary">' + a.probes_ok + '/' + a.probes_total + ' healthy</div></td>' +
                '<td><div class="tele-avail"><div class="tele-bar"><div class="tele-bar-fill lv-' + (a.availability === null ? 'idle' : level(100 - a.availability, 1, 10)) + '" style="width:' + (a.availability || 0) + '%"></div></div>' +
                '<span class="token-date">' + (a.availability === null ? '—' : num(a.availability, 1) + '%') + '</span></div></td>' +
                '<td><div class="tele-lat"><span class="token-date">' + (a.latency_avg === null ? '—' : num(a.latency_avg, 1) + ' ms') + '</span>' + (a.spark ? sparkline(a.spark, 'var(--tc3)') : '') + '</div></td>' +
                '<td><span class="token-date">' + esc(ago(a.last_probe)) + '</span></td>' +
                '<td>' + pill(a.level, a.state === 'pending' ? 'No data' : a.level === 'warn' ? 'Degraded' : stateLabel[a.state]) + '</td></tr>';
        }).join('') + '</tbody>';
    }

    function renderAll()
    {
        document.querySelectorAll('#teleRange button').forEach(function (b) { b.classList.toggle('is-active', b.dataset.range === state.range); });

        if (data.empty) {
            $('teleSummary').innerHTML = '';
            $('teleAlertsPanel').hidden = true;
            $('teleServers').innerHTML = '<div class="tele-chart-empty">No installed agent found. Enroll an agent first.</div>';
            $('teleApps').innerHTML = '';
            return;
        }

        $('teleAlertsPanel').hidden = false;
        renderSummary();
        renderAlerts();
        renderServers();
        renderApps();
    }

    /* ---------- Load ---------- */

    async function load(silent)
    {
        if (state.controller) { state.controller.abort(); }
        const controller = new AbortController();
        state.controller = controller;

        const url = new URL(DATA_URL, window.location.origin);
        url.searchParams.set('range', state.range);

        if (!silent) { $('teleRoot').classList.add('is-loading'); }
        $('teleError').hidden = true;

        try {
            const res = await fetch(url.toString(), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal
            });

            if (res.redirected) { window.location.reload(); return; }
            if (!res.ok) { throw new Error('Unable to refresh telemetry (HTTP ' + res.status + ').'); }

            data = await res.json();
            renderAll();
        } catch (e) {
            if (e.name === 'AbortError') { return; }
            $('teleError').textContent = e.message || 'Unable to refresh telemetry.';
            $('teleError').hidden = false;
        } finally {
            if (state.controller === controller) { $('teleRoot').classList.remove('is-loading'); }
        }
    }

    function schedule()
    {
        clearInterval(state.timer);
        if (state.auto) {
            state.timer = setInterval(function () { if (!document.hidden) { load(true); } }, REFRESH_MS);
        }
    }

    /* ---------- Events ---------- */

    $('teleRange').addEventListener('click', function (e) {
        const b = e.target.closest('button[data-range]');
        if (!b || b.dataset.range === state.range) { return; }
        state.range = b.dataset.range;
        document.querySelectorAll('#teleRange button').forEach(function (x) { x.classList.toggle('is-active', x === b); });
        load(false);
    });

    $('teleFilter').addEventListener('click', function (e) {
        const b = e.target.closest('button[data-filter]');
        if (!b) { return; }
        state.filter = b.dataset.filter;
        document.querySelectorAll('#teleFilter button').forEach(function (x) { x.classList.toggle('is-active', x === b); });
        renderServers();
        renderApps();
    });

    $('teleSearch').addEventListener('input', function () {
        state.search = this.value.trim().toLowerCase();
        renderServers();
        renderApps();
    });

    $('teleRefresh').addEventListener('click', function () { load(false); });

    $('teleAuto').addEventListener('click', function () {
        state.auto = !state.auto;
        this.classList.toggle('is-on', state.auto);
        this.setAttribute('aria-pressed', state.auto ? 'true' : 'false');
        schedule();
    });

    $('teleApps').addEventListener('click', function (e) {
        const row = e.target.closest('.tele-app-row');
        if (row && row.dataset.href) { window.location.assign(row.dataset.href); }
    });

    document.addEventListener('visibilitychange', function () { if (!document.hidden && state.auto) { load(true); } });

    renderAll();
    schedule();
});
</script>

@endsection

@push('styles')
<style>
    .tele-page
    {
        --status-ok: #16a34a;
        --status-warn: #d97706;
        --status-bad: #dc2626;
        --tc1: #3b82f6;
        --tc2: #a855f7;
        --tc3: #14b8a6;
        --tc4: #f59e0b;
        display: flex;
        flex-direction: column;
        gap: 24px;
        position: relative;
    }

    .tele-page .page-header
    {
        margin-bottom: 0;
    }

    .tele-header
    {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
    }

    .tele-header .page-title
    {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    /* ---------- Status ---------- */
    .tele-state
    {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 4px 11px;
        border: 1px solid color-mix(in srgb, var(--status-ok) 40%, transparent);
        border-radius: 999px;
        background: color-mix(in srgb, var(--status-ok) 14%, transparent);
        color: var(--status-ok);
        font: 600 11px var(--mono);
        letter-spacing: .1em;
        text-transform: uppercase;
    }

    .tele-state-dot
    {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: currentColor;
        animation: tele-pulse 2s ease-out infinite;
    }

    .tele-state[data-state="stale"]
    {
        border-color: color-mix(in srgb, var(--status-warn) 40%, transparent);
        background: color-mix(in srgb, var(--status-warn) 14%, transparent);
        color: var(--status-warn);
    }

    .tele-state[data-state="offline"]
    {
        border-color: color-mix(in srgb, var(--status-bad) 40%, transparent);
        background: color-mix(in srgb, var(--status-bad) 14%, transparent);
        color: var(--status-bad);
    }

    .tele-state[data-state="offline"] .tele-state-dot
    {
        animation: none;
    }

    /* ---------- Kontrol ---------- */
    .tele-controls
    {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .tele-controls select
    {
        height: 40px;
        border: 1.5px solid var(--line);
        border-radius: 10px;
        padding: 0 12px;
        background: var(--surface);
        color: var(--ink);
        font: 500 13px var(--font);
        outline: none;
    }

    .tele-controls select[hidden]
    {
        display: none;
    }

    .tele-seg
    {
        display: inline-flex;
        padding: 3px;
        border: 1.5px solid var(--line);
        border-radius: 10px;
        background: var(--surface);
    }

    .tele-seg button
    {
        height: 32px;
        padding: 0 14px;
        border: 0;
        border-radius: 7px;
        background: transparent;
        color: var(--muted);
        font: 700 12px var(--mono);
        letter-spacing: .06em;
        cursor: pointer;
        transition: background .2s, color .2s, box-shadow .2s;
    }

    .tele-seg button:hover
    {
        color: var(--accent);
    }

    .tele-seg button.is-active
    {
        background: var(--accent-soft);
        color: var(--accent);
        box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--accent) 40%, transparent), 0 0 18px -8px var(--accent);
    }

    .tele-toggle
    {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        height: 40px;
        padding: 0 14px;
        border: 1.5px solid var(--line);
        border-radius: 10px;
        background: transparent;
        color: var(--muted);
        font: 700 12px var(--mono);
        letter-spacing: .06em;
        text-transform: uppercase;
        cursor: pointer;
        transition: color .2s, border-color .2s;
    }

    .tele-toggle-dot
    {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--muted);
        opacity: .6;
    }

    .tele-toggle.is-on
    {
        color: var(--accent);
        border-color: color-mix(in srgb, var(--accent) 45%, var(--line));
    }

    .tele-toggle.is-on .tele-toggle-dot
    {
        background: var(--accent);
        opacity: 1;
        box-shadow: 0 0 10px var(--accent);
    }

    .tele-refresh svg
    {
        width: 15px;
        height: 15px;
        margin-right: 8px;
    }

    .tele-banner
    {
        padding: 12px 16px;
        border: 1px solid color-mix(in srgb, var(--status-warn) 35%, transparent);
        border-left: 3px solid var(--status-warn);
        border-radius: 10px;
        background: color-mix(in srgb, var(--status-warn) 10%, transparent);
        color: var(--status-warn);
        font: 500 12.5px var(--mono);
    }

    .tele-banner[hidden],
    .tele-page .token-list-error[hidden]
    {
        display: none;
    }

    .tele-page .token-list-error
    {
        margin: 0;
    }

    /* ---------- Identitas ---------- */
    .tele-identity
    {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1px;
        border: 1px solid var(--line);
        border-radius: 14px;
        background: var(--line);
        overflow: hidden;
        box-shadow: var(--shadow);
    }

    .tele-id-item
    {
        display: flex;
        flex-direction: column;
        gap: 4px;
        padding: 12px 16px;
        background: var(--surface);
        min-width: 0;
    }

    .tele-id-item span
    {
        font: 600 10.5px var(--mono);
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .tele-id-item b
    {
        font: 600 12.5px var(--mono);
        color: var(--ink);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* ---------- Ringkasan (turunan gaya token-summary) ---------- */
    .tele-summary
    {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 18px;
    }

    .token-summary-card
    {
        position: relative;
        padding: 22px 22px;
        border: 1px solid var(--line);
        border-radius: 16px;
        background-color: var(--surface);
        background-image: radial-gradient(circle at 100% 0%, color-mix(in srgb, var(--accent) 14%, transparent), transparent 55%), linear-gradient(color-mix(in srgb, var(--accent) 5%, transparent) 1px, transparent 1px), linear-gradient(90deg, color-mix(in srgb, var(--accent) 5%, transparent) 1px, transparent 1px);
        background-size: auto, 22px 22px, 22px 22px;
        box-shadow: var(--shadow);
        overflow: hidden;
        transition: transform .25s ease, border-color .25s ease, box-shadow .25s ease;
    }

    .token-summary-card:hover
    {
        transform: translateY(-3px);
        border-color: color-mix(in srgb, var(--accent) 45%, var(--line));
        box-shadow: var(--shadow), 0 18px 40px -22px var(--accent);
    }

    .token-summary-card::before
    {
        content: '';
        position: absolute;
        top: -1px;
        left: 22px;
        width: 44px;
        height: 2px;
        border-radius: 0 0 3px 3px;
        background: var(--brand);
    }

    .token-summary-card::after
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

    .token-summary-top
    {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
    }

    .token-summary-icon
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

    .token-summary-icon svg
    {
        width: 19px;
        height: 19px;
    }

    .token-summary-label
    {
        font: 600 11.5px var(--mono);
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .token-summary-value
    {
        display: flex;
        align-items: baseline;
        flex-wrap: wrap;
        gap: 10px;
        font-size: 32px;
        font-weight: 800;
        letter-spacing: -.03em;
        line-height: 1.1;
        color: var(--ink);
    }

    .token-summary-value small
    {
        margin-left: 2px;
        font-size: 16px;
        font-weight: 700;
        color: var(--muted);
    }

    .token-summary-percent
    {
        padding: 3px 9px;
        border: 1px solid color-mix(in srgb, var(--accent) 35%, transparent);
        border-radius: 999px;
        background: var(--accent-soft);
        color: var(--accent);
        font: 600 11px var(--mono);
        letter-spacing: 0;
    }

    .token-summary-description
    {
        margin-top: 8px;
        font: 500 11.5px var(--mono);
        color: var(--muted);
    }

    .token-summary-meter
    {
        height: 4px;
        margin-top: 12px;
        border-radius: 999px;
        background: var(--surface-2);
        overflow: hidden;
    }

    .token-summary-meter-fill
    {
        height: 100%;
        border-radius: 999px;
        background: var(--brand);
        box-shadow: 0 0 12px var(--accent);
        transition: width .8s cubic-bezier(.2, .8, .2, 1), background .3s;
    }

    .tele-card[data-lv="warn"] .token-summary-meter-fill
    {
        background: var(--status-warn);
        box-shadow: 0 0 12px var(--status-warn);
    }

    .tele-card[data-lv="crit"] .token-summary-meter-fill
    {
        background: var(--status-bad);
        box-shadow: 0 0 12px var(--status-bad);
    }

    .tele-spark
    {
        display: block;
        width: 100%;
        height: 28px;
        margin-top: 12px;
        opacity: .9;
    }

    /* ---------- Panel ---------- */
    .token-panel
    {
        position: relative;
        border: 1px solid var(--line);
        border-radius: 18px;
        background: var(--surface);
        box-shadow: var(--shadow);
        overflow: hidden;
    }

    .token-panel::before
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

    .token-panel-header
    {
        padding: 26px 24px 20px;
        border-bottom: 1px solid var(--line);
        background-image: linear-gradient(180deg, color-mix(in srgb, var(--accent) 6%, transparent), transparent);
    }

    .token-panel-header h2
    {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
        font-size: 16px;
        font-weight: 800;
        color: var(--ink);
    }

    .token-panel-header p
    {
        margin: 5px 0 0;
        font: 500 12px var(--mono);
        color: var(--muted);
    }

    .token-panel-live
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

    .token-panel-live::before
    {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
        animation: tele-pulse 2s ease-out infinite;
    }

    .tele-split
    {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 24px;
    }

    /* ---------- Pill ---------- */
    .tele-pill
    {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border: 1px solid var(--line);
        border-radius: 999px;
        background: var(--surface-2);
        color: var(--muted);
        font: 600 11px var(--mono);
        white-space: nowrap;
    }

    .tele-pill.lv-ok
    {
        border-color: color-mix(in srgb, var(--status-ok) 40%, transparent);
        background: color-mix(in srgb, var(--status-ok) 14%, transparent);
        color: var(--status-ok);
    }

    .tele-pill.lv-info
    {
        border-color: color-mix(in srgb, var(--accent) 40%, transparent);
        background: var(--accent-soft);
        color: var(--accent);
    }

    .tele-pill.lv-warn
    {
        border-color: color-mix(in srgb, var(--status-warn) 40%, transparent);
        background: color-mix(in srgb, var(--status-warn) 14%, transparent);
        color: var(--status-warn);
    }

    .tele-pill.lv-crit
    {
        border-color: color-mix(in srgb, var(--status-bad) 40%, transparent);
        background: color-mix(in srgb, var(--status-bad) 14%, transparent);
        color: var(--status-bad);
    }

    /* ---------- Insight ---------- */
    .tele-insights
    {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        padding: 20px 24px 24px;
    }

    .tele-insight
    {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 14px 16px;
        border: 1px solid var(--line);
        border-left: 3px solid var(--line);
        border-radius: 12px;
        background: var(--surface-2);
    }

    .tele-insight .tele-pill
    {
        flex-shrink: 0;
        margin-top: 1px;
    }

    .tele-insight.lv-ok { border-left-color: var(--status-ok); }
    .tele-insight.lv-info { border-left-color: var(--accent); }
    .tele-insight.lv-warn { border-left-color: var(--status-warn); }
    .tele-insight.lv-crit { border-left-color: var(--status-bad); }

    .tele-insight-title
    {
        font-size: 13px;
        font-weight: 700;
        color: var(--ink);
    }

    .tele-insight-detail
    {
        margin-top: 4px;
        font: 500 11.5px/1.55 var(--mono);
        color: var(--muted);
    }

    /* ---------- Chart ---------- */
    .tele-grid
    {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
        padding: 20px 24px 24px;
    }

    .tele-grid-pipe
    {
        border-bottom: 1px solid var(--line);
    }

    .tele-chart-card
    {
        min-width: 0;
        padding: 16px 16px 8px;
        border: 1px solid var(--line);
        border-radius: 14px;
        background-color: var(--surface);
        background-image: linear-gradient(color-mix(in srgb, var(--accent) 4%, transparent) 1px, transparent 1px), linear-gradient(90deg, color-mix(in srgb, var(--accent) 4%, transparent) 1px, transparent 1px);
        background-size: 22px 22px;
    }

    .tele-chart-head
    {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 6px;
    }

    .tele-chart-title
    {
        font: 600 11.5px var(--mono);
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .tele-legend
    {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        font: 500 11px var(--mono);
        color: var(--muted);
    }

    .tele-dot
    {
        display: inline-block;
        width: 8px;
        height: 8px;
        margin-right: 6px;
        border-radius: 50%;
    }

    .tele-chart
    {
        position: relative;
        min-height: 210px;
    }

    .tele-chart svg
    {
        display: block;
        width: 100%;
        height: auto;
        overflow: visible;
    }

    .tele-grid-line
    {
        stroke: var(--line);
        stroke-width: 1;
        stroke-dasharray: 2 4;
    }

    .tele-threshold
    {
        stroke-width: 1;
        stroke-dasharray: 5 4;
        opacity: .55;
    }

    .tele-axis
    {
        fill: var(--muted);
        font: 500 10px var(--mono);
    }

    .tele-cross
    {
        stroke: var(--accent);
        stroke-width: 1;
        stroke-dasharray: 3 3;
        opacity: .7;
        pointer-events: none;
    }

    .tele-hit
    {
        fill: transparent;
        cursor: crosshair;
    }

    .tele-tip
    {
        position: absolute;
        z-index: 5;
        min-width: 150px;
        padding: 10px 12px;
        border: 1px solid color-mix(in srgb, var(--accent) 40%, var(--line));
        border-radius: 10px;
        background: var(--surface);
        box-shadow: var(--shadow), 0 0 24px -10px var(--accent);
        font: 500 11.5px var(--mono);
        color: var(--muted);
        pointer-events: none;
    }

    .tele-tip[hidden]
    {
        display: none;
    }

    .tele-tip-time
    {
        margin-bottom: 6px;
        color: var(--ink);
        font-weight: 700;
    }

    .tele-tip-row
    {
        display: flex;
        align-items: center;
        gap: 4px;
        padding: 2px 0;
    }

    .tele-tip-row b
    {
        margin-left: auto;
        padding-left: 14px;
        color: var(--ink);
    }

    .tele-chart-empty
    {
        display: grid;
        place-items: center;
        min-height: 120px;
        padding: 24px;
        color: var(--muted);
        font: 500 12px var(--mono);
        text-align: center;
    }

    /* ---------- Disk & jaringan ---------- */
    .tele-disks
    {
        display: flex;
        flex-direction: column;
        gap: 14px;
        padding: 20px 24px 24px;
    }

    .tele-disk
    {
        padding: 14px 16px;
        border: 1px solid var(--line);
        border-radius: 12px;
        background: var(--surface-2);
    }

    .tele-disk-top
    {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
    }

    .tele-bar
    {
        flex: 1;
        height: 6px;
        border-radius: 999px;
        background: var(--surface);
        box-shadow: inset 0 0 0 1px var(--line);
        overflow: hidden;
    }

    .tele-bar-fill
    {
        height: 100%;
        border-radius: 999px;
        background: var(--status-ok);
        box-shadow: 0 0 10px var(--status-ok);
        transition: width .8s cubic-bezier(.2, .8, .2, 1);
    }

    .tele-bar-fill.lv-warn { background: var(--status-warn); box-shadow: 0 0 10px var(--status-warn); }
    .tele-bar-fill.lv-crit { background: var(--status-bad); box-shadow: 0 0 10px var(--status-bad); }
    .tele-bar-fill.lv-idle { background: var(--muted); box-shadow: none; }

    .tele-disk-meta
    {
        display: flex;
        justify-content: space-between;
        gap: 8px;
        margin-top: 10px;
        font: 500 11px var(--mono);
        color: var(--muted);
    }

    .tele-note
    {
        padding: 10px 14px;
        border: 1px dashed color-mix(in srgb, var(--accent) 40%, var(--line));
        border-radius: 10px;
        color: var(--accent);
        font: 600 11.5px var(--mono);
    }

    .tele-netstats
    {
        padding: 20px 24px 24px;
    }

    .tele-netname
    {
        margin-bottom: 14px;
    }

    .tele-stat-grid,
    .tele-kpis
    {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .tele-kpis
    {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        padding: 20px 24px 0;
    }

    .tele-stat
    {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 0;
        padding: 14px 16px;
        border: 1px solid var(--line);
        border-radius: 12px;
        background: var(--surface-2);
    }

    .tele-stat span
    {
        font: 600 10.5px var(--mono);
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .tele-stat b
    {
        font-size: 20px;
        font-weight: 800;
        letter-spacing: -.02em;
        color: var(--ink);
    }

    .tele-stat em
    {
        font: 500 11px var(--mono);
        font-style: normal;
        color: var(--muted);
    }

    .tele-stat.lv-ok { box-shadow: inset 3px 0 0 var(--status-ok); }
    .tele-stat.lv-info { box-shadow: inset 3px 0 0 var(--accent); }
    .tele-stat.lv-warn { box-shadow: inset 3px 0 0 var(--status-warn); }
    .tele-stat.lv-crit { box-shadow: inset 3px 0 0 var(--status-bad); }

    /* ---------- Aplikasi ---------- */
    .tele-app
    {
        border-bottom: 1px solid var(--line);
    }

    .tele-app:last-child
    {
        border-bottom: 0;
    }

    .tele-app-head
    {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        padding: 18px 24px;
    }

    .tele-app-tags
    {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .tele-app-tags .token-secondary
    {
        margin-top: 0;
    }

    .tele-app-chart
    {
        margin: 20px 24px 24px;
    }

    .tele-avail
    {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 140px;
    }

    .tele-subhead
    {
        padding: 18px 24px 4px;
        font: 600 11.5px var(--mono);
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .tele-collectors
    {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        padding: 12px 24px 24px;
    }

    .tele-collector
    {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        min-width: 0;
        padding: 12px 16px;
        border: 1px solid var(--line);
        border-radius: 12px;
        background: var(--surface-2);
    }

    /* ---------- Tabel (gaya token-table) ---------- */
    .token-table-wrapper
    {
        width: 100%;
        overflow-x: auto;
    }

    .token-table
    {
        width: 100%;
        border-collapse: collapse;
        white-space: nowrap;
    }

    .token-table th
    {
        padding: 13px 20px;
        background: var(--surface-2);
        border-bottom: 1px solid var(--line);
        border-top: 1px solid var(--line);
        text-align: left;
        font: 600 11px var(--mono);
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .token-table td
    {
        padding: 14px 20px;
        border-bottom: 1px solid var(--line);
        font-size: 13px;
        color: var(--muted);
        vertical-align: middle;
    }

    .token-table tbody tr:last-child td
    {
        border-bottom: 0;
    }

    .token-table tbody tr
    {
        transition: background .2s;
    }

    .token-table tbody tr:hover
    {
        background: var(--accent-soft);
    }

    .token-table tbody tr td:first-child
    {
        box-shadow: inset 0 0 0 var(--accent);
        transition: box-shadow .2s;
    }

    .token-table tbody tr:hover td:first-child
    {
        box-shadow: inset 3px 0 0 var(--accent);
    }

    .token-main
    {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .token-main-icon
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

    .token-main-icon svg
    {
        width: 16px;
        height: 16px;
    }

    .token-primary
    {
        color: var(--ink);
        font-size: 13px;
        font-weight: 700;
    }

    .token-date
    {
        color: var(--ink);
        font: 600 12px var(--mono);
    }

    .token-secondary
    {
        margin-top: 5px;
        font: 500 11px var(--mono);
        color: var(--muted);
    }

    .token-environment
    {
        display: inline-block;
        padding: 4px 10px;
        border: 1px solid var(--line);
        border-radius: 8px;
        background: var(--surface-2);
        color: var(--ink);
        font: 600 11.5px var(--mono);
    }

    .tele-record-row
    {
        cursor: pointer;
    }

    .tele-record-row.is-open
    {
        background: var(--accent-soft);
    }

    .tele-record-detail:hover
    {
        background: transparent !important;
    }

    .tele-record-detail td
    {
        padding: 0 !important;
        white-space: normal;
    }

    .tele-record-detail pre
    {
        max-height: 360px;
        margin: 0;
        padding: 16px 24px;
        overflow: auto;
        background: var(--surface-2);
        color: var(--ink);
        font: 500 11.5px/1.6 var(--mono);
    }

    /* ---------- Error & loading ---------- */
    .token-list-error
    {
        padding: 12px 16px;
        border: 1px solid color-mix(in srgb, var(--status-bad) 35%, transparent);
        border-left: 3px solid var(--status-bad);
        border-radius: 10px;
        background: color-mix(in srgb, var(--status-bad) 10%, transparent);
        color: var(--status-bad);
        font: 500 12.5px var(--mono);
    }

    .token-filter-button
    {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 40px;
        padding: 0 18px;
        border: 0;
        border-radius: 10px;
        background: var(--brand);
        color: #ffffff;
        font: 700 13px var(--font);
        cursor: pointer;
        box-shadow: 0 10px 22px -12px rgba(47, 76, 240, .9), inset 0 1px 0 rgba(255, 255, 255, .3);
        transition: filter .2s, transform .2s;
    }

    .token-filter-button:hover
    {
        filter: brightness(1.08);
        transform: translateY(-1px);
    }

    .tele-page.is-loading::before
    {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 50;
        height: 2px;
        background: linear-gradient(90deg, transparent, var(--accent), transparent);
        background-size: 40% 100%;
        background-repeat: no-repeat;
        animation: tele-loading 1s ease-in-out infinite;
    }

    /* ---------- Animasi ---------- */
    @keyframes tele-pulse
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

    @keyframes tele-loading
    {
        0%
        {
            background-position: -40% 0;
        }

        100%
        {
            background-position: 140% 0;
        }
    }

    @media (prefers-reduced-motion: reduce)
    {
        .tele-state-dot,
        .token-panel-live::before,
        .tele-page.is-loading::before
        {
            animation: none;
        }

        .token-summary-card,
        .token-summary-meter-fill,
        .tele-bar-fill
        {
            transition: none;
        }
    }

    @media (max-width: 1280px)
    {
        .tele-summary
        {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 1000px)
    {
        .tele-grid,
        .tele-split,
        .tele-insights,
        .tele-collectors
        {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 700px)
    {
        .tele-summary
        {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .tele-grid,
        .tele-insights,
        .tele-collectors,
        .tele-disks,
        .tele-netstats
        {
            padding-left: 16px;
            padding-right: 16px;
        }

        .token-panel-header
        {
            padding: 22px 16px 16px;
        }

        .tele-app-head
        {
            padding: 16px;
        }

        .tele-app-chart
        {
            margin: 16px;
        }
    }

    @media (max-width: 480px)
    {
        .tele-summary
        {
            grid-template-columns: 1fr;
        }

        .tele-stat-grid
        {
            grid-template-columns: 1fr;
        }
    }
</style>
<style>
    .tele-toolbar
    {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }

    .tele-search
    {
        position: relative;
        flex: 1;
        min-width: 240px;
    }

    .tele-search svg
    {
        position: absolute;
        top: 50%;
        left: 12px;
        width: 16px;
        height: 16px;
        transform: translateY(-50%);
        color: var(--muted);
        pointer-events: none;
    }

    .tele-search input
    {
        width: 100%;
        height: 40px;
        box-sizing: border-box;
        border: 1.5px solid var(--line);
        border-radius: 10px;
        padding: 0 12px 0 36px;
        background: var(--surface);
        color: var(--ink);
        font: 500 13px var(--font);
        outline: none;
        transition: border-color .2s, box-shadow .2s;
    }

    .tele-search input:focus
    {
        border-color: var(--accent);
        box-shadow: 0 0 0 4px var(--accent-soft), 0 0 22px -8px var(--accent);
    }

    .tele-analysis[hidden]
    {
        display: none;
    }

    .tele-link
    {
        text-decoration: none;
        transition: transform .2s, border-color .2s, box-shadow .2s;
    }

    .tele-link:hover
    {
        transform: translateY(-2px);
        border-color: color-mix(in srgb, var(--accent) 45%, var(--line));
    }

    .tele-where
    {
        margin-left: 6px;
        padding: 2px 8px;
        border: 1px solid var(--line);
        border-radius: 6px;
        background: var(--surface);
        color: var(--accent);
        font: 600 10.5px var(--mono);
    }

    .tele-allclear
    {
        grid-column: 1 / -1;
    }

    /* ---------- Kartu server ---------- */
    .tele-fleet
    {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 18px;
        padding: 20px 24px 24px;
    }

    .tele-server
    {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 14px;
        min-width: 0;
        padding: 18px 20px;
        border: 1px solid var(--line);
        border-radius: 16px;
        background-color: var(--surface);
        background-image: radial-gradient(circle at 100% 0%, color-mix(in srgb, var(--accent) 10%, transparent), transparent 55%), linear-gradient(color-mix(in srgb, var(--accent) 4%, transparent) 1px, transparent 1px), linear-gradient(90deg, color-mix(in srgb, var(--accent) 4%, transparent) 1px, transparent 1px);
        background-size: auto, 22px 22px, 22px 22px;
        color: inherit;
        text-decoration: none;
        overflow: hidden;
        transition: transform .25s ease, border-color .25s ease, box-shadow .25s ease;
    }

    .tele-server::before
    {
        content: '';
        position: absolute;
        top: -1px;
        left: 20px;
        width: 44px;
        height: 2px;
        border-radius: 0 0 3px 3px;
        background: var(--status-ok);
        box-shadow: 0 0 12px var(--status-ok);
    }

    .tele-server.lv-warn::before { background: var(--status-warn); box-shadow: 0 0 12px var(--status-warn); }
    .tele-server.lv-crit::before { background: var(--status-bad); box-shadow: 0 0 12px var(--status-bad); }

    .tele-server:hover
    {
        transform: translateY(-3px);
        border-color: color-mix(in srgb, var(--accent) 45%, var(--line));
        box-shadow: var(--shadow), 0 18px 40px -22px var(--accent);
    }

    .tele-server-head
    {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
    }

    .tele-meters
    {
        display: flex;
        flex-direction: column;
        gap: 9px;
    }

    .tele-mrow
    {
        display: grid;
        grid-template-columns: 38px 1fr 52px;
        align-items: center;
        column-gap: 10px;
        font: 600 10.5px var(--mono);
        letter-spacing: .08em;
        color: var(--muted);
    }

    .tele-mrow b
    {
        text-align: right;
        font: 700 12px var(--mono);
        color: var(--ink);
    }

    .tele-mrow em
    {
        grid-column: 2 / 4;
        margin-top: 3px;
        font: 500 10.5px var(--mono);
        font-style: normal;
        letter-spacing: 0;
    }

    .tele-spark-wrap
    {
        position: relative;
    }

    .tele-spark-wrap span
    {
        position: absolute;
        top: 0;
        right: 0;
        font: 500 10px var(--mono);
        color: var(--muted);
    }

    .tele-spark
    {
        display: block;
        width: 100%;
        height: 34px;
        margin: 0;
    }

    .tele-spark-empty
    {
        display: grid;
        place-items: center;
        border: 1px dashed var(--line);
        border-radius: 8px;
        color: var(--muted);
        font: 500 10.5px var(--mono);
    }

    .tele-server-meta
    {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 16px;
        padding-top: 12px;
        border-top: 1px solid var(--line);
        font: 500 11px var(--mono);
        color: var(--muted);
    }

    .tele-server-meta b
    {
        color: var(--ink);
    }

    .tele-server-foot
    {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px;
    }

    .tele-chips
    {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .tele-topissue
    {
        padding: 8px 12px;
        border-left: 3px solid var(--status-warn);
        border-radius: 6px;
        background: color-mix(in srgb, var(--status-warn) 10%, transparent);
        color: var(--status-warn);
        font: 600 11.5px var(--mono);
    }

    .tele-server.lv-crit .tele-topissue
    {
        border-left-color: var(--status-bad);
        background: color-mix(in srgb, var(--status-bad) 10%, transparent);
        color: var(--status-bad);
    }

    /* ---------- Tabel aplikasi ---------- */
    .tele-app-row
    {
        cursor: pointer;
    }

    .tele-dots
    {
        display: inline-flex;
        gap: 5px;
    }

    .tele-pdot
    {
        width: 10px;
        height: 10px;
        border-radius: 3px;
        background: var(--muted);
        opacity: .5;
    }

    .tele-pdot.lv-ok { background: var(--status-ok); opacity: 1; box-shadow: 0 0 8px var(--status-ok); }
    .tele-pdot.lv-info { background: var(--accent); opacity: .9; }
    .tele-pdot.lv-warn { background: var(--status-warn); opacity: 1; }

    .tele-lat
    {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .tele-lat .tele-spark
    {
        width: 90px;
        height: 24px;
    }

    .tele-pill.lv-idle
    {
        opacity: .8;
    }

    @media (max-width: 700px)
    {
        .tele-fleet
        {
            grid-template-columns: 1fr;
            padding: 16px;
        }
    }
</style>
@endpush