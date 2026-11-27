# NADI Central Telemetry Storage and Retention Audit v1.0

Date: 23 November 2026
Branch: `security/telemetry-capability-enforcement`
Scope: NADI Central only

## 1. Current Central persistence path

The current Central ingestion implementation persists telemetry into MySQL tables:

- `nadi_telemetry_batches`: accepted batch identity, payload hash, counts, and replay acknowledgement.
- `nadi_telemetry_streams`: per-Agent and per-scope stream registry and sequence watermarks.
- `nadi_telemetry_record_keys`: record identity, sequence ownership, and payload hash for idempotency.
- `nadi_telemetry_records`: metric and non-HTTP telemetry payloads.
- `nadi_http_request_events`: dedicated HTTP event persistence path.
- `nadi_telemetry_gaps`: detected and resolved sequence gaps.

The current ingestion path writes metric and log records through the generic telemetry-record persistence path. The HTTP-event persistence path exists in the service, but the currently registered HTTP stream names have no capability mapping and are rejected by authorization before persistence.

## 2. Retention implementation observed

The Central cleanup service currently supports five MySQL policy keys:

| Policy key | Table | Expiry timestamp |
|---|---|---|
| telemetry_records | nadi_telemetry_records | created_at |
| http_request_events | nadi_http_request_events | created_at |
| telemetry_record_keys | nadi_telemetry_record_keys | last_seen_at |
| telemetry_batches | nadi_telemetry_batches | received_at |
| telemetry_gaps | nadi_telemetry_gaps | gap_resolved_at; resolved rows only |

Batch rows are protected from deletion while referenced by telemetry records or HTTP request events. Cleanup is chunked in batches of 1,000 rows. Unresolved gaps are not eligible for deletion. Audit, approval, registry, and notification tables are not part of this telemetry cleanup list.

The scheduled cleanup command runs daily at 02:30 and supports a dry-run option.

## 3. Changes in this audit

### 3.1 Ingestion and cleanup policy consistency

The ingestion age check now reads the complete `telemetry_batches` policy:

- Missing policy remains fail-closed with HTTP 503.
- Disabled policy no longer rejects old batches based on the retention cutoff.
- Enabled policy must contain a positive integer retention period.
- Timestamp cutoff calculation uses UTC.

### 3.2 Cleanup safety

Before calculating a deletion cutoff, cleanup now validates that the configured retention period is a positive integer. An invalid policy is reported as `invalid_policy`, logged as a warning, and skipped without deleting rows. Cutoff calculation uses UTC.

## 4. Architecture baseline not yet represented in Central storage

The approved Master Architecture specifies:

- Metrics: Prometheus.
- Logs: Loki.
- Events: MySQL or a selected event store.
- Alerts: Alertmanager plus Central metadata.
- Audit and approval: MySQL, excluded from telemetry cleanup.
- Global configurable retention policy applied consistently to Prometheus, Loki, and MySQL.

The current Central source inspected in this audit has a MySQL telemetry intake/storage path and MySQL cleanup only. No Prometheus write/query integration, Loki write/query integration, or backend-specific retention operation was found in the inspected Central telemetry files.

This is an implementation gap against the storage baseline, not an authorization to change the approved architecture. The backend integration design and deployment prerequisites must be addressed before treating MySQL telemetry tables as the final metrics/logs storage.

## 5. Remaining Central work

1. Verify the actual migrations and indexes for all telemetry tables, including unique constraints and foreign-key behavior.
2. Define and enforce retention dependency rules so record-idempotency keys cannot expire earlier than the data/batch window they protect.
3. Decide how long unresolved sequence gaps must be retained and how their lifecycle is closed.
4. Implement and verify the Central-side Prometheus and Loki integration according to the approved baseline, without changing the architecture silently.
5. Extend the global retention orchestration to the configured metrics/logs backends; preserve audit and approval history.
6. Keep storage status truthful: do not report a metrics/logs backend as active until ingestion and query paths are operational.

## 6. Verification boundary

This audit is based on Central source inspection on the branch listed above. No test suite, migration, database operation, deployment, Prometheus, or Loki runtime verification was performed as part of this work.
