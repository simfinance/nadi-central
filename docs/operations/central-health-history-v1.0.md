# Central Health Snapshot History v1.0

## Purpose

Persist Central Health measurements independently of browser visits. The existing
`/central-health` page continues to produce its live snapshot using the existing controller
and response structure. The scheduled collector stores a separate full snapshot for
historical use.

## Collection

- Artisan command: `php artisan nadi:central-health-collect`
- Schedule: every minute.
- Scheduler overlap protection: enabled.
- Collection runs on the Central application host and does not depend on the page
  being open.
- Each successful run inserts one row into `nadi_central_health_snapshots`.

The stored JSON payload contains the complete snapshot returned by the existing
Central Health collector, including host, CPU, load, memory, pressure, storage,
network, processes, services, database, PHP, Laravel, system, findings, and overall
health information.

Indexed summary columns are also stored for efficient history filtering:
- `sampled_at`
- `overall_status`
- `overall_score`
- Critical, warning, and info finding counts.

## Retention

The `central_health_snapshots` data type is available in the existing
Management > Telemetry Retention policy interface.

No automatic deletion occurs when the policy is absent or disabled. When enabled
with a valid positive retention period, the existing daily cleanup command removes
expired snapshots in chunks using `sampled_at`.

## Deployment

After syncing the Central branch:

```bash
php artisan migrate --force
php artisan nadi:central-health-collect
php artisan schedule:list
```

Laravel's scheduler must be invoked by the host cron every minute. The standard
cron entry is:

```cron
* * * * * cd /var/www/nadi-central && php artisan schedule:run >> /dev/null 2>&1
```

The manual command creates an initial historical row. Subsequent rows are created
by the scheduler even when no user opens the Central Health page.

## Scope

- No Blade, CSS, JavaScript, or route interface changes are included.
- The live page and its JSON response remain unchanged.
- This change only adds background collection, database persistence, and retention
  support for the stored snapshots.
