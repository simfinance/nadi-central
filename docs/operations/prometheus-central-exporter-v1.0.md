# NADI Central Prometheus Exporter v1.0

Status: Central-side read-only exporter implementation. Prometheus server installation and runtime scrape verification remain deployment tasks.

## Scope and compatibility

- Central only. No NADI Agent or Laravel Bridge repository changes are required.
- No Agent telemetry payload, stream name, capability, acknowledgement, or ingestion endpoint is changed.
- The exporter reads the existing MySQL telemetry records and stream metadata.
- The exporter does not write to telemetry tables and does not delete or transform stored records.
- `server_metrics`, `application_metrics`, and `database_metrics` streams are exported.
- Logs and HTTP events are deliberately excluded.

## Endpoint

```text
GET /api/internal/prometheus/metrics
Authorization: Bearer <NADI_PROMETHEUS_METRICS_TOKEN>
```

Responses:

- HTTP 200: Prometheus text exposition format.
- HTTP 401: missing or invalid bearer token.
- HTTP 503: exporter token has not been configured.

The endpoint fails closed when the token is empty. Configure a long random token in the Central `.env` file:

```dotenv
NADI_PROMETHEUS_METRICS_TOKEN=<long-random-secret>
```

Do not commit the token to Git. Keep the token in a root-readable Prometheus secret file as well. The endpoint uses constant-time token comparison. It rechecks the current Agent/application grant and stream capability before exposing each stream and does not cache rendered exposition, so a recent suspension, revocation or expiry is not hidden behind a cached authorized response.

Prometheus should reach this endpoint over HTTPS on a private network or through a reverse proxy restricted to the Prometheus host. Do not expose the endpoint broadly to the public internet.

## Exported metrics

### Stream freshness

- `nadi_telemetry_stream_last_received_timestamp_seconds`
- `nadi_telemetry_stream_age_seconds`
- `nadi_telemetry_stream_up`: 1 when the last record is no older than 300 seconds.
- `nadi_telemetry_stream_stale`: 1 when the record is older than 120 seconds and no older than 300 seconds.

### Server metrics

- `nadi_server_cpu_usage_percent`
- `nadi_server_memory_usage_percent`
- `nadi_server_memory_bytes` with `state=total|used|available`
- `nadi_server_load_average` with `period=1m|5m|15m`
- `nadi_server_uptime_seconds`
- `nadi_server_disk_usage_percent`
- `nadi_server_disk_bytes` with `state=total|used|available`
- `nadi_server_network_bytes_total` with `direction=receive|transmit`
- `nadi_server_network_packets_total`
- `nadi_server_network_dropped_total`
- `nadi_server_network_errors_total`
- `nadi_server_nginx_process_up`

### Application metrics

- `nadi_application_probe_up` with `probe=health|info|database|metrics|queue|scheduler`, when the corresponding probe data is present in the application metrics payload
- `nadi_application_probe_latency_milliseconds`

### Laravel database probe metrics

- `laravel_db_connection_status`: 1 when the latest database probe succeeded, otherwise 0
- `laravel_db_connection_duration_seconds`: duration of the latest database probe
- `laravel_db_last_success_timestamp`: Unix timestamp of the most recent successful probe
- `laravel_db_probe_failures_total`: cumulative failed probes persisted by Central
- `laravel_db_query_success`: 1 when the latest SELECT 1 probe succeeded, otherwise 0

Common identity labels are `agent_id`, `server_id`, `hostname`, and `stream`. Application metrics also include `application_id`, `application_name`, and `environment`. Disk and network metrics include mount point/filesystem or interface labels.

The exporter selects the highest accepted sequence per stream, avoiding observed-time ties and Agent clock-skew ambiguity. Prometheus scrape timestamps represent exporter collection time; the separate stream freshness metrics expose the age of the Agent's last received record. Database probe counters and last-success state are updated transactionally during ingestion and retained independently from raw telemetry cleanup. Network byte and packet values are cumulative counters from the Agent's Linux kernel snapshot.

## Prometheus scrape configuration example

Adapt the target hostname to the actual private DNS name or reverse-proxy address used by the Central host:

```yaml
scrape_configs:
    - job_name: nadi-central
      scrape_interval: 30s
      metrics_path: /api/internal/prometheus/metrics
      scheme: https
      static_configs:
          - targets:
                - nadi-central.internal
      bearer_token_file: /etc/prometheus/secrets/nadi-central-metrics-token
```

The secret file must contain only the bearer token and be readable by the Prometheus service account. Ensure the configured target resolves to the Central HTTPS endpoint and that its certificate validates.

## Operational notes

1. Configure the same secret in Central and Prometheus.
2. Run `php artisan config:cache` after changing the Central environment configuration.
3. Reload the PHP application workers if required by the deployment's process model.
4. Add the scrape job to Prometheus and reload Prometheus configuration.
5. Verify the target is UP in Prometheus and inspect `/api/internal/prometheus/metrics` from the Prometheus host.
6. Keep the existing Central telemetry ingestion and MySQL retention processes unchanged.

PR #7 introduced a forward-only migration for `nadi_telemetry_database_probe_states`, which stores the cumulative database probe counter and last-success state. The migration chain was validated on fresh MySQL 8.4 CI. Verify the live table schema and indexes before deployment.

## Verification boundary

CI now starts an isolated Prometheus 3.5 instance, scrapes the Central endpoint with a test-only Bearer token, checks target health, and queries `nadi_prometheus_exporter_up=1`. This does not install or configure the target Prometheus deployment, reverse proxy/firewall, production secret, or production scrape job.
