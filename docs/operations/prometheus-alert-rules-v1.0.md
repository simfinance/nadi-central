# NADI Prometheus Alert Rules v1.0

## Purpose

This rule set evaluates the NADI Central metrics already exposed by the Central Prometheus exporter. It covers:

- NADI Central scrape availability.
- Telemetry stream stale and offline state.
- Server CPU, memory, and root filesystem usage.
- Nginx process state.
- Application health probes and probe latency.
- Network errors and dropped packets.

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

Tune these values after observing normal workload patterns and operational requirements. Do not interpret a missing time series as a healthy value.

## Metric coverage boundaries

The rules intentionally use only metric families currently emitted by `PrometheusMetricsController`.

Database connection, queue worker, and scheduler alerts are not included in this version. Although the application probe payload has probe names for these checks, their presence in a probe definition does not establish that every application currently exports a corresponding sample. Add dedicated rules only after the relevant series and label behavior are confirmed from live scrape data.

The `nadi_prometheus_exporter_up` metric indicates successful exporter rendering when present. Prometheus scrape availability is therefore evaluated using its built-in `up{job="nadi-central"}` series.

## Alertmanager delivery

This rule file creates Prometheus alert evaluations and forwards firing/resolved notifications to the local Alertmanager endpoint. It does not configure the final notification receiver.

Configure the Alertmanager receiver and route separately after the Central webhook endpoint, authentication token, and notification policy have been reviewed. Until a receiver is configured, alerts may be visible in Alertmanager but will not be delivered to an external notification destination.

## Applying the configuration

1. Back up the current Prometheus configuration.
2. Install the rule file at the path above.
3. Merge `rule_files` and `alerting.alertmanagers` into the existing Prometheus configuration.
4. Validate the rule file with `promtool check rules /etc/prometheus/rules/nadi-alert-rules.yml`.
5. Validate the full Prometheus configuration with `promtool check config /etc/prometheus/prometheus.yml`.
6. Reload or restart Prometheus using the site's approved change procedure.
7. Confirm the rule groups are loaded in Prometheus and that Alertmanager appears as a configured target.
8. Confirm test alerts reach Alertmanager before enabling external notification routes.

These are deployment instructions only. No production configuration has been changed by this repository update.
