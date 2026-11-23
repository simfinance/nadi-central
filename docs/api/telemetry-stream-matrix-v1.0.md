# NADI Central Telemetry Stream Matrix v1.0

Status: Central registry consolidated. Agent runtime compatibility is documented from source inspection; no Agent repository changes are included.

## Central source of truth

The Central stream matrix is defined in:

- `app/Services/Telemetry/TelemetryStreamRegistry.php`

The registry is the shared source for:

- Request validation in `AgentTelemetryController`.
- Recognized-stream validation in `TelemetryIngestionService`.
- Stream scope validation in `TelemetryIngestionService`.
- Capability authorization and log classification in `TelemetryAuthorizationService`.

A recognized stream is not necessarily authorized. A stream with no capability mapping is rejected by authorization with HTTP 422 and is not persisted.

## Mapped streams

These 12 streams have the same required capability mapping as the Agent sender's `streamCapabilities` map on branch `feature/nginx-error-log-collection-foundation`.

| Stream | Required capability | Scope | Log |
|---|---|---|---|
| `server_metrics` | `server_metrics` | Server | No |
| `nginx_metrics` | `server_metrics` | Server | No |
| `nginx_vhost_metrics` | `server_metrics` | Server | No |
| `nginx_error_logs` | `system_logs` | Server | Yes |
| `application_metrics` | `application_metrics` | Application | No |
| `application_logs` | `application_logs` | Application | Yes |
| `database_metrics` | `database_monitoring` | Application | No |
| `database_connections` | `database_monitoring` | Application | No |
| `queue_metrics` | `queue_monitoring` | Application | No |
| `queue_jobs` | `queue_monitoring` | Application | No |
| `scheduler_metrics` | `scheduler_monitoring` | Application | No |
| `scheduler_events` | `scheduler_monitoring` | Application | No |

Server streams must omit `application_id`. Application streams must carry the exact `application_id` owned by the authenticated Agent and must pass the application's acknowledged-grant authorization.

Log streams remain disabled by default through `NADI_TELEMETRY_LOGS_ENABLED=false`.

## Recognized but deliberately unmapped streams

These five names remain recognized by the Central request validator for explicit compatibility errors. They have no capability mapping and are rejected by the authorization layer with HTTP 422. They are not stored.

| Stream | Scope | Reason |
|---|---|---|
| `nginx_access_logs` | Server | No capability or approved payload contract |
| `application_events` | Application | No event capability is defined |
| `nginx_http_requests` | Server | No exact capability mapping is defined |
| `application_http_requests` | Application | No exact capability mapping is defined |
| `application_http_errors` | Application | No exact capability mapping is defined |

Do not add capability aliases or infer a capability from a stream prefix. A new stream requires an explicit capability, scope, payload schema, persistence path, and compatibility update.

## Agent-side source observations

Read-only inspection of the Agent branch `feature/nginx-error-log-collection-foundation` found:

- `internal/telemetry/sender.go` declares the same 12 capability mappings listed above.
- Its server-scope map contains `server_metrics`, `nginx_metrics`, `nginx_vhost_metrics`, and `nginx_error_logs`. It does not map `nginx_access_logs`.
- `internal/app/telemetry.go` currently emits `server_metrics`, `nginx_error_logs`, `application_metrics`, `database_metrics`, and `application_logs`.
- The runtime source inspected does not currently emit `nginx_metrics`, `nginx_vhost_metrics`, `database_connections`, `queue_metrics`, `queue_jobs`, `scheduler_metrics`, or `scheduler_events`.

These observations describe the inspected Agent branch only. They are not proof of deployment behavior or successful Agent-to-Central delivery.

## Change policy

This work centralizes the Central-side contract only. The Agent repository remains unchanged. Agent-side collector and runtime gaps must be handled in the Agent workstream, while any change to stream names, capability mappings, scope, or payload contracts must be coordinated through a versioned Central-Agent contract update.
