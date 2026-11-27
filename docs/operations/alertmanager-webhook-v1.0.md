# NADI Central Alertmanager Webhook v1.0

## Status

Central-side authenticated Alertmanager webhook and alert persistence implementation, with a matching Alertmanager receiver example.

This phase connects Prometheus alert delivery to Central alert persistence. It does not configure SMTP or other external notification destinations, change Agent contracts, or perform remediation.

## Endpoint

- Method: POST
- Path: `/api/v1/nadi/alertmanager/webhook`
- Content type: `application/json`
- Authentication: Bearer token from `NADI_ALERTMANAGER_WEBHOOK_TOKEN`
- Rate limit: 120 requests per minute
- Maximum request body: 1 MiB
- Maximum alerts per webhook group: 200

The webhook token is independent from Agent credentials and `NADI_PROMETHEUS_METRICS_TOKEN`. An unset token disables the endpoint with HTTP 503. An invalid token returns HTTP 401.

## Payload

The endpoint accepts the standard Alertmanager webhook envelope, including `version`, `groupKey`, `status`, `receiver`, `externalURL`, and an `alerts` list.

Each alert must provide:

- `status`: `firing` or `resolved`
- `labels.alertname`
- `startsAt` and `endsAt`
- `fingerprint`

Labels, annotations, generator URL, group key, receiver, and external URL are persisted as alert metadata. The Alertmanager zero-time end timestamp (year 0001) is normalized to NULL because it is outside MySQL DATETIME's supported range.

Only controlled Prometheus labels and annotations should be included. Do not place credentials, secrets, personal data, or business payloads in alert labels or annotations.

## Persistence and idempotency

- `nadi_alerts` stores the latest accepted state per Alertmanager fingerprint.
- `nadi_alert_events` stores accepted state changes as history.
- Event identity is a SHA-256 hash of fingerprint, status, start/end timestamps, labels, and annotations.
- Duplicate event deliveries are acknowledged but not inserted again.
- A new event updates the current alert snapshot and appends one history row.
- No Agent or application identity is inferred from labels in this phase.

The tables use UUID primary keys and do not cascade-delete operational history.

## Response

Successful processing returns HTTP 200 with received, accepted, and duplicate counts.

Authentication failures return HTTP 401. Invalid payloads return HTTP 422. Oversized bodies return HTTP 413. Missing webhook configuration returns HTTP 503. Internal failures return a generic HTTP 500 response; the raw webhook payload and credential are not written to application logs by this controller.

## Central configuration

Set a dedicated random token in the Central environment:

```dotenv
NADI_ALERTMANAGER_WEBHOOK_TOKEN=<dedicated-random-secret>
```

After changing the environment, refresh Laravel's configuration cache using the site's approved deployment procedure. Do not place the token in source control or paste it into support logs.

## Alertmanager receiver configuration

The repository example is:

`docs/operations/alertmanager/alertmanager.yml.example`

It configures the receiver to POST firing and resolved alerts to:

`https://nadi.pesantrenalirsyad.org/api/v1/nadi/alertmanager/webhook`

The example expects a token file at:

`/etc/alertmanager/secrets/nadi-central-webhook-token`

Create the directory and token file on the Alertmanager host. The file must contain only the same secret value configured as `NADI_ALERTMANAGER_WEBHOOK_TOKEN`, followed by an optional newline. Restrict directory and file permissions so only the Alertmanager service account and trusted administrators can read them. Do not put the token directly in `alertmanager.yml`.

Merge the receiver, route, and global settings from the example into the existing Alertmanager configuration; back up the existing file first. Preserve any independently approved routes or receivers. The example uses the existing local Alertmanager listener and does not require exposing port 9093 publicly.

Validate the merged configuration with:

```bash
amtool check-config /etc/alertmanager/alertmanager.yml
```

Then reload or restart Alertmanager using the site's approved change procedure. Confirm Prometheus is connected to Alertmanager and that a controlled test alert appears in Central Alert Management. Do not enable external notification destinations as part of this step.

## Delivery behavior and boundaries

- Alertmanager sends firing and resolved notifications to Central.
- Alertmanager retries delivery according to its webhook delivery behavior; Central idempotency prevents duplicate event rows for identical events.
- Alert grouping is controlled by the Alertmanager route configuration.
- Central persists alert metadata and history only.
- This receiver does not send email or Telegram notifications.
- No auto-remediation is performed.

The existing notification outbox remains unchanged. SMTP routing, recipients, severity policy, and notification deduplication remain separate work requiring explicit policy decisions.

## Operational safety

- Keep Alertmanager's web listener bound to `127.0.0.1:9093`.
- Keep Prometheus and Alertmanager local APIs private.
- Use HTTPS for the Central webhook endpoint.
- Use a dedicated high-entropy webhook token and rotate it through an approved change.
- Do not expose credentials, secrets, personal data, or business payloads in alert labels or annotations.
- No production configuration is changed by this repository documentation update.
