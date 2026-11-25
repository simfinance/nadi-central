@extends('layouts.app')

@section('title', 'Grafana Dashboards - NADI Central')

@section('breadcrumb', 'Monitoring / Grafana Dashboards')

@push('styles')
<style>
    .grafana-page
    {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .grafana-page .page-header
    {
        margin-bottom: 0;
    }

    .grafana-status-panel
    {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        padding: 18px 20px;
        border: 1px solid var(--line);
        border-radius: 14px;
        background: var(--surface);
        box-shadow: var(--shadow);
    }

    .grafana-status-title
    {
        color: var(--ink);
        font-size: 13px;
        font-weight: 800;
    }

    .grafana-status-description
    {
        margin-top: 5px;
        color: var(--muted);
        font: 500 11px var(--mono);
    }

    .grafana-status
    {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 6px 10px;
        border: 1px solid var(--line);
        border-radius: 999px;
        color: var(--muted);
        font: 700 10px var(--mono);
        text-transform: uppercase;
    }

    .grafana-status::before
    {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: currentColor;
        content: '';
    }

    .grafana-status.is-up
    {
        border-color: color-mix(in srgb, var(--ok) 35%, var(--line));
        background: var(--ok-soft);
        color: var(--ok);
    }

    .grafana-status.is-down
    {
        border-color: color-mix(in srgb, var(--danger) 35%, var(--line));
        background: var(--danger-soft);
        color: var(--danger);
    }

    .grafana-frame-panel
    {
        overflow: hidden;
        min-height: 760px;
        border: 1px solid var(--line);
        border-radius: 16px;
        background: var(--surface);
        box-shadow: var(--shadow);
    }

    .grafana-frame
    {
        display: block;
        width: 100%;
        min-height: 760px;
        border: 0;
        background: var(--surface);
    }

    .grafana-empty
    {
        display: grid;
        min-height: 420px;
        align-content: center;
        justify-items: center;
        gap: 12px;
        padding: 32px;
        text-align: center;
    }

    .grafana-empty-title
    {
        color: var(--ink);
        font-size: 16px;
        font-weight: 800;
    }

    .grafana-empty-description
    {
        max-width: 620px;
        color: var(--muted);
        font-size: 12px;
        line-height: 1.7;
    }

    .grafana-security-note
    {
        padding: 13px 16px;
        border-left: 3px solid var(--accent);
        border-radius: 8px;
        background: var(--accent-soft);
        color: var(--muted);
        font-size: 11px;
        line-height: 1.7;
    }

    @media (max-width: 700px)
    {
        .grafana-frame-panel,
        .grafana-frame
        {
            min-height: 620px;
        }
    }
</style>
@endpush

@section('content')
<div class="grafana-page">
    <div class="page-header">
        <h1 class="page-title">Grafana Dashboards</h1>
        <p class="page-description">
            Visualisasi time-series NADI melalui Grafana, dengan autentikasi yang terhubung ke sesi Central.
        </p>
    </div>

    <section class="grafana-status-panel">
        <div>
            <div class="grafana-status-title">Grafana Service</div>
            <div class="grafana-status-description">
                Pemeriksaan koneksi internal dari NADI Central
            </div>
        </div>

        <span class="grafana-status {{ $grafanaReachable ? 'is-up' : 'is-down' }}">
            {{ $grafanaReachable ? 'Reachable' : 'Unavailable' }}
        </span>
    </section>

    <section class="grafana-frame-panel">
        @if ($iframeUrl)
            <iframe
                class="grafana-frame"
                src="{{ $iframeUrl }}"
                title="NADI Central Grafana Dashboard"
                loading="lazy"
                referrerpolicy="same-origin"
                allow="fullscreen"
            ></iframe>
        @else
            <div class="grafana-empty">
                <div class="grafana-empty-title">
                    {{ $grafanaReachable ? 'Dashboard belum dikonfigurasi' : 'Grafana belum dapat dijangkau' }}
                </div>

                <p class="grafana-empty-description">
                    @if (! $grafanaReachable)
                        Periksa service Grafana dan konfigurasi alamat internal NADI Central. Halaman ini tidak akan mengalihkan pengguna ke endpoint Grafana eksternal.
                    @else
                        Konfigurasikan NADI_GRAFANA_DASHBOARD_UID dengan UID dashboard NADI yang sudah dibuat di Grafana, kemudian perbarui cache konfigurasi Laravel.
                    @endif
                </p>
            </div>
        @endif
    </section>

    <div class="grafana-security-note">
        Akses dashboard ini menggunakan sesi NADI Central dan MFA. Reverse proxy harus memvalidasi sesi melalui endpoint autentikasi internal sebelum meneruskan permintaan ke Grafana. Jangan mengaktifkan anonymous access atau membuka port Grafana ke jaringan publik.
    </div>
</div>
@endsection
