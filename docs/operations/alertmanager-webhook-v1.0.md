# NADI Central Alertmanager Webhook v1.0

## Status

Central-side authenticated Alertmanager webhook and alert persistence implementation.

This phase does not change Prometheus scrape configuration, Prometheus alert rules, Alertmanager production configuration, Agent contracts, or notification delivery routing.

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

## Configuration

Set a dedicated random token in the Central environment:

```dotenv
NADI_ALERTMANAGER_WEBHOOK_TOKEN=<dedicated-random-secret>
```

Configure Alertmanager to send the same token using its `http_config.bearer_token_file` option. The token file should be readable only by the Alertmanager service account and trusted administrators.

Do not expose Alertmanager's web listener publicly. Keep its listener bound to `127.0.0.1:9093`.

## Explicitly deferred

- Prometheus alert rules and Alertmanager receiver configuration.
- Alert Management UI and filtering.
- Automatic SMTP routing, recipients, severity policy, and notification deduplication.
- Mapping alert labels to NADI server/application registry records.
- Retention policy values for alert history.

The existing notification outbox remains unchanged. This webhook phase persists alert metadata only; it does not send email.
