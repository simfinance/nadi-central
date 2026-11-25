@extends('layouts.app')

@section('title', 'Alert Management - NADI Console')

@section('breadcrumb', 'Management / Alerts')

@section('content')
<div class="alert-page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Alert Management</h1>
            <p class="page-description">Current alert state and event history received from Prometheus Alertmanager.</p>
        </div>
    </div>

    <style>
        .alert-page {
            display: grid;
            gap: 22px;
        }

        .alert-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .alert-summary-card,
        .alert-panel {
            padding: 20px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--surface);
            box-shadow: var(--shadow);
        }

        .alert-summary-label {
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
        }

        .alert-summary-value {
            margin-top: 8px;
            color: var(--ink);
            font-size: 28px;
            font-weight: 800;
        }

        .alert-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            gap: 12px;
        }

        .alert-field {
            display: grid;
            flex: 1 1 180px;
            gap: 7px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
        }

        .alert-field input,
        .alert-field select {
            width: 100%;
            min-height: 42px;
            padding: 9px 12px;
            border: 1px solid var(--line);
            border-radius: 10px;
            color: var(--ink);
            background: var(--surface-2);
            font: inherit;
        }

        .alert-button,
        .alert-link {
            display: inline-flex;
            min-height: 40px;
            align-items: center;
            justify-content: center;
            padding: 9px 14px;
            border: 1px solid var(--line);
            border-radius: 10px;
            color: var(--ink);
            background: var(--surface-2);
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .alert-table-wrap {
            overflow-x: auto;
        }

        .alert-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
        }

        .alert-table th,
        .alert-table td {
            padding: 13px 12px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: top;
            font-size: 12px;
        }

        .alert-table th {
            color: var(--muted);
            font-size: 10px;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .alert-table td strong {
            display: block;
            color: var(--ink);
            font-size: 12px;
        }

        .alert-table td small {
            display: block;
            margin-top: 5px;
            color: var(--muted);
            overflow-wrap: anywhere;
        }

        .alert-status {
            display: inline-flex;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .alert-status-firing {
            color: #b42318;
            background: rgba(217, 45, 32, .12);
        }

        .alert-status-resolved {
            color: #067647;
            background: rgba(18, 161, 80, .12);
        }

        .alert-empty {
            padding: 34px 16px;
            color: var(--muted);
            text-align: center;
            font-size: 13px;
        }

        .alert-pagination {
            margin-top: 18px;
        }

        @media (max-width: 760px) {
            .alert-summary {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="alert-summary">
        <div class="alert-summary-card">
            <div class="alert-summary-label">Firing</div>
            <div class="alert-summary-value">{{ number_format($totals['firing']) }}</div>
        </div>

        <div class="alert-summary-card">
            <div class="alert-summary-label">Resolved</div>
            <div class="alert-summary-value">{{ number_format($totals['resolved']) }}</div>
        </div>

        <div class="alert-summary-card">
            <div class="alert-summary-label">Recorded events</div>
            <div class="alert-summary-value">{{ number_format($totals['events']) }}</div>
        </div>
    </div>

    <section class="alert-panel">
        <form method="GET" action="{{ route('management.alerts') }}" class="alert-toolbar">
            <label class="alert-field">
                <span>Search</span>
                <input
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    maxlength="255"
                    placeholder="Alert name, summary, fingerprint..."
                >
            </label>

            <label class="alert-field">
                <span>Status</span>
                <select name="status">
                    <option value="all" @selected($status === 'all')>All statuses</option>
                    <option value="firing" @selected($status === 'firing')>Firing</option>
                    <option value="resolved" @selected($status === 'resolved')>Resolved</option>
                </select>
            </label>

            <label class="alert-field">
                <span>Severity</span>
                <select name="severity">
                    <option value="">All severities</option>
                    @foreach ($severities as $severityOption)
                        <option value="{{ $severityOption }}" @selected($severity === $severityOption)>
                            {{ ucfirst($severityOption) }}
                        </option>
                    @endforeach
                </select>
            </label>

            <button type="submit" class="alert-button">Apply filters</button>

            <a href="{{ route('management.alerts') }}" class="alert-link">Reset</a>
        </form>
    </section>

    <section class="alert-panel">
        <div class="token-panel-header">
            <h2>Alerts <span class="token-panel-live">{{ $alerts->total() }}</span></h2>
            <p>Latest accepted state for each Alertmanager fingerprint.</p>
        </div>

        <div class="alert-table-wrap">
            <table class="alert-table">
                <thead>
                    <tr>
                        <th>Alert</th>
                        <th>Severity</th>
                        <th>Status</th>
                        <th>Started</th>
                        <th>Last received</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($alerts as $item)
                        <tr>
                            <td>
                                <strong>{{ $item->alertname }}</strong>
                                <small>{{ $item->summary ?: 'No summary provided' }}</small>
                            </td>
                            <td>{{ $item->severity ? ucfirst($item->severity) : '—' }}</td>
                            <td>
                                <span class="alert-status alert-status-{{ $item->status }}">
                                    {{ $item->status }}
                                </span>
                            </td>
                            <td>{{ $item->starts_at ?: '—' }}</td>
                            <td>{{ $item->last_received_at }}</td>
                            <td>
                                <a
                                    href="{{ route('management.alerts.show', ['fingerprint' => $item->fingerprint]) }}"
                                    class="alert-link"
                                >
                                    Details
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="alert-empty">
                                No alerts match the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="alert-pagination">
            {{ $alerts->links() }}
        </div>
    </section>
</div>
@endsection
