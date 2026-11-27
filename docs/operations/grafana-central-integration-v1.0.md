# NADI Central Grafana Integration v1.0

Status: Central application integration and deployment reference added. Reverse proxy and Grafana server configuration must be applied and verified on the target host.

## Application routes

- `GET /monitoring/grafana` — Grafana dashboard page, protected by the existing authenticated session and MFA middleware.
- `GET /internal/grafana/auth` — reverse-proxy authentication subrequest endpoint.

The authentication endpoint returns HTTP 401 for an unauthenticated session, HTTP 403 when the account has not enabled MFA, and a response header named `X-NADI-Grafana-User` for an authenticated MFA-enabled session.

The Central login flow establishes the authenticated session only after successful MFA challenge completion. The authentication endpoint therefore uses the authenticated Laravel session and additionally checks that the user's TOTP enrollment is active.

The header value is a namespaced opaque Central user identifier (`nadi-{user UUID}`). It does not use a user-controlled display name or email address. The reverse proxy must overwrite any incoming `X-WEBAUTH-USER` header with this value; never pass a browser-supplied identity header to Grafana.

## Environment

Set these values in the Central environment:

- `NADI_GRAFANA_INTERNAL_URL=http://127.0.0.1:3000`
- `NADI_GRAFANA_PUBLIC_PATH=/grafana/`
- `NADI_GRAFANA_DASHBOARD_UID=nadi-overview`
- `NADI_GRAFANA_ORG_ID=1` (change only to the actual Grafana organization containing the dashboard)

The internal URL is used only by the Laravel server for the Grafana health check. The browser iframe uses the same-origin public path.

## Grafana configuration

The current host runs Grafana on `127.0.0.1:3000`, with Nginx on the same host. Configure the following values in the matching sections of `/etc/grafana/grafana.ini`; preserve unrelated existing settings.

```ini
[server]
http_addr = 127.0.0.1
http_port = 3000
domain = nadi.pesantrenalirsyad.org
root_url = https://nadi.pesantrenalirsyad.org/grafana/
serve_from_sub_path = true

[security]
allow_embedding = true

[users]
auto_assign_org = true
auto_assign_org_id = 1
auto_assign_org_role = Viewer

[auth.anonymous]
enabled = false

[auth.proxy]
enabled = true
header_name = X-WEBAUTH-USER
header_property = username
auto_sign_up = true
whitelist = 127.0.0.1
```

The reverse proxy must be the only trusted caller of Grafana. Keep port 3000 bound to loopback. The Auth Proxy whitelist is `127.0.0.1` because the Nginx and Grafana processes run on the same host and Nginx connects to Grafana through loopback. Anonymous authentication remains explicitly disabled.

The application creates a unique namespaced identity from the Central user UUID. New Grafana users are assigned Viewer by default. Do not reuse a privileged Grafana username for this integration. If a dedicated Grafana organization is used, provision it and set `NADI_GRAFANA_ORG_ID` to its actual numeric ID before enabling the dashboard.

## Nginx integration for the current PHP-FPM deployment

The production virtual host serves Laravel directly through PHP-FPM at `/var/run/php/php8.5-fpm.sock`; it does not use an HTTP upstream for Laravel. Therefore, the authentication subrequest must invoke Laravel's front controller through FastCGI. Do not use a `proxy_pass` to a placeholder Laravel upstream.

Add these locations inside the existing HTTPS `server` block. Keep the existing Laravel `location /`, static asset location, PHP handler, and security rules unchanged. Place the two locations before the existing `location /` for readability.

```nginx
location = /_nadi_grafana_auth {
    internal;

    include fastcgi_params;

    fastcgi_param SCRIPT_FILENAME $realpath_root/index.php;
    fastcgi_param SCRIPT_NAME /index.php;
    fastcgi_param REQUEST_URI /internal/grafana/auth;
    fastcgi_param QUERY_STRING "";
    fastcgi_param HTTP_COOKIE $http_cookie;
    fastcgi_param HTTP_HOST $host;
    fastcgi_param HTTP_ACCEPT application/json;
    fastcgi_param HTTPS on;

    fastcgi_pass unix:/var/run/php/php8.5-fpm.sock;
    fastcgi_read_timeout 30;
}

location ^~ /grafana/ {
    auth_request /_nadi_grafana_auth;
    auth_request_set $nadi_grafana_user $upstream_http_x_nadi_grafana_user;

    proxy_pass http://127.0.0.1:3000/;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-WEBAUTH-USER $nadi_grafana_user;
    proxy_set_header Authorization "";
}
```

Important implementation details:

- The auth location is `internal`; clients cannot call it directly.
- The FastCGI subrequest forwards the browser's Laravel session cookie and forces Laravel to resolve the exact `/internal/grafana/auth` route.
- Nginx accepts only a successful auth-subrequest response. Laravel returns 401 or 403 when the session is not authorized.
- `X-WEBAUTH-USER` is set from the response header generated by Laravel, replacing any browser-supplied value.
- The `^~` modifier prevents the existing static-file regular-expression location from intercepting Grafana JavaScript, CSS, and image assets.
- The trailing slash on `proxy_pass` removes the external `/grafana/` prefix before forwarding to Grafana, which is paired with Grafana's `serve_from_sub_path = true` setting.

Do not create a public Nginx location for `/_nadi_grafana_auth`. Do not expose port 3000 publicly.

## Dashboard provisioning

Provisioning examples are included in this repository:

- `docs/operations/grafana/nadi-prometheus-datasource.yml`
- `docs/operations/grafana/nadi-dashboard-provider.yml`
- `docs/operations/grafana/nadi-overview-dashboard.json`

Copy the datasource provisioning file into `/etc/grafana/provisioning/datasources/`. Copy the dashboard provider file into `/etc/grafana/provisioning/dashboards/`, then place the JSON file at `/etc/grafana/dashboards/nadi/nadi-overview-dashboard.json` (create the directory first).

The datasource uses the local Prometheus API at `http://127.0.0.1:9090`, UID `nadi-prometheus`, and proxy access. The dashboard UID is `nadi-overview`; set `NADI_GRAFANA_DASHBOARD_UID=nadi-overview` after provisioning. The dashboard panels use the metric names and labels emitted by the current Central exporter.

The Central application does not create or overwrite Grafana dashboards or datasources at runtime.

## Security boundaries

- Browser traffic uses the same HTTPS origin as NADI Central.
- Laravel session authentication is required; the Central login flow completes MFA before creating the authenticated session.
- Grafana Auth Proxy trusts only the local Nginx reverse proxy.
- The proxy overwrites `X-WEBAUTH-USER`; client-supplied identity headers are not trusted.
- Grafana anonymous access remains disabled.
- New proxy identities receive Viewer role; no Central RBAC mapping is assumed.
- Prometheus and Grafana APIs are not exposed directly to the browser.
- Agent repositories, telemetry streams, and Agent contracts are unchanged.

## Deployment sequence

1. Deploy the Central branch containing the Grafana controller, routes, view, and configuration.
2. Set the four `NADI_GRAFANA_*` environment values, including `NADI_GRAFANA_DASHBOARD_UID=nadi-overview`.
3. Apply the Grafana settings above while preserving unrelated configuration.
4. Install the datasource YAML, dashboard provider YAML, and dashboard JSON at the paths above.
5. Add the two Nginx locations to the existing HTTPS virtual host.
6. Run `sudo nginx -t`. Reload Nginx only if the configuration test succeeds.
7. Restart Grafana so it loads its configuration and provisioning files.
8. Run `php artisan optimize:clear` and `php artisan config:cache` using the normal Central deployment procedure.
9. Validate that unauthenticated requests to `/grafana/` are rejected, authenticated Central users can load Grafana only after MFA, and the provisioned Grafana user has Viewer role.
10. Confirm direct access to port 3000 remains unavailable from public networks.

No production server configuration was changed by this repository update. No tests or deployment commands were executed.
