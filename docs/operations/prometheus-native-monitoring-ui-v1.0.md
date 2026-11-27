# NADI Central Native Infrastructure Monitoring UI v1.0

Status: Central-side page and canonical telemetry history integration added. Production deployment and live consistency validation remain pending.

## Route

- `GET /monitoring/infrastructure`
- Route name: `monitoring.infrastructure`
- Data endpoint: `GET /monitoring/infrastructure/data`
- Data route name: `monitoring.infrastructure.data`
- Both routes are protected by the existing authenticated session and MFA route middleware.

## Canonical data source

Infrastructure resource and application history is read from the same Central MySQL telemetry records used by the Telemetry page.

The data endpoint reuses the existing Telemetry aggregation methods:

- `serverSection()` for CPU, memory, root filesystem, and selected network interface history.
- `applicationSection()` for probe latency, probe status ratios, and availability.
- The same range windows, bucket sizes, latest record selection, root disk selection, and primary network interface selection as Telemetry.
- Stream freshness uses `last_received_at` and the same 120-second stale / 300-second offline thresholds used by Telemetry.

Infrastructure does not query Prometheus for resource or application metric values. Prometheus remains available for exporter health, active scrape target status, and Grafana dashboards. This prevents scrape repetition and scrape timing from changing values shown beside raw Agent telemetry.

The endpoint accepts optional `server_id` and `application_id` UUID filters. The server filter is resolved against the Agent's registered server; the application filter uses the registered Central application identity.

## UI panels

- CPU utilization
- Memory utilization
- Root filesystem utilization
- Network receive and transmit throughput on the selected primary interface
- Application probe status
- Application probe latency
- Telemetry stream freshness and availability
- Prometheus active scrape targets and exporter connectivity

Supported time ranges are 1 hour, 6 hours, 24 hours, and 7 days. Bucket sizes match Telemetry:

| Range | Bucket |
|---|---:|
| 1 hour | 60 seconds |
| 6 hours | 300 seconds |
| 24 hours | 900 seconds |
| 7 days | 3,600 seconds |

Current CPU, memory, and root disk KPI values are taken from each server's latest Agent record and averaged over the same live-server population used by Telemetry fleet totals. Historical charts use the same per-server bucket aggregates as Telemetry detail.

## Consistency acceptance criteria

The intended acceptance criterion is no more than 2% relative difference for comparable numeric metrics when server/application filters and time range are identical.

For zero or near-zero values, use an absolute comparison based on the metric's display precision rather than dividing by zero. Discrete states such as live, stale, offline, and current probe status must match exactly.

A live production comparison is still required after deployment. No claim that the 2% target has been verified is made by this code change alone.

## Interpretation

The exporter currently maps non-healthy application probe states to zero. This UI reports the raw Agent probe status and historical success ratio from Telemetry records. Unsupported and unavailable probe states remain a known metric-model limitation and should be separated before alerting is introduced.

This phase does not introduce Alertmanager, automated remediation, new telemetry streams, Agent contract changes, or Grafana embedding.

## Deployment note

No migration or Agent-side change is required. Deploy the Central branch and refresh Laravel configuration cache using the normal deployment procedure.

No tests or production commands were executed as part of this change.
