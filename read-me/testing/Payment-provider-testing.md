# Payment Provider Testing

Test procedures for Stripe, PayPal, and Square, both the **payment portal** and the **SaaS subscription billing**.

Two completely separate flows go through each provider:

- **SaaS subscription payments**: argorobots.com customers paying for Argo Books Premium.
- **Portal Connect**: Argo Books desktop users authorizing the website to accept invoice payments through their own provider account.

All testing below runs against the sandbox environment of each provider. To switch the website to sandbox, set `APP_ENV=sandbox` in `.env`. Production testing instructions are in the **Switch to Production** section at the bottom.

---

## Stripe

### SaaS subscription payment

1. Go to checkout page with Stripe selected:

```
https://dev.argorobots.com/pricing/checkout/index.php?method=stripe
```

2. Fill in the form:

   - **Cardholder Name**: Any name
   - **Card Number**: `4242 4242 4242 4242`
   - **Expiry**: Any future date (e.g., 12/28)
   - **CVC**: Any 3 digits (e.g., 123)
   - **Email**: Your email address

3. Click "Pay"

4. Check results:
   - Should redirect to thank you page with license key
   - Check email for license key

#### Other test cards

- **Success**: `4242 4242 4242 4242`
- **Requires 3D Secure**: `4000 0025 0000 3155`
- **Declined**: `4000 0000 0000 9995`

#### Verify in Stripe Dashboard

1. Go to [Stripe Dashboard](https://dashboard.stripe.com/test/payments)
2. Make sure you're in **Test Mode** (toggle in top right)
3. Check the Payments section for your test payment

### Portal Connect (Express onboarding)

Unlike Square and PayPal (OAuth flows), Stripe portal Connect uses **Stripe Connect Express**: Stripe creates a fresh connected account for the merchant and they fill in identity / business / bank details on Stripe's hosted onboarding pages. In test mode, Stripe ships **magic-success values** that pass verification without making real bank calls.

1. In the Argo Books desktop app (dev mode), open **Settings -> Payment Portal** and click **Connect** on Stripe.
2. A new tab opens at `connect.stripe.com/express/onboarding/...`. Use these magic test values (full reference: [Stripe Connect testing docs](https://docs.stripe.com/connect/testing)):

   | Field | Test value |
   |---|---|
   | Phone | Any valid format, e.g. `000 000 0000` |
   | Email | Any address (will receive Express dashboard emails) |
   | Date of birth | `01 / 01 / 1901` (magic DOB that auto-passes ID verification) |
   | Address | Any valid address in the country you chose |
   | Tax ID / SSN (US) | `000000000` |
   | SIN (Canada) | `000 000 000` |
   | Bank account (US) | Routing `110000000`, account `000123456789` |
   | Bank account (Canada) | Transit `11000`, institution `000`, account `000123456789` |

3. Submit the form. Stripe redirects back to the portal callback and Stripe will show as connected in the app.

#### Verify in Stripe Dashboard

1. Go to [Stripe Dashboard - Connected Accounts](https://dashboard.stripe.com/test/connect/accounts/overview)
2. Make sure you're in **Test Mode**
3. The newly-onboarded Express account appears in the list (its account ID, starting with `acct_`, is also stored in `portal_companies.stripe_account_id`).

---

## PayPal

> **Status:** Only the SaaS subscription billing side of PayPal is testable. The portal Connect flow is disabled. See the **Portal Connect** subsection below.

### SaaS subscription payment

1. Go to checkout page with PayPal selected:

```
https://dev.argorobots.com/pricing/checkout/index.php?method=paypal
```

2. Click the PayPal button

3. Log in with a PayPal sandbox **Personal (buyer)** test account:

   - Go to [PayPal Developer Dashboard - Sandbox Accounts](https://developer.paypal.com/dashboard/accounts)
   - Use the auto-created `sb-...@personal.example.com` account, or click **Create account** -> **Personal** to make a new one
   - Get the password: click the **...** menu next to the account -> **View/edit account** -> copy the system-generated password
   - **If it asks for a verification code:** Click "Use a different method" and choose "Password" instead

4. Complete the payment in the PayPal popup

5. Check results:
   - Should redirect to thank you page with license key
   - Check email for license key

#### Verify in PayPal Dashboard

1. Go to [PayPal Sandbox](https://www.sandbox.paypal.com/)
2. Make sure you're in **Sandbox Mode**
3. Check the Payments section for your test payment

### Portal Connect: NOT CURRENTLY SUPPORTED

The PayPal portal Connect flow is **disabled**. PayPal's "Log in with PayPal" OAuth endpoint refuses to return identity for Business-account tokens, so the flow can't onboard real merchants. The desktop app hides the PayPal Connect button under Settings -> Payment Portal, and the server-side `api/portal/connect/paypal` endpoint returns 503 PROVIDER_UNSUPPORTED.

Re-enabling requires migrating to **PayPal Partner Referrals API** after approval into PayPal Platforms & Marketplaces. See `read-me/Admin-guide.md` for the program details. There is no portal Connect test to run for PayPal today; test Stripe and Square portal Connect instead.

---

## Square

### SaaS subscription payment

1. Go to checkout page with Square selected:

```
https://dev.argorobots.com/pricing/checkout/index.php?method=square
```

2. Fill in the form:

   - **Cardholder Name**: Any name
   - **Card Number**: `4111 1111 1111 1111` (Visa)
   - **Expiry**: Any future date (e.g., 12/28)
   - **CVV**: Any 3 digits (e.g., 111)
   - **Postal Code**: Any valid postal code
   - **Email**: Your email address

3. Click "Pay"

4. Check results:
   - Should redirect to thank you page with license key
   - Check email for license key

#### Other test cards

**Success:**

- Visa: `4111 1111 1111 1111`
- Mastercard: `5105 1051 0510 5100`
- Amex: `3782 822463 10005`

**Declined:**

- `4000 0000 0000 0002` (generic decline)

**More test cards:** [Square Testing Guide](https://developer.squareup.com/docs/devtools/sandbox/payments)

#### Verify in Square Dashboard

1. Go to [Square Developer Dashboard](https://developer.squareup.com/apps)
2. Make sure you're in **Sandbox** mode
3. Click on your application
4. Check the test payments

### Portal Connect (OAuth)

Testing the portal Connect flow requires a Square **sandbox seller test account**, which is distinct from your Square developer account. Without one, the authorize page is blank.

1. Open the [Square Developer Console](https://developer.squareup.com/console/en/apps) and go to **Sandbox test accounts** in the left sidebar.
2. Open the **Default Test Account** or create a new one (set Country to Canada to match the app's CAD currency).
3. A new tab opens at `app.squareupsandbox.com/dashboard` signed in as that test seller. Keep this tab open in the same browser.
4. In the Argo Books desktop app (dev mode), open **Settings -> Payment Portal** and click **Connect** on Square.
5. Click Allow, and Square will show as connected in the app.

---

## Switch to Production

When ready to go live for any of the three providers:

1. Change `.env`: `APP_ENV=production`
2. Test with a real card (you'll be charged)
