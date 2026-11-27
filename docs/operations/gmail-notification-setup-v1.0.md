# NADI Central Gmail Notification Setup v1.0

## Purpose

NADI Central can send email through a configurable Google Gmail account. Gmail credentials are managed from the Central web interface and are not stored in source control or application environment files.

## Configuration interface

Open:

- Management
- Email Notifications

Configure:

1. Gmail account address.
2. Google App Password.
3. Sender display name.
4. Enable email notifications.

The SMTP endpoint is fixed to Google's submission service:

- Host: smtp.gmail.com
- Port: 587
- Security: STARTTLS

The sender address is the configured Gmail account address.

## Google account preparation

1. Sign in to the Google account intended for NADI notifications.
2. Enable 2-Step Verification.
3. Create an App Password for NADI Central.
4. Copy the generated App Password into the Central Email Notifications page.

Use an App Password instead of the account's normal Google password. Google may display the generated password with spaces; NADI removes whitespace before encrypting and storing it.

## Credential storage and rotation

- The Gmail App Password is encrypted using Laravel's application encryption key before it is stored in MySQL.
- The plaintext password is never loaded back into the settings form.
- Leave the password field blank to retain the currently stored password.
- When changing the Gmail account address, provide the matching new App Password.
- Rotating the App Password can be done by entering the new value in the same interface.
- Keep the Laravel APP_KEY stable. Losing or changing it without a planned key migration makes previously encrypted credentials unreadable.

## Verification

After saving the settings:

1. Ensure Email Notifications is enabled.
2. Enter a recipient address in Send test email.
3. Send the test message.
4. Confirm receipt in the destination mailbox.
5. Check spam/junk folders if the message is not in the inbox.

The Last successful test timestamp is updated only after the SMTP send call completes successfully.

## Current implementation boundary

The Gmail delivery service exposes a reusable synchronous send method for Central-originated notifications. This foundation does not yet define notification templates, event subscriptions, recipient groups, queued delivery, or delivery history.

Notifications triggered by Agent communication, telemetry ingestion, or Agent events remain pending until Central-Agent communication work is resumed.
