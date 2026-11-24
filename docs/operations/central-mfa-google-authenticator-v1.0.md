# NADI Central — Google Authenticator MFA v1.0

## Status and scope

This change implements mandatory account-bound TOTP MFA in `simfinance/nadi-central`.

It does not change the Agent repository, Agent API, telemetry ingestion, or Agent-facing contracts.

## Authentication policy

- Username and password are validated first.
- Password validation does not create an authenticated application session.
- A short-lived pending-login session is created for at most five minutes.
- Enrolled accounts must pass a TOTP challenge before Laravel authentication is established.
- Accounts without MFA are redirected to mandatory enrollment and cannot access authenticated application routes.
- Enrollment is activated only after the first valid TOTP code.
- The provisioning label uses the NADI account's registered email. The email is an account label; the authenticator app does not independently validate the email address.
- Each user has a unique secret.

## TOTP profile

- RFC 6238 time-based one-time password.
- HMAC-SHA1.
- Six digits.
- Thirty-second time step.
- Verification tolerance: one time step before or after server time.
- A successfully accepted time step is stored and cannot be accepted again.
- The secret is encrypted at rest using Laravel `Crypt`.

The implementation uses PHP's HMAC and Base32 primitives and does not add a Composer runtime dependency.

## QR provisioning

The provisioning URI is created by NADI Central and passed to the browser. The QR image is generated in the browser using QRCode.js. No third-party QR image-generation endpoint receives the provisioning URI or TOTP secret. The setup screen also provides the secret for manual entry if QR rendering is unavailable.

QRCode.js is currently loaded from cdnjs; the QR payload itself is generated in the browser and is not sent to that CDN. For deployments requiring zero third-party script dependency, vendor the same library into the application's local assets before production rollout.

## Recovery codes

- Ten random recovery codes are generated after successful enrollment.
- Only password hashes are stored.
- Each recovery code is single-use.
- Codes are displayed once after enrollment and must be stored securely.
- A recovery-code login is audited.

## Abuse and replay protection

- Login password attempts retain the existing five-attempt username/IP limiter.
- MFA challenge attempts are limited to five per user/IP within five minutes.
- Accepted TOTP time steps are protected by a row lock and persisted replay counter.
- Pending MFA login state expires after five minutes.
- MFA success, failure, enrollment, and recovery-code use are written to `user_mfa_audits`.

## Deployment

After synchronizing the Central branch:

```bash
php artisan migrate --force
php artisan optimize:clear
```

All users are required to enroll at their next authenticated access. Existing authenticated sessions without MFA are redirected to enrollment.

No test suite or runtime verification was run as part of this implementation.

## Administrative recovery boundary

Recovery codes provide the user-level fallback when the authenticator device is unavailable.

An administrative MFA reset endpoint is intentionally not included yet. The inspected User model and searched Central code did not establish an existing role/permission authorization contract. Adding a reset endpoint without a proven privileged authorization mechanism would create an MFA bypass. A future admin reset must be tied to the Central's established administrator authorization, require an audited operator action, revoke existing recovery codes, and force re-enrollment.

## Operational notes

- The Laravel application encryption key must remain stable and securely backed up. Losing `APP_KEY` makes encrypted TOTP secrets unreadable.
- Protect the database and session store as authentication-sensitive data.
- The application server clock must be synchronized using NTP.
- The QR provisioning URI and manual secret must never be logged or sent to external QR APIs.
- If a user loses both the authenticator and all recovery codes, use a separately approved operator recovery procedure; do not manually edit the database as a normal workflow.
