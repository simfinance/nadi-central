@extends('layouts.app')

@section('title', 'Applications - NADI Console')

@section('breadcrumb', 'Monitoring / Applications')

@push('styles')
<style>
    .applications-page,
    .swal2-container
    {
        --status-ok: #16a34a;
        --status-warn: #d97706;
        --status-bad: #dc2626;
    }

    .applications-page
    {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .applications-page .page-header
    {
        margin-bottom: 0;
    }

    /* ---------- Ringkasan ---------- */
    .applications-summary
    {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
    }

    .application-summary-card
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

    .application-summary-card:hover
    {
        transform: translateY(-3px);
        border-color: color-mix(in srgb, var(--accent) 45%, var(--line));
        box-shadow: var(--shadow), 0 18px 40px -22px var(--accent);
    }

    .application-summary-card::before
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
    .application-summary-card::after
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

    .application-summary-top
    {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
    }

    .application-summary-icon
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

    .application-summary-icon svg
    {
        width: 19px;
        height: 19px;
    }

    .application-summary-label
    {
        font: 600 11.5px var(--mono);
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .application-summary-value
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

    .application-summary-percent
    {
        padding: 3px 9px;
        border: 1px solid color-mix(in srgb, var(--accent) 35%, transparent);
        border-radius: 999px;
        background: var(--accent-soft);
        color: var(--accent);
        font: 600 11px var(--mono);
        letter-spacing: 0;
    }

    .application-summary-description
    {
        margin-top: 8px;
        font: 500 11.5px var(--mono);
        color: var(--muted);
    }

    .application-summary-meter
    {
        height: 4px;
        margin-top: 16px;
        border-radius: 999px;
        background: var(--surface-2);
        overflow: hidden;
    }

    .application-summary-meter-fill
    {
        height: 100%;
        border-radius: 999px;
        background: var(--brand);
        box-shadow: 0 0 12px var(--accent);
        transition: width .8s cubic-bezier(.2, .8, .2, 1);
    }

    /* ---------- Panel inventaris ---------- */
    .applications-panel
    {
        position: relative;
        border: 1px solid var(--line);
        border-radius: 18px;
        background: var(--surface);
        box-shadow: var(--shadow);
        overflow: hidden;
    }

    .applications-panel::before
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

    .applications-panel-header
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

    .applications-panel-title
    {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 16px;
        font-weight: 800;
    }

    .applications-panel-live
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

    .applications-panel-live::before
    {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
        animation: applications-pulse 2s ease-out infinite;
    }

    .applications-panel-subtitle
    {
        margin-top: 5px;
        font: 500 12px var(--mono);
        color: var(--muted);
    }

    .applications-filter
    {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .applications-search
    {
        position: relative;
    }

    .applications-search svg
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

    .applications-search:focus-within svg
    {
        color: var(--accent);
    }

    .applications-filter input,
    .applications-filter select
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

    .applications-filter input
    {
        width: 230px;
        padding-left: 36px;
    }

    .applications-filter input::placeholder
    {
        color: var(--muted);
        opacity: .8;
    }

    .applications-filter input:focus,
    .applications-filter select:focus
    {
        background: var(--surface);
        border-color: var(--accent);
        box-shadow: 0 0 0 4px var(--accent-soft), 0 0 22px -8px var(--accent);
    }

    .applications-filter button
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

    .applications-filter button:hover
    {
        filter: brightness(1.08);
        transform: translateY(-1px);
    }

    .applications-reset
    {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 40px;
        padding: 0 18px;
        border: 1.5px solid var(--line);
        border-radius: 10px;
        background: transparent;
        color: var(--muted);
        font: 700 13px var(--font);
        text-decoration: none;
        transition: border-color .2s, color .2s, transform .2s;
    }

    .applications-reset:hover
    {
        border-color: var(--accent);
        color: var(--accent);
        transform: translateY(-1px);
    }

    /* ---------- Tabel ---------- */
    .applications-table-wrapper
    {
        overflow-x: auto;
    }

    .applications-table
    {
        width: 100%;
        border-collapse: collapse;
        white-space: nowrap;
    }

    .applications-table th
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

    .applications-table td
    {
        padding: 16px 20px;
        border-bottom: 1px solid var(--line);
        font-size: 13px;
        color: var(--muted);
        vertical-align: middle;
    }

    .applications-table tbody tr:last-child td
    {
        border-bottom: 0;
    }

    .applications-table tbody tr
    {
        transition: background .2s;
    }

    .applications-table tbody tr:hover
    {
        background: var(--accent-soft);
    }

    .applications-table tbody tr td:first-child
    {
        box-shadow: inset 0 0 0 var(--accent);
        transition: box-shadow .2s;
    }

    .applications-table tbody tr:hover td:first-child
    {
        box-shadow: inset 3px 0 0 var(--accent);
    }

    .application-main
    {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .application-main-icon
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

    .application-main-icon svg
    {
        width: 16px;
        height: 16px;
    }

    .application-name
    {
        font-size: 13px;
        font-weight: 700;
        color: var(--ink);
    }

    .application-framework
    {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 4px;
        font: 500 11.5px var(--mono);
        color: var(--muted);
    }

    .application-php
    {
        padding: 1px 7px;
        border: 1px dashed color-mix(in srgb, var(--accent) 45%, var(--line));
        border-radius: 6px;
        color: var(--accent);
        font: 600 10.5px var(--mono);
    }

    .application-environment
    {
        display: inline-flex;
        padding: 5px 11px;
        border: 1px solid var(--line);
        border-radius: 8px;
        background: var(--surface-2);
        color: var(--ink);
        font: 600 11.5px var(--mono);
    }

    .application-server
    {
        font-weight: 600;
        color: var(--ink);
    }

    .application-server-ip
    {
        display: inline-block;
        margin-top: 5px;
        padding: 2px 8px;
        border: 1px solid var(--line);
        border-radius: 6px;
        background: var(--surface-2);
        font: 500 11px var(--mono);
        color: var(--muted);
    }

    .application-endpoint
    {
        max-width: 260px;
        overflow: hidden;
        text-overflow: ellipsis;
        font: 500 12px var(--mono);
        color: var(--ink);
    }

    .application-endpoint-prefix
    {
        margin-top: 4px;
        font: 500 11px var(--mono);
        color: var(--accent);
    }

    /* ---------- Status ---------- */
    .application-status
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

    .application-status-dot
    {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
    }

    .application-status.approved,
    .application-status.active
    {
        border-color: color-mix(in srgb, var(--status-ok) 40%, transparent);
        background: color-mix(in srgb, var(--status-ok) 14%, transparent);
        color: var(--status-ok);
        box-shadow: 0 0 18px -8px var(--status-ok);
    }

    .application-status.approved .application-status-dot,
    .application-status.active .application-status-dot
    {
        animation: applications-pulse 2s ease-out infinite;
    }

    .application-status.pending,
    .application-status.dormant
    {
        border-color: color-mix(in srgb, var(--status-warn) 40%, transparent);
        background: color-mix(in srgb, var(--status-warn) 14%, transparent);
        color: var(--status-warn);
    }

    .application-status.rejected,
    .application-status.revoked,
    .application-status.failed
    {
        border-color: color-mix(in srgb, var(--status-bad) 40%, transparent);
        background: color-mix(in srgb, var(--status-bad) 14%, transparent);
        color: var(--status-bad);
    }

    .application-status.suspended,
    .application-status.inactive
    {
        border-color: var(--line);
        background: var(--surface-2);
        color: var(--muted);
    }

    /* ---------- Kredensial ---------- */
    .application-credential-count
    {
        display: flex;
        align-items: baseline;
        gap: 6px;
        font: 700 14px var(--mono);
        color: var(--ink);
    }

    .application-credential-active
    {
        margin-top: 4px;
        font: 500 11px var(--mono);
        color: var(--muted);
    }

    .application-credential-meter
    {
        width: 72px;
        height: 3px;
        margin-top: 8px;
        border-radius: 999px;
        background: var(--surface-2);
        overflow: hidden;
    }

    .application-credential-meter-fill
    {
        height: 100%;
        border-radius: 999px;
        background: var(--brand);
        box-shadow: 0 0 8px var(--accent);
    }

    /* ---------- Aksi ---------- */
    .application-action-button
    {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        height: 36px;
        padding: 0 14px;
        border: 1.5px solid transparent;
        border-radius: 10px;
        font: 700 12.5px var(--font);
        cursor: pointer;
        transition: filter .2s, transform .2s, background .2s, box-shadow .2s;
    }

    .application-action-button svg
    {
        width: 14px;
        height: 14px;
    }

    .application-action-button:hover
    {
        transform: translateY(-1px);
    }

    .application-action-button:disabled
    {
        opacity: .6;
        cursor: not-allowed;
        transform: none;
    }

    .application-action-button.suspend
    {
        border-color: color-mix(in srgb, var(--status-warn) 40%, transparent);
        background: color-mix(in srgb, var(--status-warn) 14%, transparent);
        color: var(--status-warn);
    }

    .application-action-button.suspend:hover
    {
        background: color-mix(in srgb, var(--status-warn) 24%, transparent);
        box-shadow: 0 0 22px -8px var(--status-warn);
    }

    .application-action-button.resume
    {
        border-color: color-mix(in srgb, var(--status-ok) 40%, transparent);
        background: color-mix(in srgb, var(--status-ok) 14%, transparent);
        color: var(--status-ok);
    }

    .application-action-button.resume:hover
    {
        background: color-mix(in srgb, var(--status-ok) 24%, transparent);
        box-shadow: 0 0 22px -8px var(--status-ok);
    }

    .application-action-none
    {
        font: 500 12px var(--mono);
        color: var(--muted);
    }

    /* ---------- Kosong ---------- */
    .applications-empty
    {
        padding: 64px 24px;
        text-align: center;
    }

    .applications-empty-icon
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

    .applications-empty h3
    {
        margin: 0 0 8px;
        font-size: 16px;
        font-weight: 800;
    }

    .applications-empty p
    {
        margin: 0;
        font-size: 13px;
        color: var(--muted);
    }

    /* ---------- Pagination ---------- */
    .applications-pagination
    {
        padding: 18px 24px;
        border-top: 1px solid var(--line);
        font-size: 13px;
        color: var(--muted);
    }

    .applications-pagination svg
    {
        width: 16px;
        height: 16px;
    }

    .applications-pagination p
    {
        margin: 0;
        color: var(--muted);
    }

    .applications-pagination a
    {
        color: var(--accent);
        text-decoration: none;
    }

    /* ---------- SweetAlert (mengikuti tema terang dan gelap) ---------- */
    .swal2-container.swal2-backdrop-show,
    .swal2-container.swal2-noanimation
    {
        background: rgba(8, 12, 24, .6);
        backdrop-filter: blur(4px);
    }

    .swal2-popup.nadi-swal-popup
    {
        position: relative;
        width: 26em;
        max-width: 92vw;
        padding: 30px 24px 22px;
        border: 1px solid var(--line);
        border-radius: 18px;
        background: var(--surface);
        color: var(--ink);
        font-family: var(--font);
        box-shadow: var(--shadow), 0 30px 60px -30px var(--accent);
    }

    .swal2-popup.nadi-swal-popup::before
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

    .nadi-swal-title
    {
        padding: 0;
        font: 800 18px var(--font);
        color: var(--ink);
    }

    .nadi-swal-text
    {
        margin: 10px 0 0;
        font: 500 12.5px var(--mono);
        line-height: 1.6;
        color: var(--muted);
        white-space: pre-line;
        word-break: break-word;
    }

    .nadi-swal-actions
    {
        gap: 10px;
        margin-top: 22px;
    }

    .nadi-swal-btn
    {
        height: 40px;
        padding: 0 22px;
        border: 1.5px solid transparent;
        border-radius: 10px;
        font: 700 13px var(--font);
        cursor: pointer;
        transition: filter .2s, transform .2s, background .2s, box-shadow .2s, border-color .2s, color .2s;
    }

    .nadi-swal-btn:hover
    {
        transform: translateY(-1px);
    }

    .nadi-swal-btn:focus-visible
    {
        outline: none;
        box-shadow: 0 0 0 4px var(--accent-soft);
    }

    .nadi-swal-btn-primary
    {
        border: 0;
        background: var(--brand);
        color: #ffffff;
        box-shadow: 0 10px 22px -12px rgba(47, 76, 240, .9), inset 0 1px 0 rgba(255, 255, 255, .3);
    }

    .nadi-swal-btn-success
    {
        border-color: color-mix(in srgb, var(--status-ok) 45%, transparent);
        background: color-mix(in srgb, var(--status-ok) 16%, transparent);
        color: var(--status-ok);
    }

    .nadi-swal-btn-success:hover
    {
        background: color-mix(in srgb, var(--status-ok) 26%, transparent);
        box-shadow: 0 0 22px -8px var(--status-ok);
    }

    .nadi-swal-btn-warning
    {
        border-color: color-mix(in srgb, var(--status-warn) 45%, transparent);
        background: color-mix(in srgb, var(--status-warn) 16%, transparent);
        color: var(--status-warn);
    }

    .nadi-swal-btn-warning:hover
    {
        background: color-mix(in srgb, var(--status-warn) 26%, transparent);
        box-shadow: 0 0 22px -8px var(--status-warn);
    }

    .nadi-swal-btn-danger
    {
        border-color: color-mix(in srgb, var(--status-bad) 45%, transparent);
        background: color-mix(in srgb, var(--status-bad) 16%, transparent);
        color: var(--status-bad);
    }

    .nadi-swal-btn-danger:hover
    {
        background: color-mix(in srgb, var(--status-bad) 26%, transparent);
        box-shadow: 0 0 22px -8px var(--status-bad);
    }

    .nadi-swal-btn-secondary
    {
        border-color: var(--line);
        background: transparent;
        color: var(--muted);
    }

    .nadi-swal-btn-secondary:hover
    {
        border-color: var(--accent);
        color: var(--accent);
    }

    .nadi-swal-popup .swal2-icon.swal2-question
    {
        border-color: color-mix(in srgb, var(--accent) 45%, transparent);
        color: var(--accent);
    }

    .nadi-swal-popup .swal2-icon.swal2-warning
    {
        border-color: color-mix(in srgb, var(--status-warn) 45%, transparent);
        color: var(--status-warn);
    }

    .nadi-swal-popup .swal2-icon.swal2-error
    {
        border-color: color-mix(in srgb, var(--status-bad) 45%, transparent);
        color: var(--status-bad);
    }

    .nadi-swal-popup .swal2-icon.swal2-error [class^='swal2-x-mark-line']
    {
        background-color: var(--status-bad);
    }

    .nadi-swal-popup .swal2-loader
    {
        border-color: var(--accent) transparent var(--accent) transparent;
    }

    .swal2-popup.nadi-swal-toast
    {
        padding: 12px 16px;
        border: 1px solid var(--line);
        border-left: 3px solid var(--status-ok);
        border-radius: 12px;
        background: var(--surface);
        color: var(--ink);
        font-family: var(--font);
        box-shadow: var(--shadow), 0 18px 40px -22px var(--status-ok);
    }

    .nadi-swal-toast-title
    {
        margin: 0 0 0 10px;
        font: 500 12.5px var(--mono);
        color: var(--ink);
    }

    .nadi-swal-toast .swal2-icon.swal2-success
    {
        border-color: color-mix(in srgb, var(--status-ok) 40%, transparent);
        color: var(--status-ok);
    }

    .nadi-swal-toast .swal2-timer-progress-bar
    {
        background: var(--status-ok);
    }

    .nadi-swal-toast .swal2-success-ring
    {
        border-color: color-mix(in srgb, var(--status-ok) 30%, transparent);
    }

    .nadi-swal-toast [class^='swal2-success-line']
    {
        background-color: var(--status-ok);
    }

    .nadi-swal-toast [class^='swal2-success-circular-line'],
    .nadi-swal-toast .swal2-success-fix
    {
        background: transparent;
    }

    /* ---------- Animasi ---------- */
    @keyframes applications-pulse
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
        .applications-panel-live::before,
        .application-status.approved .application-status-dot,
        .application-status.active .application-status-dot
        {
            animation: none;
        }

        .application-summary-card,
        .application-summary-meter-fill,
        .application-action-button,
        .nadi-swal-btn
        {
            transition: none;
        }
    }

    @media (max-width: 1100px)
    {
        .applications-filter input
        {
            width: 190px;
        }
    }

    @media (max-width: 900px)
    {
        .applications-summary
        {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 600px)
    {
        .applications-summary
        {
            grid-template-columns: 1fr;
        }

        .applications-filter,
        .applications-search
        {
            width: 100%;
        }

        .applications-filter input
        {
            width: 100%;
        }

        .applications-filter select
        {
            flex: 1;
        }

        .applications-panel-header
        {
            padding: 22px 16px 16px;
        }
    }
</style>
@endpush

@section('content')
@php
    $totalBase = max((int) $totalApplications, 1);
    $pendingPercent = round(((int) $pendingRegistrations / $totalBase) * 100);
    $activePercent = round(((int) $activeMonitoring / $totalBase) * 100);
@endphp
<div class="applications-page">

    <div class="applications-header page-header">
        <div>
            <h1 class="page-title">Applications</h1>
            <p class="page-description">Monitor registered Laravel applications across NADI infrastructure.</p>
        </div>
    </div>

    <div class="applications-summary">

        <div class="application-summary-card">
            <div class="application-summary-top">
                <div class="application-summary-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="16" rx="3"/>
                        <path d="M3 9h18M7 14h4"/>
                    </svg>
                </div>
                <div class="application-summary-label">Total Applications</div>
            </div>
            <div class="application-summary-value">
                {{ number_format($totalApplications) }}
            </div>
            <div class="application-summary-description">
                Applications registered in NADI
            </div>
            <div class="application-summary-meter">
                <div class="application-summary-meter-fill" style="width: 100%;"></div>
            </div>
        </div>

        <div class="application-summary-card">
            <div class="application-summary-top">
                <div class="application-summary-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M12 7.5V12l3 2"/>
                    </svg>
                </div>
                <div class="application-summary-label">Pending Registration</div>
            </div>
            <div class="application-summary-value">
                {{ number_format($pendingRegistrations) }}
                <span class="application-summary-percent">{{ $pendingPercent }}%</span>
            </div>
            <div class="application-summary-description">
                Waiting for registration review
            </div>
            <div class="application-summary-meter">
                <div class="application-summary-meter-fill" style="width: {{ $pendingPercent }}%;"></div>
            </div>
        </div>

        <div class="application-summary-card">
            <div class="application-summary-top">
                <div class="application-summary-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 12h4l3-8 4 16 3-8h4"/>
                    </svg>
                </div>
                <div class="application-summary-label">Active Monitoring</div>
            </div>
            <div class="application-summary-value">
                {{ number_format($activeMonitoring) }}
                <span class="application-summary-percent">{{ $activePercent }}%</span>
            </div>
            <div class="application-summary-description">
                Monitoring status active
            </div>
            <div class="application-summary-meter">
                <div class="application-summary-meter-fill" style="width: {{ $activePercent }}%;"></div>
            </div>
        </div>

    </div>

    <div class="applications-panel">

        <div class="applications-panel-header">
            <div>
                <div class="applications-panel-title">
                    Application Inventory
                    <span class="applications-panel-live">Live</span>
                </div>
                <div class="applications-panel-subtitle">
                    {{ $applications->total() }} application(s) found
                </div>
            </div>

            <form method="GET" action="{{ route('monitoring.applications') }}" class="applications-filter">

                <div class="applications-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/>
                        <path d="M20 20l-3.5-3.5"/>
                    </svg>

                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Search app, server, IP..."
                    >
                </div>

                <select name="environment">
                    <option value="">All Environments</option>

                    @foreach ($environments as $environment)
                        <option value="{{ $environment }}" {{ request('environment') === $environment ? 'selected' : '' }}>
                            {{ ucfirst($environment) }}
                        </option>
                    @endforeach
                </select>

                <select name="registration_status">
                    <option value="">All Registrations</option>

                    @foreach ($registrationStatuses as $status)
                        <option value="{{ $status }}" {{ request('registration_status') === $status ? 'selected' : '' }}>
                            {{ ucfirst($status) }}
                        </option>
                    @endforeach
                </select>

                <select name="activation_status">
                    <option value="">All Activations</option>

                    @foreach ($activationStatuses as $status)
                        <option value="{{ $status }}" {{ request('activation_status') === $status ? 'selected' : '' }}>
                            {{ ucfirst($status) }}
                        </option>
                    @endforeach
                </select>

                <select name="monitoring_status">
                    <option value="">All Monitoring</option>

                    @foreach ($monitoringStatuses as $status)
                        <option value="{{ $status }}" {{ request('monitoring_status') === $status ? 'selected' : '' }}>
                            {{ ucfirst($status) }}
                        </option>
                    @endforeach
                </select>

                <button type="submit">Filter</button>

                @if (request()->hasAny([
                    'search',
                    'environment',
                    'registration_status',
                    'activation_status',
                    'monitoring_status',
                ]))
                    <a href="{{ route('monitoring.applications') }}" class="applications-reset">
                        Reset
                    </a>
                @endif

            </form>
        </div>

        @if ($applications->count())
            <div class="applications-table-wrapper">
                <table class="applications-table">
                    <thead>
                        <tr>
                            <th>Application</th>
                            <th>Environment</th>
                            <th>Server / Agent</th>
                            <th>Endpoint</th>
                            <th>Registration</th>
                            <th>Activation</th>
                            <th>Monitoring</th>
                            <th>Credentials</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($applications as $application)
                            @php
                                $credentialTotal = (int) $application->credentials_count;
                                $credentialActive = (int) $application->active_credentials_count;
                                $credentialPercent = $credentialTotal > 0 ? round(($credentialActive / $credentialTotal) * 100) : 0;
                            @endphp
                            <tr>
                                <td>
                                    <div class="application-main">
                                        <div class="application-main-icon">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="3" y="4" width="18" height="16" rx="3"/>
                                                <path d="M3 9h18M7 14h4"/>
                                            </svg>
                                        </div>

                                        <div>
                                            <div class="application-name">
                                                {{ $application->application_name }}
                                            </div>

                                            <div class="application-framework">
                                                <span>
                                                    {{ ucfirst($application->framework) }}
                                                    {{ $application->framework_version }}
                                                </span>
                                                <span class="application-php">
                                                    PHP {{ $application->php_version ?: '—' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="application-environment">
                                        {{ ucfirst($application->environment) }}
                                    </span>
                                </td>

                                <td>
                                    @if ($application->agent && $application->agent->server)
                                        <div class="application-server">
                                            {{ $application->agent->server->hostname }}
                                        </div>

                                        <div class="application-server-ip">
                                            {{ $application->agent->server->ip_address ?: '—' }}
                                        </div>
                                    @else
                                        <span>—</span>
                                    @endif
                                </td>

                                <td>
                                    @if ($application->endpoint)
                                        <div class="application-endpoint" title="{{ $application->endpoint->base_url }}">
                                            {{ $application->endpoint->base_url }}
                                        </div>

                                        <div class="application-endpoint-prefix">
                                            {{ $application->endpoint->endpoint_prefix }}
                                        </div>
                                    @else
                                        <span>Not configured</span>
                                    @endif
                                </td>

                                <td>
                                    <span class="application-status {{ strtolower($application->registration_status) }}">
                                        <span class="application-status-dot"></span>
                                        {{ ucfirst($application->registration_status) }}
                                    </span>
                                </td>

                                <td>
                                    <span class="application-status {{ strtolower($application->activation_status) }}">
                                        <span class="application-status-dot"></span>
                                        {{ ucfirst($application->activation_status) }}
                                    </span>
                                </td>

                                <td>
                                    @if ($application->monitoring_suspended_at !== null)
                                        <span class="application-status suspended" style="background: color-mix(in srgb, var(--status-bad) 14%, transparent); border-color: color-mix(in srgb, var(--status-warn) 40%, transparent); color: var(--status-warn);">
                                            <span class="status-dot"></span>
                                            Suspended
                                        </span>
                                    @else
                                        <span class="application-status {{ $application->monitoring_status }}">
                                            <span class="status-dot"></span>
                                            {{ ucfirst($application->monitoring_status) }}
                                        </span>
                                    @endif
                                </td>

                                <td>
                                    <div class="application-credential-count">
                                        {{ $application->credentials_count }}
                                    </div>

                                    <div class="application-credential-active">
                                        {{ $application->active_credentials_count }} active
                                    </div>

                                    <div class="application-credential-meter">
                                        <div class="application-credential-meter-fill" style="width: {{ $credentialPercent }}%;"></div>
                                    </div>
                                </td>

                                <td>
                                    @if (
                                        $application->registration_status === 'approved'
                                        && $application->activation_status === 'active'
                                        && $application->monitoring_suspended_at === null
                                    )
                                        <form
                                            method="POST"
                                            action="{{ route('monitoring.applications.suspend', $application->id) }}"
                                            data-confirm-title="Suspend monitoring for this application?"
                                            data-confirm-detail="{{ $application->application_name }} · {{ $application->agent?->server?->hostname ?? '—' }}"
                                            data-confirm-button="Suspend"
                                            data-confirm-variant="warning"
                                        >
                                            @csrf

                                            <button type="submit" class="application-action-button suspend">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M8 5v14M16 5v14"/>
                                                </svg>
                                                Suspend
                                            </button>
                                        </form>
                                    @elseif (
                                        $application->registration_status === 'approved'
                                        && $application->activation_status === 'active'
                                        && $application->monitoring_suspended_at !== null
                                    )
                                        <form
                                            method="POST"
                                            action="{{ route('monitoring.applications.resume', $application->id) }}"
                                            data-confirm-title="Resume monitoring for this application?"
                                            data-confirm-detail="{{ $application->application_name }} · {{ $application->agent?->server?->hostname ?? '—' }}"
                                            data-confirm-button="Resume"
                                            data-confirm-variant="success"
                                        >
                                            @csrf

                                            <button type="submit" class="application-action-button resume">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M7 5l12 7-12 7z"/>
                                                </svg>
                                                Resume
                                            </button>
                                        </form>
                                    @else
                                        <span class="application-action-none">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($applications->hasPages())
                <div class="applications-pagination">
                    {{ $applications->links() }}
                </div>
            @endif

        @else
            <div class="applications-empty">
                <div class="applications-empty-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="16" rx="3"/>
                        <path d="M3 9h18M7 14h4"/>
                    </svg>
                </div>

                @if (request()->hasAny([
                    'search',
                    'environment',
                    'registration_status',
                    'activation_status',
                    'monitoring_status',
                ]))
                    <h3>No applications found</h3>
                    <p>No applications match your search or selected filters.</p>
                @else
                    <h3>No applications registered yet</h3>
                    <p>Applications will appear here when an NADI Agent registers them with Central.</p>
                @endif
            </div>
        @endif

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function ()
{
    const flash = {
        success: @json(session('success')),
        error: @json(session('error')),
        validation: @json($errors->any() ? $errors->first() : null)
    };

    /*
     * Tanpa SweetAlert, aksi suspend dan resume tidak boleh berjalan tanpa konfirmasi.
     * Form diblokir dan penyebabnya dicatat di konsol.
     */
    if (typeof Swal === 'undefined')
    {
        document.addEventListener('submit', function (event)
        {
            if (event.target.hasAttribute && event.target.hasAttribute('data-confirm-title'))
            {
                event.preventDefault();

                console.error('SweetAlert2 failed to load. The monitoring action was blocked.');
            }
        });

        return;
    }

    function dialogClass(variant)
    {
        return {
            popup: 'nadi-swal-popup',
            title: 'nadi-swal-title',
            htmlContainer: 'nadi-swal-text',
            actions: 'nadi-swal-actions',
            confirmButton: 'nadi-swal-btn nadi-swal-btn-' + (variant || 'primary'),
            cancelButton: 'nadi-swal-btn nadi-swal-btn-secondary'
        };
    }

    const modal = Swal.mixin({
        buttonsStyling: false,
        reverseButtons: true,
        customClass: dialogClass('primary')
    });

    const toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true,
        customClass: {
            popup: 'nadi-swal-toast',
            title: 'nadi-swal-toast-title'
        },
        didOpen: function (element)
        {
            element.addEventListener('mouseenter', Swal.stopTimer);
            element.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    /* ---------- Pesan dari server ---------- */
    if (flash.success)
    {
        toast.fire({
            icon: 'success',
            title: flash.success
        });
    }

    if (flash.error)
    {
        modal.fire({
            icon: 'error',
            title: 'Action failed',
            text: flash.error,
            confirmButtonText: 'OK',
            customClass: dialogClass('danger')
        });
    }
    else if (flash.validation)
    {
        modal.fire({
            icon: 'warning',
            title: 'Please check your input',
            text: flash.validation,
            confirmButtonText: 'OK'
        });
    }

    function setActionButtonsDisabled(disabled)
    {
        document.querySelectorAll('.application-action-button').forEach(function (button)
        {
            button.disabled = disabled;
        });
    }

    /* ---------- Konfirmasi suspend dan resume ---------- */
    document.addEventListener('submit', async function (event)
    {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm-title'))
        {
            return;
        }

        event.preventDefault();

        if (form.dataset.submitting === '1')
        {
            return;
        }

        const variant = form.dataset.confirmVariant || 'primary';
        const cautious = variant === 'danger' || variant === 'warning';

        const result = await modal.fire({
            icon: cautious ? 'warning' : 'question',
            title: form.dataset.confirmTitle,
            text: form.dataset.confirmDetail || '',
            showCancelButton: true,
            confirmButtonText: form.dataset.confirmButton || 'Confirm',
            cancelButtonText: 'Cancel',
            focusCancel: cautious,
            customClass: dialogClass(variant)
        });

        if (!result.isConfirmed)
        {
            return;
        }

        form.dataset.submitting = '1';

        setActionButtonsDisabled(true);

        modal.fire({
            title: 'Processing...',
            text: 'Please wait.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function ()
            {
                Swal.showLoading();
            }
        });

        form.submit();
    });

    /* Kembali lewat tombol Back: tutup dialog proses dan aktifkan lagi tombolnya. */
    window.addEventListener('pageshow', function (event)
    {
        if (!event.persisted)
        {
            return;
        }

        Swal.close();

        document.querySelectorAll('form[data-submitting]').forEach(function (form)
        {
            delete form.dataset.submitting;
        });

        setActionButtonsDisabled(false);
    });
});
</script>
@endsection