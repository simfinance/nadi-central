# NADI Central Telemetry Storage Gap Audit v1.1

Date: 27 November 2026
Repository: `simfinance/nadi-central`
Base branch: `security/telemetry-capability-enforcement`
Follow-up baseline: `6cd6ffb31fdfe91297eb7a1376900468cbb3a2ef`
Scope: source repository inspection only

## 1. Governing architecture requirements

The approved Master Architecture v1.0 establishes the following storage baseline:

- Metrics: Prometheus.
- Logs: Loki.
- Events: MySQL or a selected event store.
- Alerts: Alertmanager plus Central metadata.
- Audit and approval history: MySQL and excluded from telemetry cleanup.
- Retention: one global Central policy with configurable values per data type, applied consistently to Prometheus, Loki, and MySQL.
- Raw metrics and logs are not to be retained in MySQL as their final time-series/log store.
- Agent pushes metrics, logs, and events to Central after a valid activation grant.
- The Agent-to-Central transport baseline is JSON Lines plus gzip. This does not, by itself, define the Central-to-backend write protocol.
- No telemetry, probe, or operational heartbeat may be collected or accepted before approval and a valid activation grant.

The Master Architecture does not specify the final Central-to-Prometheus write mechanism, the Loki label contract, or the durability boundary for asynchronous backend delivery. Those details remain open and must not be silently selected by implementation.

## 2. Repository state inspected

The audit inspected the following source paths on the audited branch:

- `database/schema/nadi-baseline.sql`
- `database/migrations/`
- `app/Http/Controllers/Api/V1/Nadi/AgentTelemetryController.php`
- `app/Services/Telemetry/TelemetryIngestionService.php`
- `app/Services/Telemetry/TelemetryAuthorizationService.php`
- `app/Services/Telemetry/TelemetryStreamRegistry.php`
- `app/Services/TelemetryRetentionCleanupService.php`
- `app/Services/Nadi/TelemetryRetentionService.php`
- `app/Console/Commands/TelemetryRetentionCleanupCommand.php`
- `app/Console/Commands/TelemetryRetentionCommand.php`
- `app/Services/PrometheusQueryService.php`
- `config/nadi.php`
- `routes/console.php`
- `docs/api/telemetry-ingestion-security-contract-v1.1.md`
- `docs/architecture/telemetry-storage-retention-audit-v1.0.md`

This audit does not include a live database connection, migration execution, production host inspection, or runtime verification.

## 3. Findings

### 3.1 Telemetry schema is not reproducible from the checked-in schema and migrations

The checked-in `database/schema/nadi-baseline.sql` contains 14 table definitions. It defines `nadi_retention_policies`, but does not define any of these tables used by the ingestion and retention services:

- `nadi_telemetry_batches`
- `nadi_telemetry_streams`
- `nadi_telemetry_record_keys`
- `nadi_telemetry_records`
- `nadi_http_request_events`
- `nadi_telemetry_gaps`

The complete migration directory at the audited commit also contains no migration creating those six tables.

The ingestion and cleanup code directly queries or writes these tables. Therefore, a fresh environment created solely from the checked-in baseline schema and migrations cannot be assumed to support the current telemetry code.

This finding does not prove that the tables are absent from an existing Central database. They may have been created by manually executed SQL or another untracked process. Live database state was not available for this audit.

Follow-up completed in repository PR #3: the six-table forward-only migration was merged, and a fresh MySQL 8.4 CI database successfully applied the complete Central migration chain. This proves reproducibility from the checked-in baseline in CI; it does not prove the live production database is free of schema drift. Existing telemetry tables are skipped when present, so compare live `SHOW CREATE TABLE` output before deployment. Do not use destructive schema replacement.

### 3.2 Current ingestion persists raw telemetry in MySQL

`TelemetryIngestionService` writes accepted batches, record keys, stream metadata, sequence watermarks, gaps, and generic telemetry records into MySQL. Its generic record persistence path stores both `metric_set` and `data` in `nadi_telemetry_records`. The log stream registry includes log streams, and the security contract specifies log sanitization, but no Loki writer was found in the inspected Central source.

The current code therefore provides a Central intake and MySQL persistence path; it does not establish Prometheus or Loki as the operational destination for Agent telemetry.

### 3.3 Prometheus integration found is query/export oriented, not Agent telemetry write integration

The repository contains Prometheus query services/controllers, Prometheus monitoring UI, a Central metrics exporter, and alert rules. These components do not establish an Agent telemetry write path into Prometheus.

No Loki integration source, Loki client configuration, Loki push implementation, or Loki retention operation was found in the audited repository tree.

### 3.4 Two MySQL retention services overlap but differ

The scheduled command in `routes/console.php` is:

- `nadi:telemetry-retention-cleanup`
- Daily at 02:30
- Uses `TelemetryRetentionCleanupService`
- Supports `--dry-run`

A second command, `nadi:telemetry:prune`, calls `TelemetryRetentionService`. It is not scheduled in the inspected `routes/console.php`.

The two services have different policy definitions and timestamp semantics:

| Concern | Scheduled cleanup service | Alternate prune service |
|---|---|---|
| Service | `TelemetryRetentionCleanupService` | `TelemetryRetentionService` |
| Record expiry column | `created_at` | `observed_at` |
| HTTP event expiry column | `created_at` | `observed_at` |
| Record-key expiry | `last_seen_at` | `last_seen_at` |
| Batch expiry | `received_at` | `received_at` |
| Gap expiry | `gap_resolved_at`, resolved only | `gap_resolved_at`, resolved only |
| Central health snapshots | Included | Not included |
| Dry-run | Supported | Not supported |

The scheduled cleanup service currently guards batch deletion while dependent telemetry records or HTTP events remain. It does not currently include a backend retention operation for Prometheus or Loki.

The alternate service is not shown as scheduled in the inspected console schedule. Its operational usage outside the inspected scheduler was not verified.

### 3.5 Retention UI and cleanup policy do not cover the required backend data types

The current retention controller exposes MySQL-oriented policy keys:

- `telemetry_records`
- `http_request_events`
- `telemetry_record_keys`
- `telemetry_batches`
- `telemetry_gaps`
- `central_health_snapshots`

These are not yet a complete representation of the Master Architecture's backend retention scope, which includes metrics, application logs, system logs, events, alerts, audit logs, and notification logs.

The current cleanup service only deletes configured MySQL tables. There is no evidence in the inspected implementation that a policy update changes Prometheus or Loki retention.

### 3.7 Alternate prune command helper-call defect (fixed in PR #3)

The original source at the audited commit declared the private helper as:

```php
private function deleteInChunks(
    string $table,
    $query
): int
```

However, both `deleteExpiredRows()` and `deleteExpiredBatches()` call it with only the query argument:

- `return $this->deleteInChunks($query);` in `deleteExpiredRows()`
- `return $this->deleteInChunks($query);` in `deleteExpiredBatches()`

The alternate command `nadi:telemetry:prune` invokes this service. When an enabled policy reaches either deletion path, PHP will raise an argument-count error because the required `$query` parameter is missing after the single argument is bound to `$table`.

PR #3 corrected both call sites to pass the table name and added MySQL regression tests for expired-record deletion, child-before-batch deletion, and preservation of batches with remaining dependent records. CI passed these tests. The command remains unscheduled in the inspected `routes/console.php`; external operational use remains unverified. Do not switch the scheduled cleanup to this command until its operational ownership and policy semantics are reviewed.

### 3.6 Log ingestion remains disabled by default

`config/nadi.php` sets `telemetry.logs_enabled` from `NADI_TELEMETRY_LOGS_ENABLED`, defaulting to false. This is consistent with the security contract's deployment gate. Keep it disabled until Agent schema compatibility, Central tests, and non-production end-to-end verification are completed.

### 3.7 Supplied DDL reconciled with ingestion source

The project owner supplied complete `CREATE TABLE` definitions for all six telemetry tables listed in finding 3.1. The definitions were reviewed against the Central ingestion source, including batch replay, stream resolution, record idempotency, generic record persistence, HTTP event persistence, sequence watermarks, and gap creation/resolution.

The reviewed column and constraint contract is consistent with the fields used by the inspected ingestion service:

- Batch persistence uses the supplied batch identity, payload hash, status, counters, timestamps, and JSON acknowledgement.
- Stream resolution uses the supplied agent/application scope, stream key/name/source, sequence watermarks, and observed/received timestamps.
- Record idempotency uses the supplied agent/record and stream/sequence unique constraints.
- Generic telemetry persistence uses the supplied batch/stream/agent/application references, sequence, observed/collected timestamps, and JSON data.
- HTTP event persistence uses the supplied HTTP metadata fields, including sequence, status, sizes, durations, sanitized request URI, and JSON metadata.
- Sequence-gap tracking uses the supplied nullable sequence range, status, and detected/resolved timestamps.

The following forward-only Laravel migrations were merged in PR #3:

- `database/migrations/2026_11_27_000001_create_nadi_telemetry_storage_tables.php`
- `database/migrations/2026_11_27_000002_add_activation_grant_acknowledgement_fields.php`
- `database/migrations/2026_11_27_000003_add_monitoring_suspension_timestamp_to_nadi_applications.php`
- PR: https://github.com/simfinance/nadi-central/pull/3

The telemetry migration creates each telemetry table only when absent. It does not alter an existing table or reconcile schema drift. All three migrations are forward-only and intentionally do not remove persisted telemetry, grant acknowledgement, or suspension state on rollback. The migration chain was executed successfully on a fresh MySQL 8.4.13 CI database after importing the checked-in baseline.


### 3.8 Activation grant acknowledgement schema was missing

The checked-in baseline definition for `nadi_activation_grants` did not include fields used by `NadiActivationGrant`, `ActivationGrantService`, and `ActivationAcknowledgementService`:

- `acknowledgement_status`
- `acknowledgement_request_id`
- `acknowledged_at`
- `acknowledgement_error_code`
- `acknowledgement_message`

The absence caused authorization and activation-grant tests to fail against a fresh database. PR #3 added these columns and a unique index on `acknowledgement_request_id`. Existing grant rows receive the safe default status `pending`; do not infer successful Agent acknowledgement from an existing application status during production rollout.

### 3.9 Application monitoring suspension timestamp was missing

The baseline definition for `nadi_applications` did not include `monitoring_suspended_at`, although `ApplicationMonitoringService`, activation acknowledgement handling, and telemetry authorization use it. PR #3 added the nullable microsecond-precision timestamp and updated the `NadiApplication` model fillable/cast metadata.

### 3.10 Fresh-schema CI validation is now available

PR #3 added `.github/workflows/central-ci.yml`. The workflow:

1. Starts an isolated MySQL 8.4 service.
2. Imports `database/schema/nadi-baseline.sql`.
3. Runs the complete Central migration chain.
4. Runs the PHPUnit suite.

The successful run used PHP 8.4.28 and MySQL 8.4.13 and completed with 49 tests passed and 155 assertions. This is CI validation only; it is not a production migration or runtime verification.

### 3.11 PHP runtime requirement is inconsistent in Composer metadata

The repository declares PHP `^8.3` in `composer.json`, while the locked dependency set includes Symfony 8.1 packages requiring PHP 8.4.1 or newer. The CI runner therefore uses PHP 8.4.28. Verify the production Central PHP runtime before deployment and reconcile the declared Composer platform requirement with the supported runtime. No production runtime was inspected or changed.

## 4. Decisions that remain open

The following are not established by the Master Architecture and must remain explicit open decisions:

1. Central-to-Prometheus write mechanism and protocol.
2. Whether Central writes synchronously or uses a durable outbox/queue before acknowledging Agent batches.
3. Exact durability point for Agent ACK: durable Central intake, durable backend write, or another explicitly approved boundary.
4. Retry, timeout, backoff, dead-letter/quarantine, and replay behavior for Prometheus and Loki failures.
5. Mapping of NADI stream, server, agent, application, environment, and capability identity into Prometheus labels and Loki labels/structured metadata.
6. Loki label cardinality limits and which identity fields belong in labels versus log body metadata.
7. Mapping and normalization of metric names, units, types, timestamps, and metric_set into Prometheus samples.
8. Whether existing MySQL telemetry tables are retained as a bounded staging/outbox/idempotency layer, or whether their roles are split into metadata-only tables.
9. Global retention data_type taxonomy and the exact mapping to Prometheus, Loki, and MySQL operations.
10. Retention semantics for idempotency keys, batches, and unresolved/resolved sequence gaps.
11. How retention changes are safely applied to Prometheus and Loki without silently altering unrelated backend data.
12. Required backend readiness, authentication, TLS, and service endpoint configuration for the Central deployment.

No paid service or paid dependency is required or proposed by this audit. Any implementation must preserve the project's zero-cost software/API/license constraint.

## 5. Recommended implementation sequence

This is a sequence recommendation, not a new architecture approval:

1. Before production rollout, compare the live Central schema against all three merged forward-only migrations and reconcile any pre-existing schema drift without destructive replacement.
2. Approve the Central-to-Prometheus and Central-to-Loki write contracts, including identity/label mapping and ACK durability semantics.
3. Define one authoritative retention orchestration path; retire or consolidate the duplicate MySQL cleanup path only after confirming no external operational dependency.
4. Implement backend adapters and durable failure handling according to the approved contracts.
5. Preserve Agent-facing API compatibility unless a separately reviewed contract change is approved.
6. Add unit, feature, failure/retry, idempotency, retention, and non-production integration tests.
7. Keep log ingestion disabled until the Agent schema and end-to-end deployment gate are verified.
8. Report Prometheus/Loki storage as active only after real write and query verification.

## 6. Explicit non-actions

During the original source audit, no source code, migration, database, or production configuration was changed. Subsequent repository follow-up is recorded in PR #3. Across the audit and follow-up:

- No production database or production server was accessed or modified.
- No Agent repository was modified.
- No Prometheus or Loki endpoint was contacted.
- No telemetry was fabricated.
- CI success is limited to the isolated MySQL test environment; production readiness is not claimed.
- ADR-001 through ADR-009 were not modified.

## 7. Checkpoint

PR #2 documentation audit and PR #3 schema/CI follow-up are merged into `security/telemetry-capability-enforcement`. PR #3 adds the six telemetry tables, activation-grant acknowledgement fields, the application monitoring suspension timestamp, a retention-service helper fix, schema/retention tests, and a fresh MySQL CI workflow. CI passed with 49 tests and 155 assertions. The live Central database, production PHP runtime, and deployed Agent compatibility remain unverified.

The Agent-facing Central intake and MySQL persistence path now has reproducible schema in CI. Prometheus/Loki write and retention integration remain unimplemented. Next work must verify non-production deployment compatibility and obtain explicit decisions for backend write protocols, label mapping, retry handling, and the Agent ACK durability boundary before implementing storage adapters.
