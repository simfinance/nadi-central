# NADI Central Grafana Integration v1.0

Status: Central application integration and deployment reference added. Reverse proxy and Grafana server configuration must be applied and verified on the target host.

## Application routes

- `GET /monitoring/grafana` — Grafana dashboard page, protected by the existing authenticated session and MFA middleware.
- `GET /internal/grafana/auth` — reverse-proxy authentication subrequest endpoint.

The authentication endpoint returns HTTP 401 for an unauthenticated session, HTTP 403 when the account has not enabled MFA, and a response header named `X-NADI-Grafana-User` for an authenticated MFA-enabled session.

The header value is a namespaced opaque Central user identifier (`nadi-{user UUID}`). It does not use a user-controlled display name or email address. The reverse proxy must overwrite any incoming `X-WEBAUTH-USER` header with this value; never pass a browser-supplied identity header to Grafana.

## Environment

Set these values in the Central environment:

- `NADI_GRAFANA_INTERNAL_URL=http://127.0.0.1:3000`
- `NADI_GRAFANA_PUBLIC_PATH=/grafana/`
- `NADI_GRAFANA_DASHBOARD_UID=<UID of the provisioned NADI overview dashboard>`
- `NADI_GRAFANA_ORG_ID=1` (change only to the actual Grafana organization containing the dashboard)

The internal URL is used only by the Laravel server for the Grafana health check. The browser iframe uses the same-origin public path.

## Grafana configuration

Configure Grafana behind the existing HTTPS reverse proxy. Example settings (adapt paths and proxy addresses to the actual host):

```ini
[server]
domain = nadi.pesantrenalirsyad.org
root_url = https://nadi.pesantrenalirsyad.org/grafana/
serve_from_sub_path = true

[security]
allow_embedding = true

[auth.proxy]
enabled = true
header_name = X-WEBAUTH-USER
header_property = username
auto_sign_up = true
auto_assign_org_role = Viewer
whitelist = 127.0.0.1
```

The reverse proxy must be the only trusted caller of Grafana. Keep port 3000 bound to loopback or a private interface and restrict network access. Anonymous authentication must remain disabled. Auth Proxy must only trust the actual reverse-proxy source address.

The application creates a unique namespaced identity from the Central user UUID. New Grafana users are assigned Viewer by default. Do not reuse a privileged Grafana username for this integration. If a dedicated Grafana organization is used, provision it and set `NADI_GRAFANA_ORG_ID` to its actual numeric ID before enabling the dashboard.

## Nginx reverse-proxy integration reference

Add equivalent locations to the existing TLS virtual host. Replace `nadi-central-upstream` with the actual upstream used by the existing Laravel application. This is a reference fragment, not a complete virtual host:

```nginx
location = /_nadi_grafana_auth {
    internal;

    proxy_pass http://nadi-central-upstream/internal/grafana/auth;
    proxy_pass_request_body off;
    proxy_set_header Content-Length "";
    proxy_set_header Cookie $http_cookie;
    proxy_set_header Accept application/json;
    proxy_set_header X-Original-URI $request_uri;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
}

location /grafana/ {
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

Ensure the existing Laravel upstream accepts the session cookie and the auth subrequest is routed through the same application/session configuration. The `internal` directive prevents external callers from invoking the Nginx auth subrequest location directly. Do not expose a second public route that bypasses `auth_request`.

For deployments where Laravel is served directly by PHP-FPM rather than an HTTP upstream, adapt the auth subrequest to the existing FastCGI configuration; do not copy the proxy_pass example unchanged.

## Dashboard

Create or provision a Grafana dashboard with UID configured in `NADI_GRAFANA_DASHBOARD_UID`. The application embeds the dashboard at `/grafana/d/{uid}/nadi-overview?orgId={org_id}&kiosk`.

Provision the dashboard and its Prometheus datasource in Grafana. The Central application does not create or overwrite Grafana dashboards or datasources.

## Security boundaries

- Browser traffic uses the same HTTPS origin as NADI Central.
- Laravel session authentication and completed MFA are checked on each Grafana proxy request.
- Grafana Auth Proxy trusts only the reverse proxy.
- The proxy overwrites `X-WEBAUTH-USER`; client-supplied identity headers are not trusted.
- Grafana anonymous access remains disabled.
- New proxy identities receive Viewer role; no Central RBAC mapping is assumed.
- Prometheus and Grafana APIs are not exposed directly to the browser.
- Agent repositories, telemetry streams, and Agent contracts are unchanged.

## Deployment and verification

1. Configure Grafana Auth Proxy and bind Grafana to a private interface.
2. Add the Nginx locations to the existing TLS virtual host and replace the Laravel upstream placeholder.
3. Set the four `NADI_GRAFANA_*` environment values.
4. Provision the Prometheus datasource and the dashboard UID.
5. Run `php artisan optimize:clear` and `php artisan config:cache` using the normal deployment procedure.
6. Validate that unauthenticated requests to `/grafana/` are rejected, authenticated Central users complete MFA before Grafana loads, and Grafana users are Viewer.
7. Confirm direct access to port 3000 is unavailable from public networks.

No production server configuration was changed by this repository commit. No tests or deployment commands were executed.
