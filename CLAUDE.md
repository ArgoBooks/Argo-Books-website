# Argo Books Website: Claude Code Guide

PHP/MySQL website for [Argo Books](https://argorobots.com/) accounting software. Handles marketing, software downloads, license sales, customer portal, community forum, admin dashboard, and API endpoints for the desktop app.

**Stack:** PHP 8.3+, MySQL (InnoDB, `utf8mb4`), Laragon locally, Composer. No build step, just refresh the browser.

**Companion repos:** The Avalonia/C# desktop app lives at `C:\Users\evand\Desktop\Argo-Books-Avalonia`. Many endpoints in `/api/` are called from there.

## Environment files

- `.env`: active environment file (gitignored)
- `.env - production` and `.env - sandbox`: reference copies kept on disk, not in
  the repo. `.gitignore` excludes `.env*`, so a fresh clone has none of them and
  they have never been committed. They hold real credentials; keep it that way.
- `APP_ENV` (`sandbox` or `production`) drives all environment-conditional code (see `current_environment()` in `db_connect.php`)

## Key files

| Path | Why it matters |
|---|---|
| `db_connect.php` | Global `$pdo` + AES-256-GCM helpers + `current_environment()` |
| `email_sender.php` / `smtp_mailer.php` | All transactional email goes through here |
| `license_functions.php` | License key generation, validation, redemption |
| `statistics.php` | Page-view tracking + bot detection (`is_likely_bot()`) |
| `track_referral.php` / `track_referral_event.php` | Referral attribution + full-funnel event log |
| `mysql_schema.sql` | Source of truth for the schema. Update when adding tables |
| `config/pricing.php` | Pricing config (reads `.env`) |
| `config/rate_limits.php` | Every abuse limit, name => [max, window], overridable with `RL_*` in `.env` |
| `config/plans.json` | Free vs Premium feature definitions |

## Database access

**There is ONE database on the server, shared by production (`argorobots.com`) and dev (`dev.argorobots.com`).** They are not separate instances. Rows are separated by an `environment` column (`sandbox` / `production`), compared against `current_environment()`.

Consequences:

- Schema changes are run **once**. Applying them "on dev" applies them to production, because it is the same database.
- Any query over a table with an `environment` column must filter on it. Forget it and a sandbox test row is treated as live production data, e.g. emailing a real customer from a test invoice.
- Locally, Laragon has `argo_books` (development) and `argo_books_test` (PHPUnit only). These are the only truly separate databases.

All queries go through the global `$pdo`. PDO is configured with `ATTR_ERRMODE => ERRMODE_EXCEPTION`, default fetch mode `FETCH_ASSOC`, `ATTR_EMULATE_PREPARES => false`. Inside functions, declare `global $pdo;` before use.

- Use prepared statements for anything touching user input. Pass params as an array to `execute([...])`; never concatenate into SQL.
- `$stmt->fetch()` returns `false` when there's no row. Check explicitly.
- Let `PDOException` bubble unless you have a specific user-facing error to return.

**Never create migration files.** Schema changes go in `mysql_schema.sql` plus a chat-message SQL block (CREATE/ALTER statements) for the user to run manually in HeidiSQL. Do not create a `migrations/` folder.

## Email sending

**All transactional email must go through Resend via the SMTP relay.** Never call `mail()` directly without first attempting `create_smtp_mailer()`. Raw `mail()` bypasses Resend, loses deliverability, and silently no-ops on servers without an MTA.

Pattern for new callers outside `email_sender.php`: try SMTP first, fall back to `mail()` only when `create_smtp_mailer()` returns `null`. Reference implementations: `api/invoice/invoice_email_sender.php`, `api/portal/portal-helper.php`.

For local dev, set up MailHog so the fallback path doesn't try to hit a real MTA. See `read-me/setup/Local email setup.md`.

## Payment gateways

**Portal Connect flow** (merchants accepting invoice payments through their own provider account): **Stripe** and **Square** only.

**PayPal portal Connect is disabled.** The "Log in with PayPal" OAuth endpoint refuses to return identity for Business-account tokens, and proper merchant onboarding requires the Partner Referrals API (gated behind Platforms & Marketplaces partner enrollment). All portal-side PayPal handlers (`api/portal/connect.php`, `connect-callback.php`, `checkout.php` `handle_paypal_checkout()`, `process-payment.php` `process_paypal_payment()`) return 503 `PROVIDER_UNSUPPORTED`, and `get_available_payment_methods()` in `api/portal/portal-helper.php` deliberately omits PayPal even when `paypal_merchant_id` is set. The desktop app hides the PayPal Connect button.

**PayPal IS still used for the SaaS subscription flow** (Argo Books Premium billing on argorobots.com): separate, working integration with its own webhook handler (`webhooks/paypal-subscription.php`), plan IDs, and checkout. Do not touch SaaS-subscription PayPal code when working on portal features.

## Header / footer loading

The header and footer are **server-side PHP includes**, already in the DOM on first paint. Each page renders them with `<?php include __DIR__ . '/resources/header/header.php'; ?>` and the matching `resources/footer/footer.php` (path depth varies, e.g. `/../../resources/...` from nested pages). There is no client-side injection: the old jQuery `.load()` mechanism (empty `#includeHeader`/`#includeFooter` divs filled from `resources/header/index.html`) is gone, and those HTML files no longer exist.

`resources/scripts/main.js` (loaded in `<head>`) is small and vanilla. It only:
- detects the base path for local Laragon subfolder installs vs production root (`getBasePath()` / `BASE_PATH`),
- populates the account avatar in the already-rendered header via `fetch` to `community/get_avatar_info.php`.

Consequences:
- Header/footer markup changes go in `header.php` / `footer.php` directly. New site-wide footer links (e.g. a new compare or guide page) are added there; there is no `fixLinks` URL-rewriting step anymore.
- Internal links inside header/footer use a `$base` prefix so they resolve under the local subfolder mount; keep that pattern when adding links.

## CSS / theming

All colors come from CSS variables in `resources/styles/custom-colors.css`. Don't hardcode hex values. Dark mode uses `[data-theme="dark"]` selectors. Shared admin styles in `admin/common-style.css` already cover `.stat-card`, `.table-container`, `.chart-container`, `.section-tabs`, `.modal`, etc. for both themes; only add `[data-theme="dark"]` overrides for custom components those defaults don't reach.

## Preserving scroll on filter reload

Admin pages with filter pills that reload the page (period selectors, source pills, range buttons) use a shared `sessionStorage.scrollPosition` pattern so the reload doesn't jump back to the top. When adding a new filter, extend the existing handler's selector on that page rather than writing a parallel script. URL anchors (`#section-id`) do NOT solve this; they only change where the jump lands. Reference implementations: `admin/referral-links/index.php`, `admin/website-stats/index.php`, `admin/users/index.php`, `admin/crons/index.php`.

## Server access

**There is no shell on the server.** No SSH, no cPanel Terminal. Never hand over a command to "run on the server" and never write a tool whose only entry point is a shell prompt.

What is available:

- **cPanel > Cron Jobs** is the only way to execute a script server-side. Set a schedule a minute or two out, let it fire, then restore or delete the entry. It runs through the shell, so `php_sapi_name() === 'cli'` and CLI guards still pass.
- **HeidiSQL** for all SQL. Schema changes and any one-off queries go to the user as a copy-pasteable block.
- **Local Laragon** for anything genuinely interactive. Reproduce there first; the server is not a debugging environment.

**Cron mail is not configured, so stdout goes nowhere.** Never treat `echo` as a way to report anything, and don't add one. No cron script echoes.

## Cron scripts

`admin/crons/` is where cron results are read. **It is the only channel.** There is no cron mail and nobody tails the log files, so a cron that does not write to `cron_runs` is invisible, whatever else it logs.

Required for every cron:

- Wrap the run in `cron_run_start($pdo, '<name>')` / `cron_run_finish($pdo, $runId, 'ok'|'error', $msg)` from `cron/lib/run_tracker.php`, and record what it did with `cron_metric_incr()` (a running count) or `cron_metric_set()` (a value such as a total or a mode). All fourteen scripts do this; it is the only universal convention.
- **Put the numbers in `cron_metric_incr()`, not in prose.** The admin page renders metrics as tiles, so counts recorded there are readable at a glance and comparable across runs. The optional 4th argument to `cron_run_finish()` is for a short summary line shown under "Last run detail"; it lands in the `error_message` column either way, so on an `ok` run keep it to a summary and not an error.
- **Report failures through `cron_runs` too**, including ones that happen before the main work starts. An early `exit` that skips `cron_run_start` leaves no trace on the admin page and looks identical to the cron never firing.
- Add a `$cronConfig` entry in `admin/crons/index.php` with the metric labels, or the page has nothing to render.
- Add a section to `read-me/Cron jobs.md` with the crontab line.
- CLI guard at the top (`php_sapi_name()`), so the script cannot be triggered over HTTP.
- `error_log()` for anything worth diagnosing later.

Situational, not required: a `flock` lock file (5 of 11, for scripts where overlapping runs would double-process), a `--dry-run` flag (3 of 11, worth it for anything that sends email or money), and a daily log in `cron/logs/` (6 of 11).

## Tests

PHPUnit suite lives in `/tests/`. Run with `./vendor/bin/phpunit` from the project root. Requires a separate `argo_books_test` database (creds in `.env.testing`). The deploy workflow does not run tests; they're a local / pre-commit guardrail only.

## Git workflow

Commit directly to `main`. Don't branch-first or park changes on a feature branch unless asked.

## "What's New" page

For end users, plain language, no jargon. Include only user-visible changes; skip refactors, dependency updates, and other developer-facing work. Frame as benefits ("Faster invoice loading", not "Optimized SQL query").

**Never make bugs sound scary.** Accounting software has to feel rock-solid. Avoid words like *crash*, *bug*, *broken*, *error*, *lost*, *corrupted*, *vulnerability*. Either skip the entry or rephrase as a positive:

- "Fixed a bug where invoices could be lost" → skip, or "More reliable invoice saving"
- "Patched a security vulnerability" → skip entirely

## Documentation style

When writing markdown docs (in `read-me/` or anywhere else in the repo), don't use em dashes (the `—` character). Use a comma, a colon, or a period instead, to match the project's house style. Don't substitute a regular hyphen either: that reads as a compound-word marker, not a clause break.

## Explaining technical work

Every technical explanation ends with an `## In simple terms` section as the very last thing in the response. This applies to all technical explanations: code changes, debugging, architecture, data analysis, "why is X happening" answers. Not just the complicated ones.

The reader is a programmer, so don't dumb it down too much. Standard technical vocabulary (SQL, HTTP headers, indexes, caching, regex) needs no translation or analogy. What the section strips is project-specific detail: file paths, function names, table and column names, and anything that assumes familiarity with this codebase.

Think "explaining it to a developer who has never seen this repo", not "explaining it to a non-technical person". Keep it short, a few sentences to a short paragraph, and cover what happened and why it matters rather than restating the mechanics.

If a response has no technical content (a quick yes/no, or a basic question), skip it.

## Security

- Admin requires TOTP 2FA. Secret stored in `admin_users.two_factor_secret`
- Sensitive portal data is AES-256-GCM encrypted via `portal_encrypt()` / `portal_decrypt()` in `db_connect.php`
- `.htaccess` blocks direct access to `.env`, `.sql`, log files

## Detailed docs

The `read-me/` directory has authoritative reference docs that are kept current. Don't duplicate their content here:

- `read-me/Tool page standards.md`: conventions for the free tools under `/tools/`, including which currencies each tool offers and why
- `read-me/Cron jobs.md`: every scheduled cron and its frequency
- `read-me/Deployment.md`: how `.github/workflows/deploy.yml` ships code to the server
- `read-me/Email outreach.md`: outreach pipeline behavior
- `read-me/Admin guide.md`: payment processor fees, sandbox vs production modes, key rotation, admin-account scripts
- `read-me/setup/Payment provider setup.md`: Stripe / PayPal / Square provider config
- `read-me/testing/Payment provider testing.md`: sandbox testing procedures
- `read-me/testing/First-run install tracking.md`: how the install funnel attributes back to the originating ad click, why your own machine deduplicates itself, and the end-to-end test procedure
- `read-me/setup/Local email setup.md`: MailHog setup
- `read-me/setup/Google Ads campaign setup.md`: Google Ads campaign + UET/gtag wiring
- `read-me/procedures/Refund block response procedure.md`: what to do when the refund safety check fires
