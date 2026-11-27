# NADI Central Telemetry Ingestion Security Contract v1.1

Status: Central implementation branch; Agent compatibility is not yet verified end-to-end.

## Endpoint and envelope

The existing endpoint and JSON envelope are unchanged:

- `POST /api/v1/nadi/agent/telemetry/batches`
- Agent authentication remains the existing `nadi.agent` middleware.
- The Agent identity is taken from the authenticated Central Agent record.
- Required envelope fields remain `protocol_version`, `batch_id`, `agent_instance_id`, `sent_at`, and `records`.
- Each record retains `record_id`, `stream`, `sequence`, `observed_at`, `collected_at`, optional `application_id`, optional `metric_set`, and `data`.

Limits remain 500 records per batch, 1 MiB per batch, and 64 KiB per record. Central validates the authenticated Agent instance and UTC-normalizes timestamps before persistence.

## Grant authorization

A telemetry record is accepted only when Central finds a grant that satisfies every condition below:

1. `grant_status = active`.
2. `acknowledgement_status = success` (pending and failed are denied).
3. `revoked_at IS NULL`.
4. `expires_at` is in the future.
5. The grant, registration, application, Agent, and server identities match the authenticated Central records.
6. The signed grant payload has `monitoring_enabled = true`, contains the exact required capability, and its Ed25519 signature verifies.

Application telemetry requires the exact `application_id` owned by the authenticated Agent. An application grant never authorizes telemetry for another application.

### Server-level rule

Server-level streams must omit `application_id`. They are authorized only if at least one application on the same Agent has all of the following: approved registration, active activation, no monitoring suspension, and an active, unexpired, non-revoked, successfully acknowledged grant that explicitly contains the required server capability (for example `server_metrics` or `system_logs`). This follows the Master Architecture rule that system-level collection may run when at least one application on that server is active with a valid grant. An application grant does not authorize server telemetry merely because it exists.

## Capability allowlist and stream matrix

Grant generation uses the Central `nadi.activation.capabilities` allowlist:

- `server_metrics`
- `application_metrics`
- `database_monitoring`
- `queue_monitoring`
- `scheduler_monitoring`
- `system_logs`
- `application_logs`

No capability is treated as a substitute for another capability.

| Stream | Required capability | Scope | Central validation |
|---|---|---|---|
| `server_metrics` | `server_metrics` | Server | No application_id; at least one eligible app grant on same server with exact capability |
| `nginx_metrics` | `server_metrics` | Server | Same server-level rule |
| `nginx_vhost_metrics` | `server_metrics` | Server | Same server-level rule |
| `nginx_error_logs` | `system_logs` | Server | Same server-level rule; strict log schema; sanitized; feature flag off by default |
| `application_metrics` | `application_metrics` | Application | Exact application grant, ACK success, signature, identity, status and expiry |
| `application_logs` | `application_logs` | Application | Exact application grant; strict log schema; sanitized; feature flag off by default |
| `database_metrics`, `database_connections` | `database_monitoring` | Application | Exact application grant and application_id |
| `queue_metrics`, `queue_jobs` | `queue_monitoring` | Application | Exact application grant and application_id |
| `scheduler_metrics`, `scheduler_events` | `scheduler_monitoring` | Application | Exact application grant and application_id |
| `nginx_access_logs` | Not mapped | Server | Rejected until its own schema and ingestion path are defined |
| `application_events` | Not mapped | Application | Rejected; no event capability is currently defined |
| `nginx_http_requests` | Not mapped | Server | Rejected; no exact capability mapping is defined |
| `application_http_requests`, `application_http_errors` | Not mapped | Application | Rejected; no exact capability mapping is defined |

The last four legacy stream names remain recognized by the request validator for clear compatibility errors, but the authorization layer rejects them with HTTP 422 until their capability and payload contracts are explicitly defined. They are not stored.

## Log payload schemas

Log ingestion is deliberately disabled by default with `NADI_TELEMETRY_LOGS_ENABLED=false`. Do not enable it until the Agent release is confirmed to emit these exact schemas and Central/Agent integration tests pass.

### nginx_error_logs

`data` accepts exactly:

- `timestamp`: required date/time string parseable by Central.
- `severity`: required string; one of `emergency`, `alert`, `critical`, `error`, `warning`, `notice`, `info`, `debug`.
- `message`: required non-empty string, maximum 8,192 bytes.
- `source`: optional; if present it must equal `nginx`.

The stored representation contains only the normalized timestamp, normalized severity, fixed source `nginx`, and redacted message. Unknown fields are rejected.

### application_logs

`data` accepts exactly:

- `timestamp`: required date/time string parseable by Central.
- `level`: required string using the same eight levels listed above.
- `channel`: required non-empty string, maximum 100 bytes.
- `message`: required non-empty string, maximum 8,192 bytes.
- `context`: optional object with at most 50 top-level fields.

Context strings are limited to 2,048 characters. Sensitive keys such as authorization, password, token, cookie, API key, secret, credential, and stack trace are redacted. Message text is scanned for common authorization, bearer, cookie, password, token, API-key and secret patterns. Raw stack traces are rejected. Unknown fields and unsupported context value types are rejected. The enclosing record remains subject to the 64 KiB record limit and 1 MiB batch limit.

Application log records require an `application_id`; Central resolves it under the authenticated Agent and authorizes only that application's acknowledged grant. No cross-application lookup is permitted by the authorization path.

## HTTP error contract

The existing JSON error envelope is preserved: `success: false` and `message`. The HTTP status is authoritative.

| HTTP | Meaning | Agent action |
|---|---|---|
| 409 Conflict | Batch ID reused with a different payload, record ID conflict, or sequence already owned by another record | Do not retry the same conflicting payload indefinitely. Quarantine the batch and report the conflict; never mutate record_id/sequence in place. |
| 410 Gone | Batch is older than configured retention, or the application/server monitoring scope no longer has a valid acknowledged grant | Permanent for that payload/scope. Stop retrying; poll control plane for a renewed grant or scope status. |
| 413 Payload Too Large | Batch exceeds 1 MiB, has more than 500 records, or one record exceeds 64 KiB | Split the batch into smaller batches with new batch_id values while preserving each record_id and sequence. If a single record exceeds 64 KiB, do not retry unchanged; quarantine it for correction. |
| 422 Unprocessable Entity | Envelope, timestamp, stream mapping, identity field, or log schema is invalid | Permanent for the unchanged payload. Do not retry without correcting the payload/contract. |
| 403 Forbidden | Authenticated Agent does not own the requested application | Permanent scope violation; do not retry. |
| 503 Service Unavailable | Log ingestion feature is disabled pending contract verification, or retention/config dependency is unavailable | Retry with bounded exponential backoff and jitter; do not drop the batch. |

Duplicate accepted batches with the same batch_id and identical canonical payload return the stored ACK. Reusing a batch_id with a different payload returns 409. Record IDs and sequence numbers must remain stable across retries. A 413 split creates new batch IDs but preserves record identity and sequence.

## Deployment gate

Log streams stay disabled unless an operator explicitly sets `NADI_TELEMETRY_LOGS_ENABLED=true` after:

1. Confirming the Agent's emitted schema matches this contract.
2. Running Central unit and integration tests, including redaction and cross-application isolation.
3. Running a real Agent-to-Central integration test in a non-production environment.
4. Confirming the Agent handles 409/410/413/422/503 as specified.

This document does not assert that the Agent currently emits these log schemas or that the integration tests have passed.
