# NADI Central Production Readiness Gates v1.0

**Status:** Not production-approved  
**Date:** 27 November 2026  
**Branch:** `security/telemetry-capability-enforcement`  
**Purpose:** Evidence-based release gate for Central deployment

## 1. Executive status

The repository now has a reproducible MySQL schema path, a passing CI suite, Ed25519 Activation Grant signing compatible with the Agent verifier algorithm, database probe metrics in the Prometheus scrape exporter, and safer telemetry retention.

These results do **not** establish production readiness. No production server, database, Prometheus, Loki, Alertmanager, DNS, TLS endpoint, secret store, or backup system has been accessed or changed.

**Release decision: HOLD.** Production deployment must not begin until all blocking gates in this document have evidence from the target non-production environment and the production environment has passed a read-only preflight.

## 2. Verified repository changes

| Area | Current evidence | Status |
|---|---|---|
| Telemetry schema | Six telemetry tables created by migration; complete Central migration chain applied on fresh MySQL 8.4 CI | CI verified |
| Activation schema | Acknowledgement columns/index and application suspension timestamp added | CI verified |
| Database probe state | Transactional per-application status, last success, latency, sequence and cumulative failure counter table | CI verified |
| Retention safety | Record idempotency keys are retained while matching telemetry/event rows exist in both cleanup implementations | CI verified |
| Prometheus exporter | Bearer-protected exposition; current Agent/application authorization rechecked before stream samples are exposed | PHP feature test verified |
| Database metrics | Five Laravel DB metrics emitted from persisted probe state | PHP feature test verified |
| Activation signing | Central uses Ed25519; deterministic Go compatibility vector matches Agent canonicalization and signature verification algorithm | PHP + Go CI verified |
| Key generation | Non-overwriting Artisan key-generation command; private seed mode 0600, public key mode 0644 | PHP feature test verified |
| Loki delivery | No Loki push adapter or durable Loki delivery worker in Central | Not implemented |
| Event/HTTP streams | Several stream names remain unmapped/rejected; no complete Agent producer path verified | Not ready |
| Retention backends | MySQL cleanup exists; Prometheus TSDB and Loki retention are not yet governed by a verified single policy | Not ready |

Relevant merged changes:
- PR #2: telemetry storage audit.
- PR #3: telemetry and activation schema migrations, CI and retention helper fix.
- PR #4: audit follow-up.
- PR #6: idempotency-key retention safety.
- PR #7: database probe metrics export and exporter authorization gating.
- PR #8: Ed25519 Activation Grant compatibility.
- PR #5 remains a draft decision proposal; it is not an approved ADR.

## 3. Blocking gates

### 3.1 Agent/Central activation interoperability

**Repository state:** Central now signs Activation Grants using Ed25519. CI verifies a deterministic signature/canonical JSON vector using PHP sodium and a Go verifier matching the Agent Ed25519 verification algorithm.

**Still required:**
- Run the actual Agent binary against a non-production Central instance.
- Install a raw 32-byte Ed25519 public key on the Agent.
- Verify `key_id` matches on both sides.
- Issue a grant, verify it in the Agent, receive the Agent acknowledgement, and confirm Central changes the application activation state to active.
- Verify invalid signature, wrong key ID, expired grant, revoked grant and scope mismatch all fail closed.

**Key migration warning:** Existing RSA private/public key files are not compatible and are not converted. Existing RSA-signed grants cannot be reused by the Ed25519 signer. Revoke legacy active grants through the approved Central administrative flow and issue new Ed25519 grants during a coordinated change window.

The current Agent verifier accepts only one active key ID/public key. Zero-downtime key rotation or multi-key overlap is not supported by the inspected Agent source.

### 3.2 Agent/Central wire format

The written Agent Side Monitoring Specification says metrics/log batches use JSONL+gzip. The inspected Agent sender currently posts a JSON batch envelope with `Content-Type: application/json`, and Central currently validates that JSON envelope.

The inspected Agent source and Central source match each other, but the written specification differs. Resolve this discrepancy with a versioned protocol decision and compatibility matrix before production rollout. Do not silently change the wire format on either side.

### 3.3 Prometheus scrape

Central has a protected scrape endpoint at `GET /api/internal/prometheus/metrics`. It currently exports:
- `server_metrics`
- `application_metrics`
- `database_metrics`

The exporter rechecks current Agent/application grant and capability before exposing samples and does not cache authorized exposition.

**Still required:**
- Configure Prometheus to scrape the endpoint over the internal network with a dedicated Bearer token.
- Verify TLS or an approved private-network transport and secret handling.
- Set a scrape interval compatible with the Agent collection/push cadence.
- Verify `up`, `nadi_prometheus_exporter_up`, stream freshness and database metrics in the real Prometheus UI/API.
- Test that suspended, revoked, expired and unauthorized applications stop producing active samples.
- Test Prometheus outage/recovery and confirm Central ingestion remains available.

No Prometheus endpoint was contacted during this repository work.

### 3.4 Loki and logs

No Loki push adapter, delivery worker, retry queue or real Loki integration test exists in the inspected Central source. Log ingestion remains disabled by default through `NADI_TELEMETRY_LOGS_ENABLED=false`.

Do not enable this flag in production until:
- The Loki push contract and authentication/TLS are approved.
- A durable delivery/outbox design and Agent ACK boundary are approved.
- Redaction, label allowlist, structured metadata, retry, quarantine and delivery observability are implemented.
- Non-production tests verify real push, outage, recovery, duplicate delivery and retention.

### 3.5 Agent collector completeness

The inspected Agent release is `v0.7.5-dev` and explicitly identifies itself as an installable development/pilot candidate, not a production release. It is not yet a complete implementation of every stream named in the specifications:
- Nginx-specific streams and HTTP request/error event streams are not collected or emitted.
- HTTP event redaction is not implemented because those event streams are not emitted.
- The Bridge metrics endpoint currently returns HTTP 501.
- Queue and scheduler endpoints currently return HTTP 501; the Agent does not fabricate their data.
- Agent HTTP status-specific stop/quarantine/retry policy is incomplete for 401, 403, 409, 410, 413, 422 and 429.
- Automatic batch splitting for HTTP 413 and pre-send size splitting are not implemented.
- No live Agent-to-Central E2E or Central storage verification has been performed.
- Central intentionally rejects several currently unmapped event/HTTP stream names.

A production rollout must use an explicitly identified Agent release and verify each capability/collector against the Central capability registry. Do not treat a capability name in a grant as proof that its collector is implemented.

### 3.6 Runtime and Composer compatibility

The repository declares PHP `^8.3`, while the locked Symfony 8.1 dependency set requires PHP 8.4.1 or newer. CI uses PHP 8.4.28.

Before deployment:
- Verify the actual Central PHP-FPM and CLI versions.
- Reconcile Composer platform metadata with the supported runtime.
- Confirm required PHP extensions, including sodium, PDO MySQL, OpenSSL, cURL, intl and mbstring.
- Run `composer check-platform-reqs` on the target release artifact.

No production PHP runtime was inspected.

### 3.7 Database migration and schema drift

CI imported `database/schema/nadi-baseline.sql` and successfully ran the complete migration chain on a fresh MySQL 8.4.13 database.

The telemetry migration creates tables only when absent. It does not reconcile an existing table with a different manually-created schema.

Before any non-production or production migration:
- Capture and review `SHOW CREATE TABLE` for all six telemetry tables and the new database probe state table.
- Compare indexes, foreign keys, collations, column types and datetime precision with the repository migration.
- Take and verify a database backup.
- Run migration on a restored copy first.
- Do not use `migrate:fresh`, `db:wipe`, or destructive replacement.

No production schema was inspected.

### 3.8 Retention and recovery

The two MySQL cleanup implementations now preserve record idempotency keys while corresponding telemetry/event records remain. This does not complete cross-backend retention.

Before production:
- Approve one retention taxonomy for metrics, logs, events, outbox/staging, idempotency, gaps, alerts, notification logs and audit history.
- Map metrics retention to Prometheus TSDB.
- Map logs retention to Loki compactor.
- Verify unresolved gaps and audit/approval history are excluded from telemetry deletion.
- Verify Agent local spool retention is shorter than Central idempotency retention.
- Test cleanup with restored production-like data and verify backup/restore.

Prometheus/Loki retention operations have not been implemented or executed.

### 3.9 Alerts and operator visibility

Central has alert rule files, an Alertmanager webhook path and Prometheus query services, but the complete production alert pipeline has not been verified.

Required evidence:
- Prometheus rules loaded and evaluated.
- Alertmanager routes and receivers validated.
- Dedicated webhook token configured.
- Test alert delivered to the intended notification channel.
- Ingestion lag, stale/offline Agent, Loki delivery lag, retry/quarantine and exporter failures are visible.
- Operators have a runbook for acknowledgement, triage and recovery.

No alert receiver or notification destination was contacted.


### 3.10 Control command delivery

The Agent Side Monitoring Specification explicitly states that marking a command as `delivered` before the Agent acknowledgement is not sufficient to guarantee recoverable delivery. Central currently retries unacknowledged commands after a configured interval, but the inspected Agent control client implements Activation Grant acknowledgement only; a command acknowledgement client/path was not found in the inspected Agent release.

Until the Agent command receipt/acknowledgement lifecycle is implemented and verified:
- Do not use Central commands for production remediation or business-service operations.
- Treat command delivery as at-least-once/repeated delivery, not exactly-once.
- Require idempotent command handlers and explicit expiry before any non-production command test.
- Add Agent command acknowledgement, retry, timeout and final-state tests before enabling operational commands in production.

## 4. Required non-production acceptance test

Run one complete non-production test with the actual Central release candidate, Agent release candidate, MySQL, Prometheus and Loki:

1. Provision a fresh test database from the approved baseline and run all migrations.
2. Enroll an Agent and register an application.
3. Approve the application and issue an Ed25519 Activation Grant.
4. Verify the grant with the actual Agent binary and receive a successful acknowledgement.
5. Confirm no telemetry is collected or sent before valid activation.
6. Send server metrics and verify Prometheus samples.
7. Send application metrics and database probe results and verify all five DB metric families.
8. Suspend one application and verify its telemetry stops while another approved application on the same Agent continues.
9. Revoke a grant and verify data-plane authorization fails closed.
10. Exercise network outage, retry, duplicate batch, sequence gap and recovery.
11. After the Loki adapter is approved and implemented, send redacted logs and verify delivery, retry and quarantine.
12. Test Agent spool behavior, Central retention, Prometheus TSDB retention, Loki retention and audit-history preservation.
13. Verify dashboard freshness, alerts and operator runbooks.

Save test evidence, timestamps, release SHAs, configuration hashes (without secret values), and results.

## 5. Production deployment gates

- [ ] Owner approves the remaining delivery/ACK and wire-format decisions.
- [ ] Agent and Laravel Bridge release versions are explicitly pinned and production-ready.
- [ ] Ed25519 key distribution and grant re-issuance plan approved.
- [ ] Actual Agent-to-Central activation E2E passed.
- [ ] Command receipt/acknowledgement/retry/expiry E2E passed before enabling operational commands.
- [ ] Prometheus scrape E2E passed.
- [ ] Loki delivery E2E passed before enabling logs.
- [ ] Database schema drift audit and verified backup completed.
- [ ] PHP runtime and Composer platform requirements verified.
- [ ] TLS, firewall, reverse proxy, request-size limits and rate limits reviewed.
- [ ] Secrets stored outside source control with least privilege.
- [ ] Scheduler, queue worker, process supervision and log rotation verified.
- [ ] Alert delivery and incident runbooks tested.
- [ ] Retention and backup/restore tests passed.
- [ ] Rollout, rollback and forward-fix plan approved.

## 6. Explicit non-actions

- No production server, database, configuration, key or grant was accessed or changed.
- No production Prometheus or Loki endpoint was contacted.
- No production migration or key generation was executed.
- No Agent repository or deployed Agent was modified.
- No log ingestion flag was enabled.
- No production readiness claim is made by this checklist.
