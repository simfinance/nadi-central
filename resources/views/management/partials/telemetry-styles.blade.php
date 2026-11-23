@extends('layouts.app')

@section('title', 'Server Telemetry - NADI Console')

@section('breadcrumb', 'Management / Telemetry / Server Detail')

@section('content')

<div class="tele-page" id="teleRoot">

    <div class="page-header tele-header">
        <div>
            <a href="{{ route('management.telemetry') }}" class="tele-back">&larr; All Servers &amp; Applications</a>
            <h1 class="page-title">
                Telemetry Analytics
                <span class="tele-state" id="teleState" data-state="live">
                    <span class="tele-state-dot"></span>
                    <span id="teleStateText">Live</span>
                </span>
            </h1>
            <p class="page-description">Server metrics, application probes, and ingest pipeline health in one view.</p>
        </div>

        <div class="tele-controls">
            <select id="teleAgent" aria-label="Agent" hidden></select>

            <div class="tele-seg" id="teleRange" role="group" aria-label="Time range">
                @foreach ($ranges as $key => $label)
                    <button type="button" data-range="{{ $key }}" title="{{ $label }}">{{ strtoupper($key) }}</button>
                @endforeach
            </div>

            <button type="button" class="tele-toggle is-on" id="teleAuto" aria-pressed="true" title="Auto refresh every 15 seconds">
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
    <div class="tele-banner" id="teleBanner" hidden></div>

    <div class="tele-identity" id="teleIdentity"></div>

    <div class="token-summary tele-summary" id="teleSummary"></div>

    <div class="token-panel tele-analysis">
        <div class="token-panel-header">
            <h2>
                Automated Analysis
                <span class="token-panel-live">Rules</span>
            </h2>
            <p>Findings derived from the metrics in the selected window.</p>
        </div>
        <div class="tele-insights" id="teleInsights"></div>
    </div>

    <div class="token-panel">
        <div class="token-panel-header">
            <h2>
                Resource Telemetry
                <span class="token-panel-live">Live</span>
            </h2>
            <p id="teleWindowLabel">Server metrics aggregated per time bucket.</p>
        </div>

        <div class="tele-grid">
            <div class="tele-chart-card">
                <div class="tele-chart-head">
                    <div class="tele-chart-title">CPU &amp; Memory</div>
                    <div class="tele-legend" id="legCpu"></div>
                </div>
                <div class="tele-chart" id="chartCpu"></div>
            </div>

            <div class="tele-chart-card">
                <div class="tele-chart-head">
                    <div class="tele-chart-title">Load Average</div>
                    <div class="tele-legend" id="legLoad"></div>
                </div>
                <div class="tele-chart" id="chartLoad"></div>
            </div>

            <div class="tele-chart-card">
                <div class="tele-chart-head">
                    <div class="tele-chart-title">Network Throughput</div>
                    <div class="tele-legend" id="legNet"></div>
                </div>
                <div class="tele-chart" id="chartNet"></div>
            </div>

            <div class="tele-chart-card">
                <div class="tele-chart-head">
                    <div class="tele-chart-title">Root Disk Usage</div>
                    <div class="tele-legend" id="legDisk"></div>
                </div>
                <div class="tele-chart" id="chartDisk"></div>
            </div>
        </div>
    </div>

    <div class="tele-split">
        <div class="token-panel">
            <div class="token-panel-header">
                <h2>Storage</h2>
                <p>Real filesystems only. Snap/squashfs and bind mounts are folded.</p>
            </div>
            <div class="tele-disks" id="teleDisks"></div>
        </div>

        <div class="token-panel">
            <div class="token-panel-header">
                <h2>Network Interface</h2>
                <p>Cumulative counters since boot.</p>
            </div>
            <div class="tele-netstats" id="teleNetStats"></div>
        </div>
    </div>

    <div class="token-panel">
        <div class="token-panel-header">
            <h2>
                Application Probes
                <span class="token-panel-live">Bridge</span>
            </h2>
            <p>Health, info, database, metrics, queue, and scheduler endpoints exposed by the application bridge.</p>
        </div>
        <div id="teleApps"></div>
    </div>

    <div class="token-panel">
        <div class="token-panel-header">
            <h2>
                Ingest Pipeline
                <span class="token-panel-live">Stream</span>
            </h2>
            <p>Batches received from the agent, sequence integrity, and collectors.</p>
        </div>

        <div class="tele-kpis" id="telePipeKpis"></div>

        <div class="tele-grid tele-grid-pipe">
            <div class="tele-chart-card">
                <div class="tele-chart-head">
                    <div class="tele-chart-title">Records per Bucket</div>
                    <div class="tele-legend" id="legIngest"></div>
                </div>
                <div class="tele-chart" id="chartIngest"></div>
            </div>

            <div class="tele-chart-card">
                <div class="tele-chart-head">
                    <div class="tele-chart-title">Ingest Lag (sent → received)</div>
                    <div class="tele-legend" id="legLag"></div>
                </div>
                <div class="tele-chart" id="chartLag"></div>
            </div>
        </div>

        <div class="token-table-wrapper">
            <table class="token-table" id="teleStreams"></table>
        </div>

        <div class="tele-subhead">Collectors &amp; Gaps</div>
        <div class="tele-collectors" id="teleCollectors"></div>
    </div>

    <div class="token-panel">
        <div class="token-panel-header">
            <h2>
                Recent Records
                <span class="token-panel-live">Raw</span>
            </h2>
            <p>Latest telemetry records. Click a row to inspect the raw JSON payload.</p>
        </div>
        <div class="token-table-wrapper">
            <table class="token-table tele-records" id="teleRecent"></table>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function ()
{
    const DATA_URL = @json(route('management.telemetry.data'));
    const FLEET_URL = @json(route('management.telemetry'));
    const RECORD_URL = @json(route('management.telemetry.record', ['id' => '__ID__']));
    const REFRESH_MS = 15000;

    let data = @json($payload);

    const state = {
        range: data.range ? data.range.key : '24h',
        agent: data.agent ? data.agent.id : '',
        auto: true,
        timer: null,
        controller: null
    };

    const $ = function (id) { return document.getElementById(id); };
    const charts = {};

    /* ---------- Helper format ---------- */

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
        const dec = d === undefined ? (n >= 100 || i === 0 ? 0 : n >= 10 ? 1 : 2) : d;
        return (v < 0 ? '-' : '') + n.toFixed(dec) + ' ' + u[i];
    }

    function rate(v)
    {
        return v === null || v === undefined ? '—' : bytes(v) + '/s';
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
        if (!iso) { return '—'; }
        const s = (Date.now() - Date.parse(iso)) / 1000;
        return s < 5 ? 'just now' : dur(s) + ' ago';
    }

    function dt(iso)
    {
        if (!iso) { return '—'; }
        const d = new Date(iso);
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' +
            d.toLocaleTimeString('en-GB', { hour12: false });
    }

    function level(v, warn, crit)
    {
        if (v === null || v === undefined) { return 'idle'; }
        return v >= crit ? 'crit' : v >= warn ? 'warn' : 'ok';
    }

    function icon(name)
    {
        const p = {
            cpu: '<rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v3M15 2v3M9 19v3M15 19v3M2 9h3M2 15h3M19 9h3M19 15h3"/>',
            mem: '<rect x="3" y="7" width="18" height="10" rx="2"/><path d="M7 11v2M11 11v2M15 11v2M7 17v3M12 17v3M17 17v3"/>',
            disk: '<ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6"/><path d="M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>',
            load: '<path d="M3 17l5-6 4 4 4-8 5 6"/><path d="M3 21h18"/>',
            ingest: '<path d="M12 4v11"/><path d="M7.5 11l4.5 4.5 4.5-4.5"/><path d="M5 19h14"/>'
        };
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + (p[name] || '') + '</svg>';
    }

    /* ---------- Chart SVG ---------- */

    function niceMax(v)
    {
        if (!isFinite(v) || v <= 0) { return 1; }
        const e = Math.pow(10, Math.floor(Math.log10(v)));
        const f = v / e;
        return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10) * e;
    }

    function xLabel(ms, spanMs)
    {
        const d = new Date(ms);
        if (spanMs > 2 * 86400000) {
            return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
        }
        return d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', hour12: false });
    }

    function sparkline(values, color)
    {
        const v = values.filter(function (x) { return x !== null; });
        if (v.length < 2) { return ''; }
        const W = 100, H = 28, min = Math.min.apply(null, v), max = Math.max.apply(null, v), span = (max - min) || 1;
        let pts = [];
        values.forEach(function (y, i) {
            if (y === null) { return; }
            pts.push((i / (values.length - 1) * W).toFixed(1) + ',' + (H - 3 - (y - min) / span * (H - 6)).toFixed(1));
        });
        return '<svg class="tele-spark" viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="none" aria-hidden="true">' +
            '<polyline points="' + pts.join(' ') + '" fill="none" style="stroke:' + color + '" stroke-width="1.6" vector-effect="non-scaling-stroke" stroke-linejoin="round"/></svg>';
    }

    /**
     * o: { t:[iso], bucket:sec, series:[{name,color,values,type:'line'|'area'|'bar'}],
     *      fmt:fn, yMax:number|null, yMin:number, stacked:bool, height:number }
     */
    function drawChart(id, o)
    {
        const el = $(id);
        if (data.window && o.x0 === undefined) {
            o.x0 = Date.parse(data.window.start);
            o.x1 = Date.parse(data.window.end);
        }
        charts[id] = o;

        if (!o.t || !o.t.length) {
            el.innerHTML = '<div class="tele-chart-empty">No data in this window</div>';
            return;
        }

        const W = Math.max(el.clientWidth, 280), H = o.height || 210;
        const m = { l: 52, r: 14, t: 12, b: 26 };
        const iw = W - m.l - m.r, ih = H - m.t - m.b;
        const ts = o.t.map(function (x) { return Date.parse(x); });
        const bucketMs = (o.bucket || 60) * 1000;
        const isBar = o.series.some(function (s) { return s.type === 'bar'; });

        // Sumbu X mengikuti window penuh, sehingga jeda data terlihat apa adanya.
        let x0 = o.x0 !== undefined ? o.x0 : ts[0];
        let x1 = o.x1 !== undefined ? o.x1 : ts[ts.length - 1] + (isBar ? bucketMs : 0);
        if (isBar && x1 < ts[ts.length - 1] + bucketMs) { x1 = ts[ts.length - 1] + bucketMs; }
        if (x1 <= x0) { x1 = x0 + bucketMs; }

        let ymax = o.yMax;
        if (ymax === null || ymax === undefined) {
            let mx = 0;
            ts.forEach(function (_, i) {
                if (o.stacked) {
                    let sum = 0;
                    o.series.forEach(function (s) { sum += s.values[i] || 0; });
                    mx = Math.max(mx, sum);
                } else {
                    o.series.forEach(function (s) { if (s.values[i] !== null && s.values[i] !== undefined) { mx = Math.max(mx, s.values[i]); } });
                }
            });
            ymax = niceMax(mx * 1.08);
        }
        const ymin = o.yMin || 0;

        const X = function (t) { return m.l + (t - x0) / (x1 - x0) * iw; };
        const Y = function (v) { return m.t + ih - (Math.min(Math.max(v, ymin), ymax) - ymin) / (ymax - ymin) * ih; };

        let svg = '<svg viewBox="0 0 ' + W + ' ' + H + '" width="' + W + '" height="' + H + '" role="img">';

        // grid + sumbu Y
        for (let i = 0; i <= 4; i++) {
            const v = ymin + (ymax - ymin) * i / 4, y = Y(v);
            svg += '<line class="tele-grid-line" x1="' + m.l + '" x2="' + (W - m.r) + '" y1="' + y.toFixed(1) + '" y2="' + y.toFixed(1) + '"/>';
            svg += '<text class="tele-axis" x="' + (m.l - 8) + '" y="' + (y + 3.5).toFixed(1) + '" text-anchor="end">' + esc((o.axis || o.fmt)(v)) + '</text>';
        }

        // sumbu X
        const span = x1 - x0;
        for (let i = 0; i <= 4; i++) {
            const t = x0 + span * i / 4;
            svg += '<text class="tele-axis" x="' + X(t).toFixed(1) + '" y="' + (H - 8) + '" text-anchor="' + (i === 0 ? 'start' : i === 4 ? 'end' : 'middle') + '">' + xLabel(t, span) + '</text>';
        }

        // batas threshold
        (o.thresholds || []).forEach(function (th) {
            if (th.value > ymax || th.value < ymin) { return; }
            const y = Y(th.value);
            svg += '<line class="tele-threshold" style="stroke:' + th.color + '" x1="' + m.l + '" x2="' + (W - m.r) + '" y1="' + y.toFixed(1) + '" y2="' + y.toFixed(1) + '"/>';
        });

        // seri
        const gapMs = bucketMs * 2.5;

        if (isBar) {
            const bucketW = bucketMs / (x1 - x0) * iw, bw = Math.max(1.5, bucketW * 0.72);
            ts.forEach(function (t, i) {
                let acc = 0;
                o.series.forEach(function (s) {
                    const v = s.values[i] || 0;
                    if (v <= 0) { return; }
                    const yTop = Y(acc + v), yBot = Y(acc);
                    svg += '<rect x="' + (X(t) + (bucketW - bw) / 2).toFixed(1) + '" y="' + yTop.toFixed(1) + '" width="' + bw.toFixed(1) +
                        '" height="' + Math.max(0.5, yBot - yTop).toFixed(1) + '" rx="1.5" style="fill:' + s.color + '"/>';
                    acc += v;
                });
            });
        } else {
            o.series.forEach(function (s, si) {
                let seg = [], segs = [];
                ts.forEach(function (t, i) {
                    const v = s.values[i];
                    if (v === null || v === undefined || (seg.length && t - seg[seg.length - 1][2] > gapMs)) {
                        if (seg.length) { segs.push(seg); }
                        seg = [];
                    }
                    if (v !== null && v !== undefined) { seg.push([X(t), Y(v), t]); }
                });
                if (seg.length) { segs.push(seg); }

                segs.forEach(function (sg) {
                    const line = sg.map(function (p, i) { return (i ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1); }).join(' ');
                    if (s.type === 'area' && sg.length > 1) {
                        svg += '<path d="' + line + ' L' + sg[sg.length - 1][0].toFixed(1) + ' ' + Y(ymin).toFixed(1) + ' L' + sg[0][0].toFixed(1) + ' ' + Y(ymin).toFixed(1) +
                            ' Z" style="fill:' + s.color + '" opacity="0.13"/>';
                    }
                    svg += '<path d="' + line + '" fill="none" style="stroke:' + s.color + '" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"/>';
                    if (sg.length === 1) {
                        svg += '<circle cx="' + sg[0][0].toFixed(1) + '" cy="' + sg[0][1].toFixed(1) + '" r="2.6" style="fill:' + s.color + '"/>';
                    }
                });
            });
        }

        svg += '<line class="tele-cross" id="' + id + '_x" y1="' + m.t + '" y2="' + (m.t + ih) + '" x1="-10" x2="-10"/>';
        svg += '<rect class="tele-hit" x="' + m.l + '" y="' + m.t + '" width="' + iw + '" height="' + ih + '"/></svg>';

        el.innerHTML = svg + '<div class="tele-tip" id="' + id + '_tip" hidden></div>';

        const hit = el.querySelector('.tele-hit'), cross = $(id + '_x'), tip = $(id + '_tip');

        hit.addEventListener('mousemove', function (e)
        {
            const r = el.getBoundingClientRect();
            const px = (e.clientX - r.left) / r.width * W;
            const t = x0 + (px - m.l) / iw * (x1 - x0) - (isBar ? bucketMs / 2 : 0);

            let best = 0, bd = Infinity;
            ts.forEach(function (v, i) { const d = Math.abs(v - t); if (d < bd) { bd = d; best = i; } });

            const cx = X(ts[best]) + (isBar ? bucketMs / (x1 - x0) * iw / 2 : 0);
            cross.setAttribute('x1', cx); cross.setAttribute('x2', cx);

            let html = '<div class="tele-tip-time">' + esc(dt(o.t[best])) + '</div>';
            o.series.forEach(function (s) {
                html += '<div class="tele-tip-row"><span class="tele-dot" style="background:' + s.color + '"></span>' + esc(s.name) +
                    '<b>' + esc(s.values[best] === null || s.values[best] === undefined ? '—' : o.fmt(s.values[best])) + '</b></div>';
            });
            tip.innerHTML = html;
            tip.hidden = false;

            const left = (cx / W) * r.width;
            tip.style.left = Math.min(Math.max(left + 14, 6), r.width - tip.offsetWidth - 6) + 'px';
            tip.style.top = '10px';
        });

        hit.addEventListener('mouseleave', function () { tip.hidden = true; cross.setAttribute('x1', -10); cross.setAttribute('x2', -10); });
    }

    function legend(id, items)
    {
        $(id).innerHTML = items.map(function (i) {
            return '<span><span class="tele-dot" style="background:' + i.color + '"></span>' + esc(i.name) + '</span>';
        }).join('');
    }

    /* ---------- Warna seri ---------- */
    const C1 = 'var(--tc1)', C2 = 'var(--tc2)', C3 = 'var(--tc3)', C4 = 'var(--tc4)';

    /* ---------- Render ---------- */

    function stateOf(d)
    {
        const streams = (d.pipeline && d.pipeline.streams) || [];
        if (!streams.length) { return 'offline'; }
        const order = { live: 0, stale: 1, offline: 2 };
        return streams.reduce(function (a, s) { return order[s.state] > order[a] ? s.state : a; }, 'live');
    }

    function renderHead(d)
    {
        const st = stateOf(d);
        $('teleState').dataset.state = st;
        $('teleStateText').textContent = st === 'live' ? 'Live' : st === 'stale' ? 'Delayed' : 'Offline';

        const sel = $('teleAgent');
        if (d.agents && d.agents.length > 1) {
            sel.hidden = false;
            sel.innerHTML = d.agents.map(function (a) {
                return '<option value="' + esc(a.id) + '"' + (a.id === state.agent ? ' selected' : '') + '>' + esc(a.hostname) + '</option>';
            }).join('');
        } else {
            sel.hidden = true;
        }

        document.querySelectorAll('#teleRange button').forEach(function (b) {
            b.classList.toggle('is-active', b.dataset.range === state.range);
        });

        const banner = $('teleBanner');
        if (d.anchored) {
            banner.hidden = false;
            banner.innerHTML = '<b>Showing historical data.</b> The agent has not reported since ' + esc(dt(d.window.end)) +
                ' (' + esc(ago(d.window.end)) + '), so the window is anchored to its last record.';
        } else {
            banner.hidden = true;
        }

        $('teleWindowLabel').textContent = d.window ? dt(d.window.start) + '  →  ' + dt(d.window.end) + ' · ' + (d.range.bucket >= 3600 ? '1h' : d.range.bucket >= 60 ? (d.range.bucket / 60) + 'm' : d.range.bucket + 's') + ' buckets' : '';
    }

    function renderIdentity(d)
    {
        const a = d.agent, s = d.server || {};
        const items = [
            ['Host', a.hostname],
            ['OS', a.os || s.os],
            ['Arch', a.architecture || s.architecture],
            ['Agent', (a.version || '—') + ' · proto ' + (a.protocol || '—')],
            ['Uptime', dur(s.uptime)],
            ['Last Record', s.observed_at ? ago(s.observed_at) : '—'],
            ['Last Control', a.last_control_at ? ago(a.last_control_at) : '—']
        ];
        $('teleIdentity').innerHTML = items.map(function (i) {
            return '<div class="tele-id-item"><span>' + esc(i[0]) + '</span><b title="' + esc(i[1]) + '">' + esc(i[1] || '—') + '</b></div>';
        }).join('');
    }

    function card(opt)
    {
        return '<div class="token-summary-card tele-card" data-lv="' + opt.lv + '">' +
            '<div class="token-summary-top"><div class="token-summary-icon">' + icon(opt.icon) + '</div>' +
            '<div class="token-summary-label">' + esc(opt.label) + '</div></div>' +
            '<div class="token-summary-value">' + opt.value + (opt.chip ? '<span class="token-summary-percent">' + esc(opt.chip) + '</span>' : '') + '</div>' +
            '<div class="token-summary-description">' + esc(opt.desc) + '</div>' +
            (opt.spark || '') +
            '<div class="token-summary-meter"><div class="token-summary-meter-fill" style="width:' + Math.min(100, Math.max(0, opt.meter || 0)) + '%"></div></div></div>';
    }

    function renderSummary(d)
    {
        const s = d.server, p = d.pipeline.totals;
        let html = '';

        if (s) {
            const cpu = s.cpu.usage, mem = s.memory ? s.memory.usage_percent : null;
            const root = (s.disks || [])[0];
            const ld = s.load;

            html += card({ icon: 'cpu', label: 'CPU', lv: level(cpu, 70, 90), value: cpu === null ? '—' : num(cpu, 1) + '<small>%</small>',
                chip: (s.cpu.cores || '?') + ' cores', desc: 'avg ' + num(s.stats.cpu_avg, 1) + '% · peak ' + num(s.stats.cpu_peak, 1) + '%',
                meter: cpu, spark: sparkline(s.series.cpu_avg, C1) });

            html += card({ icon: 'mem', label: 'Memory', lv: level(mem, 80, 90), value: mem === null ? '—' : num(mem, 1) + '<small>%</small>',
                chip: s.memory ? bytes(s.memory.total_bytes, 1) : '', desc: s.memory ? bytes(s.memory.used_bytes, 1) + ' used · ' + bytes(s.memory.available_bytes, 1) + ' free' : '—',
                meter: mem, spark: sparkline(s.series.mem_avg, C2) });

            let diskDesc = root ? bytes(root.available, 1) + ' free' : '—';
            if (s.disk_trend && s.disk_trend.eta_days !== null && s.disk_trend.direction === 'growing') {
                diskDesc += ' · full in ~' + num(s.disk_trend.eta_days, 0) + 'd';
            }
            html += card({ icon: 'disk', label: 'Disk ' + (root ? root.mount : '/'), lv: level(root ? root.usage : null, 75, 90),
                value: root ? num(root.usage, 1) + '<small>%</small>' : '—', chip: root ? bytes(root.total, 1) : '', desc: diskDesc,
                meter: root ? root.usage : 0, spark: sparkline(s.series.disk_pct, C4) });

            html += card({ icon: 'load', label: 'Load 1m', lv: level(ld ? ld.per_core : null, 0.7, 1), value: ld ? num(ld.one_minute, 2) : '—',
                chip: ld && ld.per_core !== null ? num(ld.per_core * 100, 0) + '% / core' : '', desc: ld ? '5m ' + num(ld.five_minutes, 2) + ' · 15m ' + num(ld.fifteen_minutes, 2) : '—',
                meter: ld && ld.per_core !== null ? ld.per_core * 100 : 0, spark: sparkline(s.series.load1, C3) });
        }

        const acc = p.accept_rate;
        html += card({ icon: 'ingest', label: 'Ingest', lv: p.rejected > 0 ? 'crit' : (d.pipeline.open_gaps > 0 ? 'warn' : 'ok'),
            value: num(p.records), chip: acc === null ? '' : num(acc, 1) + '% ok',
            desc: p.batches + ' batches · lag ' + (p.lag_avg_ms === null ? '—' : num(p.lag_avg_ms, 0) + ' ms'),
            meter: acc === null ? 0 : acc, spark: sparkline(d.pipeline.series.accepted, C1) });

        $('teleSummary').innerHTML = html;
    }

    function renderInsights(d)
    {
        const label = { crit: 'Critical', warn: 'Warning', info: 'Info', ok: 'Healthy' };
        $('teleInsights').innerHTML = d.insights.length ? d.insights.map(function (i) {
            return '<div class="tele-insight lv-' + i.level + '"><span class="tele-pill lv-' + i.level + '">' + label[i.level] + '</span>' +
                '<div><div class="tele-insight-title">' + esc(i.title) + '</div><div class="tele-insight-detail">' + esc(i.detail) + '</div></div></div>';
        }).join('') : '<div class="tele-chart-empty">No findings</div>';
    }

    function renderCharts(d)
    {
        const s = d.server;
        const bucket = d.range.bucket;

        if (s) {
            const sr = s.series;

            legend('legCpu', [{ name: 'CPU', color: C1 }, { name: 'Memory', color: C2 }]);
            drawChart('chartCpu', { t: sr.t, bucket: bucket, yMax: 100, fmt: function (v) { return num(v, 1) + '%'; }, axis: function (v) { return num(v, 0) + '%'; },
                thresholds: [{ value: 90, color: 'var(--status-bad)' }],
                series: [{ name: 'CPU avg', color: C1, type: 'area', values: sr.cpu_avg }, { name: 'Memory avg', color: C2, type: 'area', values: sr.mem_avg }] });

            legend('legLoad', [{ name: '1m', color: C1 }, { name: '5m', color: C3 }, { name: '15m', color: C4 }]);
            drawChart('chartLoad', { t: sr.t, bucket: bucket, fmt: function (v) { return num(v, 2); }, axis: function (v) { return num(v, 2); },
                thresholds: s.cpu.cores ? [{ value: s.cpu.cores, color: 'var(--status-bad)' }] : [],
                series: [{ name: 'Load 1m', color: C1, type: 'line', values: sr.load1 }, { name: 'Load 5m', color: C3, type: 'line', values: sr.load5 },
                    { name: 'Load 15m', color: C4, type: 'line', values: sr.load15 }] });

            legend('legNet', [{ name: 'RX', color: C3 }, { name: 'TX', color: C2 }]);
            drawChart('chartNet', { t: sr.t, bucket: bucket, fmt: rate, axis: function (v) { return bytes(v, 0); },
                series: [{ name: 'Receive', color: C3, type: 'area', values: sr.rx_rate }, { name: 'Transmit', color: C2, type: 'area', values: sr.tx_rate }] });

            const dmin = Math.max(0, Math.floor(Math.min.apply(null, sr.disk_pct.filter(function (v) { return v !== null; })) - 2));
            legend('legDisk', [{ name: '/ usage', color: C4 }]);
            drawChart('chartDisk', { t: sr.t, bucket: bucket, yMin: isFinite(dmin) ? dmin : 0, yMax: 100, fmt: function (v) { return num(v, 2) + '%'; }, axis: function (v) { return num(v, 0) + '%'; },
                thresholds: [{ value: 90, color: 'var(--status-bad)' }, { value: 75, color: 'var(--status-warn)' }],
                series: [{ name: 'Root usage', color: C4, type: 'area', values: sr.disk_pct }] });
        } else {
            ['chartCpu', 'chartLoad', 'chartNet', 'chartDisk'].forEach(function (id) { $(id).innerHTML = '<div class="tele-chart-empty">No server_metrics stream</div>'; });
        }

        const ps = d.pipeline.series;
        legend('legIngest', [{ name: 'Accepted', color: C1 }, { name: 'Duplicate', color: C4 }, { name: 'Rejected', color: 'var(--status-bad)' }]);
        drawChart('chartIngest', { t: ps.t, bucket: bucket, stacked: true, fmt: function (v) { return num(v, 0); }, axis: function (v) { return num(v, 0); },
            series: [{ name: 'Accepted', color: C1, type: 'bar', values: ps.accepted }, { name: 'Duplicate', color: C4, type: 'bar', values: ps.duplicates },
                { name: 'Rejected', color: 'var(--status-bad)', type: 'bar', values: ps.rejected }] });

        legend('legLag', [{ name: 'avg lag', color: C3 }]);
        drawChart('chartLag', { t: ps.t, bucket: bucket, fmt: function (v) { return num(v, 0) + ' ms'; }, axis: function (v) { return num(v, 0); },
            series: [{ name: 'Lag', color: C3, type: 'area', values: ps.lag_ms }] });
    }

    function renderDisks(d)
    {
        const s = d.server;
        if (!s || !s.disks.length) { $('teleDisks').innerHTML = '<div class="tele-chart-empty">No disk data</div>'; return; }

        $('teleDisks').innerHTML = s.disks.map(function (k) {
            const lv = level(k.usage, 75, 90);
            return '<div class="tele-disk"><div class="tele-disk-top"><div><div class="token-primary">' + esc(k.mount) + '</div>' +
                '<div class="token-secondary">' + esc(k.filesystem) + (k.aliases.length ? ' · also ' + esc(k.aliases.join(', ')) : '') + '</div></div>' +
                '<span class="tele-pill lv-' + lv + '">' + num(k.usage, 1) + '%</span></div>' +
                '<div class="tele-bar"><div class="tele-bar-fill lv-' + lv + '" style="width:' + Math.min(100, k.usage) + '%"></div></div>' +
                '<div class="tele-disk-meta"><span>' + bytes(k.used, 1) + ' used</span><span>' + bytes(k.available, 1) + ' free</span><span>' + bytes(k.total, 1) + ' total</span></div></div>';
        }).join('') + (s.disk_trend ? '<div class="tele-note">Trend: ' + (s.disk_trend.direction === 'growing' ? '▲ +' : s.disk_trend.direction === 'shrinking' ? '▼ ' : '● ') +
            bytes(s.disk_trend.bytes_per_day) + '/day' + (s.disk_trend.eta_days !== null && s.disk_trend.direction === 'growing' ? ' · projected full in ~' + num(s.disk_trend.eta_days, 0) + ' days' : '') + '</div>' : '');
    }

    function renderNet(d)
    {
        const n = d.server && d.server.network, st = d.server && d.server.stats;
        if (!n) { $('teleNetStats').innerHTML = '<div class="tele-chart-empty">No network data</div>'; return; }

        const tile = function (label, value, sub, lv) {
            return '<div class="tele-stat' + (lv ? ' lv-' + lv : '') + '"><span>' + esc(label) + '</span><b>' + value + '</b><em>' + esc(sub || '') + '</em></div>';
        };

        $('teleNetStats').innerHTML =
            '<div class="tele-netname"><span class="token-environment">' + esc(n.interface) + '</span></div>' +
            '<div class="tele-stat-grid">' +
            tile('Received', bytes(n.receive_bytes, 1), num(n.receive_packets) + ' packets') +
            tile('Transmitted', bytes(n.transmit_bytes, 1), num(n.transmit_packets) + ' packets') +
            tile('RX Dropped', num(n.receive_dropped), num(n.drop_ratio, 2) + '% of RX' + (n.dropped_in_window !== null ? ' · +' + num(n.dropped_in_window) + ' in window' : ''), n.drop_ratio >= 1 ? 'warn' : 'ok') +
            tile('Errors', num((n.receive_errors || 0) + (n.transmit_errors || 0)), 'RX ' + num(n.receive_errors) + ' · TX ' + num(n.transmit_errors), (n.receive_errors || n.transmit_errors) ? 'crit' : 'ok') +
            tile('Avg Throughput', rate(st.rx_avg), 'TX ' + rate(st.tx_avg)) +
            tile('Peak Throughput', rate(st.rx_peak), 'TX ' + rate(st.tx_peak)) +
            '</div>';
    }

    function probeLv(status)
    {
        return ['available', 'ok', 'healthy', 'up'].indexOf(status) >= 0 ? 'ok' : status === 'unsupported' ? 'info' : status === 'unknown' ? 'idle' : 'warn';
    }

    function renderApps(d)
    {
        if (!d.applications.length) { $('teleApps').innerHTML = '<div class="tele-chart-empty">No application stream registered for this agent</div>'; return; }

        $('teleApps').innerHTML = d.applications.map(function (a, idx) {
            const rows = Object.keys(a.probes).map(function (k) {
                const p = a.probes[k], lv = probeLv(p.status);
                return '<tr><td><span class="token-primary">' + esc(k) + '</span></td>' +
                    '<td><span class="tele-pill lv-' + lv + '">' + esc(p.status) + '</span></td>' +
                    '<td><span class="token-date">' + (p.http_status || '—') + '</span></td>' +
                    '<td><span class="token-secondary">' + esc(p.error_code || '—') + '</span></td>' +
                    '<td><span class="token-date">' + (p.latency_ms === null ? '—' : num(p.latency_ms, 1) + ' ms') + '</span>' +
                    '<div class="token-secondary">avg ' + (p.latency_avg === null ? '—' : num(p.latency_avg, 1)) + ' · max ' + (p.latency_max === null ? '—' : num(p.latency_max, 1)) + '</div></td>' +
                    '<td><div class="tele-avail"><div class="tele-bar"><div class="tele-bar-fill lv-' + (p.availability === null ? 'idle' : level(100 - p.availability, 1, 10)) + '" style="width:' + (p.availability || 0) + '%"></div></div>' +
                    '<span class="token-date">' + (p.availability === null ? '—' : num(p.availability, 1) + '%') + '</span></div></td></tr>';
            }).join('');

            return '<div class="tele-app">' +
                '<div class="tele-app-head"><div class="token-main"><div class="token-main-icon">' + icon('load') + '</div><div>' +
                '<div class="token-primary">' + esc(a.name || 'Unnamed application') + '</div>' +
                '<div class="token-secondary">' + esc(a.key || '') + ' · ' + esc(a.framework || '') + '</div></div></div>' +
                '<div class="tele-app-tags"><span class="token-environment">' + esc(a.environment || '—') + '</span>' +
                '<span class="tele-pill lv-' + (a.monitoring_status === 'active' ? 'ok' : 'warn') + '">monitoring ' + esc(a.monitoring_status || '—') + '</span>' +
                '<span class="token-secondary">last probe ' + esc(ago(a.observed_at)) + ' · ' + num(a.samples) + ' samples</span></div></div>' +
                '<div class="token-table-wrapper"><table class="token-table"><thead><tr><th>Probe</th><th>Status</th><th>HTTP</th><th>Error Code</th><th>Latency</th><th>Availability</th></tr></thead><tbody>' + rows + '</tbody></table></div>' +
                '<div class="tele-chart-card tele-app-chart"><div class="tele-chart-head"><div class="tele-chart-title">Probe Latency</div><div class="tele-legend" id="legProbe' + idx + '"></div></div><div class="tele-chart" id="chartProbe' + idx + '"></div></div>' +
                '</div>';
        }).join('');

        d.applications.forEach(function (a, idx) {
            const colors = { health: C1, info: C3, database: C2, metrics: C4, queue: 'var(--muted)', scheduler: 'var(--status-ok)' };
            const series = Object.keys(colors).filter(function (k) { return a.series[k].some(function (v) { return v !== null; }); })
                .map(function (k) { return { name: k, color: colors[k], type: 'line', values: a.series[k] }; });

            legend('legProbe' + idx, series.map(function (s) { return { name: s.name, color: s.color }; }));

            if (!series.length) { $('chartProbe' + idx).innerHTML = '<div class="tele-chart-empty">No latency data (probes returned no response time)</div>'; return; }
            drawChart('chartProbe' + idx, { t: a.series.t, bucket: d.range.bucket, fmt: function (v) { return num(v, 1) + ' ms'; }, axis: function (v) { return num(v, 0); }, series: series, height: 190 });
        });
    }

    function renderPipeline(d)
    {
        const p = d.pipeline, t = p.totals;
        const kpi = function (label, value, sub, lv) {
            return '<div class="tele-stat' + (lv ? ' lv-' + lv : '') + '"><span>' + esc(label) + '</span><b>' + value + '</b><em>' + esc(sub || '') + '</em></div>';
        };

        $('telePipeKpis').innerHTML =
            kpi('Batches', num(t.batches), 'in window') +
            kpi('Records', num(t.records), num(t.accepted) + ' accepted', 'ok') +
            kpi('Accept Rate', t.accept_rate === null ? '—' : num(t.accept_rate, 2) + '%', 'accepted / total', t.accept_rate !== null && t.accept_rate < 99 ? 'warn' : 'ok') +
            kpi('Duplicates', num(t.duplicates), 'retries', t.duplicates ? 'info' : '') +
            kpi('Rejected', num(t.rejected), 'validation failures', t.rejected ? 'crit' : 'ok') +
            kpi('Avg Lag', t.lag_avg_ms === null ? '—' : num(t.lag_avg_ms, 0) + ' ms', 'max ' + (t.lag_max_ms === null ? '—' : num(t.lag_max_ms, 0) + ' ms'), t.lag_avg_ms > 5000 ? 'warn' : 'ok') +
            kpi('Open Gaps', num(p.open_gaps), 'unresolved', p.open_gaps ? 'warn' : 'ok');

        const stl = { live: 'ok', stale: 'warn', offline: 'crit' };
        $('teleStreams').innerHTML = '<thead><tr><th>Stream</th><th>State</th><th>Sequence</th><th>Missing</th><th>Interval</th><th>In Window</th><th>Last Received</th></tr></thead><tbody>' +
            (p.streams.length ? p.streams.map(function (s) {
                return '<tr><td><div class="token-primary">' + esc(s.name) + '</div><div class="token-secondary">' + esc(s.application || s.source) + ' · ' + esc(s.source) + '</div></td>' +
                    '<td><span class="tele-pill lv-' + stl[s.state] + '">' + esc(s.state) + '</span></td>' +
                    '<td><span class="token-date">' + num(s.last_contiguous) + '</span><div class="token-secondary">max ' + num(s.max_seen) + '</div></td>' +
                    '<td><span class="tele-pill lv-' + (s.missing ? 'warn' : 'ok') + '">' + num(s.missing) + '</span></td>' +
                    '<td><span class="token-date">' + (s.interval_seconds === null ? '—' : num(s.interval_seconds, 1) + 's') + '</span></td>' +
                    '<td><span class="token-date">' + num(s.records_in_window) + '</span></td>' +
                    '<td><span class="token-date">' + esc(ago(s.last_received_at)) + '</span><div class="token-secondary">' + esc(dt(s.last_received_at)) + '</div></td></tr>';
            }).join('') : '<tr><td colspan="7"><div class="tele-chart-empty">No streams</div></td></tr>') + '</tbody>';

        let html = '';

        if (d.collectors.length) {
            html += d.collectors.map(function (c) {
                const lv = c.status === 'healthy' || c.status === 'available' || c.status === 'ok' ? 'ok' : c.status === 'unknown' ? 'idle' : 'warn';
                return '<div class="tele-collector"><div><div class="token-primary">' + esc(c.name) + '</div><div class="token-secondary">' + esc(c.application || 'server') +
                    (c.error_code ? ' · ' + esc(c.error_code) : '') + '</div></div><span class="tele-pill lv-' + lv + '">' + esc(c.status) + '</span></div>';
            }).join('');
        }

        const ng = d.server && d.server.nginx;
        if (ng) {
            const lv = ng.service === 'running' ? (ng.collector === 'unavailable' ? 'info' : 'ok') : 'warn';
            html += '<div class="tele-collector"><div><div class="token-primary">nginx</div><div class="token-secondary">service ' + esc(ng.service || '—') + ' · collector ' + esc(ng.collector || '—') +
                (ng.error ? ' · ' + esc(ng.error) : '') + '</div></div><span class="tele-pill lv-' + lv + '">' + esc(ng.service || 'unknown') + '</span></div>';
        }

        html += p.gaps.length ? p.gaps.map(function (g) {
            return '<div class="tele-collector"><div><div class="token-primary">Gap #' + esc(g.from) + ' → ' + esc(g.to) + '</div><div class="token-secondary">' + esc(dt(g.started_at)) + (g.description ? ' · ' + esc(g.description) : '') +
                '</div></div><span class="tele-pill lv-' + (g.status === 'open' ? 'warn' : 'ok') + '">' + esc(g.status) + '</span></div>';
        }).join('') : '<div class="tele-collector tele-collector-ok"><div><div class="token-primary">No sequence gaps</div><div class="token-secondary">All sequences arrived in order.</div></div><span class="tele-pill lv-ok">clean</span></div>';

        $('teleCollectors').innerHTML = html;
    }

    function renderRecent(d)
    {
        $('teleRecent').innerHTML = '<thead><tr><th>Stream</th><th>Sequence</th><th>Observed</th><th>Received</th><th>Size</th></tr></thead><tbody>' +
            (d.recent.length ? d.recent.map(function (r) {
                return '<tr class="tele-record-row" data-id="' + esc(r.id) + '"><td><span class="token-environment">' + esc(r.stream) + '</span></td>' +
                    '<td><span class="token-date">#' + num(r.sequence) + '</span></td>' +
                    '<td><span class="token-date">' + esc(dt(r.observed_at)) + '</span></td>' +
                    '<td><span class="token-date">' + esc(ago(r.received_at)) + '</span></td>' +
                    '<td><span class="token-secondary">' + bytes(r.size, 1) + '</span></td></tr>';
            }).join('') : '<tr><td colspan="5"><div class="tele-chart-empty">No records</div></td></tr>') + '</tbody>';
    }

    function renderAll()
    {
        if (data.empty) {
            $('teleSummary').innerHTML = '';
            $('teleIdentity').innerHTML = '';
            $('teleInsights').innerHTML = '<div class="tele-chart-empty">No installed agent found. Enroll an agent first.</div>';
            return;
        }

        renderHead(data);
        renderIdentity(data);
        renderSummary(data);
        renderInsights(data);
        renderCharts(data);
        renderDisks(data);
        renderNet(data);
        renderApps(data);
        renderPipeline(data);
        renderRecent(data);
    }

    /* ---------- Load data ---------- */

    async function load(silent)
    {
        if (state.controller) { state.controller.abort(); }
        const controller = new AbortController();
        state.controller = controller;

        const url = new URL(DATA_URL, window.location.origin);
        url.searchParams.set('range', state.range);
        if (state.agent) { url.searchParams.set('agent', state.agent); }

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
            if (data.agent) { state.agent = data.agent.id; }
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

    $('teleAgent').addEventListener('change', function () { state.agent = this.value; load(false); });
    $('teleRefresh').addEventListener('click', function () { load(false); });

    $('teleAuto').addEventListener('click', function () {
        state.auto = !state.auto;
        this.classList.toggle('is-on', state.auto);
        this.setAttribute('aria-pressed', state.auto ? 'true' : 'false');
        schedule();
    });

    $('teleRecent').addEventListener('click', async function (e) {
        const row = e.target.closest('.tele-record-row');
        if (!row) { return; }

        const next = row.nextElementSibling;
        if (next && next.classList.contains('tele-record-detail')) { next.remove(); row.classList.remove('is-open'); return; }

        row.classList.add('is-open');
        const tr = document.createElement('tr');
        tr.className = 'tele-record-detail';
        tr.innerHTML = '<td colspan="5"><pre>Loading…</pre></td>';
        row.after(tr);

        try {
            const res = await fetch(RECORD_URL.replace('__ID__', encodeURIComponent(row.dataset.id)), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
            if (!res.ok) { throw new Error('HTTP ' + res.status); }
            const rec = await res.json();
            tr.querySelector('pre').textContent = JSON.stringify(rec.data, null, 2);
        } catch (err) {
            tr.querySelector('pre').textContent = 'Unable to load record: ' + err.message;
        }
    });

    let resizeTimer = null;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            Object.keys(charts).forEach(function (id) { if ($(id)) { drawChart(id, charts[id]); } });
        }, 150);
    });

    document.addEventListener('visibilitychange', function () { if (!document.hidden && state.auto) { load(true); } });

    renderAll();
    schedule();

    // Perbarui label "x ago" tanpa request.
    setInterval(function () { if (!data.empty && !document.hidden) { renderIdentity(data); } }, 5000);
});
</script>

@endsection

@push('styles')
@include('management.partials.telemetry-styles')
<style>
    .tele-back { display: inline-block; margin-bottom: 8px; color: var(--accent); font: 600 12px var(--mono); text-decoration: none; }
    .tele-back:hover { text-decoration: underline; }
</style>
@endpush