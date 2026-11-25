# NADI Alerting Production Activation Runbook v1.0

## Status and scope

This runbook describes a controlled production activation of the existing Prometheus alert rules, local Alertmanager integration, and authenticated NADI Central webhook.

It is an operational procedure, not evidence that production activation has occurred. Follow the site's approved change window, access controls, backup policy, and deployment procedure.

This activation is limited to alert evaluation and persistence in NADI Central. It does not configure SMTP, Telegram, external notification destinations, Agent behavior, or automated remediation.

## Preconditions

Do not begin the production change until all of the following are true:

- The Central webhook implementation and its database migration have been reviewed and deployed through the approved Central release process.
- The migration creating `nadi_alerts` and `nadi_alert_events` has completed successfully.
- The Central application is reachable over HTTPS at the webhook URL.
- The Prometheus and Alertmanager services are healthy and remain bound to their approved local-only listeners.
- The current Prometheus and Alertmanager configurations have been backed up and their paths/owners recorded.
- A dedicated webhook secret has been generated and transferred through an approved secret-handling channel.
- The Central environment and Alertmanager secret file can be updated without exposing the secret in source control, shell history, terminal recordings, or logs.
- The operator has a rollback window and permission to reload the two services.

If any precondition is not met, stop. Do not compensate by bypassing authentication, exposing local APIs, or changing Agent configuration.

## Fixed integration values

| Item | Value |
| --- | --- |
| Central webhook | `https://nadi.pesantrenalirsyad.org/api/v1/nadi/alertmanager/webhook` |
| Central environment key | `NADI_ALERTMANAGER_WEBHOOK_TOKEN` |
| Alertmanager token file | `/etc/alertmanager/secrets/nadi-central-webhook-token` |
| Alertmanager local target | `127.0.0.1:9093` |
| Prometheus rule file | `/etc/prometheus/rules/nadi-alert-rules.yml` |

The webhook secret is separate from Agent credentials and `NADI_PROMETHEUS_METRICS_TOKEN`. Never commit the secret or include it in alert labels/annotations.

## Activation sequence

Perform one stage at a time. Record the result before proceeding.

### Stage 1 — Prepare and back up

1. Confirm the active service names, service accounts, configuration paths, and approved reload mechanism on the host.
2. Back up `/etc/prometheus/prometheus.yml` and `/etc/alertmanager/alertmanager.yml` to timestamped, access-restricted files.
3. Confirm the current Prometheus targets are healthy and record the current Alertmanager service state.
4. Confirm the Central webhook release and migration are complete before configuring Alertmanager to send to it.

Do not overwrite either configuration with a repository example. Both examples are merge references.

### Stage 2 — Configure the dedicated webhook secret

1. Generate one high-entropy random secret using the site's approved secret-generation method. A 32-byte random value or stronger is appropriate.
2. Set that exact value as `NADI_ALERTMANAGER_WEBHOOK_TOKEN` in the Central environment using the approved secret-handling process.
3. Store the same value in `/etc/alertmanager/secrets/nadi-central-webhook-token` on the Alertmanager host.
4. Restrict the secret directory and file so only the Alertmanager service account and trusted administrators can read them. Confirm the service account can read the file.
5. Do not put the secret value in a command argument, shell history, source control, ticket, screenshot, or log.

After changing the Central environment, refresh Laravel's configuration cache using the site's approved deployment procedure. Do not run broad cache-clearing commands as an improvised substitute for the approved release process.

### Stage 3 — Configure Alertmanager receiver

1. Merge the receiver and route settings from `docs/operations/alertmanager/alertmanager.yml.example` into the existing Alertmanager configuration.
2. Preserve unrelated, approved routes, receivers, inhibition rules, and global settings. Resolve route conflicts deliberately; do not blindly replace the existing file.
3. Keep the Alertmanager listener bound to `127.0.0.1:9093`.
4. Validate the complete merged configuration:

```bash
amtool check-config /etc/alertmanager/alertmanager.yml
```

5. If validation fails, stop and restore/fix the configuration before any reload.
6. Reload or restart Alertmanager using the site's approved change procedure.
7. Confirm the service is active and inspect service logs for configuration or delivery errors. Do not print or log the bearer token.

### Stage 4 — Install Prometheus rules and connect Alertmanager

1. Install `docs/operations/prometheus/nadi-alert-rules.yml` at `/etc/prometheus/rules/nadi-alert-rules.yml`.
2. Ensure the Prometheus service account can read the rule file and its parent directory.
3. Merge the following sections into the existing `/etc/prometheus/prometheus.yml`. Preserve the existing `global` and `scrape_configs` sections and all unrelated settings:

```yaml
rule_files:
    - /etc/prometheus/rules/nadi-alert-rules.yml

alerting:
    alertmanagers:
        - static_configs:
              - targets:
                    - 127.0.0.1:9093
```

4. Validate the rule file:

```bash
promtool check rules /etc/prometheus/rules/nadi-alert-rules.yml
```

5. Validate the complete Prometheus configuration:

```bash
promtool check config /etc/prometheus/prometheus.yml
```

6. If either validation fails, stop. Do not reload Prometheus with invalid configuration.
7. Reload or restart Prometheus using the site's approved change procedure.

### Stage 5 — Verify end-to-end delivery

1. Confirm Prometheus targets remain healthy.
2. Confirm the rule groups are loaded and evaluate without errors.
3. Confirm Prometheus reports the local Alertmanager as configured/reachable.
4. Trigger one controlled, non-disruptive test alert using an approved test method. Do not induce real CPU, memory, disk, network, or application failures on production.
5. Confirm Alertmanager receives the firing notification and posts it to the Central webhook.
6. Confirm the alert appears in NADI Central Alert Management and that one corresponding event is persisted.
7. Confirm a resolved notification updates the alert state and creates the expected state-history event.
8. Confirm duplicate delivery does not create duplicate history rows.
9. Review logs for delivery failures without exposing credentials or sensitive payloads.

Do not declare activation complete until both firing and resolved delivery have been observed. If a safe test cannot be performed, record the integration as configured but not end-to-end verified.

## Rollback

Rollback only the components changed during this activation.

1. If delivery is failing or the Central endpoint is unavailable, restore the previous Alertmanager configuration from the recorded backup and validate it with `amtool check-config`.
2. Reload/restart Alertmanager using the approved procedure. This stops webhook delivery without exposing Alertmanager publicly.
3. If Prometheus rule evaluation or Alertmanager connectivity is unhealthy, restore the previous Prometheus configuration and validate it with `promtool check config`.
4. Remove the newly installed rule file only if it was introduced by this change and the restored Prometheus configuration no longer references it.
5. Reload/restart Prometheus using the approved procedure.
6. Revoke/rotate the dedicated webhook secret if it may have been exposed. Update Central and Alertmanager together through the approved secret procedure.
7. Preserve Central alert records and event history for investigation. Do not drop the alert tables as part of routine rollback.
8. Confirm Prometheus scraping and the pre-change monitoring path are healthy again.

Do not roll back unrelated routes, receivers, scrape jobs, Agent settings, or application configuration.

## Completion record

Record the following in the change ticket or approved operations log:

- Change window and operator.
- Backup locations and timestamps (do not attach secrets).
- Central release identifier and migration result.
- Prometheus and Alertmanager validation results.
- Service reload/restart results.
- Firing delivery result and Central alert reference.
- Resolved delivery result and history reference.
- Duplicate-delivery behavior.
- Any warnings, deviations, or rollback actions.

## Operational boundaries

- Prometheus evaluates metrics and forwards alert notifications.
- Alertmanager groups and routes notifications to the Central webhook.
- NADI Central authenticates the webhook and persists alert metadata/history.
- SMTP, Telegram, and other external notification destinations are outside this activation.
- No automated remediation is performed.
- No Agent contract, database, or runtime behavior is changed by this procedure.
- Production configuration is changed only by an operator following the approved change process.
