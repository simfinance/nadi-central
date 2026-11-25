# NADI Central Native Infrastructure Monitoring UI v1.0

Status: Central-side page and read-only query integration added. Production rendering and live data validation remain deployment tasks.

## Route

- `GET /monitoring/infrastructure`
- Route name: `monitoring.infrastructure`
- Protected by the existing authenticated session and MFA route middleware.

## Data sources

- Server and application inventory selectors are read from the Central MySQL registry.
- Historical metric values are fetched through the Laravel Prometheus Query Service.
- Prometheus remains private to the Central server. Browser requests are same-origin requests to Laravel.
- Scrape target status is retrieved through the authenticated Central gateway.

## UI panels

- CPU utilization
- Memory utilization
- Disk utilization by mount point
- Network receive throughput
- Application probe status
- Application probe latency
- Prometheus active scrape targets

Supported time ranges are 1 hour, 6 hours, 24 hours, and 7 days. The page selects a bounded step size for each range and uses the existing query service limits.

## Interpretation

The exporter currently maps non-healthy application probe states to zero. This UI therefore labels the series as reported probe status and explicitly warns that zero does not by itself prove an application outage. Unsupported and unavailable probe states remain a known metric-model limitation and should be separated before alerting is introduced.

The initial UI does not introduce Alertmanager, automated remediation, new telemetry streams, Agent contract changes, or Grafana embedding.

## Deployment note

No migration or Agent-side change is required. Deploy the Central branch, configure `NADI_PROMETHEUS_URL` to the private Prometheus API address (default `http://127.0.0.1:9090`), and refresh Laravel configuration cache using the normal deployment procedure.

No tests or production commands were executed as part of this change.
