<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'NADI')</title>

    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Cdefs%3E%3ClinearGradient id='g' x1='0' y1='0' x2='1' y2='0.35'%3E%3Cstop offset='0' stop-color='%232f4cf0'/%3E%3Cstop offset='0.55' stop-color='%230a8fe0'/%3E%3Cstop offset='1' stop-color='%2308b5c4'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='64' height='64' rx='18' fill='url(%23g)'/%3E%3Cg transform='translate(15 15) scale(1.4167)' fill='none' stroke='%23fff' stroke-width='2.6' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M2 12h5l2-4 3 9 3-13 2 8h5'/%3E%3C/g%3E%3C/svg%3E">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    {{-- Terapkan tema tersimpan sebelum halaman digambar agar tidak berkedip --}}
    <script>
        (function () {
            var theme = 'light';
            try { theme = localStorage.getItem('nadi-theme') === 'dark' ? 'dark' : 'light'; } catch (e) {}
            document.documentElement.setAttribute('data-theme', theme);

            try {
                if (localStorage.getItem('nadi-sidebar') === 'hidden') {
                    document.documentElement.classList.add('sidebar-hidden');
                }
            } catch (e) {}
        })();
    </script>

    <style>
        * { box-sizing: border-box; }

        :root {
            color-scheme: light;

            --bg: #eaf0fb;
            --surface: #ffffff;
            --surface-2: #f6f8fe;
            --ink: #0b1430;
            --muted: #5b6685;
            --line: #dce3f1;
            --accent: #2f4cf0;
            --accent-soft: rgba(47, 76, 240, .10);
            --cyan: #08b5c4;
            --ok: #12a150;
            --ok-soft: rgba(18, 161, 80, .12);
            --danger: #d92d20;
            --grid: rgba(30, 41, 110, .07);
            --glow-a: rgba(47, 76, 240, .18);
            --glow-b: rgba(8, 181, 196, .20);
            --shadow: 0 24px 50px -32px rgba(30, 41, 110, .5);
            --sidebar-bg: #ffffff;
            --topbar-bg: rgba(255, 255, 255, .92);
            --brand: linear-gradient(100deg, #2f4cf0 0%, #0a8fe0 55%, #08b5c4 100%);

            --sidebar-width: 262px;
            --font: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', Arial, sans-serif;
            --mono: 'JetBrains Mono', ui-monospace, 'SFMono-Regular', Menlo, Consolas, monospace;

            /* Alias agar halaman lain yang memakai nama variabel lama tetap berfungsi */
            --main-bg: var(--bg);
            --text-primary: var(--ink);
            --text-secondary: var(--muted);
            --border-color: var(--line);
        }

        :root[data-theme="dark"] {
            color-scheme: dark;

            --bg: #060b1a;
            --surface: #0d1530;
            --surface-2: #121c3f;
            --ink: #e8eeff;
            --muted: #8e9bc0;
            --line: #1f2b52;
            --accent: #6b86ff;
            --accent-soft: rgba(107, 134, 255, .15);
            --cyan: #22d3e6;
            --ok: #34d27b;
            --ok-soft: rgba(52, 210, 123, .14);
            --danger: #ff6b60;
            --grid: rgba(120, 150, 255, .07);
            --glow-a: rgba(47, 76, 240, .30);
            --glow-b: rgba(8, 181, 196, .16);
            --shadow: 0 24px 50px -30px rgba(0, 0, 0, .85);
            --sidebar-bg: #0a1128;
            --topbar-bg: rgba(10, 16, 38, .92);
        }

        html, body { min-height: 100%; }

        body {
            margin: 0;
            font-family: var(--font);
            color: var(--ink);
            background:
                radial-gradient(720px 460px at 8% 0%, var(--glow-a), transparent 62%),
                radial-gradient(760px 500px at 100% 100%, var(--glow-b), transparent 62%),
                var(--bg);
            background-attachment: fixed;
            -webkit-font-smoothing: antialiased;
        }

        /* Grid presisi sebagai latar */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 0;
            background-image:
                linear-gradient(var(--grid) 1px, transparent 1px),
                linear-gradient(90deg, var(--grid) 1px, transparent 1px);
            background-size: 44px 44px;
            -webkit-mask-image: radial-gradient(ellipse 85% 75% at 60% 30%, #000 10%, transparent 80%);
            mask-image: radial-gradient(ellipse 85% 75% at 60% 30%, #000 10%, transparent 80%);
            pointer-events: none;
        }

        .app-layout { position: relative; z-index: 1; min-height: 100vh; }

        /* ---------- Sidebar ---------- */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            border-right: 1px solid var(--line);
            display: flex;
            flex-direction: column;
            z-index: 1000;
            transition: transform .25s ease, visibility 0s;
        }

        .sidebar-brand {
            height: 76px;
            padding: 0 22px;
            display: flex;
            align-items: center;
            gap: 13px;
            border-bottom: 1px solid var(--line);
        }

        .brand-logo {
            width: 42px;
            height: 42px;
            border-radius: 13px;
            background: var(--brand);
            display: grid;
            place-items: center;
            flex-shrink: 0;
            box-shadow: 0 12px 24px -12px rgba(47, 76, 240, .9), 0 0 0 4px var(--accent-soft);
        }

        .brand-logo svg { width: 23px; height: 23px; }

        .brand-name {
            font-size: 17px;
            font-weight: 800;
            letter-spacing: .24em;
            line-height: 1;
        }

        .brand-subtitle {
            margin-top: 6px;
            font: 500 9.5px/1.35 var(--mono);
            color: var(--muted);
        }

        .sidebar-menu { padding: 22px 14px; flex: 1; overflow-y: auto; }

        .menu-section { margin-bottom: 24px; }

        .menu-heading {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 12px;
            margin-bottom: 10px;
            font: 500 11px var(--mono);
            color: var(--muted);
        }

        .menu-heading::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--line);
        }

        .menu-link {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            margin-bottom: 4px;
            border-radius: 11px;
            color: var(--muted);
            font-size: 13.5px;
            font-weight: 600;
            text-decoration: none;
            transition: background .2s, color .2s;
        }

        .menu-link:hover { background: var(--accent-soft); color: var(--ink); }

        .menu-link.active {
            background: var(--accent-soft);
            color: var(--accent);
            box-shadow: inset 3px 0 0 var(--accent);
        }

        .menu-icon { width: 20px; height: 20px; flex-shrink: 0; }

        .sidebar-footer { border-top: 1px solid var(--line); padding: 16px; }

        .sidebar-footer-text {
            font: 500 10.5px/1.7 var(--mono);
            color: var(--muted);
            text-align: center;
        }

        /* ---------- Area utama ---------- */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: margin-left .25s ease;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 100;
            height: 68px;
            padding: 0 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            background: var(--topbar-bg);
            border-bottom: 1px solid var(--line);
        }

        .topbar-left { display: flex; align-items: center; gap: 14px; min-width: 0; }

        .page-context {
            font: 500 12px var(--mono);
            color: var(--muted);
            white-space: nowrap;
        }

        .page-context b { color: var(--ink); font-weight: 500; }
        .page-context i { font-style: normal; margin: 0 7px; color: var(--line); }

        .topbar-right { display: flex; align-items: center; gap: 10px; }

        .clock {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-right: 6px;
            padding: 7px 12px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: var(--surface-2);
            font: 500 12px var(--mono);
            color: var(--muted);
        }

        .clock b { color: var(--ink); font-weight: 500; }

        .icon-btn {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            padding: 0;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: var(--surface);
            color: var(--muted);
            cursor: pointer;
            transition: color .2s, border-color .2s, background .2s;
        }

        .icon-btn[hidden] { display: none; }
        .icon-btn svg { width: 18px; height: 18px; }
        .icon-btn:hover { color: var(--accent); border-color: var(--accent); background: var(--accent-soft); }

        /* Ikon bergantian sesuai status */
        .icon-sun, .icon-compress { display: none; }
        :root[data-theme="dark"] .icon-sun { display: block; }
        :root[data-theme="dark"] .icon-moon { display: none; }
        .icon-btn.is-on .icon-compress { display: block; }
        .icon-btn.is-on .icon-expand { display: none; }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: 6px;
            padding-left: 14px;
            border-left: 1px solid var(--line);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 11px;
            background: var(--brand);
            color: #fff;
            display: grid;
            place-items: center;
            font-weight: 800;
            font-size: 14px;
            box-shadow: 0 8px 18px -10px rgba(47, 76, 240, .9);
        }

        .user-info-name { font-size: 12.5px; font-weight: 700; }
        .user-info-role { margin-top: 3px; font: 500 10.5px var(--mono); color: var(--muted); }

        .logout-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 38px;
            padding: 0 14px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: var(--surface);
            color: var(--muted);
            font: 700 12px var(--font);
            cursor: pointer;
            transition: color .2s, border-color .2s, background .2s;
        }

        .logout-button svg { width: 16px; height: 16px; }
        .logout-button:hover { color: var(--danger); border-color: var(--danger); background: rgba(217, 45, 32, .07); }

        :focus-visible { outline: 3px solid var(--accent-soft); outline-offset: 2px; box-shadow: 0 0 0 2px var(--accent); }

        /* ---------- Konten ---------- */
        .content-wrapper { flex: 1; padding: 32px; }

        .page-header { margin-bottom: 26px; }

        .page-title {
            margin: 0 0 8px;
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -.02em;
        }

        .page-description { margin: 0; font-size: 13.5px; color: var(--muted); }

        .content-card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--shadow);
        }

        /* Tanda sudut ala panel instrumen (dipakai kartu utama) */
        .corner {
            position: absolute;
            width: 16px;
            height: 16px;
            border: 0 solid var(--accent);
            pointer-events: none;
        }

        .corner.tl { top: -8px; left: -8px; border-top-width: 2px; border-left-width: 2px; border-top-left-radius: 5px; }
        .corner.tr { top: -8px; right: -8px; border-top-width: 2px; border-right-width: 2px; border-top-right-radius: 5px; }
        .corner.bl { bottom: -8px; left: -8px; border-bottom-width: 2px; border-left-width: 2px; border-bottom-left-radius: 5px; }
        .corner.br { bottom: -8px; right: -8px; border-bottom-width: 2px; border-right-width: 2px; border-bottom-right-radius: 5px; }

        .app-footer {
            padding: 18px 32px;
            font: 500 11px var(--mono);
            color: var(--muted);
            text-align: center;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(6, 11, 26, .55);
            z-index: 999;
        }

        /* ---------- Sidebar tersembunyi (desktop) ---------- */
        @media (min-width: 901px) {
            :root.sidebar-hidden .sidebar {
                transform: translateX(-100%);
                visibility: hidden;
                transition: transform .25s ease, visibility 0s linear .25s;
            }

            :root.sidebar-hidden .main-wrapper { margin-left: 0; }
        }

        /* ---------- Responsif ---------- */
        @media (max-width: 1180px) {
            .clock { display: none; }
        }

        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .sidebar-overlay.show { display: block; }
            .main-wrapper { margin-left: 0; }
            .menu-toggle { display: grid; }
            .topbar { padding: 0 20px; }
            .content-wrapper { padding: 24px 20px; }
        }

        @media (max-width: 600px) {
            .topbar { height: 62px; padding: 0 14px; }
            .topbar-right { gap: 8px; }
            .page-context { display: none; }
            .user-info { display: none; }
            .user-profile { padding-left: 10px; margin-left: 2px; }
            .logout-button { width: 38px; padding: 0; justify-content: center; }
            .logout-button span { display: none; }
            .content-wrapper { padding: 20px 14px; }
            .page-title { font-size: 22px; }
            .app-footer { padding: 16px; }
        }
    </style>

    @stack('styles')
</head>
<body>

    <div class="app-layout">

        <aside class="sidebar" id="sidebar">

            <div class="sidebar-brand">
                <div class="brand-logo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 12h5l2-4 3 9 3-13 2 8h5"/>
                    </svg>
                </div>

                <div>
                    <div class="brand-name">NADI</div>
                    <div class="brand-subtitle">Monitoring Platform <br> PIA Tengaran</div>
                </div>
            </div>

            <nav class="sidebar-menu">

                <div class="menu-section">
                    <div class="menu-heading">Overview</div>

                    <a href="{{ route('dashboard') }}" class="menu-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <svg class="menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="2"/><rect x="14" y="3" width="7" height="5" rx="2"/><rect x="14" y="12" width="7" height="9" rx="2"/><rect x="3" y="16" width="7" height="5" rx="2"/></svg>
                        <span>Dashboard</span>
                    </a>
                </div>

                <div class="menu-section">
                    <div class="menu-heading">Monitoring</div>

                    <a href="{{ route('monitoring.servers') }}" class="menu-link {{ request()->routeIs('monitoring.servers') ? 'active' : '' }}">
                        <svg class="menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="6" rx="2"/><rect x="3" y="14" width="18" height="6" rx="2"/><path d="M7 7h.01M7 17h.01"/></svg>
                        <span>Servers</span>
                    </a>

                    <a href="{{ route('monitoring.applications') }}" class="menu-link {{ request()->routeIs('monitoring.applications') ? 'active' : '' }}">
                        <svg class="menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="3"/><path d="M3 9h18M7 14h4"/></svg>
                        <span>Applications</span>
                    </a>

                    <a href="{{ route('monitoring.databases') }}" class="menu-link {{ request()->routeIs('monitoring.databases') ? 'active' : '' }}">
                        <svg class="menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M46v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/></svg>
                        <span>Databases</span>
                    </a>

                    <a href="{{ route('monitoring.central-health') }}" class="menu-link {{ request()->routeIs('monitoring.central-health') ? 'active' : '' }}">
                        <svg class="menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 12h4l2-6 4 12 2-6h6"/>
                            <path d="M4 4h16v16H4z"/>
                        </svg>
                        <span>Central Health</span>
                    </a>

                    <a href="{{ route('management.telemetry') }}" class="menu-link {{ request()->routeIs('management.telemetry', 'management.telemetry.*') ? 'active' : '' }}">
                        <svg class="menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 12h4l3-7 4 14 3-7h4"/>
                            <path d="M3 3v18h18"/>
                        </svg>
                        <span>Telemetry</span>
                    </a>
                </div>

                <div class="menu-section">
                    <div class="menu-heading">Management</div>

                    <a href="{{ route('management.agents') }}" class="menu-link {{ request()->routeIs('management.agents') ? 'active' : '' }}">
                        <svg class="menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="6" width="12" height="12" rx="2"/><rect x="9.5" y="9.5" width="5" height="5" rx="1"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/></svg>
                        <span>Agents</span>
                    </a>

                    <a href="{{ route('management.registrations') }}" class="menu-link {{ request()->routeIs('management.registrations') ? 'active' : '' }}">
                        <svg class="menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4" width="14" height="17" rx="3"/><path d="M9 4V3h6v1M9 13l2 2 4-4"/></svg>
                        <span>Registrations</span>
                    </a>

                    <a href="{{ route('management.enrollment-tokens') }}" class="menu-link {{ request()->routeIs('management.enrollment-tokens') ? 'active' : '' }}">
                        <svg class="menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="8" cy="15" r="4"/>
                            <path d="m10.85 12.15 8.15-8.15 2 2-2 2-2-2-2 2"/>
                            <path d="M6.5 15h.01"/>
                        </svg>
                        <span>Enrollment Tokens</span>
                    </a>

                    <a href="{{ route('management.approval-workflow') }}" class="menu-link {{ request()->routeIs('management.approval-workflow*') ? 'active' : '' }}">
                        <svg class="menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 11l3 3L22 4"/>
                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                        </svg>
                        <span>Approval Workflow</span>
                    </a>

                    <a href="{{ route('management.activation-grants') }}" class="menu-link {{ request()->routeIs('management.activation-grants*') ? 'active' : '' }}">
                        <svg class="menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11z"/>
                            <path d="M9 12l2 2 4-4"/>
                        </svg>
                        <span>Activation Grants</span>
                    </a>

                    <a href="{{ route('management.telemetry-retention') }}" class="menu-link {{ request()->routeIs('management.telemetry-retention*') ? 'active' : '' }}">
                        <svg class="menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 6h18"/>
                            <path d="M8 6V4h8v2"/>
                            <path d="M6 6l1 14h10l1-14"/>
                            <path d="M10 10v6M14 10v6"/>
                        </svg>
                        <span>Telemetry Retention</span>
                    </a>
                </div>

            </nav>

            <div class="sidebar-footer">
                <div class="sidebar-footer-text">
                    NADI Monitoring Platform
                    <br>
                    Central Console
                </div>
            </div>

        </aside>

        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <div class="main-wrapper">

            <header class="topbar">

                <div class="topbar-left">
                    <button type="button" class="icon-btn menu-toggle" id="menuToggle" aria-label="Buka navigasi">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    </button>

                    <div class="page-context">
                        NADI Central<i>/</i><b>@yield('breadcrumb', 'Dashboard')</b>
                    </div>
                </div>

                <div class="topbar-right">

                    <div class="clock" aria-hidden="true">
                        <span id="clockDate">--</span>
                        <b id="clockTime">--:--:--</b>
                    </div>

                    <button type="button" class="icon-btn" id="fullscreenToggle" aria-label="Layar penuh" title="Layar penuh">
                        <svg class="icon-expand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v3M16 3h3a2 2 0 0 1 2 2v3M8 21H5a2 2 0 0 1-2-2v-3M16 21h3a2 2 0 0 0 2-2v-3"/></svg>
                        <svg class="icon-compress" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8h3a2 2 0 0 0 2-2V3M21 8h-3a2 2 0 0 1-2-2V3M3 16h3a2 2 0 0 1 2 2v3M21 16h-3a2 2 0 0 0-2 2v3"/></svg>
                    </button>

                    <button type="button" class="icon-btn" id="themeToggle" aria-label="Ganti ke mode gelap" aria-pressed="false" title="Ganti tema">
                        <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                        <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                    </button>

                    <div class="user-profile">

                        <div class="user-avatar">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </div>

                        <div class="user-info">
                            <div class="user-info-name">{{ auth()->user()->name }}</div>
                            <div class="user-info-role">Administrator</div>
                        </div>

                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf

                        <button type="submit" class="logout-button" title="Logout">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                            <span>Logout</span>
                        </button>
                    </form>

                </div>

            </header>

            <main class="content-wrapper">

                @yield('content')

            </main>

            <footer class="app-footer">
                NADI Monitoring Platform &copy; {{ date('Y') }}
            </footer>

        </div>

    </div>

    <script>
        (function () {
            var root = document.documentElement;
            var sidebar = document.getElementById('sidebar');
            var overlay = document.getElementById('sidebarOverlay');
            var menuToggle = document.getElementById('menuToggle');
            var themeBtn = document.getElementById('themeToggle');
            var fsBtn = document.getElementById('fullscreenToggle');

            /* ----- Sidebar: desktop = sembunyikan/tampilkan, mobile = drawer ----- */
            function isDesktop() { return window.innerWidth > 900; }

            function syncMenuButton() {
                var text, expanded;

                if (isDesktop()) {
                    var hidden = root.classList.contains('sidebar-hidden');
                    text = hidden ? 'Tampilkan sidebar' : 'Sembunyikan sidebar';
                    expanded = !hidden;
                } else {
                    var open = sidebar.classList.contains('open');
                    text = open ? 'Tutup navigasi' : 'Buka navigasi';
                    expanded = open;
                }

                menuToggle.setAttribute('aria-label', text);
                menuToggle.setAttribute('title', text);
                menuToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            }

            function openSidebar() {
                sidebar.classList.add('open');
                overlay.classList.add('show');
                syncMenuButton();
            }

            function closeSidebar() {
                sidebar.classList.remove('open');
                overlay.classList.remove('show');
                syncMenuButton();
            }

            menuToggle.addEventListener('click', function () {
                if (isDesktop()) {
                    var hide = !root.classList.contains('sidebar-hidden');

                    root.classList.toggle('sidebar-hidden', hide);

                    try { localStorage.setItem('nadi-sidebar', hide ? 'hidden' : 'visible'); } catch (e) {}

                    syncMenuButton();

                    // Beri tahu komponen (mis. grafik) bahwa lebar area konten berubah
                    setTimeout(function () { window.dispatchEvent(new Event('resize')); }, 280);
                } else if (sidebar.classList.contains('open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });

            syncMenuButton();

            overlay.addEventListener('click', closeSidebar);

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { closeSidebar(); }
            });

            window.addEventListener('resize', function () {
                if (window.innerWidth > 900) { closeSidebar(); } else { syncMenuButton(); }
            });

            /* ----- Tema terang / gelap ----- */
            function syncThemeButton() {
                var dark = root.getAttribute('data-theme') === 'dark';
                var text = dark ? 'Ganti ke mode terang' : 'Ganti ke mode gelap';

                themeBtn.setAttribute('aria-pressed', dark ? 'true' : 'false');
                themeBtn.setAttribute('aria-label', text);
                themeBtn.setAttribute('title', text);
            }

            themeBtn.addEventListener('click', function () {
                var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';

                root.setAttribute('data-theme', next);

                try { localStorage.setItem('nadi-theme', next); } catch (e) {}

                syncThemeButton();
                document.dispatchEvent(new CustomEvent('nadi:theme', { detail: { theme: next } }));
            });

            syncThemeButton();

            /* ----- Layar penuh ----- */
            var requestFs = root.requestFullscreen || root.webkitRequestFullscreen;

            function fsElement() {
                return document.fullscreenElement || document.webkitFullscreenElement || null;
            }

            function syncFsButton() {
                var on = !!fsElement();
                var text = on ? 'Keluar dari layar penuh' : 'Layar penuh';

                fsBtn.classList.toggle('is-on', on);
                fsBtn.setAttribute('aria-label', text);
                fsBtn.setAttribute('title', text);
            }

            if (!requestFs) {
                fsBtn.hidden = true;
            } else {
                fsBtn.addEventListener('click', function () {
                    if (fsElement()) {
                        (document.exitFullscreen || document.webkitExitFullscreen).call(document);
                    } else {
                        var result = requestFs.call(root);
                        if (result && typeof result.catch === 'function') { result.catch(function () {}); }
                    }
                });

                document.addEventListener('fullscreenchange', syncFsButton);
                document.addEventListener('webkitfullscreenchange', syncFsButton);
            }

            /* ----- Jam ----- */
            var clockDate = document.getElementById('clockDate');
            var clockTime = document.getElementById('clockTime');

            function pad(n) { return n < 10 ? '0' + n : '' + n; }

            function tick() {
                var d = new Date();

                try {
                    clockDate.textContent = d.toLocaleDateString('id-ID', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });
                } catch (e) {
                    clockDate.textContent = d.toDateString();
                }

                clockTime.textContent = pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
            }

            tick();
            setInterval(tick, 1000);
        })();
    </script>

    @stack('scripts')

</body>
</html>