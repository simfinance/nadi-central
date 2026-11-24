# NADI Central Notification Queue v1.0

## Scope

This feature provides Central-originated email composition, asynchronous delivery through the configured Gmail account, and a delivery history page.

It does not subscribe to Agent events, telemetry events, or Agent communication. Those integrations remain a separate dependency and are intentionally not changed while Agent work is in progress.

## User interface

Open Management → Notifications.

The page provides:

- Recipient email address.
- Optional selection of an active email template.
- Subject and plain-text message body, which can be edited after selecting a template.
- Queue submission.
- Paginated delivery history.
- Delivery status, attempt count, queued timestamp, sent timestamp, and last error.

### Email template management

Open Management → Email Templates.

Authenticated Central operators can create, edit, activate/deactivate, and delete templates. Each template stores a unique name, subject, plain-text body, and active flag.

Active templates are loaded into the Notifications composer. Selecting one copies its subject and body into the compose fields; the operator can edit either field before queueing. Changes to a template do not modify notifications that were already queued.

### Supported placeholders

Placeholders use double curly braces and can be placed in the subject or plain-text body. They are resolved by Central when the notification is submitted, before the encrypted outbox record is created.

Automatic placeholders:

| Placeholder | Value |
|---|---|
| `{{recipient_email}}` | Validated recipient email address |
| `{{current_date}}` | Central application date in `d-m-Y` format |
| `{{current_datetime}}` | Central application date and time in `d-m-Y H:i:s` format |

Operator-supplied placeholders:

| Placeholder | Input on Notifications |
|---|---|
| `{{application_name}}` | Application name; defaults to `APP_NAME` |
| `{{server_name}}` | Server name |
| `{{severity}}` | Severity label, for example Critical, Warning, or Info |
| `{{event_name}}` | Event or notification title |
| `{{event_time}}` | Event time as entered by the operator |
| `{{message}}` | Additional message or event details |

The operator fills the values used by the selected template in the Notifications composer. Unused fields can remain blank. Unknown placeholder names are rejected before the notification is queued so misspelled tokens are not silently sent to recipients. Rendered subject and body lengths are checked again after substitution.

Placeholder values are inserted as plain text. No HTML rendering, expression evaluation, database lookup, or Agent event integration is performed. These placeholders are manual inputs for Central-originated notifications; automatic values from Agent telemetry or event payloads remain out of scope until Agent work is complete.

The template management routes use the existing authenticated Central web route group.

## Delivery lifecycle

A notification is stored in the Central outbox before the queue job is dispatched.

Statuses:

- queued: accepted and waiting for a queue worker.
- sending: a worker is currently attempting delivery.
- sent: the SMTP send operation returned successfully.
- failed: Laravel exhausted the configured retry attempts.

The job uses the notifications queue and retries up to three attempts with backoff intervals of 60, 300, and 900 seconds.

The job payload contains only the delivery UUID. The message body is encrypted with Laravel Crypt in the outbox table and decrypted only when a worker sends it.

The recipient and subject are stored as searchable delivery metadata. Do not place secrets or sensitive credentials in message subjects.

## Queue worker deployment

The current Central queue configuration defaults to the database driver. The existing jobs and failed_jobs tables are provided by the standard queue migration.

Ensure the runtime environment uses:

    QUEUE_CONNECTION=database

Run migrations:

    php artisan migrate --force

Start a worker that includes the notifications queue:

    php artisan queue:work database --queue=notifications,default --tries=3 --timeout=60

For production, run the worker under the server's process supervisor (for example, systemd or Supervisor) with automatic restart enabled. Keep the worker running continuously; otherwise notifications remain queued.

After deploying new application code, restart long-running workers:

    php artisan queue:restart

## Delivery semantics and operational notes

- Queue delivery is at-least-once. A process interruption after SMTP acceptance but before the outbox status update can cause a retry and duplicate email.
- A status of sent means the SMTP provider accepted the message; it does not prove inbox placement or that the recipient read it.
- The test email on Management → Email Notifications remains synchronous and independent of this queue.
- Message bodies are encrypted using APP_KEY. Keep APP_KEY stable and protected; changing it without a key migration makes existing encrypted bodies unreadable.
- The current compose page is intended for Central-originated operational messages. It is not yet a template engine, recipient-group manager, or event rule engine.
- Queue worker health and queue backlog monitoring are not yet surfaced in the Central UI.

## Explicit exclusions

No Agent repository, Agent API, Agent configuration, telemetry ingestion contract, or Agent event subscription has been changed.
