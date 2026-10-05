# Cron Jobs

All cron scripts live in `/cron/` and must be run via CLI only. Each script writes daily logs to `/cron/logs/`.

## Run history retention

`cron_runs` is pruned by `cron_runs_prune()` in `cron/lib/run_tracker.php`, called from `cron_run_finish()` on roughly one run in five hundred. Retention is 90 days, well beyond the 30 day maximum the admin page displays.

It lives in the tracker rather than in any single cron because that file is what writes the rows, so every cron contributes to the cleanup regardless of which one happens to fire.

Two rows are never deleted, whatever their age:

- The newest run of each cron. Without that, a cron that has not fired in months would disappear from `/admin/crons/` and read as "never run", which is the one question that page exists to answer.
- Anything still marked `running`, since that is a stuck run worth seeing.

The table had no cleanup at all before this. A cron on a one minute schedule adds around 525,000 rows a year on its own, and two of them are on that schedule.

---

## 1. Subscription Renewal

**Script:** `cron/subscription_renewal.php` **Schedule:** Daily at 3:00 PM

```bash
0 15 * * * /usr/bin/php /home/argorobots/public_html/cron/subscription_renewal.php
```

### What It Does

1. Finds active Premium subscriptions due for renewal within 24 hours
2. Processes credit-based renewals first (no charge if credit covers it)
3. Charges payment methods (Stripe/Square) for remaining balance
4. Sends email receipts for successful renewals
5. Sends failure notifications for failed payments
6. Suspends subscriptions after 3 consecutive failures
7. Marks non-auto-renew subscriptions as expired (this environment's only)

### Overlap Protection

Takes an exclusive lock on `cron/logs/subscription_renewal.lock` and exits immediately if a previous run still holds it, recording the skip in `cron_runs`. Both the charged path and the fully-credit-covered path also check for a completed renewal payment in the last 23 hours before doing anything, so a manual re-run cannot charge a card or spend credit twice.

### Manual Execution

```bash
php /home/argorobots/public_html/cron/subscription_renewal.php
```

### Logs

`/cron/logs/subscription_renewal_YYYY-MM-DD.log`

---

## 2. Account Purge

**Script:** `cron/account_purge.php` **Schedule:** Daily at 4:00 AM

```bash
0 4 * * * /usr/bin/php /home/argorobots/public_html/cron/account_purge.php
```

### What It Does

1. Finds user accounts whose 30-day deletion grace period has expired (users schedule deletion from their profile; logging back in cancels the request)
2. Cancels any active Premium subscriptions for the account
3. Deletes the user record (foreign key cascades handle related community data)
4. Logs each deletion or failure

### Manual Execution

```bash
php /home/argorobots/public_html/cron/account_purge.php
```

### Logs

`/cron/logs/account_purge_YYYY-MM-DD.log`

---

## 3. Outreach Pipeline

**Script:** `cron/outreach_pipeline.php` **Schedule:** Daily at 8:00 AM

```bash
0 8 * * * /usr/bin/php /home/argorobots/public_html/cron/outreach_pipeline.php
```

### What It Does

It works through the leads already on the admin Outreach page. It does not find leads: they are added there by hand or by CSV.

1. Generates AI email drafts for leads that don't have one
2. Auto-approves drafts (configurable)
3. Sends approved emails up to the daily limit
4. Stops follow-ups for leads who replied, unsubscribed or bounced
5. Drafts and sends the follow-ups that are due

### Environment Variables

| Variable | Default | Description |
|---|---|---|
| `GEMINI_API_KEY` | (required) | Required for AI draft generation |
| `OUTREACH_DAILY_SEND_LIMIT` | 10 | Max first-touch emails sent per day |
| `OUTREACH_DAILY_FOLLOWUP_LIMIT` | 30 | Max follow-up emails sent per day (separate cap, oldest-due first) |

The Auto-send vs Review-before-send mode is controlled at runtime via the **Settings tab** in the admin (writes to `outreach_pipeline_state.auto_send_mode`); no env var is needed.

### CLI Flags

```bash
php outreach_pipeline.php                  # Run full pipeline
php outreach_pipeline.php --draft-only     # Only generate AI drafts
php outreach_pipeline.php --send-only      # Only send approved emails
php outreach_pipeline.php --dry-run        # Log what would happen without doing it
```

### Logs

`/cron/logs/outreach_pipeline_YYYY-MM-DD.log`

A lock file (`/cron/logs/outreach_pipeline.lock`) prevents overlapping runs.

---

## 4. Outreach Reply Checker

**Script:** `cron/reply_checker.php` **Schedule:** Hourly

```bash
0 * * * * /usr/bin/php /home/argorobots/public_html/cron/reply_checker.php
```

### What It Does

1. Connects to the contact mailbox via IMAP
2. Fetches emails received in the last 7 days
3. Skips auto-responders (out-of-office, auto-reply headers)
4. Matches sender addresses against outreach leads in `contacted` status
5. Auto-promotes matched leads to `replied` status (only if the email arrived after `sent_at`)
6. Logs a `reply_received` activity entry for each match

Admins can then manually classify replied leads as `interested` or `not_interested` in the outreach dashboard.

### Requirements

- PHP `imap` extension must be enabled. In cPanel: **Select PHP Version → Extensions → check `imap`**
- Mailbox credentials configured in `.env` (see below)

### Environment Variables

| Variable | Default | Description |
|---|---|---|
| `IMAP_HOST` | (required) | e.g. `mail.argorobots.com` |
| `IMAP_PORT` | 993 | IMAP SSL port |
| `IMAP_USERNAME` | (required) | Full email address (e.g. `contact@argorobots.com`) |
| `IMAP_PASSWORD` | (required) | Mailbox password or app password |
| `IMAP_MAILBOX` | INBOX | Folder to check |

### Manual Execution

```bash
php /home/argorobots/public_html/cron/reply_checker.php
```

### Logs

`/cron/logs/reply_checker_YYYY-MM-DD.log`

A lock file (`/cron/logs/reply_checker.lock`) prevents overlapping runs.

### Limitations

- Replies sent from a different address than the lead's email won't be detected
- Relies on the contact mailbox being the reply-to for outreach emails (it is, per `send_outreach_lead()`)

---

## 5. Refund Cooling-Off Promoter

**Script:** `cron/refund_cooling_off_promoter.php` **Schedule:** Every 1 minute

```bash
* * * * * /usr/bin/php /home/argorobots/public_html/cron/refund_cooling_off_promoter.php
```

### What It Does

Promotes refund requests from `cooling_off` to `processing` once their cooling-off timer has elapsed. Without this cron, refunds flagged for soft review would sit in `cooling_off` forever.

---

## 6. Refund Stale Processing Reconcile

**Script:** `cron/refund_stale_processing_reconcile.php` **Schedule:** Every 5 minutes

```bash
*/5 * * * * /usr/bin/php /home/argorobots/public_html/cron/refund_stale_processing_reconcile.php
```

### What It Does

Queries Stripe for refund requests stuck in `processing` for more than 30 minutes and updates their state based on the provider's response. Catches refunds where the provider succeeded but our callback never fired. A refund it finds succeeded is also written to the portal payment records, so it still syncs to the desktop app.

---

## 7. Refund Stale Request Cleanup

**Script:** `cron/refund_stale_request_cleanup.php` **Schedule:** Hourly

```bash
0 * * * * /usr/bin/php /home/argorobots/public_html/cron/refund_stale_request_cleanup.php
```

### What It Does

1. Cancels refund requests stuck in `pending_code` for more than 1 hour (user never typed the verification code).
2. Vacuums old entries out of the refund idempotency cache.

---

## 8. Refund Velocity Baseline Recompute

**Script:** `cron/refund_velocity_baseline_recompute.php` **Schedule:** Nightly at 2:00 AM

```bash
0 2 * * * /usr/bin/php /home/argorobots/public_html/cron/refund_velocity_baseline_recompute.php
```

### What It Does

Refreshes `refund_velocity_baselines` per company so the established-account hard-block tier (≥ 50% of trailing 30-day revenue) has accurate inputs. If baselines drift out of date, the refund safety check fires too often or not enough. See the [Hard-Block Response Procedure](procedures/Refund-block-response-procedure.md) for symptoms.

---

## 9. Marketing Broadcast Sender

**Script:** `cron/marketing_broadcast.php` **Schedule:** Every 5 minutes

```bash
*/5 * * * * /usr/bin/php /home/argorobots/public_html/cron/marketing_broadcast.php
```

### What It Does

Drains queued broadcasts composed in the admin **Marketing** page (`marketing_broadcasts` / `marketing_broadcast_recipients`). Each run:

1. Picks broadcasts in `queued` or `sending` status, oldest first, and marks them `sending`.
2. Sends up to 100 emails per run total (the per-run cap keeps each run under Resend's rate limit and the time budget). A broadcast larger than the cap finishes over multiple runs.
3. Re-checks every recipient against the send-time gate (`should_send_marketing_email`), so anyone who unsubscribed between queue time and send time is marked `skipped`, not emailed.
4. Appends the one-click unsubscribe footer (subscriber token for the `newsletter` audience, community-user token for community contexts).
5. Marks the broadcast `sent` once no `pending` recipients remain.

Audience is one of: `newsletter` (no-account opt-in subscribers in `marketing_subscribers`) or a community context (`product_updates`, `tips_onboarding`, `promotions`) that maps to a `community_users.email_pref_*` column.

### Manual Execution

```bash
php /home/argorobots/public_html/cron/marketing_broadcast.php
```

### Logs

A lock file (`/cron/logs/marketing_broadcast.lock`) prevents overlapping runs.

---

## 10. IndexNow Submit

**Script:** `cron/indexnow_submit.php` **Schedule:** Daily at 5:00 AM

```bash
0 5 * * * /usr/bin/php /home/argorobots/public_html/cron/indexnow_submit.php
```

### What It Does

Pings IndexNow with the pages whose source files changed since the last successful run, so freshly deployed or edited pages get recrawled quickly without manual submission. IndexNow notifies Bing, Yandex, DuckDuckGo, Seznam, and Naver in one call.

1. Builds the full URL list from `sitemap_build_urls()` in `sitemap_urls.php` (the same source the XML sitemap uses, so new pages are picked up automatically).
2. Selects URLs whose source file modification time is newer than the stored watermark (`/cron/logs/indexnow_last_submit`). On the server, file mtime reflects the last deploy that touched the file.
3. POSTs them to `https://api.indexnow.org/indexnow` via the helper in `indexnow.php`.
4. Advances the watermark only on full success, so a transient failure retries the same URLs next run.

The first run has no watermark, so it submits every URL once as a bootstrap. Run with `--baseline` first if you would rather start clean and only announce future changes.

Google does NOT participate in IndexNow, so this cron does nothing for Google. Keep using Search Console for Google.

### Setup (one time)

1. The ownership key file `77469e7877e34a30ab5fab27e275650e.txt` lives at the site root and deploys with the rest of the repo. Confirm it is reachable at `https://argorobots.com/77469e7877e34a30ab5fab27e275650e.txt` after the first deploy.
2. Add the crontab line above on the server.

The key can be rotated by generating a new one in Bing Webmaster Tools, replacing the root `.txt` file, and setting `INDEXNOW_KEY` in `.env`.

### CLI Flags

```bash
php indexnow_submit.php             # Submit URLs changed since last run
php indexnow_submit.php --all       # Force-submit every URL
php indexnow_submit.php --baseline  # Record the watermark without submitting
php indexnow_submit.php --dry-run   # Log what would be submitted; send nothing
```

### Logs

`/cron/logs/indexnow_submit_YYYY-MM-DD.log`

A lock file (`/cron/logs/indexnow_submit.lock`) prevents overlapping runs.

---

## 11. Portal Invoice Reminders

**Script:** `cron/portal_invoice_reminders.php` **Schedule:** Daily at 9:00 AM

```bash
0 9 * * * /usr/bin/php /home/argorobots/public_html/cron/portal_invoice_reminders.php
```

### What It Does

Emails a merchant's customers when a portal invoice goes unpaid, so chasing payment keeps happening while Argo Books is closed.

1. Selects overdue invoices belonging to companies with `portal_companies.reminders_enabled = 1`, skipping any company that is inactive or locked.
2. Works out which reminder stage is due: 3, 7 or 14 days past the due date. The highest eligible stage wins, so a run that was missed for a week sends one reminder rather than three.
3. Claims the stage by inserting into `portal_invoice_reminders` before sending. `UNIQUE (portal_invoice_id, stage)` means a duplicate insert fails, which is the entire defence against sending the same reminder twice.
4. Re-reads the invoice immediately before sending and skips it if it was paid or cancelled in the meantime, or if the customer sits in `email_suppressions` under the `portal` or `all_marketing` context.
5. Sends a white-label reminder from the merchant's name via `send_invoice_reminder()`, with a reply-to pointing at the merchant when their owner email is verified.

Stops permanently once an invoice is paid, cancelled, or has been chased three times.

### Behaviour Worth Knowing

- **Enabling never releases a backlog.** `portal_companies.reminders_enabled_at` is re-stamped on every off-to-on transition, and only invoices whose due date falls after it are ever chased. A merchant switching this on for the first time will not blast months of old overdue invoices at their customers.
- **Environment is filtered.** Sandbox and production share a database, so the run only ever touches invoices matching `current_environment()`. Without that filter a sandbox test invoice would email a real customer.
- **A failed send is never retried.** The next stage still fires on schedule, so a transient SMTP failure costs one touch. Retrying is how you end up sending four reminders.
- **Caps.** 200 reminders per run across all companies, 50 per company. Anything deferred is picked up first on the next run, since selection is ordered by due date.
- **Invoices more than 45 days overdue are ignored**, so a long outage cannot fire a "final reminder" at something from months ago.

### CLI Flags

```bash
php portal_invoice_reminders.php            # Normal run
php portal_invoice_reminders.php --dry-run  # Log what would be sent; send nothing, write no rows
```

### Logs

`/cron/logs/portal_invoice_reminders_YYYY-MM-DD.log`

A lock file (`/cron/logs/portal_invoice_reminders.lock`) prevents overlapping runs.

---

## 12. Payroll Rate Reminder

**Script:** `cron/payroll_rate_reminder.php`  
**Frequency:** daily

```
0 8 * * * /usr/bin/php /home/argorobots/public_html/cron/payroll_rate_reminder.php
```

### What It Does

Emails a reminder that CRA is about to publish new payroll deduction tables. CRA changes them twice a year, effective 1 January and 1 July, and Argo Books ships no tax numbers in the app, so a new rate file has to be prepared and uploaded before each changeover.

Runs daily but does nothing outside the two windows: the 10th to the 20th of December, which warns about the January edition, and the same days in June for July's.

### Behaviour Worth Knowing

Sends **once per window**, not once per day. It checks `cron_runs` for a successful send naming the same edition in the last 30 days, so a daily schedule produces one email rather than eleven.

**Cannot be disabled.** There is no notification preference for it. The whole point is that it arrives in a month when payroll is not on anyone's mind, and missing a changeover means every pay run calculated afterwards uses the wrong rates.

December warns about `YYYY+1-01`, June about `YYYY-07`.

### Logs

No daily log file. Activity is visible on `/admin/crons/`, and failures go to `error_log`.

Full instructions for actually doing the update are in `docs/PayrollRateUpdates.md` in the desktop repository.

---

## 13. API Webhook Delivery

**Script:** `cron/api_webhook_delivery.php` **Schedule:** Every minute

```bash
* * * * * /usr/bin/php /home/argorobots/public_html/cron/api_webhook_delivery.php
```

### What It Does

Delivers Argo Books public API events to the webhook endpoints developers have registered.

Events fire when a merchant acts on data a developer pushed: `<object>.imported`, `<object>.rejected`, `import_batch.completed`, `import_batch.reverted`. There is deliberately no `<object>.created` event, since the developer who created it already knows.

1. Claims up to 200 due deliveries, oldest first
2. POSTs each one with an `Argo-Signature: t=<unix>,v1=<hmac-sha256>` header
3. Treats 2xx as success, everything else as retryable
4. Backs off across six attempts spanning about 15 hours
5. Auto-disables an endpoint whose last 20 deliveries all failed
6. Prunes `api_events` rows older than 90 days

### Why It Is a Cron

Delivery is not inline with the request that creates the event, because a developer's slow or hanging server would otherwise become a merchant's slow import. The cost is up to a minute of latency, which is nothing next to a queue whose next step is a person opening an app.

### Behaviour Worth Knowing

Takes a `flock` on `cron/logs/api_webhook_delivery.lock`. Overlapping runs would double-POST the same delivery, so a run that cannot get the lock records an `ok` result saying it skipped, rather than exiting silently and looking like a missed cron.

A 410 is retried like any other failure. Guessing "this endpoint is gone" from a status code would drop events during a bad deploy on the developer's side; the attempt limit catches a genuinely dead URL soon enough.

Redirects are never followed. The signature is for the URL the merchant approved.

The destination is re-checked against the private-address rules immediately before every POST, not just when the endpoint was registered. Checking only at registration would leave DNS rebinding open: a name that resolved publicly then can resolve to an internal address by delivery time. A destination that fails the re-check is counted under "Refused, destination not public".

**This is the one cron that does not filter on `environment`,** and it is on purpose. Only production runs crons, so filtering would queue sandbox webhooks and never deliver them. The trade is that its metrics count both environments together.

Supports `--dry-run`, which resolves everything and logs what would be sent without sending it.

### Logs

No daily log file. Metrics are on `/admin/crons/`, and failures go to `error_log`.

---

## 14. Argo Books Books Sync

**Script:** `cron/argo_books_sync.php` **Schedule:** Daily at 4:25 AM

```bash
25 4 * * * /usr/bin/php /home/argorobots/public_html/cron/argo_books_sync.php
```

### What It Does

Pushes this business's own trading history into a company inside Argo Books, through the public API at `/v1`. It is the company's real bookkeeping, not a demo.

1. Creates the two categories everything else hangs off: subscription income, affiliate commissions
2. Creates one customer per paying email address, not one per subscription, so a renewing customer stays a single person with several payments
3. Creates a supplier for each affiliate that has actually been paid
4. Sends completed and refunded subscription payments as revenue, referencing the customer and category
5. Sends affiliate payouts as expenses, referencing the supplier and category
6. Sends a refund against the revenue for each payment whose status is `refunded`

Nothing reaches the books unattended. The API is an ingest queue: objects arrive as proposals and someone accepts them inside the app.

### How Repeat Runs Stay Honest

Every write carries an `Idempotency-Key` derived from the source row, so the API refuses a duplicate even if the script runs twice in the same minute.

`argo_books_sync_map` records the object id the API returned for each source row alongside a hash of what was sent. A row whose payload has not changed is skipped without a request, and one that has changed is updated in place rather than posted again as a second object. Most days almost everything is skipped, so a large "Skipped, unchanged" count beside small created counts is the healthy shape.

### Discarding Something in the App

The review step inside Argo Books offers three answers, not two: import, discard, or cancel. Cancel leaves everything queued for next time. Discard marks the objects rejected on the server and tells the apps that sent them.

Each run starts by asking the API which objects have been rejected and marking those rows in the map, before any other work. That step is what makes discard safe. The map otherwise records only "sent", so a discarded row would still match its hash on the next run, be skipped as unchanged, and silently stay missing from the books for good.

A rejected row is never sent again and never re-offered. Reversing that decision means deleting its map row by hand, which is deliberate: the whole point is that a refusal sticks.

### Environment Variables

| Variable | Purpose |
|---|---|
| `ARGO_BOOKS_API_KEY` | The API key minted in the app under Settings, Integrations. Environment-scoped, so a production key only works against production. |

### CLI Flags

| Flag | Effect |
|---|---|
| `--dry-run` | Resolves and hashes everything, logs what would be sent, sends nothing |
| `--since=YYYY-MM-DD` | Only considers source rows dated on or after this |
| `--limit=N` | Stops after N writes, for a cautious first run |

### Manual Execution

```bash
php /home/argorobots/public_html/cron/argo_books_sync.php --dry-run
php /home/argorobots/public_html/cron/argo_books_sync.php --limit=25
```

### Known Gaps

Payment processor fees are not stored per payment, so `fee_amount` is left at zero and the margin picture is incomplete.

StackSocial income never appears. Free key redemptions create a subscription with no payment row, and the money arrives as Tipalti payouts that do not touch this database. Those are manual entries.

There is no `refunded_at` column; a payment row simply flips to `refunded`. Refunds are therefore dated to the original sale rather than to when the money went back.

### Logs

Daily file at `cron/logs/argo_books_sync-YYYY-MM-DD.log`, plus metrics on `/admin/crons/` and failures in `error_log`.

---

## 15. API Retention

**Script:** `cron/api_retention.php` **Schedule:** Daily at 4:30 AM

```bash
30 4 * * * /usr/bin/php /home/argorobots/public_html/cron/api_retention.php
```

### What It Does

Deletes Argo Books public API objects that will never be used again, 30 days after they stopped mattering:

1. Objects a developer deleted, counted from when they were deleted
2. Objects the merchant rejected in the app, counted from when they were rejected
3. The line items of any expense or revenue deleted in steps 1 and 2

### Behaviour Worth Knowing

Imported objects are never deleted. The app looks them up again later: a new sale can name a customer that was imported months ago, and a refund names the sale it reverses. Deleting them would break those references.

An object that anything else still points at is kept too, even if it is deleted or rejected. The list of references is read from the resource definitions in `api/v1/lib/definitions.php`, so a new reference field is covered without changing this script.

Objects that point at others are processed first, refunds before revenue and revenue before customers, so a dead customer whose only reference was a dead sale goes in the same run instead of the next one.

What stops the API filling the server is the daily create limit (`RL_API_V1_CREATES_PER_DAY_*`), not this cron. The cron only keeps dead rows from piling up over time.

Like API Webhook Delivery, it does not filter on `environment`. Only production runs crons, so filtering would leave dead sandbox rows in place forever.

### Logs

No daily log file. Metrics are on `/admin/crons/`, and failures go to `error_log`.

## 16. Purge Import Files

**Script:** `cron/purge_import_files.php` **Schedule:** Hourly

```bash
0 * * * * /usr/bin/php /home/argorobots/public_html/cron/purge_import_files.php
```

### What It Does

Deletes the diagnostic files people send after an import fails, once each one passes its retention window of seven days. The file is removed from `storage/import-diagnostics/` and its row is marked so the admin page stops offering it. The row itself stays, because it is the only record that a file existed and who took it.

It also sweeps orphans: a file on disk with no row in `import_diagnostic_files`. That can only happen if an upload died between writing the file and inserting its row, which the upload endpoint already tries to clean up after itself. Anything other than zero orphans is worth looking at.

### Behaviour Worth Knowing

This cron is the thing that keeps a promise made to customers, not a tidying job. The Import Files page tells you every file deletes itself whether or not you looked at it, and the privacy policy says the same. If this stops running, files that people were told would be deleted are not deleted, so the failure paths report through `cron_runs` rather than exiting quietly.

It does not filter on `environment`. An expired sandbox file is as real on disk as a production one, and only production runs crons anyway.

The retention window lives in two places that have to agree: `IMPORT_DIAGNOSTIC_RETENTION_DAYS` in `api/import-diagnostic/upload.php`, which stamps `expires_at` when a file arrives, and `$retentionDays` in `admin/import-files/index.php`, which is only what the page tells you. The cron reads `expires_at` and so follows the first of those.

Hourly rather than daily because the window is short. A file deleted up to a day late has spent a seventh of its whole retention period past the point it should have been gone.

### Metrics

`still_held` is the one to watch. It is how many customer files are on the server right now, and it should fall back to zero on its own. A number that keeps climbing means files are arriving faster than anyone is dealing with them.

### Logs

No daily log file. Metrics are on `/admin/crons/`, and failures go to `error_log`.
