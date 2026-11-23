@extends('layouts.app')

@section('title', 'Telemetry Retention - NADI Console')

@section('breadcrumb', 'Management / Telemetry Retention')

@section('content')

@php
    $policyList = collect($policies);
    $policyTotal = $policyList->count();
    $policyConfigured = $policyList->filter(fn ($policy) => $policy['configured'])->count();
    $policyEnabled = $policyList->filter(fn ($policy) => $policy['enabled'])->count();

    $policyBase = max($policyTotal, 1);
    $configuredPercent = round(($policyConfigured / $policyBase) * 100);
    $enabledPercent = round(($policyEnabled / $policyBase) * 100);
@endphp

<div class="retention-page">

    <div class="page-header">
        <div>
            <h1 class="page-title">Telemetry Retention</h1>
            <p class="page-description">
                Configure global retention policies for NADI telemetry data.
            </p>
        </div>
    </div>

    <div class="retention-summary">

        <div class="retention-stat">
            <div class="retention-stat-top">
                <div class="retention-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <ellipse cx="12" cy="5" rx="8" ry="3"/>
                        <path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/>
                        <path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>
                    </svg>
                </div>
                <div class="retention-stat-label">Data Categories</div>
            </div>
            <div class="retention-stat-value">{{ number_format($policyTotal) }}</div>
            <div class="retention-stat-description">Telemetry categories with a policy</div>
            <div class="retention-stat-meter">
                <div class="retention-stat-meter-fill" style="width: 100%;"></div>
            </div>
        </div>

        <div class="retention-stat">
            <div class="retention-stat-top">
                <div class="retention-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M8 12.5l3 3 5-6"/>
                    </svg>
                </div>
                <div class="retention-stat-label">Configured</div>
            </div>
            <div class="retention-stat-value">
                {{ number_format($policyConfigured) }}
                <span class="retention-stat-percent">{{ $configuredPercent }}%</span>
            </div>
            <div class="retention-stat-description">Retention period is set</div>
            <div class="retention-stat-meter">
                <div class="retention-stat-meter-fill" style="width: {{ $configuredPercent }}%;"></div>
            </div>
        </div>

        <div class="retention-stat">
            <div class="retention-stat-top">
                <div class="retention-stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 3v9"/>
                        <path d="M6.3 7.3a8 8 0 1 0 11.4 0"/>
                    </svg>
                </div>
                <div class="retention-stat-label">Enabled</div>
            </div>
            <div class="retention-stat-value">
                {{ number_format($policyEnabled) }}
                <span class="retention-stat-percent">{{ $enabledPercent }}%</span>
            </div>
            <div class="retention-stat-description">Allowed to delete expired data</div>
            <div class="retention-stat-meter">
                <div class="retention-stat-meter-fill" style="width: {{ $enabledPercent }}%;"></div>
            </div>
        </div>

    </div>

    <div class="retention-notice">
        <div class="retention-notice-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 3l10 18H2z"/>
                <path d="M12 10v5M12 18v.01"/>
            </svg>
        </div>

        <div>
            <strong>Important:</strong>
            Enabled policies allow NADI to permanently delete expired telemetry.
            Unconfigured or disabled policies do not trigger deletion.
            Unresolved telemetry gaps are always preserved.
        </div>
    </div>

    <form
        method="POST"
        action="{{ route('management.telemetry-retention.update') }}"
        id="retentionForm"
        class="retention-form"
        novalidate
    >
        @csrf

        <div class="retention-panel">

            <div class="retention-panel-header">
                <div>
                    <h2>
                        Global Retention Policies
                        <span class="retention-panel-live">Live</span>
                    </h2>
                    <p>
                        Configure retention independently for each telemetry category.
                    </p>
                </div>
            </div>

            <div class="retention-table-wrapper">

                <table class="retention-table">

                    <thead>
                        <tr>
                            <th>Data Category</th>
                            <th>Retention Period</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        @foreach ($policies as $policy)

                            <tr>
                                <td>
                                    <div class="retention-main">
                                        <div class="retention-main-icon">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                <ellipse cx="12" cy="5" rx="8" ry="3"/>
                                                <path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/>
                                                <path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>
                                            </svg>
                                        </div>

                                        <div>
                                            <div class="retention-name">
                                                {{ $policy['label'] }}
                                            </div>

                                            <div class="retention-description">
                                                {{ $policy['description'] }}
                                            </div>

                                            <div class="retention-type">
                                                {{ $policy['data_type'] }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <div class="retention-days-field">
                                        <input
                                            type="number"
                                            name="policies[{{ $policy['data_type'] }}][retention_days]"
                                            value="{{ old('policies.' . $policy['data_type'] . '.retention_days', $policy['retention_days']) }}"
                                            min="1"
                                            max="36500"
                                            placeholder="Not configured"
                                        >

                                        <span>days</span>
                                    </div>

                                    <div class="retention-description">
                                        Minimum 1 day, maximum 36,500 days.
                                    </div>
                                </td>

                                <td>
                                    <label class="retention-toggle">
                                        <input
                                            type="checkbox"
                                            name="policies[{{ $policy['data_type'] }}][enabled]"
                                            value="1"
                                            @checked(
                                                old(
                                                    'policies.' . $policy['data_type'] . '.enabled',
                                                    $policy['enabled']
                                                )
                                            )
                                        >

                                        <span class="retention-toggle-label">
                                            Enabled
                                        </span>
                                    </label>

                                    @if (!$policy['configured'])
                                        <div class="retention-description">
                                            <span class="retention-chip">Not configured</span>
                                        </div>
                                    @endif
                                </td>
                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

            <div class="retention-panel-footer">
                <div class="retention-footer-note">
                    Saving an empty retention period leaves that policy unconfigured.
                </div>

                <button type="submit" class="retention-save-button">
                    Save Policies
                </button>
            </div>

        </div>

    </form>

</div>

<style>
    .retention-page,
    .swal2-container
    {
        --status-ok: #16a34a;
        --status-warn: #d97706;
        --status-bad: #dc2626;
    }

    .retention-page
    {
        display: flex;
        flex-direction: column;
        gap: 24px;
        width: 100%;
    }

    .retention-page .page-header
    {
        margin-bottom: 0;
    }

    /* ---------- Ringkasan ---------- */
    .retention-summary
    {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
    }

    .retention-stat
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

    .retention-stat:hover
    {
        transform: translateY(-3px);
        border-color: color-mix(in srgb, var(--accent) 45%, var(--line));
        box-shadow: var(--shadow), 0 18px 40px -22px var(--accent);
    }

    .retention-stat::before
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
    .retention-stat::after
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

    .retention-stat-top
    {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
    }

    .retention-stat-icon
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

    .retention-stat-icon svg
    {
        width: 19px;
        height: 19px;
    }

    .retention-stat-label
    {
        font: 600 11.5px var(--mono);
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .retention-stat-value
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

    .retention-stat-percent
    {
        padding: 3px 9px;
        border: 1px solid color-mix(in srgb, var(--accent) 35%, transparent);
        border-radius: 999px;
        background: var(--accent-soft);
        color: var(--accent);
        font: 600 11px var(--mono);
        letter-spacing: 0;
    }

    .retention-stat-description
    {
        margin-top: 8px;
        font: 500 11.5px var(--mono);
        color: var(--muted);
    }

    .retention-stat-meter
    {
        height: 4px;
        margin-top: 16px;
        border-radius: 999px;
        background: var(--surface-2);
        overflow: hidden;
    }

    .retention-stat-meter-fill
    {
        height: 100%;
        border-radius: 999px;
        background: var(--brand);
        box-shadow: 0 0 12px var(--accent);
        transition: width .8s cubic-bezier(.2, .8, .2, 1);
    }

    /* ---------- Catatan penting ---------- */
    .retention-notice
    {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        padding: 16px 18px;
        border: 1px solid color-mix(in srgb, var(--status-warn) 35%, transparent);
        border-left: 3px solid var(--status-warn);
        border-radius: 12px;
        background: color-mix(in srgb, var(--status-warn) 9%, transparent);
        color: var(--muted);
        font: 500 12.5px var(--mono);
        line-height: 1.7;
    }

    .retention-notice strong
    {
        color: var(--status-warn);
    }

    .retention-notice-icon
    {
        width: 32px;
        height: 32px;
        display: grid;
        place-items: center;
        flex-shrink: 0;
        border: 1px solid color-mix(in srgb, var(--status-warn) 40%, transparent);
        border-radius: 10px;
        background: color-mix(in srgb, var(--status-warn) 14%, transparent);
        color: var(--status-warn);
    }

    .retention-notice-icon svg
    {
        width: 17px;
        height: 17px;
    }

    /* ---------- Panel ---------- */
    .retention-panel
    {
        position: relative;
        overflow: hidden;
        border: 1px solid var(--line);
        border-radius: 18px;
        background: var(--surface);
        box-shadow: var(--shadow);
    }

    .retention-panel::before
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

    .retention-panel-header
    {
        padding: 26px 24px 20px;
        border-bottom: 1px solid var(--line);
        background-image: linear-gradient(180deg, color-mix(in srgb, var(--accent) 6%, transparent), transparent);
    }

    .retention-panel-header h2
    {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
        font-size: 16px;
        font-weight: 800;
        color: var(--ink);
    }

    .retention-panel-header p
    {
        margin: 5px 0 0;
        font: 500 12px var(--mono);
        color: var(--muted);
    }

    .retention-panel-live
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

    .retention-panel-live::before
    {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
        animation: retention-pulse 2s ease-out infinite;
    }

    /* ---------- Tabel ---------- */
    .retention-table-wrapper
    {
        overflow-x: auto;
    }

    .retention-table
    {
        width: 100%;
        border-collapse: collapse;
    }

    .retention-table th
    {
        padding: 13px 20px;
        border-bottom: 1px solid var(--line);
        background: var(--surface-2);
        text-align: left;
        font: 600 11px var(--mono);
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .retention-table td
    {
        padding: 20px;
        border-bottom: 1px solid var(--line);
        vertical-align: middle;
    }

    .retention-table tbody tr:last-child td
    {
        border-bottom: 0;
    }

    .retention-table tbody tr
    {
        transition: background .2s;
    }

    .retention-table tbody tr:hover
    {
        background: var(--accent-soft);
    }

    .retention-table tbody tr td:first-child
    {
        box-shadow: inset 0 0 0 var(--accent);
        transition: box-shadow .2s;
    }

    .retention-table tbody tr:hover td:first-child
    {
        box-shadow: inset 3px 0 0 var(--accent);
    }

    .retention-main
    {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .retention-main-icon
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

    .retention-main-icon svg
    {
        width: 16px;
        height: 16px;
    }

    .retention-name
    {
        margin-bottom: 5px;
        font-size: 14px;
        font-weight: 700;
        color: var(--ink);
    }

    .retention-description
    {
        margin-top: 5px;
        font: 500 11.5px var(--mono);
        line-height: 1.6;
        color: var(--muted);
    }

    .retention-type
    {
        display: inline-block;
        margin-top: 8px;
        padding: 2px 8px;
        border: 1px dashed color-mix(in srgb, var(--accent) 45%, var(--line));
        border-radius: 6px;
        color: var(--accent);
        font: 600 11px var(--mono);
    }

    .retention-chip
    {
        display: inline-block;
        padding: 2px 8px;
        border: 1px solid var(--line);
        border-radius: 6px;
        background: var(--surface-2);
        color: var(--muted);
        font: 500 11px var(--mono);
    }

    /* ---------- Input periode ---------- */
    .retention-days-field
    {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .retention-days-field input
    {
        width: 150px;
        height: 40px;
        box-sizing: border-box;
        border: 1.5px solid var(--line);
        border-radius: 10px;
        padding: 0 12px;
        background: var(--surface-2);
        color: var(--ink);
        font: 600 13px var(--mono);
        outline: none;
        transition: border-color .2s, box-shadow .2s, background .2s;
    }

    .retention-days-field input::placeholder
    {
        color: var(--muted);
        font-weight: 500;
        opacity: .8;
    }

    .retention-days-field input:focus
    {
        background: var(--surface);
        border-color: var(--accent);
        box-shadow: 0 0 0 4px var(--accent-soft), 0 0 22px -8px var(--accent);
    }

    .retention-days-field input.is-invalid
    {
        border-color: var(--status-bad);
        box-shadow: 0 0 0 4px color-mix(in srgb, var(--status-bad) 14%, transparent);
    }

    .retention-days-field span
    {
        font: 600 11.5px var(--mono);
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--muted);
    }

    /* ---------- Toggle ---------- */
    .retention-toggle
    {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
    }

    .retention-toggle input
    {
        -webkit-appearance: none;
        appearance: none;
        position: relative;
        width: 42px;
        height: 24px;
        margin: 0;
        border: 1.5px solid var(--line);
        border-radius: 999px;
        background: var(--surface-2);
        cursor: pointer;
        transition: border-color .2s, background .2s, box-shadow .2s;
    }

    .retention-toggle input::before
    {
        content: '';
        position: absolute;
        top: 2px;
        left: 2px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: var(--muted);
        transition: transform .2s ease, background .2s, box-shadow .2s;
    }

    .retention-toggle input:checked
    {
        border-color: color-mix(in srgb, var(--status-ok) 45%, transparent);
        background: color-mix(in srgb, var(--status-ok) 18%, transparent);
    }

    .retention-toggle input:checked::before
    {
        transform: translateX(18px);
        background: var(--status-ok);
        box-shadow: 0 0 12px var(--status-ok);
    }

    .retention-toggle input:focus-visible
    {
        outline: none;
        box-shadow: 0 0 0 4px var(--accent-soft);
    }

    .retention-toggle-label
    {
        font: 600 12px var(--mono);
        color: var(--muted);
        transition: color .2s;
    }

    .retention-toggle:has(input:checked) .retention-toggle-label
    {
        color: var(--status-ok);
    }

    /* ---------- Footer ---------- */
    .retention-panel-footer
    {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 18px 24px;
        border-top: 1px solid var(--line);
        background: var(--surface-2);
    }

    .retention-footer-note
    {
        font: 500 11.5px var(--mono);
        line-height: 1.6;
        color: var(--muted);
    }

    .retention-save-button
    {
        height: 40px;
        padding: 0 22px;
        border: 0;
        border-radius: 10px;
        background: var(--brand);
        color: #ffffff;
        font: 700 13px var(--font);
        cursor: pointer;
        box-shadow: 0 10px 22px -12px rgba(47, 76, 240, .9), inset 0 1px 0 rgba(255, 255, 255, .3);
        transition: filter .2s, transform .2s;
    }

    .retention-save-button:hover
    {
        filter: brightness(1.08);
        transform: translateY(-1px);
    }

    .retention-save-button:disabled
    {
        opacity: .6;
        cursor: not-allowed;
        transform: none;
        filter: none;
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

    .nadi-swal-list
    {
        margin: 0;
        padding: 0;
        list-style: none;
        text-align: left;
    }

    .nadi-swal-list li
    {
        margin-top: 8px;
        padding: 8px 12px;
        border-left: 2px solid var(--status-warn);
        border-radius: 0 8px 8px 0;
        background: color-mix(in srgb, var(--status-warn) 9%, transparent);
        font: 500 12px var(--mono);
        line-height: 1.5;
        color: var(--muted);
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
    @keyframes retention-pulse
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
        .retention-panel-live::before
        {
            animation: none;
        }

        .retention-stat,
        .retention-stat-meter-fill,
        .retention-toggle input,
        .retention-toggle input::before,
        .retention-save-button,
        .nadi-swal-btn
        {
            transition: none;
        }
    }

    @media (max-width: 900px)
    {
        .retention-summary
        {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 768px)
    {
        .retention-panel-footer
        {
            align-items: stretch;
            flex-direction: column;
        }

        .retention-save-button
        {
            width: 100%;
        }

        .retention-table th,
        .retention-table td
        {
            padding: 14px;
        }

        .retention-panel-header
        {
            padding: 22px 16px 16px;
        }
    }

    @media (max-width: 600px)
    {
        .retention-summary
        {
            grid-template-columns: 1fr;
        }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function ()
{
    const flash = {
        success: @json(session('success')),
        errors: @json($errors->any() ? $errors->all() : [])
    };

    if (typeof Swal === 'undefined')
    {
        console.error('SweetAlert2 failed to load. Notifications and validation dialogs are unavailable.');

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

    function buildList(items)
    {
        const list = document.createElement('ul');

        list.className = 'nadi-swal-list';

        items.forEach(function (item)
        {
            const entry = document.createElement('li');

            entry.textContent = item;

            list.appendChild(entry);
        });

        return list;
    }

    /* ---------- Pesan dari server ---------- */
    if (flash.success)
    {
        toast.fire({
            icon: 'success',
            title: flash.success
        });
    }

    if (flash.errors.length > 0)
    {
        modal.fire({
            icon: 'error',
            title: 'Unable to save retention policies.',
            html: buildList(flash.errors),
            confirmButtonText: 'OK',
            customClass: dialogClass('danger')
        });
    }

    /* ---------- Validasi dan penyimpanan ---------- */
    const form = document.getElementById('retentionForm');

    if (!form)
    {
        return;
    }

    const saveButton = form.querySelector('.retention-save-button');
    const dayFields = form.querySelectorAll('input[type="number"][name$="[retention_days]"]');

    dayFields.forEach(function (field)
    {
        field.addEventListener('input', function ()
        {
            field.classList.remove('is-invalid');
        });
    });

    function categoryName(field)
    {
        const row = field.closest('tr');
        const name = row ? row.querySelector('.retention-name') : null;

        return name ? name.textContent.trim() : field.name;
    }

    function collectProblems()
    {
        const problems = [];

        dayFields.forEach(function (field)
        {
            const raw = field.value.trim();

            field.classList.remove('is-invalid');

            /* Kosong berarti kebijakan dibiarkan belum dikonfigurasi. */
            if (raw === '')
            {
                return;
            }

            const min = Number(field.getAttribute('min')) || 1;
            const max = Number(field.getAttribute('max')) || 36500;
            const valid = /^\d+$/.test(raw) && Number(raw) >= min && Number(raw) <= max;

            if (!valid)
            {
                field.classList.add('is-invalid');

                problems.push({
                    field: field,
                    message: categoryName(field) + ': enter a whole number between ' + min + ' and ' + max.toLocaleString('en-US') + ' days.'
                });
            }
        });

        return problems;
    }

    form.addEventListener('submit', function (event)
    {
        const problems = collectProblems();

        if (problems.length > 0)
        {
            event.preventDefault();

            modal.fire({
                icon: 'warning',
                title: 'Please check the retention period',
                html: buildList(problems.map(function (problem)
                {
                    return problem.message;
                })),
                confirmButtonText: 'OK'
            }).then(function ()
            {
                problems[0].field.focus();
            });

            return;
        }

        saveButton.disabled = true;
        saveButton.textContent = 'Saving...';

        modal.fire({
            title: 'Saving policies...',
            text: 'Please wait.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function ()
            {
                Swal.showLoading();
            }
        });
    });

    /* Kembali lewat tombol Back: tutup dialog proses dan aktifkan lagi tombolnya. */
    window.addEventListener('pageshow', function (event)
    {
        if (!event.persisted)
        {
            return;
        }

        Swal.close();

        saveButton.disabled = false;
        saveButton.textContent = 'Save Policies';
    });
});
</script>

@endsection