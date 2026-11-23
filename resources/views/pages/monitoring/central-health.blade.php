@extends('layouts.app')

@section('title', 'Central Health | NADI')

@section('content')

<div class="ch-page" id="chRoot">

    <header class="ch-header">
        <div class="ch-header-main">
            <div class="ch-score" id="chScore" data-lv="healthy">
                <svg viewBox="0 0 72 72" aria-hidden="true">
                    <circle class="ch-score-track" cx="36" cy="36" r="30"/>
                    <circle class="ch-score-fill" id="chScoreFill" cx="36" cy="36" r="30" stroke-dasharray="188.5" stroke-dashoffset="188.5"/>
                </svg>
                <b id="chScoreValue">--</b>
            </div>

            <div>
                <h1 class="ch-title">
                    Central Health
                    <span class="ch-state" id="chState" data-lv="healthy"><span class="ch-state-dot"></span><span id="chStateText">Loading</span></span>
                </h1>
                <p class="ch-sub" id="chHostLine">Live health snapshot for the NADI Central host.</p>
            </div>
        </div>

        <div class="ch-controls">
            <span class="ch-sampled" id="chSampled">—</span>

            <select id="chInterval" aria-label="Refresh interval">
                <option value="3000">3s</option>
                <option value="5000" selected>5s</option>
                <option value="10000">10s</option>
                <option value="30000">30s</option>
            </select>

            <button type="button" class="ch-toggle is-on" id="chAuto" aria-pressed="true"><span class="ch-toggle-dot"></span> Live</button>

            <button type="button" class="ch-btn" id="chRefresh">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 4v7h-7"/></svg>
                Refresh
            </button>
        </div>
    </header>

    <div class="ch-error" id="chError" role="alert" hidden></div>

    <div class="ch-identity" id="chIdentity"></div>

    <section class="ch-kpis" id="chKpis"></section>

    <section class="ch-panel">
        <div class="ch-panel-head">
            <h2>Findings <span class="ch-tag" id="chFindCount">0</span></h2>
            <p>Automatic analysis of the current snapshot. Critical items first.</p>
        </div>
        <div class="ch-findings" id="chFindings"></div>
    </section>

    <section class="ch-panel">
        <div class="ch-panel-head">
            <h2>Live Telemetry <span class="ch-tag" id="chHistLabel">collecting…</span></h2>
            <p>Rolling window kept in this browser. Rates are computed between refreshes; nothing is stored on the server.</p>
        </div>
        <div class="ch-grid-2 ch-pad">
            <div class="ch-chart-card"><div class="ch-chart-head"><span>CPU &amp; I/O wait</span><div class="ch-legend" id="legCpu"></div></div><div class="ch-chart" id="chartCpu"></div></div>
            <div class="ch-chart-card"><div class="ch-chart-head"><span>Memory &amp; swap</span><div class="ch-legend" id="legMem"></div></div><div class="ch-chart" id="chartMem"></div></div>
            <div class="ch-chart-card"><div class="ch-chart-head"><span>Network throughput</span><div class="ch-legend" id="legNet"></div></div><div class="ch-chart" id="chartNet"></div></div>
            <div class="ch-chart-card"><div class="ch-chart-head"><span>Disk I/O</span><div class="ch-legend" id="legDisk"></div></div><div class="ch-chart" id="chartDisk"></div></div>
        </div>
    </section>

    <div class="ch-grid-2">
        <section class="ch-panel">
            <div class="ch-panel-head"><h2>CPU <span class="ch-tag" id="chCpuTag"></span></h2><p id="chCpuModel">—</p></div>
            <div class="ch-pad" id="chCpu"></div>
        </section>

        <section class="ch-panel">
            <div class="ch-panel-head"><h2>Memory</h2><p>Breakdown of physical RAM and swap.</p></div>
            <div class="ch-pad" id="chMemory"></div>
        </section>
    </div>

    <section class="ch-panel">
        <div class="ch-panel-head"><h2>Storage <span class="ch-tag" id="chStorageTag"></span></h2><p>Mounted filesystems, inode usage, and block-device activity.</p></div>
        <div class="ch-mounts" id="chMounts"></div>
        <div class="ch-table-wrap"><table class="ch-table" id="chDevices"></table></div>
    </section>

    <section class="ch-panel">
        <div class="ch-panel-head"><h2>Network <span class="ch-tag" id="chNetTag"></span></h2><p>Interfaces, TCP connection states, and listening ports.</p></div>
        <div class="ch-table-wrap"><table class="ch-table" id="chIfaces"></table></div>
        <div class="ch-grid-2 ch-pad">
            <div><div class="ch-subhead">TCP connection states</div><div class="ch-tiles" id="chTcp"></div></div>
            <div><div class="ch-subhead">Listening ports</div><div class="ch-ports" id="chPorts"></div></div>
        </div>
    </section>

    <section class="ch-panel">
        <div class="ch-panel-head"><h2>Services <span class="ch-tag" id="chSvcTag"></span></h2><p>systemd units (read-only). Optional services appear only when installed.</p></div>
        <div class="ch-services" id="chServices"></div>
    </section>

    <section class="ch-panel">
        <div class="ch-panel-head"><h2>Processes <span class="ch-tag" id="chProcTag"></span></h2><p>Grouped by command name. CPU measured over the sampling window.</p></div>
        <div class="ch-tiles ch-pad" id="chProcTiles"></div>
        <div class="ch-grid-2 ch-pad ch-flush-top">
            <div><div class="ch-subhead">Top memory</div><div class="ch-table-wrap"><table class="ch-table" id="chTopMem"></table></div></div>
            <div><div class="ch-subhead">Top CPU</div><div class="ch-table-wrap"><table class="ch-table" id="chTopCpu"></table></div></div>
        </div>
    </section>

    <section class="ch-panel">
        <div class="ch-panel-head"><h2>Database <span class="ch-tag" id="chDbTag"></span></h2><p>Connectivity and engine statistics for the central database.</p></div>
        <div class="ch-pad" id="chDb"></div>
    </section>

    <div class="ch-grid-2">
        <section class="ch-panel">
            <div class="ch-panel-head"><h2>PHP Runtime</h2><p>Interpreter limits and OPcache.</p></div>
            <div class="ch-pad" id="chPhp"></div>
        </section>

        <section class="ch-panel">
            <div class="ch-panel-head"><h2>Laravel Application</h2><p>Environment, optimisation, queue, and storage access.</p></div>
            <div class="ch-pad" id="chLaravel"></div>
        </section>
    </div>

    <section class="ch-panel">
        <div class="ch-panel-head"><h2>Logs &amp; System Housekeeping</h2><p>Recent application errors, pending updates, reboot state, and clock sync.</p></div>
        <div class="ch-grid-2 ch-pad">
            <div id="chLogs"></div>
            <div id="chSystem"></div>
        </div>
    </section>

    <div class="ch-note">
        Read-only dashboard: it reads <code>/proc</code>, <code>/sys</code>, <code>systemctl show</code>, <code>df</code>, and SELECT queries. No service is restarted and no configuration is changed.
        Missing permissions or unsupported checks appear as N/A. History shown in the charts lives only in this browser tab.
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function ()
{
    const DATA_URL = @json(route('monitoring.central-health'));
    const HISTORY_MAX = 90;

    let snap = @json($snapshot);
    let prev = null;
    let derived = null;

    const S = { auto: true, interval: 5000, timer: null, controller: null, loading: false };
    const hist = { t: [], cpu: [], iow: [], mem: [], swap: [], load: [], rx: [], tx: [], rd: [], wr: [], dbl: [] };
    const charts = {};

    const $ = function (id) { return document.getElementById(id); };
    const C1 = 'var(--c1)', C2 = 'var(--c2)', C3 = 'var(--c3)', C4 = 'var(--c4)', BAD = 'var(--bad)';

    /* ---------- Format ---------- */

    function esc(v)
    {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function num(v, d)
    {
        if (v === null || v === undefined || isNaN(v)) { return 'N/A'; }
        return Number(v).toLocaleString('en-US', { minimumFractionDigits: d || 0, maximumFractionDigits: d || 0 });
    }

    function bytes(v, d)
    {
        if (v === null || v === undefined || isNaN(v)) { return 'N/A'; }
        const u = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        let i = 0, n = Math.abs(Number(v));
        while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
        return n.toFixed(d === undefined ? (n >= 100 || i === 0 ? 0 : n >= 10 ? 1 : 2) : d) + ' ' + u[i];
    }

    function rate(v) { return v === null || v === undefined ? 'N/A' : bytes(v) + '/s'; }

    function bits(v)
    {
        if (v === null || v === undefined) { return 'N/A'; }
        const u = ['bps', 'Kbps', 'Mbps', 'Gbps'];
        let i = 0, n = v * 8;
        while (n >= 1000 && i < u.length - 1) { n /= 1000; i++; }
        return n.toFixed(n >= 100 ? 0 : 1) + ' ' + u[i];
    }

    function dur(sec)
    {
        if (sec === null || sec === undefined) { return 'N/A'; }
        sec = Math.max(0, Math.floor(sec));
        const d = Math.floor(sec / 86400), h = Math.floor(sec % 86400 / 3600), m = Math.floor(sec % 3600 / 60), s = sec % 60;
        if (d) { return d + 'd ' + h + 'h ' + m + 'm'; }
        if (h) { return h + 'h ' + m + 'm'; }
        if (m) { return m + 'm ' + s + 's'; }
        return s + 's';
    }

    function dtime(iso)
    {
        if (!iso) { return 'N/A'; }
        const d = new Date(iso);
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + d.toLocaleTimeString('en-GB', { hour12: false });
    }

    function lv(v, warn, crit) { return v === null || v === undefined ? 'idle' : v >= crit ? 'bad' : v >= warn ? 'warn' : 'ok'; }

    const LV = { healthy: 'ok', warning: 'warn', critical: 'bad', unknown: 'idle', info: 'info' };

    function pill(level, text) { return '<span class="ch-pill lv-' + level + '">' + esc(text) + '</span>'; }

    function bar(pct, level, h)
    {
        return '<div class="ch-bar' + (h ? ' ch-bar-' + h : '') + '"><div class="ch-bar-fill lv-' + level + '" style="width:' + Math.min(100, Math.max(0, pct || 0)) + '%"></div></div>';
    }

    function tile(label, value, sub, level)
    {
        return '<div class="ch-tile' + (level ? ' lv-' + level : '') + '"><span>' + esc(label) + '</span><b>' + value + '</b><em>' + esc(sub || '') + '</em></div>';
    }

    function row(label, value) { return '<div class="ch-row"><span>' + esc(label) + '</span><b>' + value + '</b></div>'; }

    function yn(v, good) { return pill(v === good ? 'ok' : 'warn', v ? 'Yes' : 'No'); }

    function icon(name)
    {
        const p = {
            cpu: '<rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v3M15 2v3M9 19v3M15 19v3M2 9h3M2 15h3M19 9h3M19 15h3"/>',
            load: '<path d="M3 17l5-6 4 4 4-8 5 6"/><path d="M3 21h18"/>',
            mem: '<rect x="3" y="7" width="18" height="10" rx="2"/><path d="M7 11v2M11 11v2M15 11v2M7 17v3M12 17v3M17 17v3"/>',
            disk: '<ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6"/><path d="M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>',
            net: '<path d="M5 12a10 10 0 0 1 14 0M8.5 15.5a5 5 0 0 1 7 0"/><path d="M12 19h.01"/>',
            db: '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>'
        };
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + (p[name] || '') + '</svg>';
    }

    /* ---------- Derived rates ---------- */

    function derive(d, p)
    {
        const dt = p ? (d.sampled_ms - p.sampled_ms) / 1000 : 0;
        const useDelta = p && dt >= 0.5;
        const r = { dt: dt, net: { rx: d.network.rx_bps, tx: d.network.tx_bps, per: {} }, dio: { read: 0, write: 0, riops: 0, wiops: 0 }, qps: null,
            cpu: { usage: d.cpu.usage, user: d.cpu.user, system: d.cpu.system, iowait: d.cpu.iowait, steal: d.cpu.steal } };

        d.network.interfaces.forEach(function (i) { r.net.per[i.name] = { rx: i.rx_bps, tx: i.tx_bps }; });
        d.storage.devices.forEach(function (x) { r.dio.read += x.read_bps; r.dio.write += x.write_bps; r.dio.riops += x.read_iops; r.dio.wiops += x.write_iops; });

        if (!useDelta) { return r; }

        const a = p.cpu.jiffies, b = d.cpu.jiffies, T = b.total - a.total;
        if (T > 0) {
            const pc = function (x) { return Math.max(0, Math.min(100, x / T * 100)); };
            r.cpu = { usage: pc(T - (b.idle - a.idle)), user: pc(b.user - a.user), system: pc(b.system - a.system), iowait: pc(b.iowait - a.iowait), steal: pc(b.steal - a.steal) };
        }

        const pi = {};
        p.network.interfaces.forEach(function (i) { pi[i.name] = i; });
        let rx = 0, tx = 0;
        d.network.interfaces.forEach(function (i) {
            const o = pi[i.name];
            if (!o) { return; }
            const rr = Math.max(0, i.rx_bytes - o.rx_bytes) / dt, tt = Math.max(0, i.tx_bytes - o.tx_bytes) / dt;
            r.net.per[i.name] = { rx: rr, tx: tt };
            rx += rr; tx += tt;
        });
        r.net.rx = rx; r.net.tx = tx;

        const ca = p.storage.io_counters, cb = d.storage.io_counters;
        r.dio = { read: Math.max(0, cb.read_bytes - ca.read_bytes) / dt, write: Math.max(0, cb.write_bytes - ca.write_bytes) / dt,
            riops: Math.max(0, cb.read_ops - ca.read_ops) / dt, wiops: Math.max(0, cb.write_ops - ca.write_ops) / dt };

        if (d.database.stats && p.database.stats) { r.qps = Math.max(0, d.database.stats.questions - p.database.stats.questions) / dt; }

        return r;
    }

    function pushHistory(d, r)
    {
        const add = function (k, v) { hist[k].push(v); if (hist[k].length > HISTORY_MAX) { hist[k].shift(); } };
        add('t', d.sampled_ms);
        add('cpu', r.cpu.usage); add('iow', r.cpu.iowait);
        add('mem', d.memory.usage_percent); add('swap', d.memory.swap_percent === null ? 0 : d.memory.swap_percent);
        add('load', d.load.one);
        add('rx', r.net.rx); add('tx', r.net.tx);
        add('rd', r.dio.read); add('wr', r.dio.write);
        add('dbl', d.database.latency_ms);
    }

    /* ---------- Charts ---------- */

    function niceMax(v)
    {
        if (!isFinite(v) || v <= 0) { return 1; }
        const e = Math.pow(10, Math.floor(Math.log10(v))), f = v / e;
        return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 5 ? 5 : 10) * e;
    }

    function spark(values, color)
    {
        const v = values.filter(function (x) { return x !== null && x !== undefined; });
        if (v.length < 2) { return '<div class="ch-spark ch-spark-empty">collecting…</div>'; }
        const W = 100, H = 30, mn = Math.min.apply(null, v), mx = Math.max.apply(null, v), sp = (mx - mn) || 1, pts = [];
        values.forEach(function (y, i) { if (y === null || y === undefined) { return; } pts.push((i / (values.length - 1) * W).toFixed(1) + ',' + (H - 3 - (y - mn) / sp * (H - 6)).toFixed(1)); });
        return '<svg class="ch-spark" viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="none" aria-hidden="true">' +
            '<polygon points="0,' + H + ' ' + pts.join(' ') + ' ' + W + ',' + H + '" style="fill:' + color + '" opacity="0.12"/>' +
            '<polyline points="' + pts.join(' ') + '" fill="none" style="stroke:' + color + '" stroke-width="1.6" vector-effect="non-scaling-stroke" stroke-linejoin="round"/></svg>';
    }

    function legend(id, items)
    {
        $(id).innerHTML = items.map(function (i) { return '<span><span class="ch-dot" style="background:' + i.color + '"></span>' + esc(i.name) + '</span>'; }).join('');
    }

    function drawChart(id, o)
    {
        const el = $(id);
        charts[id] = o;

        if (hist.t.length < 2) { el.innerHTML = '<div class="ch-empty">Collecting samples… (needs 2 refreshes)</div>'; return; }

        const W = Math.max(el.clientWidth, 260), H = 190, m = { l: 54, r: 12, t: 10, b: 24 };
        const iw = W - m.l - m.r, ih = H - m.t - m.b, ts = hist.t;
        const x0 = ts[0], x1 = ts[ts.length - 1];

        let ymax = o.yMax;
        if (ymax === undefined || ymax === null) {
            let mx = 0;
            o.series.forEach(function (s) { s.values.forEach(function (v) { if (v !== null && v !== undefined) { mx = Math.max(mx, v); } }); });
            ymax = niceMax(mx * 1.1);
        }

        const X = function (t) { return m.l + (t - x0) / (x1 - x0 || 1) * iw; };
        const Y = function (v) { return m.t + ih - Math.min(Math.max(v, 0), ymax) / ymax * ih; };

        let svg = '<svg viewBox="0 0 ' + W + ' ' + H + '" width="' + W + '" height="' + H + '" role="img">';

        for (let i = 0; i <= 4; i++) {
            const y = Y(ymax * i / 4);
            svg += '<line class="ch-gl" x1="' + m.l + '" x2="' + (W - m.r) + '" y1="' + y.toFixed(1) + '" y2="' + y.toFixed(1) + '"/>' +
                '<text class="ch-ax" x="' + (m.l - 8) + '" y="' + (y + 3.5).toFixed(1) + '" text-anchor="end">' + esc(o.axis(ymax * i / 4)) + '</text>';
        }

        for (let i = 0; i <= 4; i++) {
            const t = x0 + (x1 - x0) * i / 4;
            svg += '<text class="ch-ax" x="' + X(t).toFixed(1) + '" y="' + (H - 8) + '" text-anchor="' + (i === 0 ? 'start' : i === 4 ? 'end' : 'middle') + '">' +
                new Date(t).toLocaleTimeString('en-GB', { hour12: false, minute: '2-digit', second: '2-digit' }) + '</text>';
        }

        (o.thresholds || []).forEach(function (th) {
            if (th.value <= ymax) { svg += '<line class="ch-th" style="stroke:' + th.color + '" x1="' + m.l + '" x2="' + (W - m.r) + '" y1="' + Y(th.value).toFixed(1) + '" y2="' + Y(th.value).toFixed(1) + '"/>'; }
        });

        o.series.forEach(function (s) {
            const pts = [];
            ts.forEach(function (t, i) { const v = s.values[i]; if (v !== null && v !== undefined) { pts.push([X(t), Y(v)]); } });
            if (!pts.length) { return; }
            const line = pts.map(function (p, i) { return (i ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1); }).join(' ');
            if (s.area) {
                svg += '<path d="' + line + ' L' + pts[pts.length - 1][0].toFixed(1) + ' ' + Y(0).toFixed(1) + ' L' + pts[0][0].toFixed(1) + ' ' + Y(0).toFixed(1) + ' Z" style="fill:' + s.color + '" opacity="0.13"/>';
            }
            svg += '<path d="' + line + '" fill="none" style="stroke:' + s.color + '" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"/>';
        });

        svg += '<line class="ch-cross" id="' + id + '_x" y1="' + m.t + '" y2="' + (m.t + ih) + '" x1="-10" x2="-10"/>' +
            '<rect class="ch-hit" x="' + m.l + '" y="' + m.t + '" width="' + iw + '" height="' + ih + '"/></svg>';

        el.innerHTML = svg + '<div class="ch-tip" id="' + id + '_tip" hidden></div>';

        const hit = el.querySelector('.ch-hit'), cross = $(id + '_x'), tip = $(id + '_tip');

        hit.addEventListener('mousemove', function (e) {
            const r = el.getBoundingClientRect(), px = (e.clientX - r.left) / r.width * W;
            const t = x0 + (px - m.l) / iw * (x1 - x0);
            let best = 0, bd = Infinity;
            ts.forEach(function (v, i) { const d = Math.abs(v - t); if (d < bd) { bd = d; best = i; } });
            const cx = X(ts[best]);
            cross.setAttribute('x1', cx); cross.setAttribute('x2', cx);
            let html = '<div class="ch-tip-time">' + new Date(ts[best]).toLocaleTimeString('en-GB', { hour12: false }) + '</div>';
            o.series.forEach(function (s) {
                html += '<div class="ch-tip-row"><span class="ch-dot" style="background:' + s.color + '"></span>' + esc(s.name) + '<b>' + esc(s.values[best] === null || s.values[best] === undefined ? 'N/A' : o.fmt(s.values[best])) + '</b></div>';
            });
            tip.innerHTML = html; tip.hidden = false;
            tip.style.left = Math.min(Math.max(cx / W * r.width + 14, 6), r.width - tip.offsetWidth - 6) + 'px';
            tip.style.top = '10px';
        });

        hit.addEventListener('mouseleave', function () { tip.hidden = true; cross.setAttribute('x1', -10); cross.setAttribute('x2', -10); });
    }

    /* ---------- Render ---------- */

    function renderHeader(d)
    {
        const o = d.overall, lvl = LV[o.status];
        $('chState').dataset.lv = lvl;
        $('chStateText').textContent = o.status === 'healthy' ? 'Healthy' : o.status === 'warning' ? 'Warning' : 'Critical';
        $('chScore').dataset.lv = lvl;
        $('chScoreValue').textContent = o.score;
        $('chScoreFill').style.strokeDashoffset = 188.5 * (1 - o.score / 100);
        $('chHostLine').textContent = d.host.hostname + ' · ' + d.host.os + ' · Kernel ' + d.host.kernel + ' · Uptime ' + dur(d.host.uptime_seconds);
        $('chSampled').textContent = 'Sampled ' + dtime(d.sampled_at) + ' · ' + Math.round(d.sample_window * 1000) + ' ms window';
    }

    function renderIdentity(d)
    {
        const h = d.host, c = d.cpu;
        const items = [['Host', h.hostname], ['OS', h.os], ['Kernel', h.kernel + ' · ' + h.arch], ['CPU', (c.model || 'Unknown') + (c.mhz ? ' @ ' + num(c.mhz) + ' MHz' : '')],
            ['Cores', c.cores_count], ['RAM', bytes(d.memory.total, 1)], ['Boot time', dtime(h.boot_time)], ['Platform', h.virtualization || 'N/A']];
        $('chIdentity').innerHTML = items.map(function (i) { return '<div class="ch-id"><span>' + esc(i[0]) + '</span><b title="' + esc(i[1]) + '">' + esc(i[1] === null || i[1] === undefined ? 'N/A' : i[1]) + '</b></div>'; }).join('');
    }

    function kpi(o)
    {
        return '<div class="ch-kpi" data-lv="' + o.lv + '"><div class="ch-kpi-top"><div class="ch-kpi-icon">' + icon(o.icon) + '</div><div class="ch-kpi-label">' + esc(o.label) + '</div></div>' +
            '<div class="ch-kpi-value">' + o.value + (o.chip ? '<span class="ch-chip">' + esc(o.chip) + '</span>' : '') + '</div>' +
            '<div class="ch-kpi-desc">' + esc(o.desc) + '</div>' + (o.spark || '') +
            '<div class="ch-meter"><div class="ch-meter-fill" style="width:' + Math.min(100, Math.max(0, o.meter || 0)) + '%"></div></div></div>';
    }

    function renderKpis(d, r)
    {
        const m = d.memory, root = d.storage.mounts[0], db = d.database, ld = d.load;
        const dbLv = db.status === 'healthy' ? (db.latency_ms >= 200 ? 'warn' : 'ok') : 'bad';

        $('chKpis').innerHTML =
            kpi({ icon: 'cpu', label: 'CPU Usage', lv: lv(r.cpu.usage, 75, 90), value: num(r.cpu.usage, 1) + '<small>%</small>', chip: d.cpu.cores_count + ' cores',
                desc: 'user ' + num(r.cpu.user, 1) + '% · sys ' + num(r.cpu.system, 1) + '% · iowait ' + num(r.cpu.iowait, 1) + '%', meter: r.cpu.usage, spark: spark(hist.cpu, C1) }) +
            kpi({ icon: 'load', label: 'Load / Core', lv: lv(ld.per_core, 1, 2), value: ld.per_core === null ? 'N/A' : num(ld.per_core, 2), chip: 'load ' + num(ld.one, 2),
                desc: '5m ' + num(ld.five, 2) + ' · 15m ' + num(ld.fifteen, 2) + ' · ' + (ld.running === null ? '?' : ld.running) + '/' + (ld.total_tasks === null ? '?' : ld.total_tasks) + ' tasks',
                meter: ld.per_core === null ? 0 : ld.per_core * 50, spark: spark(hist.load, C3) }) +
            kpi({ icon: 'mem', label: 'Memory', lv: lv(m.usage_percent, 85, 95), value: num(m.usage_percent, 1) + '<small>%</small>', chip: bytes(m.total, 1),
                desc: bytes(m.used, 1) + ' used · ' + bytes(m.available_bytes, 1) + ' available', meter: m.usage_percent, spark: spark(hist.mem, C2) }) +
            kpi({ icon: 'disk', label: 'Root Filesystem', lv: root ? lv(root.percent, 85, 95) : 'idle', value: root ? num(root.percent, 1) + '<small>%</small>' : 'N/A', chip: root ? bytes(root.total, 1) : '',
                desc: root ? bytes(root.used, 1) + ' used · ' + bytes(root.free, 1) + ' free' : 'Unavailable', meter: root ? root.percent : 0 }) +
            kpi({ icon: 'net', label: 'Network', lv: 'ok', value: '<span class="ch-dir">↓</span>' + bits(r.net.rx), chip: '↑ ' + bits(r.net.tx),
                desc: d.network.interfaces.filter(function (i) { return i.state === 'up'; }).length + ' interface(s) up · ' + num(d.network.tcp.states.established) + ' established', meter: Math.min(100, (r.net.rx + r.net.tx) / 125000), spark: spark(hist.rx, C3) }) +
            kpi({ icon: 'db', label: 'Database', lv: dbLv, value: db.latency_ms === null ? 'DOWN' : num(db.latency_ms, 1) + '<small>ms</small>', chip: db.stats ? db.stats.threads_connected + ' conn' : db.driver,
                desc: db.status === 'healthy' ? (db.version ? db.version + ' · ' : '') + (r.qps !== null ? num(r.qps, 1) + ' qps' : db.state) : db.state, meter: db.latency_ms === null ? 100 : Math.min(100, db.latency_ms / 2), spark: spark(hist.dbl, C4) });
    }

    function renderFindings(d)
    {
        const f = d.findings, o = d.overall;
        $('chFindCount').textContent = o.critical + ' critical · ' + o.warning + ' warning · ' + o.info + ' info';

        if (!f.length) {
            $('chFindings').innerHTML = '<div class="ch-finding lv-ok ch-wide">' + pill('ok', 'Healthy') + '<div><div class="ch-finding-title">All checks passed</div><div class="ch-finding-detail">No critical, warning, or informational findings.</div></div></div>';
            return;
        }

        const label = { critical: 'Critical', warning: 'Warning', info: 'Info' };
        $('chFindings').innerHTML = f.map(function (x) {
            return '<div class="ch-finding lv-' + LV[x.level] + '">' + pill(LV[x.level], label[x.level]) + '<div><div class="ch-finding-title">' + esc(x.title) + ' <span class="ch-area">' + esc(x.area) + '</span></div>' +
                '<div class="ch-finding-detail">' + esc(x.detail) + '</div></div></div>';
        }).join('');
    }

    function renderCharts(d)
    {
        $('chHistLabel').textContent = hist.t.length < 2 ? 'collecting…' : hist.t.length + ' samples · ' + dur((hist.t[hist.t.length - 1] - hist.t[0]) / 1000);

        legend('legCpu', [{ name: 'CPU', color: C1 }, { name: 'I/O wait', color: C4 }]);
        drawChart('chartCpu', { yMax: 100, fmt: function (v) { return num(v, 1) + '%'; }, axis: function (v) { return num(v, 0) + '%'; }, thresholds: [{ value: 90, color: BAD }],
            series: [{ name: 'CPU', color: C1, area: true, values: hist.cpu }, { name: 'I/O wait', color: C4, values: hist.iow }] });

        legend('legMem', [{ name: 'RAM', color: C2 }, { name: 'Swap', color: C3 }]);
        drawChart('chartMem', { yMax: 100, fmt: function (v) { return num(v, 1) + '%'; }, axis: function (v) { return num(v, 0) + '%'; }, thresholds: [{ value: 95, color: BAD }, { value: 85, color: 'var(--warn)' }],
            series: [{ name: 'RAM', color: C2, area: true, values: hist.mem }, { name: 'Swap', color: C3, values: hist.swap }] });

        legend('legNet', [{ name: 'RX', color: C3 }, { name: 'TX', color: C2 }]);
        drawChart('chartNet', { fmt: bits, axis: bits, series: [{ name: 'Receive', color: C3, area: true, values: hist.rx }, { name: 'Transmit', color: C2, area: true, values: hist.tx }] });

        legend('legDisk', [{ name: 'Read', color: C1 }, { name: 'Write', color: C4 }]);
        drawChart('chartDisk', { fmt: rate, axis: function (v) { return bytes(v, 0); }, series: [{ name: 'Read', color: C1, area: true, values: hist.rd }, { name: 'Write', color: C4, area: true, values: hist.wr }] });
    }

    function renderCpu(d, r)
    {
        const c = d.cpu, p = d.pressure;
        $('chCpuTag').textContent = c.cores_count + ' cores';
        $('chCpuModel').textContent = (c.model || 'Unknown CPU') + (c.mhz ? ' @ ' + num(c.mhz) + ' MHz' : '');

        const seg = function (pct, cls, t) { return '<i class="' + cls + '" style="width:' + Math.max(0, pct) + '%" title="' + t + '"></i>'; };
        let html = '<div class="ch-subhead">Time breakdown</div><div class="ch-stack">' +
            seg(r.cpu.user, 'sg1', 'user ' + num(r.cpu.user, 1) + '%') + seg(r.cpu.system, 'sg2', 'system ' + num(r.cpu.system, 1) + '%') +
            seg(r.cpu.iowait, 'sg3', 'iowait ' + num(r.cpu.iowait, 1) + '%') + seg(r.cpu.steal, 'sg4', 'steal ' + num(r.cpu.steal, 1) + '%') + '</div>' +
            '<div class="ch-legend ch-legend-block"><span><span class="ch-dot sg1"></span>user ' + num(r.cpu.user, 1) + '%</span><span><span class="ch-dot sg2"></span>system ' + num(r.cpu.system, 1) + '%</span>' +
            '<span><span class="ch-dot sg3"></span>iowait ' + num(r.cpu.iowait, 1) + '%</span><span><span class="ch-dot sg4"></span>steal ' + num(r.cpu.steal, 1) + '%</span><span><span class="ch-dot sg5"></span>idle ' + num(100 - r.cpu.usage, 1) + '%</span></div>';

        html += '<div class="ch-subhead">Per core</div><div class="ch-cores">' + c.cores.map(function (k) {
            return '<div class="ch-core"><span>CPU ' + k.id + '</span>' + bar(k.usage, lv(k.usage, 75, 90), 'sm') + '<b>' + num(k.usage, 0) + '%</b></div>';
        }).join('') + '</div>';

        html += '<div class="ch-tiles">' + tile('Running / blocked', num(c.running) + ' / ' + num(c.blocked), 'runnable tasks', c.blocked > 0 ? 'warn' : '') +
            tile('Context switches', num(c.context_switches), 'since boot') + tile('Forks', num(c.forks), 'since boot') + '</div>';

        const names = { cpu: 'CPU', memory: 'Memory', io: 'I/O' };
        const keys = Object.keys(p);
        if (keys.length) {
            html += '<div class="ch-subhead">Pressure stall (PSI, 10s avg)</div><div class="ch-tiles">' + keys.map(function (k) {
                const v = p[k].some ? p[k].some.avg10 : 0;
                return tile(names[k], num(v, 2) + '%', 'avg60 ' + num(p[k].some ? p[k].some.avg60 : 0, 2) + '% · full ' + num(p[k].full ? p[k].full.avg10 : 0, 2) + '%', lv(v, 8, 20) === 'ok' ? 'ok' : lv(v, 8, 20));
            }).join('') + '</div>';
        }

        $('chCpu').innerHTML = html;
    }

    function renderMemory(d)
    {
        const m = d.memory;
        if (!m.available) { $('chMemory').innerHTML = '<div class="ch-empty">Memory information unavailable.</div>'; return; }

        const pc = function (x) { return m.total ? x / m.total * 100 : 0; };
        let html = '<div class="ch-subhead">Physical RAM · ' + bytes(m.total, 1) + '</div><div class="ch-stack">' +
            '<i class="sg1" style="width:' + pc(m.apps) + '%" title="Applications ' + bytes(m.apps) + '"></i><i class="sg2" style="width:' + pc(m.cache) + '%" title="Cache ' + bytes(m.cache) + '"></i>' +
            '<i class="sg5" style="width:' + pc(m.free) + '%" title="Free ' + bytes(m.free) + '"></i></div>' +
            '<div class="ch-legend ch-legend-block"><span><span class="ch-dot sg1"></span>Applications ' + bytes(m.apps, 1) + '</span><span><span class="ch-dot sg2"></span>Cache/buffers ' + bytes(m.cache, 1) + '</span><span><span class="ch-dot sg5"></span>Free ' + bytes(m.free, 1) + '</span></div>';

        html += '<div class="ch-subhead">Swap</div>';
        html += m.swap_total > 0 ? '<div class="ch-swap">' + bar(m.swap_percent, lv(m.swap_percent, 30, 60)) + '<b>' + num(m.swap_percent, 1) + '%</b></div><div class="ch-kv-s">' + bytes(m.swap_used, 1) + ' of ' + bytes(m.swap_total, 1) + '</div>'
            : '<div class="ch-kv-s">No swap configured. ' + pill('info', 'OOM risk under pressure') + '</div>';

        html += '<div class="ch-tiles">' + tile('Available', bytes(m.available_bytes, 1), 'usable without swapping', 'ok') + tile('Dirty pages', bytes(m.dirty, 1), 'waiting to be written') +
            tile('Shared (tmpfs)', bytes(m.shmem, 1), 'Shmem') + tile('Slab', bytes(m.slab, 1), 'kernel caches') +
            tile('Committed', bytes(m.committed, 1), 'limit ' + bytes(m.commit_limit, 1), m.commit_limit && m.committed > m.commit_limit ? 'warn' : '') +
            tile('OOM kills', num(m.oom_kills), 'since boot', m.oom_kills > 0 ? 'bad' : 'ok') + '</div>';

        $('chMemory').innerHTML = html;
    }

    function renderStorage(d)
    {
        const mounts = d.storage.mounts;
        $('chStorageTag').textContent = mounts.length + ' mount(s)';

        $('chMounts').innerHTML = mounts.length ? mounts.map(function (k) {
            const l = lv(k.percent, 85, 95);
            return '<div class="ch-mount"><div class="ch-mount-top"><div><div class="ch-strong">' + esc(k.mount) + '</div><div class="ch-muted">' + esc(k.device) + ' · ' + esc(k.fs) + '</div></div>' + pill(l, num(k.percent, 1) + '%') + '</div>' +
                bar(k.percent, l) + '<div class="ch-mount-meta"><span>' + bytes(k.used, 1) + ' used</span><span>' + bytes(k.free, 1) + ' free</span><span>' + bytes(k.total, 1) + '</span></div>' +
                (k.inodes ? '<div class="ch-mount-inodes"><span>inodes</span>' + bar(k.inodes.percent, lv(k.inodes.percent, 80, 95), 'sm') + '<b>' + num(k.inodes.percent, 0) + '%</b></div>' : '') + '</div>';
        }).join('') : '<div class="ch-empty">No filesystem information available.</div>';

        const dev = d.storage.devices;
        $('chDevices').innerHTML = '<thead><tr><th>Device</th><th>Read</th><th>Write</th><th>Read IOPS</th><th>Write IOPS</th><th>Utilisation</th><th>In flight</th></tr></thead><tbody>' +
            (dev.length ? dev.map(function (x) {
                return '<tr><td><span class="ch-env">' + esc(x.name) + '</span></td><td>' + rate(x.read_bps) + '</td><td>' + rate(x.write_bps) + '</td><td>' + num(x.read_iops, 1) + '</td><td>' + num(x.write_iops, 1) + '</td>' +
                    '<td><div class="ch-inline">' + bar(x.util, lv(x.util, 70, 90), 'sm') + '<b>' + num(x.util, 0) + '%</b></div></td><td>' + num(x.inflight) + '</td></tr>';
            }).join('') : '<tr><td colspan="7"><div class="ch-empty">No block devices exposed.</div></td></tr>') + '</tbody>';
    }

    function renderNetwork(d, r)
    {
        const ifs = d.network.interfaces, t = d.network.tcp;
        $('chNetTag').textContent = '↓ ' + bits(r.net.rx) + ' · ↑ ' + bits(r.net.tx);

        $('chIfaces').innerHTML = '<thead><tr><th>Interface</th><th>State</th><th>Address</th><th>Download</th><th>Upload</th><th>Total RX / TX</th><th>Errors</th><th>Dropped</th></tr></thead><tbody>' +
            (ifs.length ? ifs.map(function (i) {
                const p = r.net.per[i.name] || { rx: i.rx_bps, tx: i.tx_bps };
                const dropPct = i.rx_packets > 0 ? i.rx_dropped / i.rx_packets * 100 : 0;
                return '<tr><td><div class="ch-strong">' + esc(i.name) + '</div><div class="ch-muted">' + esc(i.mac || '') + (i.mtu ? ' · MTU ' + i.mtu : '') + (i.speed_mbps ? ' · ' + i.speed_mbps + ' Mbps' : '') + '</div></td>' +
                    '<td>' + pill(i.state === 'up' ? 'ok' : (i.state === 'down' ? 'idle' : 'info'), i.state) + '</td><td>' + (i.addresses.length ? esc(i.addresses.join(', ')) : '<span class="ch-muted">—</span>') + '</td>' +
                    '<td><b>' + bits(p.rx) + '</b></td><td><b>' + bits(p.tx) + '</b></td><td>' + bytes(i.rx_bytes, 1) + ' / ' + bytes(i.tx_bytes, 1) + '</td>' +
                    '<td>' + pill(i.rx_errors + i.tx_errors ? 'warn' : 'ok', num(i.rx_errors + i.tx_errors)) + '</td><td>' + pill(dropPct >= 1 ? 'warn' : 'ok', num(i.rx_dropped + i.tx_dropped) + (dropPct >= 0.05 ? ' · ' + num(dropPct, 2) + '%' : '')) + '</td></tr>';
            }).join('') : '<tr><td colspan="8"><div class="ch-empty">No network interfaces.</div></td></tr>') + '</tbody>';

        const s = t.states;
        $('chTcp').innerHTML = tile('Established', num(s.established), 'active connections', 'ok') + tile('Listening', num(s.listen), 'sockets') +
            tile('TIME_WAIT', num(s.time_wait), 'closing', s.time_wait >= 10000 ? 'warn' : '') + tile('CLOSE_WAIT', num(s.close_wait), 'awaiting app close', s.close_wait >= 100 ? 'warn' : '') +
            tile('SYN_RECV', num(s.syn_recv), 'half-open', s.syn_recv >= 200 ? 'warn' : '') + tile('Sockets in use', num(d.network.sockets.used), 'TCP ' + num(d.network.sockets.tcp_inuse));

        $('chPorts').innerHTML = t.listening.length ? t.listening.map(function (p) {
            const level = p.scope === 'local' ? 'ok' : (p.sensitive ? 'bad' : 'info');
            return '<div class="ch-port lv-' + level + '"><b>' + p.port + '</b><span>' + esc(p.service || 'unknown') + '</span>' + pill(level, p.scope === 'local' ? 'localhost' : p.scope === 'public' ? 'all interfaces' : 'bound') + '</div>';
        }).join('') : '<div class="ch-empty">No listening TCP ports detected.</div>';
    }

    function renderServices(d)
    {
        const items = d.services.items, failed = d.services.failed;
        const bad = items.filter(function (s) { return s.status === 'critical'; }).length;
        $('chSvcTag').textContent = (items.length - bad) + '/' + items.length + ' running' + (failed.length ? ' · ' + failed.length + ' failed unit(s)' : '');

        $('chServices').innerHTML = items.map(function (s) {
            return '<div class="ch-service lv-' + LV[s.status] + '"><div class="ch-service-top"><div><div class="ch-strong">' + esc(s.label) + '</div><div class="ch-muted">' + esc(s.key) + '</div></div>' + pill(LV[s.status], s.state + (s.sub && s.sub !== s.state ? ' (' + s.sub + ')' : '')) + '</div>' +
                '<div class="ch-service-meta"><span>PID <b>' + (s.pid || '—') + '</b></span><span>Mem <b>' + (s.memory === null ? '—' : bytes(s.memory, 1)) + '</b></span><span>Tasks <b>' + (s.tasks === null ? '—' : s.tasks) + '</b></span>' +
                '<span>Up <b>' + (s.uptime === null ? '—' : dur(s.uptime)) + '</b></span>' + (s.restarts !== null ? '<span>Restarts <b>' + s.restarts + '</b></span>' : '') + '</div></div>';
        }).join('') + (failed.length ? '<div class="ch-service lv-bad ch-wide"><div class="ch-strong">Failed units</div><div class="ch-muted">' + failed.map(function (f) { return esc(f.unit); }).join(', ') + '</div></div>' : '');
    }

    function procTable(list, key)
    {
        if (!list.length) { return '<tbody><tr><td colspan="5"><div class="ch-empty">No data.</div></td></tr></tbody>'; }
        return '<thead><tr><th>Command</th><th>User</th><th>Procs</th><th>Memory</th><th>CPU</th></tr></thead><tbody>' + list.map(function (p) {
            return '<tr><td><span class="ch-strong">' + esc(p.name) + '</span></td><td>' + esc(p.user || '—') + '</td><td>' + p.count + '</td><td' + (key === 'mem' ? ' class="ch-hl"' : '') + '>' + bytes(p.rss, 1) + '</td><td' + (key === 'cpu' ? ' class="ch-hl"' : '') + '>' + num(p.cpu, 1) + '%</td></tr>';
        }).join('') + '</tbody>';
    }

    function renderProcesses(d)
    {
        const p = d.processes, s = p.states, fd = p.file_descriptors;
        $('chProcTag').textContent = num(p.total) + ' processes · ' + num(p.threads) + ' threads';
        $('chProcTiles').innerHTML = tile('Running', num(s.running), '', 'ok') + tile('Sleeping', num(s.sleeping), '') + tile('Disk wait (D)', num(s.disk_wait), 'uninterruptible', s.disk_wait > 3 ? 'warn' : '') +
            tile('Zombie', num(s.zombie), 'defunct', s.zombie ? 'warn' : 'ok') + tile('Stopped', num(s.stopped), '') +
            (fd ? tile('File descriptors', num(fd.open), 'of ' + num(fd.max) + ' (' + num(fd.open / fd.max * 100, 1) + '%)', fd.open / fd.max >= 0.8 ? 'warn' : '') : '');
        $('chTopMem').innerHTML = procTable(p.top_memory, 'mem');
        $('chTopCpu').innerHTML = procTable(p.top_cpu, 'cpu');
    }

    function renderDatabase(d, r)
    {
        const db = d.database;
        $('chDbTag').textContent = db.status === 'healthy' ? 'connected' : 'unreachable';

        if (db.status !== 'healthy') {
            $('chDb').innerHTML = '<div class="ch-finding lv-bad ch-wide">' + pill('bad', 'Critical') + '<div><div class="ch-finding-title">Database connection failed</div><div class="ch-finding-detail">Driver: ' + esc(db.driver) + '</div></div></div>';
            return;
        }

        const st = db.stats;
        let html = '<div class="ch-tiles">' + tile('Latency', num(db.latency_ms, 2) + ' ms', 'SELECT 1', db.latency_ms >= 200 ? 'warn' : 'ok') + tile('Engine', esc(db.version || db.engine || db.driver), esc(db.name || ''));
        if (st) {
            const cl = st.max_connections ? st.threads_connected / st.max_connections * 100 : 0;
            html += tile('Connections', num(st.threads_connected) + (st.max_connections ? ' / ' + num(st.max_connections) : ''), 'peak ' + num(st.max_used) + ' · running ' + num(st.threads_running), lv(cl, 70, 90) === 'ok' ? 'ok' : lv(cl, 70, 90)) +
                tile('Queries / s', r.qps === null ? 'N/A' : num(r.qps, 1), num(st.questions) + ' total') + tile('Slow queries', num(st.slow_queries), 'since start', st.slow_queries > 0 ? 'warn' : 'ok') +
                tile('Aborted connects', num(st.aborted_connects), 'of ' + num(st.connections), st.aborted_connects > 100 ? 'warn' : '') + tile('Buffer pool hit', st.buffer_pool_hit === null ? 'N/A' : num(st.buffer_pool_hit, 2) + '%', 'InnoDB', st.buffer_pool_hit !== null && st.buffer_pool_hit < 95 ? 'warn' : 'ok') +
                tile('DB uptime', dur(st.uptime), 'server') + tile('Database size', bytes(db.size_bytes, 1), 'data + indexes');
        }
        html += '</div>';

        if (db.top_tables && db.top_tables.length) {
            const mx = db.top_tables[0].size || 1;
            html += '<div class="ch-subhead">Largest tables</div><div class="ch-tables">' + db.top_tables.map(function (t) {
                return '<div class="ch-trow"><span class="ch-strong">' + esc(t.name) + '</span><div>' + bar(t.size / mx * 100, 'info', 'sm') + '</div><b>' + bytes(t.size, 1) + '</b><em>~' + num(t.rows) + ' rows</em></div>';
            }).join('') + '</div>';
        }

        if (db.stats_error) { html += '<div class="ch-kv-s">' + esc(db.stats_error) + '</div>'; }
        $('chDb').innerHTML = html;
    }

    function renderRuntime(d)
    {
        const p = d.php, o = p.opcache, l = d.laravel;

        let php = '<div class="ch-rows">' + row('PHP / SAPI', esc(p.version + ' / ' + p.sapi)) + row('Memory limit', esc(p.memory_limit)) + row('Max execution time', esc(p.max_execution_time) + 's') +
            row('Upload / POST max', esc(p.upload_max_filesize + ' / ' + p.post_max_size)) + row('Timezone', esc(p.timezone)) + row('Extensions loaded', p.extensions) + row('This request peak memory', bytes(p.request_memory, 1)) + '</div>';

        if (o) {
            const tot = o.used + o.free;
            php += '<div class="ch-subhead">OPcache</div><div class="ch-tiles">' + tile('Status', o.enabled ? 'Enabled' : 'Disabled', '', o.enabled ? 'ok' : 'warn') + tile('Hit rate', num(o.hit_rate, 2) + '%', num(o.scripts) + ' scripts cached', o.hit_rate < 90 ? 'warn' : 'ok') +
                tile('Memory', bytes(o.used, 1), 'free ' + bytes(o.free, 1) + ' · wasted ' + num(o.wasted_percent, 1) + '%', tot && o.free / tot < 0.05 ? 'warn' : 'ok') + tile('Restarts', num(o.restarts), '', o.restarts ? 'warn' : '') + '</div>';
        } else {
            php += '<div class="ch-subhead">OPcache</div><div class="ch-kv-s">' + (p.opcache_loaded ? 'Loaded but status is not exposed to this SAPI.' : pill('warn', 'Not loaded')) + '</div>';
        }
        $('chPhp').innerHTML = php;

        let lar = '<div class="ch-rows">' + row('Laravel', esc(l.version)) + row('Environment', pill(l.environment === 'production' ? 'ok' : 'info', l.environment)) + row('Debug', pill(l.debug ? (l.environment === 'production' ? 'bad' : 'warn') : 'ok', l.debug ? 'ON' : 'OFF')) +
            row('Maintenance mode', pill(l.maintenance ? 'warn' : 'ok', l.maintenance ? 'Active' : 'Off')) + row('Application key', pill(l.key_configured ? 'ok' : 'bad', l.key_configured ? 'Configured' : 'Missing')) +
            row('Timezone', esc(l.timezone)) + row('Queue / Cache / Session', esc(l.queue + ' / ' + l.cache + ' / ' + l.session)) +
            row('Failed jobs', l.failed_jobs === null ? 'N/A' : pill(l.failed_jobs ? 'warn' : 'ok', num(l.failed_jobs))) + (l.pending_jobs !== null ? row('Pending jobs', num(l.pending_jobs)) : '') + '</div>';

        lar += '<div class="ch-subhead">Optimisation</div><div class="ch-chips">' + [['config', l.config_cached], ['routes', l.routes_cached], ['events', l.events_cached]].map(function (x) {
            return pill(x[1] ? 'ok' : 'idle', x[0] + (x[1] ? ' cached' : ' not cached'));
        }).join('') + '</div>';

        lar += '<div class="ch-subhead">Storage access</div><div class="ch-rows">' + l.storage.map(function (s) { return row(s.label, pill(LV[s.status], s.state)); }).join('') + '</div>';
        $('chLaravel').innerHTML = lar;
    }

    function renderLogsSystem(d)
    {
        const lg = d.laravel.logs, sy = d.system;

        let html = '<div class="ch-subhead">Application log</div><div class="ch-tiles">' + tile('Errors (24h)', lg.errors_24h === null ? 'N/A' : num(lg.errors_24h), lg.newest || 'no log file', lg.errors_24h > 0 ? 'warn' : 'ok') +
            tile('Log size', bytes(lg.size, 1), num(lg.files) + ' file(s)', lg.size >= 209715200 ? 'warn' : '') + tile('Last write', lg.modified ? dtime(lg.modified) : 'N/A', '') + '</div>';

        html += lg.recent.length ? '<div class="ch-logs">' + lg.recent.map(function (e) {
            return '<div class="ch-log"><span>' + esc(e.time) + '</span>' + pill(e.level === 'ERROR' ? 'warn' : 'bad', e.level) + '<code>' + esc(e.message) + '</code></div>';
        }).join('') + '</div>' : '<div class="ch-kv-s">No recent errors found in the tail of the newest log.</div>';
        $('chLogs').innerHTML = html;

        $('chSystem').innerHTML = '<div class="ch-subhead">System</div><div class="ch-rows">' +
            row('Reboot required', pill(sy.reboot_required ? 'warn' : 'ok', sy.reboot_required ? 'Yes' : 'No')) +
            row('Pending updates', sy.pending_updates === null ? 'N/A' : pill(sy.security_updates ? 'warn' : (sy.pending_updates ? 'info' : 'ok'), num(sy.pending_updates) + (sy.security_updates ? ' · ' + sy.security_updates + ' security' : ''))) +
            row('Clock synchronised (NTP)', sy.ntp_synchronized === null ? 'N/A' : pill(sy.ntp_synchronized ? 'ok' : 'warn', sy.ntp_synchronized ? 'Yes' : 'No')) +
            row('System timezone', esc(sy.system_timezone || 'N/A')) + row('Server time', dtime(sy.server_time)) + row('Uptime', dur(d.host.uptime_seconds)) + '</div>';
    }

    function renderAll()
    {
        const d = snap, r = derived;
        renderHeader(d); renderIdentity(d); renderKpis(d, r); renderFindings(d); renderCharts(d);
        renderCpu(d, r); renderMemory(d); renderStorage(d); renderNetwork(d, r); renderServices(d); renderProcesses(d);
        renderDatabase(d, r); renderRuntime(d); renderLogsSystem(d);
    }

    /* ---------- Load ---------- */

    function accept(d)
    {
        derived = derive(d, prev);
        pushHistory(d, derived);
        prev = d;
        snap = d;
        renderAll();
    }

    async function load()
    {
        if (S.loading) { return; }
        S.loading = true;
        if (S.controller) { S.controller.abort(); }
        const controller = new AbortController();
        S.controller = controller;
        $('chRoot').classList.add('is-loading');
        $('chError').hidden = true;

        try {
            const res = await fetch(DATA_URL, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal });
            if (res.redirected) { window.location.reload(); return; }
            if (!res.ok) { throw new Error('Unable to refresh health data (HTTP ' + res.status + ').'); }
            accept(await res.json());
        } catch (e) {
            if (e.name !== 'AbortError') { $('chError').textContent = e.message || 'Unable to refresh health data.'; $('chError').hidden = false; }
        } finally {
            S.loading = false;
            $('chRoot').classList.remove('is-loading');
        }
    }

    function schedule()
    {
        clearInterval(S.timer);
        if (S.auto) { S.timer = setInterval(function () { if (!document.hidden) { load(); } }, S.interval); }
    }

    $('chRefresh').addEventListener('click', load);
    $('chInterval').addEventListener('change', function () { S.interval = parseInt(this.value, 10); schedule(); });
    $('chAuto').addEventListener('click', function () {
        S.auto = !S.auto;
        this.classList.toggle('is-on', S.auto);
        this.setAttribute('aria-pressed', S.auto ? 'true' : 'false');
        schedule();
    });
    document.addEventListener('visibilitychange', function () { if (!document.hidden && S.auto) { load(); } });

    let rt = null;
    window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(function () { Object.keys(charts).forEach(function (id) { drawChart(id, charts[id]); }); }, 150); });

    accept(snap);
    schedule();
});
</script>

@endsection

@push('styles')
<style>
    .ch-page
    {
        --ok: #16a34a;
        --warn: #d97706;
        --bad: #dc2626;
        --info: #3b82f6;
        --c1: #3b82f6;
        --c2: #a855f7;
        --c3: #14b8a6;
        --c4: #f59e0b;
        display: flex;
        flex-direction: column;
        gap: 22px;
    }

    .ch-page code { font: 500 11.5px var(--mono); }

    /* ---------- Header ---------- */
    .ch-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
    .ch-header-main { display: flex; align-items: center; gap: 18px; }
    .ch-title { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; margin: 0; font-size: 26px; font-weight: 800; letter-spacing: -.02em; color: var(--ink); }
    .ch-sub { margin: 6px 0 0; font: 500 12px var(--mono); color: var(--muted); }

    .ch-score { position: relative; width: 72px; height: 72px; flex-shrink: 0; color: var(--ok); }
    .ch-score[data-lv="warn"] { color: var(--warn); }
    .ch-score[data-lv="bad"] { color: var(--bad); }
    .ch-score svg { width: 100%; height: 100%; transform: rotate(-90deg); filter: drop-shadow(0 0 10px currentColor); }
    .ch-score-track { fill: none; stroke: var(--line); stroke-width: 6; }
    .ch-score-fill { fill: none; stroke: currentColor; stroke-width: 6; stroke-linecap: round; transition: stroke-dashoffset .8s cubic-bezier(.2, .8, .2, 1); }
    .ch-score b { position: absolute; inset: 0; display: grid; place-items: center; font: 800 20px var(--font); color: var(--ink); }

    .ch-state
    {
        display: inline-flex; align-items: center; gap: 7px; padding: 4px 11px;
        border: 1px solid color-mix(in srgb, var(--ok) 40%, transparent); border-radius: 999px;
        background: color-mix(in srgb, var(--ok) 14%, transparent); color: var(--ok);
        font: 600 11px var(--mono); letter-spacing: .1em; text-transform: uppercase;
    }
    .ch-state[data-lv="warn"] { border-color: color-mix(in srgb, var(--warn) 40%, transparent); background: color-mix(in srgb, var(--warn) 14%, transparent); color: var(--warn); }
    .ch-state[data-lv="bad"] { border-color: color-mix(in srgb, var(--bad) 40%, transparent); background: color-mix(in srgb, var(--bad) 14%, transparent); color: var(--bad); }
    .ch-state-dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; animation: ch-pulse 2s ease-out infinite; }

    .ch-controls { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }
    .ch-sampled { font: 500 11px var(--mono); color: var(--muted); }
    .ch-controls select { height: 40px; padding: 0 10px; border: 1.5px solid var(--line); border-radius: 10px; background: var(--surface); color: var(--ink); font: 600 12px var(--mono); outline: none; }

    .ch-toggle
    {
        display: inline-flex; align-items: center; gap: 8px; height: 40px; padding: 0 14px; border: 1.5px solid var(--line); border-radius: 10px;
        background: transparent; color: var(--muted); font: 700 12px var(--mono); letter-spacing: .06em; text-transform: uppercase; cursor: pointer;
    }
    .ch-toggle-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--muted); opacity: .6; }
    .ch-toggle.is-on { color: var(--accent); border-color: color-mix(in srgb, var(--accent) 45%, var(--line)); }
    .ch-toggle.is-on .ch-toggle-dot { background: var(--accent); opacity: 1; box-shadow: 0 0 10px var(--accent); }

    .ch-btn
    {
        display: inline-flex; align-items: center; justify-content: center; gap: 8px; height: 40px; padding: 0 18px; border: 0; border-radius: 10px;
        background: var(--brand); color: #fff; font: 700 13px var(--font); cursor: pointer;
        box-shadow: 0 10px 22px -12px rgba(47, 76, 240, .9), inset 0 1px 0 rgba(255, 255, 255, .3); transition: filter .2s, transform .2s;
    }
    .ch-btn:hover { filter: brightness(1.08); transform: translateY(-1px); }
    .ch-btn svg { width: 15px; height: 15px; }

    .ch-error { padding: 12px 16px; border: 1px solid color-mix(in srgb, var(--bad) 35%, transparent); border-left: 3px solid var(--bad); border-radius: 10px; background: color-mix(in srgb, var(--bad) 10%, transparent); color: var(--bad); font: 500 12.5px var(--mono); }
    .ch-error[hidden] { display: none; }

    /* ---------- Identity ---------- */
    .ch-identity { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1px; border: 1px solid var(--line); border-radius: 14px; background: var(--line); overflow: hidden; box-shadow: var(--shadow); }
    .ch-id { display: flex; flex-direction: column; gap: 4px; min-width: 0; padding: 12px 16px; background: var(--surface); }
    .ch-id span { font: 600 10.5px var(--mono); letter-spacing: .1em; text-transform: uppercase; color: var(--muted); }
    .ch-id b { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font: 600 12.5px var(--mono); color: var(--ink); }

    /* ---------- KPI ---------- */
    .ch-kpis { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }

    .ch-kpi
    {
        position: relative; padding: 20px 22px; border: 1px solid var(--line); border-radius: 16px; background-color: var(--surface);
        background-image: radial-gradient(circle at 100% 0%, color-mix(in srgb, var(--accent) 14%, transparent), transparent 55%), linear-gradient(color-mix(in srgb, var(--accent) 5%, transparent) 1px, transparent 1px), linear-gradient(90deg, color-mix(in srgb, var(--accent) 5%, transparent) 1px, transparent 1px);
        background-size: auto, 22px 22px, 22px 22px; box-shadow: var(--shadow); overflow: hidden; transition: transform .25s ease, border-color .25s ease, box-shadow .25s ease;
    }
    .ch-kpi:hover { transform: translateY(-3px); border-color: color-mix(in srgb, var(--accent) 45%, var(--line)); box-shadow: var(--shadow), 0 18px 40px -22px var(--accent); }
    .ch-kpi::before { content: ''; position: absolute; top: -1px; left: 22px; width: 44px; height: 2px; border-radius: 0 0 3px 3px; background: var(--brand); }
    .ch-kpi::after { content: ''; position: absolute; right: 12px; bottom: 12px; width: 14px; height: 14px; border-right: 2px solid var(--accent); border-bottom: 2px solid var(--accent); border-radius: 0 0 4px 0; opacity: .5; pointer-events: none; }
    .ch-kpi-top { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
    .ch-kpi-icon { width: 40px; height: 40px; display: grid; place-items: center; flex-shrink: 0; border: 1px solid color-mix(in srgb, var(--accent) 40%, transparent); border-radius: 12px; background: var(--accent-soft); color: var(--accent); box-shadow: 0 0 22px -6px var(--accent), inset 0 0 12px -6px var(--accent); }
    .ch-kpi-icon svg { width: 19px; height: 19px; }
    .ch-kpi-label { font: 600 11.5px var(--mono); letter-spacing: .08em; text-transform: uppercase; color: var(--muted); }
    .ch-kpi-value { display: flex; align-items: baseline; flex-wrap: wrap; gap: 10px; font-size: 32px; font-weight: 800; letter-spacing: -.03em; line-height: 1.1; color: var(--ink); font-variant-numeric: tabular-nums; }
    .ch-kpi-value small { margin-left: 2px; font-size: 16px; font-weight: 700; color: var(--muted); }
    .ch-dir { margin-right: 4px; font-size: 20px; color: var(--c3); }
    .ch-chip { padding: 3px 9px; border: 1px solid color-mix(in srgb, var(--accent) 35%, transparent); border-radius: 999px; background: var(--accent-soft); color: var(--accent); font: 600 11px var(--mono); letter-spacing: 0; }
    .ch-kpi-desc { margin-top: 8px; font: 500 11.5px var(--mono); color: var(--muted); }
    .ch-meter { height: 4px; margin-top: 12px; border-radius: 999px; background: var(--surface-2); overflow: hidden; }
    .ch-meter-fill { height: 100%; border-radius: 999px; background: var(--brand); box-shadow: 0 0 12px var(--accent); transition: width .8s cubic-bezier(.2, .8, .2, 1); }
    .ch-kpi[data-lv="warn"] .ch-meter-fill { background: var(--warn); box-shadow: 0 0 12px var(--warn); }
    .ch-kpi[data-lv="bad"] .ch-meter-fill { background: var(--bad); box-shadow: 0 0 12px var(--bad); }
    .ch-spark { display: block; width: 100%; height: 30px; margin-top: 12px; }
    .ch-spark-empty { display: grid; place-items: center; border: 1px dashed var(--line); border-radius: 8px; color: var(--muted); font: 500 10.5px var(--mono); }

    /* ---------- Panel ---------- */
    .ch-panel { position: relative; min-width: 0; border: 1px solid var(--line); border-radius: 18px; background: var(--surface); box-shadow: var(--shadow); overflow: hidden; }
    .ch-panel::before { content: ''; position: absolute; top: 0; left: 28px; right: 28px; height: 3px; border-radius: 0 0 4px 4px; background: var(--brand); box-shadow: 0 0 18px var(--accent); }
    .ch-panel-head { padding: 24px 24px 18px; border-bottom: 1px solid var(--line); background-image: linear-gradient(180deg, color-mix(in srgb, var(--accent) 6%, transparent), transparent); }
    .ch-panel-head h2 { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; margin: 0; font-size: 16px; font-weight: 800; color: var(--ink); }
    .ch-panel-head p { margin: 5px 0 0; font: 500 12px var(--mono); color: var(--muted); }
    .ch-tag { display: inline-flex; align-items: center; padding: 3px 9px; border: 1px solid color-mix(in srgb, var(--accent) 35%, transparent); border-radius: 999px; background: var(--accent-soft); color: var(--accent); font: 600 10.5px var(--mono); letter-spacing: .06em; }
    .ch-tag:empty { display: none; }
    .ch-pad { padding: 20px 24px 24px; }
    .ch-flush-top { padding-top: 0; }
    .ch-grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
    .ch-grid-2.ch-pad { gap: 18px; }
    .ch-subhead { margin: 18px 0 10px; font: 600 11px var(--mono); letter-spacing: .1em; text-transform: uppercase; color: var(--muted); }
    .ch-subhead:first-child { margin-top: 0; }
    .ch-muted { margin-top: 3px; font: 500 11px var(--mono); color: var(--muted); }
    .ch-strong { font-size: 13px; font-weight: 700; color: var(--ink); }
    .ch-empty { display: grid; place-items: center; min-height: 100px; padding: 20px; color: var(--muted); font: 500 12px var(--mono); text-align: center; }
    .ch-kv-s { margin-top: 8px; font: 500 11.5px var(--mono); color: var(--muted); }
    .ch-wide { grid-column: 1 / -1; }

    /* ---------- Pill / bar / tile ---------- */
    .ch-pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border: 1px solid var(--line); border-radius: 999px; background: var(--surface-2); color: var(--muted); font: 600 11px var(--mono); white-space: nowrap; }
    .ch-pill.lv-ok { border-color: color-mix(in srgb, var(--ok) 40%, transparent); background: color-mix(in srgb, var(--ok) 14%, transparent); color: var(--ok); }
    .ch-pill.lv-info { border-color: color-mix(in srgb, var(--info) 40%, transparent); background: color-mix(in srgb, var(--info) 14%, transparent); color: var(--info); }
    .ch-pill.lv-warn { border-color: color-mix(in srgb, var(--warn) 40%, transparent); background: color-mix(in srgb, var(--warn) 14%, transparent); color: var(--warn); }
    .ch-pill.lv-bad { border-color: color-mix(in srgb, var(--bad) 40%, transparent); background: color-mix(in srgb, var(--bad) 14%, transparent); color: var(--bad); }

    .ch-bar { flex: 1; height: 7px; min-width: 40px; border-radius: 999px; background: var(--surface-2); box-shadow: inset 0 0 0 1px var(--line); overflow: hidden; }
    .ch-bar-sm { height: 5px; }
    .ch-bar-fill { height: 100%; border-radius: 999px; background: var(--ok); box-shadow: 0 0 10px var(--ok); transition: width .6s cubic-bezier(.2, .8, .2, 1); }
    .ch-bar-fill.lv-warn { background: var(--warn); box-shadow: 0 0 10px var(--warn); }
    .ch-bar-fill.lv-bad { background: var(--bad); box-shadow: 0 0 10px var(--bad); }
    .ch-bar-fill.lv-info { background: var(--accent); box-shadow: 0 0 10px var(--accent); }
    .ch-bar-fill.lv-idle { background: var(--muted); box-shadow: none; }
    .ch-inline { display: flex; align-items: center; gap: 10px; min-width: 120px; }
    .ch-inline b { min-width: 34px; font: 700 12px var(--mono); color: var(--ink); text-align: right; }

    .ch-tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; margin-top: 14px; }
    .ch-subhead + .ch-tiles { margin-top: 0; }
    .ch-tile { display: flex; flex-direction: column; gap: 6px; min-width: 0; padding: 13px 15px; border: 1px solid var(--line); border-radius: 12px; background: var(--surface-2); }
    .ch-tile span { font: 600 10.5px var(--mono); letter-spacing: .1em; text-transform: uppercase; color: var(--muted); }
    .ch-tile b { font-size: 19px; font-weight: 800; letter-spacing: -.02em; color: var(--ink); overflow-wrap: anywhere; }
    .ch-tile em { font: 500 11px var(--mono); font-style: normal; color: var(--muted); overflow-wrap: anywhere; }
    .ch-tile.lv-ok { box-shadow: inset 3px 0 0 var(--ok); }
    .ch-tile.lv-warn { box-shadow: inset 3px 0 0 var(--warn); }
    .ch-tile.lv-bad { box-shadow: inset 3px 0 0 var(--bad); }
    .ch-tile.lv-info { box-shadow: inset 3px 0 0 var(--accent); }

    .ch-rows { display: flex; flex-direction: column; }
    .ch-row { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 10px 0; border-top: 1px solid var(--line); font-size: 12px; }
    .ch-row:first-child { border-top: 0; }
    .ch-row span { font-weight: 700; color: var(--ink); }
    .ch-row b { font: 500 11.5px var(--mono); color: var(--muted); text-align: right; overflow-wrap: anywhere; }
    .ch-chips { display: flex; flex-wrap: wrap; gap: 8px; }

    /* ---------- Findings ---------- */
    .ch-findings { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; padding: 20px 24px 24px; }
    .ch-finding { display: flex; align-items: flex-start; gap: 12px; padding: 14px 16px; border: 1px solid var(--line); border-left: 3px solid var(--line); border-radius: 12px; background: var(--surface-2); }
    .ch-finding .ch-pill { flex-shrink: 0; margin-top: 1px; }
    .ch-finding.lv-ok { border-left-color: var(--ok); }
    .ch-finding.lv-info { border-left-color: var(--info); }
    .ch-finding.lv-warn { border-left-color: var(--warn); }
    .ch-finding.lv-bad { border-left-color: var(--bad); }
    .ch-finding-title { font-size: 13px; font-weight: 700; color: var(--ink); }
    .ch-finding-detail { margin-top: 4px; font: 500 11.5px/1.55 var(--mono); color: var(--muted); }
    .ch-area { margin-left: 6px; padding: 2px 8px; border: 1px solid var(--line); border-radius: 6px; background: var(--surface); color: var(--accent); font: 600 10px var(--mono); text-transform: uppercase; letter-spacing: .06em; }

    /* ---------- Charts ---------- */
    .ch-chart-card { min-width: 0; padding: 16px 16px 8px; border: 1px solid var(--line); border-radius: 14px; background-color: var(--surface); background-image: linear-gradient(color-mix(in srgb, var(--accent) 4%, transparent) 1px, transparent 1px), linear-gradient(90deg, color-mix(in srgb, var(--accent) 4%, transparent) 1px, transparent 1px); background-size: 22px 22px; }
    .ch-chart-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 6px; }
    .ch-chart-head > span { font: 600 11.5px var(--mono); letter-spacing: .1em; text-transform: uppercase; color: var(--muted); }
    .ch-legend { display: flex; flex-wrap: wrap; gap: 12px; font: 500 11px var(--mono); color: var(--muted); }
    .ch-legend-block { margin-top: 10px; }
    .ch-dot { display: inline-block; width: 8px; height: 8px; margin-right: 6px; border-radius: 50%; }
    .ch-chart { position: relative; min-height: 190px; }
    .ch-chart svg { display: block; width: 100%; height: auto; overflow: visible; }
    .ch-gl { stroke: var(--line); stroke-width: 1; stroke-dasharray: 2 4; }
    .ch-th { stroke-width: 1; stroke-dasharray: 5 4; opacity: .55; }
    .ch-ax { fill: var(--muted); font: 500 10px var(--mono); }
    .ch-cross { stroke: var(--accent); stroke-width: 1; stroke-dasharray: 3 3; opacity: .7; pointer-events: none; }
    .ch-hit { fill: transparent; cursor: crosshair; }
    .ch-tip { position: absolute; z-index: 5; min-width: 150px; padding: 10px 12px; border: 1px solid color-mix(in srgb, var(--accent) 40%, var(--line)); border-radius: 10px; background: var(--surface); box-shadow: var(--shadow), 0 0 24px -10px var(--accent); font: 500 11.5px var(--mono); color: var(--muted); pointer-events: none; }
    .ch-tip[hidden] { display: none; }
    .ch-tip-time { margin-bottom: 6px; color: var(--ink); font-weight: 700; }
    .ch-tip-row { display: flex; align-items: center; gap: 4px; padding: 2px 0; }
    .ch-tip-row b { margin-left: auto; padding-left: 14px; color: var(--ink); }

    /* ---------- Stacked bars / cores ---------- */
    .ch-stack { display: flex; height: 14px; border-radius: 999px; background: var(--surface-2); box-shadow: inset 0 0 0 1px var(--line); overflow: hidden; }
    .ch-stack i { display: block; height: 100%; transition: width .6s cubic-bezier(.2, .8, .2, 1); }
    .sg1 { background: var(--c1); }
    .sg2 { background: var(--c2); }
    .sg3 { background: var(--c4); }
    .sg4 { background: var(--bad); }
    .sg5 { background: color-mix(in srgb, var(--muted) 45%, transparent); }
    .ch-cores { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 10px 18px; }
    .ch-core { display: grid; grid-template-columns: 52px 1fr 38px; align-items: center; gap: 10px; font: 600 11px var(--mono); color: var(--muted); }
    .ch-core b { text-align: right; color: var(--ink); }
    .ch-swap { display: flex; align-items: center; gap: 12px; }
    .ch-swap b { font: 700 12px var(--mono); color: var(--ink); }

    /* ---------- Mounts ---------- */
    .ch-mounts { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; padding: 20px 24px; }
    .ch-mount { padding: 14px 16px; border: 1px solid var(--line); border-radius: 12px; background: var(--surface-2); }
    .ch-mount-top { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
    .ch-mount-meta { display: flex; justify-content: space-between; gap: 8px; margin-top: 10px; font: 500 11px var(--mono); color: var(--muted); }
    .ch-mount-inodes { display: grid; grid-template-columns: 44px 1fr 34px; align-items: center; gap: 10px; margin-top: 10px; font: 600 10.5px var(--mono); letter-spacing: .06em; text-transform: uppercase; color: var(--muted); }
    .ch-mount-inodes b { text-align: right; color: var(--ink); }

    /* ---------- Tables ---------- */
    .ch-table-wrap { width: 100%; overflow-x: auto; }
    .ch-table { width: 100%; border-collapse: collapse; white-space: nowrap; }
    .ch-table th { padding: 12px 20px; background: var(--surface-2); border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); text-align: left; font: 600 11px var(--mono); letter-spacing: .1em; text-transform: uppercase; color: var(--muted); }
    .ch-table td { padding: 13px 20px; border-bottom: 1px solid var(--line); font: 500 12px var(--mono); color: var(--muted); vertical-align: middle; }
    .ch-table tbody tr:last-child td { border-bottom: 0; }
    .ch-table tbody tr { transition: background .2s; }
    .ch-table tbody tr:hover { background: var(--accent-soft); }
    .ch-table td.ch-hl { color: var(--ink); font-weight: 700; }
    .ch-table td b { color: var(--ink); }
    .ch-grid-2.ch-pad .ch-table-wrap { border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
    .ch-grid-2.ch-pad .ch-table th { border-top: 0; }
    .ch-env { display: inline-block; padding: 4px 10px; border: 1px solid var(--line); border-radius: 8px; background: var(--surface-2); color: var(--ink); font: 600 11.5px var(--mono); }

    /* ---------- Ports / services / processes ---------- */
    .ch-ports { display: flex; flex-direction: column; gap: 8px; max-height: 320px; overflow-y: auto; }
    .ch-port { display: grid; grid-template-columns: 70px 1fr auto; align-items: center; gap: 12px; padding: 9px 14px; border: 1px solid var(--line); border-left: 3px solid var(--line); border-radius: 10px; background: var(--surface-2); font: 500 12px var(--mono); color: var(--muted); }
    .ch-port b { color: var(--ink); font-size: 13px; }
    .ch-port.lv-ok { border-left-color: var(--ok); }
    .ch-port.lv-info { border-left-color: var(--accent); }
    .ch-port.lv-bad { border-left-color: var(--bad); }

    .ch-services { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; padding: 20px 24px 24px; }
    .ch-service { padding: 14px 16px; border: 1px solid var(--line); border-left: 3px solid var(--line); border-radius: 12px; background: var(--surface-2); }
    .ch-service.lv-ok { border-left-color: var(--ok); }
    .ch-service.lv-warn { border-left-color: var(--warn); }
    .ch-service.lv-bad { border-left-color: var(--bad); }
    .ch-service-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
    .ch-service-meta { display: flex; flex-wrap: wrap; gap: 6px 16px; margin-top: 12px; padding-top: 10px; border-top: 1px solid var(--line); font: 500 11px var(--mono); color: var(--muted); }
    .ch-service-meta b { color: var(--ink); }

    .ch-tables { display: flex; flex-direction: column; gap: 8px; }
    .ch-trow { display: grid; grid-template-columns: minmax(140px, 1.2fr) 2fr 90px 110px; align-items: center; gap: 14px; font: 500 12px var(--mono); color: var(--muted); }
    .ch-trow b { color: var(--ink); text-align: right; }
    .ch-trow em { font-style: normal; text-align: right; }

    /* ---------- Logs ---------- */
    .ch-logs { display: flex; flex-direction: column; gap: 8px; margin-top: 14px; }
    .ch-log { display: grid; grid-template-columns: auto auto 1fr; align-items: start; gap: 10px; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; background: var(--surface-2); font: 500 11px var(--mono); color: var(--muted); }
    .ch-log code { color: var(--ink); overflow-wrap: anywhere; }

    .ch-note { padding: 14px 18px; border: 1px solid var(--line); border-radius: 12px; background: var(--surface-2); color: var(--muted); font: 500 11.5px/1.7 var(--mono); }
    .ch-note code { padding: 1px 6px; border-radius: 5px; background: var(--surface); color: var(--accent); }

    .ch-page.is-loading::before { content: ''; position: fixed; top: 0; left: 0; right: 0; z-index: 50; height: 2px; background: linear-gradient(90deg, transparent, var(--accent), transparent); background-size: 40% 100%; background-repeat: no-repeat; animation: ch-loading 1s ease-in-out infinite; }

    @keyframes ch-pulse { 0% { box-shadow: 0 0 0 0 color-mix(in srgb, currentColor 55%, transparent); } 100% { box-shadow: 0 0 0 8px transparent; } }
    @keyframes ch-loading { 0% { background-position: -40% 0; } 100% { background-position: 140% 0; } }

    @media (prefers-reduced-motion: reduce)
    {
        .ch-state-dot, .ch-page.is-loading::before { animation: none; }
        .ch-kpi, .ch-meter-fill, .ch-bar-fill, .ch-stack i, .ch-score-fill { transition: none; }
    }

    @media (max-width: 1280px) { .ch-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

    @media (max-width: 1000px)
    {
        .ch-grid-2, .ch-findings { grid-template-columns: 1fr; }
    }

    @media (max-width: 700px)
    {
        .ch-kpis { grid-template-columns: 1fr; }
        .ch-pad, .ch-findings, .ch-mounts, .ch-services { padding-left: 16px; padding-right: 16px; }
        .ch-panel-head { padding: 22px 16px 16px; }
        .ch-trow { grid-template-columns: 1fr 1fr; }
        .ch-trow > div { grid-column: 1 / -1; order: 3; }
    }
</style>
@endpush