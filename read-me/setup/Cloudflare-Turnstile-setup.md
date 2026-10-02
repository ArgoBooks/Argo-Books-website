# Cloudflare Turnstile Setup

The free receipt scanner (`/free-receipt-scanner/`) is public with no signup, so it uses Cloudflare Turnstile to verify a real person before each scan. This keeps bots from burning through the paid Gemini scans.

Turnstile is a standalone free product.

## What it protects

`api/receipt/scan.php` requires one of two things before it will run a scan:

1. A valid Turnstile token (the visitor solved the widget), or
2. A short-lived signed "scan pass" the server hands back after the first solve, so the rest of a bulk batch runs without re-challenging.

On local dev (`127.0.0.1` / `::1`) the check is skipped entirely, so testing isn't blocked even with no keys set.

## Setup Instructions

### 1. Create a Cloudflare account

1. Go to https://dash.cloudflare.com/sign-up
2. Sign up.

### 2. Create a Turnstile widget

1. In the left sidebar, under `Application Security` click **Turnstile**.
2. Click **Add widget manually**.
3. **Widget name:** anything, e.g. `argo-receipt-scanner`.
4. **Hostnames:** add the domains the widget runs on:
   - `argorobots.com`
   - `www.argorobots.com` (if the site serves there too)
   - `dev.argorobots.com` (if you want it on the dev subdomain)

   You do not need `localhost`. Cloudflare allows localhost automatically, and the server skips the check there anyway.
5. **Widget Mode:** choose **Managed** (recommended). See [Widget modes](#widget-modes) below.
6. Click **Create**.

### 3. Copy the two keys

After creating, Cloudflare shows:

- **Site Key** (public, starts with `0x4AAAA...`)
- **Secret Key** (private, starts with `0x4AAAA...`)

### 4. Add the keys to `.env`

The keys go in the `.env` file:

```
TURNSTILE_SITE_KEY="0x4AAAA...your-site-key..."
TURNSTILE_SECRET_KEY="0x4AAAA...your-secret-key..."
```

### 5. Test

Open https://argorobots.com/free-receipt-scanner/ in a fresh browser tab. The Cloudflare widget should render, and scanning should work.

## Widget modes

Set per-widget in the dashboard. Changing the mode needs no new keys and no code change.

- **Managed** (recommended): shows the widget; most real users get a silent pass or a quick checkbox. Best bot protection.
- **Non-Interactive**: shows a small "verifying" box but never asks the user to click.
- **Invisible**: renders nothing visible, runs in the background. Lets more automated abuse through, since there's no interaction to fall back on when Cloudflare is unsure.

## What happens when verification fails

- **No valid token** (challenge fails, expires, or the visitor is flagged): the server returns 401 and the page shows "Could not verify you are human. Please reload and try again." The visitor is blocked until they reload.
- **Network error**: the page shows "Something went wrong. Please try again."

A real visitor only solves the widget once. The first successful scan returns a 10-minute scan pass that the client reuses, so bulk scans and repeat scans within that window don't re-challenge.

## Optional settings

- `WEB_RECEIPT_SCAN_DAILY_LIMIT` in `.env`: scans allowed per visitor per day (defaults to 10).

## Troubleshooting

The keys are missing or wrong on the server. Confirm `TURNSTILE_SITE_KEY` and `TURNSTILE_SECRET_KEY` are both set in the live `.env`, and that the secret key matches the site key from the **same** widget.
