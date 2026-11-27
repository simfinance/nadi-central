# Central Telemetry Backend Delivery — Decision Proposal v1.0

**Status:** Draft for owner decision; not an approved ADR  
**Date:** 27 November 2026  
**Repository:** `simfinance/nadi-central`  
**Base:** `security/telemetry-capability-enforcement`

## 1. Purpose

This proposal defines the decisions required to move Central from an Agent-facing telemetry intake with MySQL persistence to the approved storage and monitoring architecture:

- Metrics: Prometheus.
- Logs: Loki.
- Events: MySQL or an explicitly selected event store.
- Alerts: Alertmanager plus Central metadata.
- Audit and approval history: MySQL, excluded from telemetry cleanup.
- Retention: one global Central policy, consistently applied to MySQL, Prometheus, and Loki.

This document is a proposal only. It does not approve a new wire contract, backend protocol, retention policy, or Agent behavior.

## 2. Verified current Central state

The following points are based on source inspection and the successful isolated MySQL CI run:

1. The Agent telemetry endpoint authenticates the Agent, validates the payload, authorizes and sanitizes records, then calls `TelemetryIngestionService`.
2. `TelemetryIngestionService::ingest()` writes the batch, idempotency keys, stream metadata, sequence watermarks, gaps, and telemetry records/events in one MySQL transaction.
3. The service stores the accepted acknowledgement response in `nadi_telemetry_batches.ack_response` and returns it after the transaction completes.
4. The controller currently responds with HTTP 200 and `data.status = accepted`.
5. No Prometheus write adapter or Loki push adapter was found in the inspected Central source.
6. The current generic telemetry path stores raw record data in MySQL. MySQL is not yet just a bounded outbox/staging layer.
7. Log ingestion remains disabled by default through `NADI_TELEMETRY_LOGS_ENABLED=false`.
8. The stream registry recognizes these streams but has no capability mapping for them, so authorization rejects them:
   - `nginx_access_logs`
   - `application_events`
   - `nginx_http_requests`
   - `application_http_requests`
   - `application_http_errors`

The Agent-side specification describes metrics, logs, and events operations, but its current activation capability list is a target list and is not proof that every collector or stream is implemented. The capability mapping for the currently unmapped streams must be explicitly approved.

## 3. Recommended delivery model

### 3.1 Agent acknowledgement boundary

**Recommendation:** Keep the existing Agent-facing HTTP 200 contract, but define `accepted` as:

> Central has durably committed the batch, its idempotency state, and all required backend-delivery outbox records in one MySQL transaction.

It must not mean that Prometheus and Loki have already confirmed delivery.

This preserves the current Agent-facing success response while making the meaning of acceptance explicit. The Agent may remove the corresponding local spool item only after receiving the successful Central acknowledgement. If the MySQL transaction fails, Central returns an error and the Agent retains/retries the batch.

### 3.2 Durable backend outbox

Add a durable per-batch/per-backend delivery record in the same database transaction as ingestion. A proposed delivery record should track:

- Agent and batch identity.
- Target backend (`prometheus` or `loki`).
- Delivery state (`pending`, `processing`, `delivered`, `retry_wait`, `quarantined`).
- Attempt count and next-attempt timestamp.
- Lease/processing timestamp to recover abandoned workers.
- First/last attempt timestamps.
- Delivered timestamp.
- Sanitized last error code/message.

A backend dispatcher processes pending deliveries independently. A Prometheus outage must not block Loki delivery, and vice versa.

The delivery identity must be idempotent, for example one unique delivery per `(agent_id, batch_id, backend)`. A retry must not create duplicate backend samples/log entries where the backend supports idempotency or deterministic deduplication.

The existing telemetry record/event rows remain bounded staging data until all required backend deliveries are complete and the configured staging/idempotency retention period has elapsed. They must not become the final long-term store for raw metrics or logs.

### 3.3 Backend write protocols

**Prometheus recommendation:** use the Prometheus Remote Write protocol from Central, subject to explicit approval and confirmation that the target Prometheus deployment enables and secures its remote-write receiver. Convert approved numeric metric records to samples with normalized metric names, units, timestamps, and bounded labels.

A scrape/exporter alternative is possible, but it must not silently replace pushed sample delivery: a scrape can miss intermediate Agent samples if the exporter only exposes the latest state.

**Loki recommendation:** use the Loki push API from Central. Apply a strict label allowlist and place high-cardinality or request-specific values in structured metadata/log content, not labels.

Candidate stable labels include the approved stream, environment, server identity, and application identity. Request IDs, record IDs, user IDs, arbitrary URI paths, and other per-request values must not become labels.

These protocol recommendations remain unapproved until the owner confirms backend endpoint, authentication, TLS, and receiver configuration.

### 3.4 Events and HTTP request telemetry

Keep application/system events and HTTP request/error events in MySQL or the event store selected by the owner. Do not route them to Prometheus as arbitrary event payloads.

Before enabling the currently unmapped streams, approve their capability names and scope:
- server-scoped: Nginx access/request streams;
- application-scoped: application events and application HTTP streams.

Server-scoped streams must not carry `application_id`; application-scoped streams must be bound to an approved application belonging to the authenticated Agent.

### 3.5 Retry and failure handling

Proposed behavior:

- Retry transient backend failures with bounded exponential backoff and jitter.
- Persist every attempt and next-attempt time.
- Recover `processing` deliveries whose lease expires.
- Quarantine deliveries that exceed the configured retry/age threshold; do not silently discard them.
- Expose pending count, oldest pending age, retry count, quarantine count, and last successful delivery time per backend.
- Alert Central operators when delivery lag or quarantine crosses an approved threshold.
- Preserve batch and record idempotency across Agent retries and backend retries.

Exact retry counts, maximum age, and alert thresholds remain configuration decisions.

### 3.6 Retention

Use one Central policy taxonomy and apply it to the correct storage engine:

| Data class | Destination | Retention owner |
|---|---|---|
| Metrics | Prometheus | Central policy mapped to Prometheus TSDB retention |
| System/application logs | Loki | Central policy mapped to Loki compactor retention |
| Events and HTTP request/error events | MySQL/event store | Central cleanup service |
| Backend outbox/staging | MySQL | Delete only after delivery completion and dedupe safety window |
| Idempotency keys | MySQL | Separate dedupe retention; must outlive retry/replay windows |
| Unresolved gaps | MySQL | Never delete while unresolved |
| Alerts and alert history | Alertmanager + Central metadata | Explicit alert retention policy |
| Audit/approval history | MySQL | Excluded from telemetry cleanup |
| Notification delivery logs | MySQL | Explicit notification retention policy |

No Prometheus or Loki retention change should be issued until the global policy mapping and operational safeguards are approved.

## 4. Required decisions

1. **ACK boundary:** approve or reject the recommendation that Agent HTTP 200 means durable Central transaction + outbox commit, while Prometheus/Loki delivery is asynchronous.
2. **Prometheus protocol:** approve Remote Write receiver or choose the scrape/exporter alternative.
3. **Loki protocol:** approve Loki Push API and the stable label allowlist.
4. **Stream capabilities:** approve capability names and server/application scope for the currently unmapped event and HTTP streams.
5. **Retry/quarantine:** approve retry age/count and operator alert thresholds.
6. **Retention taxonomy:** approve the data classes and their backend-specific retention mapping.
7. **Deployment prerequisites:** provide/confirm non-production Prometheus/Loki endpoints, authentication, TLS, and supported server versions.

## 5. Implementation gates after approval

1. Record the approved decisions in an ADR; do not treat this proposal as approval.
2. Add outbox schema and transactional enqueue tests.
3. Implement Prometheus and Loki adapters behind configuration flags.
4. Add retry, lease recovery, idempotency, quarantine, and failure-injection tests.
5. Add capability mapping and Agent compatibility tests for approved streams.
6. Add Central UI/API visibility for ingestion and backend delivery health.
7. Run non-production end-to-end tests with real Prometheus and Loki services.
8. Verify retention behavior against test datasets and prove audit/approval data is excluded.
9. Only after these gates, prepare a production deployment plan. No production host or database action is part of this proposal.

## 6. Explicit non-actions

- No Prometheus or Loki endpoint was contacted.
- No production server, database, or configuration was accessed or changed.
- No Agent repository was modified.
- No new Agent protocol or ACK behavior was implemented.
- No telemetry was fabricated.
