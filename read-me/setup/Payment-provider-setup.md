# Payment Provider Setup

Full setup for Stripe, PayPal, and Square, both the **payment portal** and the **SaaS subscription billing**.

Each provider has two completely separate apps: **sandbox** and **live**. They have separate client IDs, secrets, webhook IDs, and redirect-URI allowlists. Configuring one does not carry over to the other. Repeat every step below twice, once per environment.

The website is environment-switched by `APP_ENV` in `.env`:

```
APP_ENV=sandbox       # uses sandbox keys and URLs
APP_ENV=production    # uses live keys and URLs
```

And `SITE_URL` controls what host is sent as `redirect_uri` to each provider, which is matched against the allowlists below:

```
# sandbox
SITE_URL=https://dev.argorobots.com

# production
SITE_URL=https://argorobots.com
```

## What each provider handles

For all three providers, the website uses the same three integration points. Provider-specific quirks are called out in the respective sections below.

1. **Portal Connect**: Argo Books desktop users authorize the website to receive invoice payments through their own provider account (OAuth / Connect onboarding).
2. **Portal payment webhooks**: backup confirmation when a portal invoice captures or refunds. The primary path is synchronous via `process-payment.php`; the webhook covers the case where the customer closes their browser before the success page loads.
3. **SaaS subscription billing**: argorobots.com customers paying for Argo Books Premium subscriptions.

---

## Stripe

The portal Connect flow uses Stripe Connect Express. Stripe Account Links generate one-time onboarding URLs, so there's **no redirect-URI allowlist** to maintain (unlike PayPal / Square). Subscription billing goes through Stripe Checkout / Subscriptions.

### A. Create / enable the platform account

1. Sign in at https://dashboard.stripe.com.
2. Click the account name in the top-left of the dashboard. For sandbox setup, click **Switch to sandbox** and pick a sandbox (or create one via **Manage sandboxes**). For live setup, click **Exit sandbox**. The dashboard reloads in the chosen environment. Every step below must be repeated once per environment.
3. Open **Developers > API keys** (easiest way: type "API keys" into the top search bar). Copy the publishable and secret keys into `.env`:
   ```
   STRIPE_SANDBOX_SECRET_KEY=sk_test_...
   STRIPE_SANDBOX_PUBLISHABLE_KEY=pk_test_...
   STRIPE_LIVE_SECRET_KEY=sk_live_...
   STRIPE_LIVE_PUBLISHABLE_KEY=pk_live_...
   ```

### B. Enable Connect (portal Connect)

1. Search "Connect onboarding options" in the top search bar, or go directly to **Settings -> Connect -> Onboarding options**. If that page loads, Connect is already enabled on the account.
2. **Countries**: click **Select all** in the top-right.
3. No redirect URLs to whitelist. The server creates one-time onboarding URLs via Stripe Account Links.
4. Search "Express Dashboard" in the top search bar, or go directly to **Settings -> Connect -> Express Dashboard**. Enter "Argo Books" for the name, and select the Argo Books logo.

### C. Portal payment webhook

In the Stripe Dashboard (Test mode for sandbox, Live mode for live):

1. Search "Webhooks" in the top search bar and click "Add destination".
2. **Endpoint URL:**
   - Sandbox: `https://dev.argorobots.com/api/portal/webhooks/stripe`
   - Live:    `https://argorobots.com/api/portal/webhooks/stripe`
3. **Events to send:**
   - `payment_intent.succeeded`
   - `charge.refunded`
4. After creating, click into the endpoint and reveal the **Destination ID** (`whsec_...`). Copy into `.env`:
   ```
   STRIPE_SANDBOX_PORTAL_WEBHOOK_SECRET=whsec_...
   STRIPE_LIVE_PORTAL_WEBHOOK_SECRET=whsec_...
   ```

### D. SaaS subscription billing

Nothing to set up; `cron/subscription_renewal.php` handles renewals against the stored PaymentMethod, using prices from `config/pricing.php`.

---

## PayPal

> **Status:** Only the SaaS subscription billing side of PayPal is active. The portal Connect flow (merchants accepting invoice payments through their own PayPal) is **currently disabled**, because PayPal's OAuth identity endpoint doesn't support Business-account onboarding, so the flow can't actually connect real merchants. Re-enabling requires migrating to PayPal Partner Referrals API after approval into PayPal Platforms & Marketplaces (see Admin guide for the application process). Until then, the portal runs on Stripe and Square only.

Subscription billing goes through PayPal Subscriptions; Plan records live in PayPal and the site stores their IDs.

### A. Create the apps

Still required for the SaaS subscription side. Do this once per environment.

1. Sandbox: https://developer.paypal.com/dashboard/applications/sandbox
2. Live:    https://developer.paypal.com/dashboard/applications/live
3. **Create App** -> name it (Any name is fine. You can choose "Argo Books"), choose **Merchant**, pick a sandbox business account on the sandbox side.
4. Note down the **Client ID** and **Secret** and put them in `.env`:
   ```
   PAYPAL_SANDBOX_CLIENT_ID=...
   PAYPAL_SANDBOX_CLIENT_SECRET=...
   PAYPAL_LIVE_CLIENT_ID=...
   PAYPAL_LIVE_CLIENT_SECRET=...
   ```

### B. Enable "Log in with PayPal" (payment portal): NOT CURRENTLY ACTIVE

> **Skip this section.** The portal Connect flow for PayPal is disabled in the code (see `api/portal/connect.php` and `api/portal/connect-callback.php`). The steps below are retained for reference / future re-enablement only. When migrating to Partner Referrals API, this section will be replaced with **Enable Commerce Platform** setup, not Log in with PayPal.

Historical steps (do not action):

1. Scroll to the **Features** section.
2. Check **Log in with PayPal**.
3. Click **Manage** under that checkbox.
4. Under **Return URL**, add the redirect that matches that app's environment.
5. Under **Information requested from customers**, check Full Name and Email.
6. Fill in **Privacy Policy URL** and **User Agreement URL**.
7. **Save** at the bottom of the sub-form.

### C. Subscription plans (SaaS billing)

PayPal needs Plan records to exist before the site can sell Premium subscriptions through it. A CLI script creates them via the PayPal API:

```
php setup-paypal-plans.php
```

It uses `APP_ENV` to decide whether to create plans in sandbox or live PayPal, so it must be run **twice**: once with `APP_ENV=sandbox` to create the sandbox plans, and again with `APP_ENV=production` for the live plans. Each run prints two Plan IDs (Monthly and Yearly):

```
PAYPAL_SANDBOX_MONTHLY_PLAN_ID=...
PAYPAL_SANDBOX_YEARLY_PLAN_ID=...
PAYPAL_LIVE_MONTHLY_PLAN_ID=...
PAYPAL_LIVE_YEARLY_PLAN_ID=...
```

The script does **not** write to `.env`. Copy the printed IDs into `.env` manually.

### D. Webhooks

PayPal pings two separate endpoints, with **two separate webhook IDs**. The setup steps are identical for each, just different URLs / events / env keys.

#### D1. SaaS subscription webhook

1. Go to the PayPal app list for the environment you're configuring:
   - Sandbox: https://developer.paypal.com/dashboard/applications/sandbox
   - Live:    https://developer.paypal.com/dashboard/applications/live
2. Click on the app.
3. Scroll to the bottom and click the blue **Add Webhook** button.
4. Fill in:

- **Webhook URL:**
  - Sandbox app: `https://dev.argorobots.com/webhooks/paypal-subscription.php`
  - Live app:    `https://argorobots.com/webhooks/paypal-subscription.php`
- **Event types**, check these:
  - `BILLING.SUBSCRIPTION.ACTIVATED`
  - `BILLING.SUBSCRIPTION.CANCELLED`
  - `BILLING.SUBSCRIPTION.EXPIRED`
  - `BILLING.SUBSCRIPTION.SUSPENDED`
  - `BILLING.SUBSCRIPTION.PAYMENT.FAILED`
  - `PAYMENT.SALE.COMPLETED`
  - `PAYMENT.SALE.DENIED`
  - `PAYMENT.SALE.REFUNDED`

Click **Save**. After saving, copy the **Webhook ID** into the `.env`:

```
PAYPAL_SANDBOX_WEBHOOK_ID=...
PAYPAL_LIVE_WEBHOOK_ID=...
```

#### D2. Portal payment webhook: NOT CURRENTLY ACTIVE

> **Skip this section.** The portal Connect flow for PayPal is disabled, so this webhook will never fire. Steps retained for reference only. When migrating to Partner Referrals API, this webhook will be re-added with additional merchant-lifecycle events (`MERCHANT.ONBOARDING.COMPLETED`, `MERCHANT.PARTNER-CONSENT.REVOKED`, etc.) on top of the capture events listed below.

Historical steps (do not action):

- **Webhook URL:**
  - Sandbox app: `https://dev.argorobots.com/api/portal/webhooks/paypal`
  - Live app:    `https://argorobots.com/api/portal/webhooks/paypal`
- **Event types:**
  - `PAYMENT.CAPTURE.COMPLETED`
  - `PAYMENT.CAPTURE.REFUNDED`

```
PAYPAL_PORTAL_SANDBOX_WEBHOOK_ID=...
PAYPAL_PORTAL_LIVE_WEBHOOK_ID=...
```

---

## Square

The portal Connect flow uses standard Square OAuth with the `PAYMENTS_WRITE`, `PAYMENTS_READ`, and `MERCHANT_PROFILE_READ` scopes. Subscription billing uses Square's Card-on-File API.

### A. Create the app

Unlike PayPal / Stripe sandbox-vs-live being separate apps, Square uses **one app with two environments**, toggled by the **Sandbox / Production** tabs at the top of each inner page.

1. Sign in at https://developer.squareup.com/console/en/apps.
2. Click the **+** tile to create a new app. Name it (e.g., "Argo Books"). If an app already exists, click **Open**.
3. In the left sidebar, go to **Credentials**. Switch between the **Sandbox** and **Production** tabs at the top of the page; for each tab copy:
   - **Application ID** (sandbox starts `sandbox-sq0idb-...`, production starts `sq0idp-...`)
   - **Access token** (click **Replace** if it's hidden / hasn't been generated yet)
4. In the left sidebar, click **Locations**. Pick a location for each environment and copy its **Location ID**.
   - **Sandbox location address:** anything. Sandbox data is never seen by real customers.
   - **Live location address:** the registered address of Argo Books. For a sole proprietor with no separate office, your home address is fine. Note that this address appears on the receipts Square emails to customers, so use a P.O. Box / virtual office instead if you'd rather not put your home address on receipts. Although, these are paid services (\$10 - \$30 / month)
5. Here's what the `.env` should look like:
   ```
   SQUARE_SANDBOX_APP_ID=sandbox-sq0idb-...
   SQUARE_SANDBOX_ACCESS_TOKEN=...
   SQUARE_SANDBOX_LOCATION_ID=...
   SQUARE_LIVE_APP_ID=sq0idp-...
   SQUARE_LIVE_ACCESS_TOKEN=...
   SQUARE_LIVE_LOCATION_ID=...
   ```
   The OAuth Application Secret (`SQUARE_*_APP_SECRET`) lives on the **OAuth** page, covered in section B.

### B. OAuth setup (portal Connect)

In the left sidebar of the app, click **OAuth**. Like the Credentials page, this page also has **Sandbox / Production** tabs at the top. Repeat the steps below once per tab.

1. **Application Secret**. Copy it into the `.env`:
   ```
   SQUARE_SANDBOX_APP_SECRET=...
   SQUARE_LIVE_APP_SECRET=...
   ```
2. **Redirect URL**: Square only allows one redirect URL per environment. Replace whatever's there with:
   - Sandbox tab: `https://dev.argorobots.com/api/portal/connect/callback/square`
   - Production tab: `https://argorobots.com/api/portal/connect/callback/square`

When an Argo Books user clicks **Connect** on Square, their browser is sent to Square's authorize URL (`connect.squareupsandbox.com/oauth2/authorize` in sandbox, `connect.squareup.com/oauth2/authorize` in live). If they land on a **blank page**, that means the Redirect URL saved on this OAuth page doesn't match what the server sent.

### C. Portal payment webhook

Square calls webhooks **Webhook subscriptions** (same idea, different name). In the left sidebar, click **Webhooks -> Subscriptions**. Repeat the steps below once per **Sandbox / Production** tab.

1. Click **Add subscription**.
2. **Name:** `Portal payments`.
3. **Notification URL:**
   - Sandbox tab: `https://dev.argorobots.com/api/portal/webhooks/square`
   - Production tab: `https://argorobots.com/api/portal/webhooks/square`
4. **API version:** `2026-01-22`. This should match the `Square-Version` header the rest of the code sends (grep `Square-Version` to verify the current value). If the code's API version is later bumped, update existing subscriptions to match so payload shapes stay consistent.
5. **Event types**, subscribe to:
   - `payment.created`
   - `payment.updated`
   - `refund.created`
   - `refund.updated`
6. After saving, click into the subscription and reveal the **Signature Key**. Copy to `.env`:
   ```
   SQUARE_SANDBOX_PORTAL_WEBHOOK_SIGNATURE_KEY=...
   SQUARE_LIVE_PORTAL_WEBHOOK_SIGNATURE_KEY=...
   ```

---

## Recap: full `.env` block for portal payments

```
# environment
APP_ENV=sandbox
SITE_URL=https://dev.argorobots.com

# Stripe
STRIPE_LIVE_SECRET_KEY=sk_live_...
STRIPE_LIVE_PUBLISHABLE_KEY=pk_live_...
STRIPE_LIVE_PORTAL_WEBHOOK_SECRET=whsec_...
STRIPE_SANDBOX_SECRET_KEY=sk_test_...
STRIPE_SANDBOX_PUBLISHABLE_KEY=pk_test_...
STRIPE_SANDBOX_PORTAL_WEBHOOK_SECRET=whsec_...

# PayPal
PAYPAL_LIVE_CLIENT_ID=...
PAYPAL_LIVE_CLIENT_SECRET=...
PAYPAL_LIVE_WEBHOOK_ID=...
PAYPAL_LIVE_MONTHLY_PLAN_ID=...
PAYPAL_LIVE_YEARLY_PLAN_ID=...
PAYPAL_SANDBOX_CLIENT_ID=...
PAYPAL_SANDBOX_CLIENT_SECRET=...
PAYPAL_SANDBOX_WEBHOOK_ID=...
PAYPAL_SANDBOX_MONTHLY_PLAN_ID=...
PAYPAL_SANDBOX_YEARLY_PLAN_ID=...
PAYPAL_PORTAL_LIVE_WEBHOOK_ID=...
PAYPAL_PORTAL_SANDBOX_WEBHOOK_ID=...

# Square
SQUARE_LIVE_APP_ID=...
SQUARE_LIVE_APP_SECRET=...
SQUARE_LIVE_ACCESS_TOKEN=...
SQUARE_LIVE_LOCATION_ID=...
SQUARE_LIVE_PORTAL_WEBHOOK_SIGNATURE_KEY=...
SQUARE_SANDBOX_APP_ID=sandbox-sq0idb-...
SQUARE_SANDBOX_APP_SECRET=...
SQUARE_SANDBOX_ACCESS_TOKEN=...
SQUARE_SANDBOX_LOCATION_ID=...
SQUARE_SANDBOX_PORTAL_WEBHOOK_SIGNATURE_KEY=...
```
