# NADI Central Prometheus Query Service v1.0

Status: Central-side authenticated query gateway implementation. Production deployment and browser integration remain separate operational steps.

## Purpose

Laravel Central acts as the only application-facing gateway for Prometheus query data. Browser clients must not call the Prometheus HTTP API directly, and the Prometheus listener should remain bound to a private/local interface.

This service is read-only. It does not modify Prometheus configuration, telemetry records, Agent payloads, or the existing scrape/exporter endpoint.

The native Infrastructure page uses the canonical MySQL telemetry aggregation endpoint exposed by `TelemetryController`, not this Prometheus query gateway, for server/application metric values. This is intentional: Infrastructure and Telemetry must use the same Agent records, bucket definitions, root filesystem selection, primary network interface selection, and probe aggregation. Prometheus remains the time-series backend for Grafana and the source for scrape-target health information.

## Access control

All query endpoints are registered inside the existing Central web route group protected by:

- Laravel authenticated session (`auth`)
- Mandatory MFA middleware (`mfa`)

No new role or permission mapping is introduced because the current Central user model does not establish a role/permission contract.

## Endpoints

All endpoints are same-origin HTTPS routes:

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/monitoring/prometheus/overview` | Prometheus reachability, exporter status, and active scrape target summary |
| GET | `/monitoring/prometheus/metrics` | List of supported metric keys and descriptions |
| GET | `/monitoring/prometheus/query/{metric}` | Instant or bounded range query for one catalog metric |

### Query examples

Instant query:

```text
GET /monitoring/prometheus/query/server_cpu_usage?server_id=<server-uuid>
```

Range query:

```text
GET /monitoring/prometheus/query/server_cpu_usage?server_id=<server-uuid>&start=2026-11-25T00:00:00Z&end=2026-11-25T01:00:00Z&step=30
```

Supported optional identity filters are `server_id`, `agent_id`, and `application_id`. Values must be UUIDs. The caller cannot submit PromQL or arbitrary Prometheus API paths.

## Initial metric catalog

- `exporter_up`
- `server_cpu_usage`
- `server_memory_usage`
- `server_load_average`
- `server_disk_usage`
- `server_network_receive`
- `server_network_transmit`
- `server_uptime`
- `application_probe_up`
- `application_probe_latency`
- `database_connection_status`
- `database_connection_duration`
- `database_last_success`
- `database_probe_failures`
- `database_query_success`
- `telemetry_stream_age`
- `telemetry_stream_up`

Network receive/transmit queries derive bytes per second from the existing cumulative counter using a fixed five-minute PromQL rate window. Database metric queries are additionally gated by `nadi_telemetry_stream_up` for the `database_metrics` stream, matched on `agent_id` and `application_id`, so stale database probe values are not presented as healthy live data.

## Query bounds and resilience

- Instant queries are the default when no range is supplied.
- Range queries require both `start` and `end`.
- Default maximum range: 168 hours (7 days).
- Default step: 30 seconds.
- Allowed explicit step: 15–300 seconds.
- Default maximum points: 11,000.
- Future query times are rejected beyond a one-minute clock-skew allowance.
- Prometheus connect timeout: 2 seconds.
- Prometheus request timeout: 5 seconds.
- Successful targets and query responses are cached for 10 seconds by default.
- Prometheus errors are returned as HTTP 503 without exposing upstream exception details.
- Unknown catalog keys return HTTP 404.
- Invalid input and out-of-bounds ranges return HTTP 422.

Values are configurable through the `NADI_PROMETHEUS_*` environment variables documented in `.env.example`. Prometheus URL defaults to `http://127.0.0.1:9090`; this is server-side configuration and is never returned to the browser.

## Operational configuration

Add or verify these values in the Central environment:

```dotenv
NADI_PROMETHEUS_URL=http://127.0.0.1:9090
NADI_PROMETHEUS_CONNECT_TIMEOUT_SECONDS=2
NADI_PROMETHEUS_TIMEOUT_SECONDS=5
NADI_PROMETHEUS_QUERY_CACHE_SECONDS=10
NADI_PROMETHEUS_MAX_RANGE_HOURS=168
NADI_PROMETHEUS_MAX_DATA_POINTS=11000
```

After deployment, refresh Laravel's configuration cache using the normal Central deployment procedure. Do not expose port 9090 publicly.

## Scope boundary

This phase adds the server-side query gateway only. It does not add monitoring pages, Grafana embedding/proxying, dashboard provisioning, Alertmanager integration, or automatic remediation. It does not change any Agent repository, Agent contract, telemetry schema, or existing Prometheus scrape configuration.

Unit tests cover the database metric catalog, UUID filter validation and freshness-gated PromQL generation. CI also runs an isolated Prometheus 3.5 scrape against the Central exporter. No production commands or deployment actions are performed by this repository change.
