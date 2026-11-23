# NADI Agent Command Delivery Contract v1.0

Status: Central implementation. Agent acknowledgement integration and end-to-end verification remain pending.

## Delivery semantics

Command delivery is **at-least-once**, not exactly-once.

Central returns commands through:

- `POST /api/v1/nadi/agent/control/poll`

A command is eligible for delivery when:

- Its `command_status` is `pending`; or
- Its `command_status` is `delivered`, it has no `acknowledged_at`, and its previous `delivered_at` is at least the configured retry interval old.

The default retry interval is 60 seconds and can be configured with:

- `NADI_AGENT_COMMAND_DELIVERY_RETRY_SECONDS`

Values below 5 seconds are clamped to 5 seconds.

Each poll delivery updates `delivered_at` and increments `delivery_attempts`. A command with an `expires_at` in the past is not returned.

## Receipt acknowledgement

After durably receiving and validating a command, Agent must call:

- `POST /api/v1/nadi/agent/commands/{commandId}/ack`

The endpoint uses the existing `nadi.agent` authentication middleware. Central only acknowledges a command belonging to the authenticated Agent.

A successful receipt acknowledgement:

- Sets `acknowledged_at`.
- Leaves `command_status` as `delivered`; this field records that Central dispatched the command, while `acknowledged_at` records receipt.
- Prevents subsequent Control Poll responses from redelivering that command.

The acknowledgement endpoint is idempotent. Repeating an acknowledgement for an already acknowledged command returns success without changing the original acknowledgement timestamp.

HTTP behavior:

| HTTP | Meaning |
|---|---|
| 200 | Receipt acknowledged, including an idempotent repeated acknowledgement |
| 401 | Agent authentication is missing or invalid |
| 404 | Command does not exist or does not belong to the authenticated Agent |
| 409 | Command has not been delivered by Central |
| 410 | Command has expired |

## Agent requirements

Agent-side implementation must:

1. Treat `command_id` as the stable idempotency key.
2. Persist or otherwise durably accept the command before acknowledging receipt.
3. Acknowledge receipt only after validation and durable acceptance.
4. Tolerate receiving the same command more than once before acknowledgement.
5. Avoid repeating non-idempotent side effects when a command is redelivered.
6. Keep receipt acknowledgement separate from command execution completion/result reporting.

Central does not claim exactly-once delivery. A network failure after dispatch but before receipt acknowledgement can cause redelivery. Command handlers therefore need idempotent execution semantics.

## Scope and verification status

This change adds the Central retry and receipt-acknowledgement contract only. It does not modify either Agent repository and does not claim that the current Agent runtime calls the new acknowledgement endpoint.

End-to-end verification must cover:

- First poll delivers a pending command.
- Poll before the retry interval does not redeliver an unacknowledged command.
- Poll after the retry interval redelivers the same command ID.
- Acknowledgement stops further delivery.
- Repeated acknowledgement is idempotent.
- One Agent cannot acknowledge another Agent's command.
- Expired commands are not delivered and cannot be acknowledged.
- Acknowledgement of a command that has not been delivered returns 409.
