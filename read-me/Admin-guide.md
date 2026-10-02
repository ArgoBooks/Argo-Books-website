# Payment System - Admin Guide

## Payment Processor Fees

Every online payment, whether it's a customer paying an invoice through the Payment Portal or a customer buying an Argo Books Premium subscription adds **2.9% + \$0.30 CAD** on top of the base amount. That's the same number no matter which provider (Stripe, PayPal, or Square), no matter the country. It's hardcoded in `.env` via `PROCESSING_FEE_PERCENT` and `PROCESSING_FEE_FIXED`.

Who actually pays the fee depends on the flow:

- **Argo Books Premium subscriptions:** the customer always pays it. They see `$10.00 + $0.59` (or whatever the base price + fee works out to) at checkout.
- **Invoices sent through the Payment Portal:** the merchant decides per invoice via the "pass processing fee" toggle:
  - **Toggle on** → the merchant passes the cost on to the customer. Customer pays invoice total + 2.9% + \$0.30.
  - **Toggle off** → customer pays just the invoice total. The merchant absorbs the cost.

Argo Books, and the companies using Argo Books pay these fees when they move the money out of their Stripe/PayPal/Square account and into their normal bank account. Below are the rates that the payment providers charge:

### Stripe

- **Standard Rate:** 2.9% + \$0.30 CAD per transaction
- **International Cards:** Additional 1.5%
- **Currency Conversion:** Additional 1%

### PayPal

> **Note:** PayPal is currently **not supported** as a payment provider in the customer-facing Payment Portal. Merchants cannot connect their PayPal Business account through Argo Books, and customers paying invoices will only see Stripe and Square as options. PayPal is still used for the Argo Books Premium subscription (the merchant paying Argo Books for their plan).
>
> The reason: PayPal's "Log in with PayPal" OAuth flow refuses to return merchant identity for Business accounts, so the Connect flow can't onboard real merchants. Proper onboarding requires PayPal **Partner Referrals API**, which is gated behind their **Platforms & Marketplaces** partner program. To re-enable PayPal in the portal, Argo Books needs to:
> 1. Form a registered business entity (e.g., a Canadian-Controlled Private Corporation. See "Forming a business entity" below if needed).
> 2. Apply at https://www.paypal.com/us/business/platforms-marketplaces (or the regional equivalent for non-US applicants).
> 3. Once approved, implement the Partner Referrals migration described in the plan file.
> 4. Optionally, drop the PayPal `BN code` (`PayPal-Partner-Attribution-Id`) into `.env` for revenue attribution.
>
> Plan to apply once Argo Books has its first ~50 paying merchants or ~$10k/month projected PayPal volume. Pre-revenue applications typically get deferred. Until then, the portal runs on Stripe + Square only.

When re-enabled, PayPal's processing fees are:

- **Standard Rate:** 2.9% + \$0.30 CAD per transaction
- **PayPal Account Payments:** Same rate
- **International:** 4.4% + fixed fee

### Square

- **Online Payments:** 2.9% + \$0.30 CAD per transaction
- **Card on File:** Same rate
- **International:** Additional 1.5%

---

## Environment Modes

### Sandbox Mode (Testing)

```
APP_ENV=sandbox
```

- Uses test API keys
- No real money processed
- Use test cards/accounts
- Perfect for development and testing

### Production Mode (Live)

```
APP_ENV=production
```

- Uses live API keys
- Real money processed
- Real cards charged
- Only use when ready to go live

**To switch:** Change `APP_ENV` in `.env` file and restart PHP.

---

## Key Rotation

### When to Rotate Keys

- **Regular schedule:** Every 90 days (recommended)
- **After breach:** Immediately if compromised
- **Staff changes:** When developers leave

### How to Rotate

**Stripe:**

1. Dashboard → Developers → API keys
2. Find "Secret key" row
3. Click the **"..."** (three dots) next to the secret key
4. Click **"Roll key"**
5. Copy the new secret key immediately (shown only once)
6. Update `STRIPE_LIVE_SECRET_KEY` in .env

**PayPal:**

1. Dashboard → Apps & Credentials → Your App → Generate new secret
2. Update `PAYPAL_LIVE_CLIENT_SECRET` in .env

**Square:**

1. Dashboard → Your App → Credentials → Replace (access token)
2. Update `SQUARE_LIVE_ACCESS_TOKEN` in .env

**After rotation:** Always ensure the website sever has the latest .env

---

## Admin Scripts

Contact Evan Di Placido to obtain these scripts.

### create_admin.php
Creates admin users

- Place in `/admin` directory
- Visit: `www.argorobots.com/admin/create_admin.php`

### reset_admin_password.php
Resets admin passwords

- Place in `/admin` directory
- Visit: `www.argorobots.com/reset_admin.php`

### create_community_admin.php
Creates admin users for the community system

- Place in `/community/users` directory
- Visit: `www.argorobots.com/community/users/create_community_admin.php`

**Important:** Delete all admin creation scripts immediately after use for security.
