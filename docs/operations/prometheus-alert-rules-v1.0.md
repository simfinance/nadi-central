# NADI Prometheus Alert Rules v1.0

## Purpose

This rule set evaluates the NADI Central metrics already exposed by the Central Prometheus exporter. It covers:

- NADI Central scrape availability.
- Telemetry stream stale and offline state.
- Server CPU, memory, and root filesystem usage.
- Nginx process state.
- Application health probes and probe latency.
- Network errors and dropped packets.
- Laravel database connection status, probe latency, last successful probe timestamp and cumulative probe failures.

This rule set is observability-only. It does not restart services, modify applications, or perform remediation.

## Rule file

The repository rule file is:

`docs/operations/prometheus/nadi-alert-rules.yml`

Install it on the Prometheus host as:

`/etc/prometheus/rules/nadi-alert-rules.yml`

Ensure the Prometheus service account can read the file and its parent directory.

## Prometheus configuration

Merge the following sections into the existing `/etc/prometheus/prometheus.yml`. Preserve the existing `global` and `scrape_configs` settings; do not replace the full configuration with this example.

```yaml
rule_files:
    - /etc/prometheus/rules/nadi-alert-rules.yml

alerting:
    alertmanagers:
        - static_configs:
              - targets:
                    - 127.0.0.1:9093
```

The Alertmanager target above matches the documented local-only listener configuration. Keep Alertmanager bound to loopback unless a separately reviewed network architecture requires otherwise.

## Initial thresholds

The numeric thresholds below are initial operational defaults, not a finalized NADI policy:

| Alert condition | Initial threshold | Pending duration |
| --- | ---: | ---: |
| CPU usage | > 85% | 10 minutes |
| Memory usage | > 90% | 10 minutes |
| Root filesystem warning | > 85% | 10 minutes |
| Root filesystem critical | > 95% | 5 minutes |
| Application probe latency | > 2000 ms | 10 minutes |
| Telemetry stale/offline | Uses Central exporter state | 2 minutes |
| Nginx process down | Exported value = 0 | 2 minutes |
| Application probe down | Exported value = 0 | 5 minutes |
| Network errors / packet drops | Any increase over 5 minutes | 1 minute |
| Laravel database connection down | Connection status = 0 | 2 minutes |
| Laravel database probe failures | Any increase over 5 minutes | 1 minute |
| Laravel database probe latency | > 2 seconds | 10 minutes |
| Laravel database last success | Older than 5 minutes | 2 minutes |

Tune these values after observing normal workload patterns and operational requirements. Do not interpret a missing time series as a healthy value.

## Metric coverage boundaries

The rules intentionally use only metric families currently emitted by `PrometheusMetricsController`.

Laravel database connection alerts are now included using the dedicated `database_metrics` stream and the five `laravel_db_*` metric families. Queue worker and scheduler alerts remain excluded: their Bridge endpoints currently return HTTP 501 and their presence in a probe definition does not establish that an application exports a real sample. Validate database alert thresholds against non-production workload before adopting them as production policy.

The `nadi_prometheus_exporter_up` metric indicates successful exporter rendering when present. Prometheus scrape availability is therefore evaluated using its built-in `up{job="nadi-central"}` series.

## Alertmanager delivery

Prometheus forwards firing and resolved alert notifications to the local Alertmanager endpoint. The repository includes a matching Alertmanager receiver example at:

`docs/operations/alertmanager/alertmanager.yml.example`

That receiver forwards alerts to the authenticated NADI Central webhook. Follow `docs/operations/alertmanager-webhook-v1.0.md` to configure the dedicated bearer token and receiver safely.

The Central webhook persists alert metadata and history. It does not send email or Telegram notifications and does not perform remediation. External notification routing remains a separate policy decision.

## Applying the configuration

1. Back up the current Prometheus configuration.
2. Install the rule file at the path above.
3. Merge `rule_files` and `alerting.alertmanagers` into the existing Prometheus configuration.
4. Validate the rule file with `promtool check rules /etc/prometheus/rules/nadi-alert-rules.yml`.
5. Validate the full Prometheus configuration with `promtool check config /etc/prometheus/prometheus.yml`.
6. Reload or restart Prometheus using the site's approved change procedure.
7. Confirm the rule groups are loaded in Prometheus and that Alertmanager appears as a configured target.
8. Configure the Central webhook receiver following the webhook operations guide.
9. Confirm a controlled test alert reaches Central Alert Management.

These are deployment instructions only. No production configuration has been changed by this repository update.
