@extends('layouts.app')

@section('title', 'Alert Detail - NADI Console')

@section('breadcrumb', 'Management / Alerts / Detail')

@section('content')
<div class="alert-page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ $alert->alertname }}</h1>
            <p class="page-description">{{ $alert->summary ?: 'Alert detail and received event history.' }}</p>
        </div>

        <a href="{{ route('management.alerts') }}" class="alert-link">Back to alerts</a>
    </div>

    <style>
        .alert-page {
            display: grid;
            gap: 20px;
        }

        .alert-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .alert-detail-card {
            min-width: 0;
            padding: 18px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--surface);
        }

        .alert-detail-label {
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
        }

        .alert-detail-value {
            margin-top: 7px;
            color: var(--ink);
            font-size: 13px;
            overflow-wrap: anywhere;
        }

        .alert-detail-block {
            padding: 18px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--surface);
        }

        .alert-detail-block h2 {
            margin: 0 0 12px;
            color: var(--ink);
            font-size: 14px;
        }

        .alert-json {
            max-height: 420px;
            overflow: auto;
            padding: 14px;
            border-radius: 10px;
            color: var(--ink);
            background: var(--surface-2);
            font: 11px/1.7 var(--mono);
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .alert-history {
            width: 100%;
            border-collapse: collapse;
        }

        .alert-history th,
        .alert-history td {
            padding: 12px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: top;
            font-size: 12px;
        }

        .alert-history th {
            color: var(--muted);
            font-size: 10px;
            text-transform: uppercase;
        }

        .alert-history-wrap {
            overflow-x: auto;
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

        @media (max-width: 700px) {
            .alert-detail-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="alert-detail-grid">
        <div class="alert-detail-card">
            <div class="alert-detail-label">Status</div>
            <div class="alert-detail-value">
                <span class="alert-status alert-status-{{ $alert->status }}">{{ $alert->status }}</span>
            </div>
        </div>

        <div class="alert-detail-card">
            <div class="alert-detail-label">Severity</div>
            <div class="alert-detail-value">{{ $alert->severity ? ucfirst($alert->severity) : 'Not specified' }}</div>
        </div>

        <div class="alert-detail-card">
            <div class="alert-detail-label">Fingerprint</div>
            <div class="alert-detail-value">{{ $alert->fingerprint }}</div>
        </div>

        <div class="alert-detail-card">
            <div class="alert-detail-label">Receiver / Group</div>
            <div class="alert-detail-value">{{ $alert->receiver ?: '—' }} / {{ $alert->group_key ?: '—' }}</div>
        </div>

        <div class="alert-detail-card">
            <div class="alert-detail-label">Starts at</div>
            <div class="alert-detail-value">{{ $alert->starts_at ?: '—' }}</div>
        </div>

        <div class="alert-detail-card">
            <div class="alert-detail-label">Ends at</div>
            <div class="alert-detail-value">{{ $alert->ends_at ?: '—' }}</div>
        </div>

        <div class="alert-detail-card">
            <div class="alert-detail-label">Last received</div>
            <div class="alert-detail-value">{{ $alert->last_received_at }}</div>
        </div>

        <div class="alert-detail-card">
            <div class="alert-detail-label">Generator URL</div>
            <div class="alert-detail-value">
                @if ($alert->generator_url)
                    <a href="{{ $alert->generator_url }}" target="_blank" rel="noopener noreferrer">Open source</a>
                @else
                    —
                @endif
            </div>
        </div>
    </div>

    <section class="alert-detail-block">
        <h2>Description</h2>
        <div class="alert-detail-value">{{ $alert->description ?: 'No description provided.' }}</div>
    </section>

    <section class="alert-detail-block">
        <h2>Labels</h2>
        <pre class="alert-json">{{ json_encode(json_decode($alert->labels, true) ?: [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
    </section>

    <section class="alert-detail-block">
        <h2>Annotations</h2>
        <pre class="alert-json">{{ json_encode(json_decode($alert->annotations, true) ?: [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
    </section>

    <section class="alert-detail-block">
        <h2>Event history <span class="token-panel-live">{{ $events->total() }}</span></h2>

        <div class="alert-history-wrap">
            <table class="alert-history">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Severity</th>
                        <th>Summary</th>
                        <th>Starts at</th>
                        <th>Ends at</th>
                        <th>Received at</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($events as $event)
                        <tr>
                            <td>
                                <span class="alert-status alert-status-{{ $event->status }}">{{ $event->status }}</span>
                            </td>
                            <td>{{ $event->severity ?: '—' }}</td>
                            <td>{{ $event->summary ?: '—' }}</td>
                            <td>{{ $event->starts_at ?: '—' }}</td>
                            <td>{{ $event->ends_at ?: '—' }}</td>
                            <td>{{ $event->received_at }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">No event history recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $events->links() }}
    </section>
</div>
@endsection
