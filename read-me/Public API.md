# Public API (v1)

The Argo Books public API lets third-party developers push accounting data into a merchant's books. It lives at `https://argorobots.com/v1`.

## Who can use it

The API is Premium only. Creating a connection or a key needs a valid license, and every `/v1` request checks that the subscription recorded on `api_accounts.subscription_id` is still active, returning 403 `premium_required` when it is not. The check runs per request rather than only when a key is made, so a key minted during one paid month stops working when the subscription ends. The desktop app refreshes `subscription_id` whenever it calls `/api/developer/account` with its license, since a renewal or a re-redeemed key can move the license to a new subscription row.

## What it is, and what it is not

It is an **ingest store**, not a copy of anyone's books.

Argo Books is desktop-first. The books live in a `.argo` file on the merchant's machine, and the only server-side copy is `mobile_sync_snapshots.ciphertext`, which is encrypted on the desktop and unreadable here. So an object created through this API is a **proposal**: it sits in a queue until the merchant opens Argo Books, reviews it, and imports it.

Three consequences that shape everything else:

1. Every resource row carries an import lifecycle: `pending`, `imported`, `rejected`.
2. API ids (`cus_`, `exp_`, ...) are not the ids the desktop assigns. The local id comes back in `import.local_ref` after an import.
3. An object can only be changed while it is `pending`. Once imported, the merchant owns a copy and mutating the original would create two versions of one fact. Update and delete return 409 `object_not_pending`.

## Resources

| Segment | Object | Notes |
|---|---|---|
| `/v1/customers` | `customer` | |
| `/v1/suppliers` | `supplier` | |
| `/v1/categories` | `category` | `kind` is `expense` or `revenue` |
| `/v1/products` | `product` | |
| `/v1/expenses` | `expense` | has `line_items` |
| `/v1/revenue` | `revenue` | has `line_items`, plus `fee_amount` for a platform's withheld cut |
| `/v1/refunds` | `refund` | always points at a `revenue` |

Plus `/v1/account` and `/v1/import_batches`.

Every resource supports create, retrieve, update (`POST` to the id, as on Stripe), delete (soft), and list. Expenses and revenue also support `GET|POST /v1/<resource>/<id>/line_items`.

## Conventions

- **Money is an integer in the currency's smallest unit.** `1999` means 19.99 USD. A decimal is rejected rather than rounded. The zero-decimal currency list is in `ArgoMoney` on the desktop and mirrored in the schema comments; both sides have to agree or a 1000 JPY sale imports as 10 JPY.
- **Dates** are `YYYY-MM-DD`. Timestamps in responses are unix integers.
- **Pagination** is cursor-based: `limit` (1 to 100, default 10), `starting_after`, `ending_before`. Lists are newest first. Offsets are deliberately not offered, because the desktop drains this queue while developers write to it.
- **Expansion**: `expand[]=customer`, `expand[]=line_items`. One level only.
- **Idempotency**: every create requires an `Idempotency-Key` header. Replays within 24 hours return the original response with `Idempotent-Replayed: true`.
- **Versioning**: `Argo-Version: 2026-08-18`. Absent means current. An unknown value is a 400 rather than a silent fallback.
- **Rate limit**: 120 requests per minute per key, reported in `X-RateLimit-*`.
- **No CORS.** A secret key must never be in a browser, so no preflight is answered and `OPTIONS` returns 405.

### Error envelope

```json
{
  "error": {
    "type": "invalid_request_error",
    "code": "parameter_missing",
    "message": "Missing required parameter 'name'.",
    "param": "name",
    "doc_url": "https://argorobots.com/documentation/pages/api/errors.php#parameter_missing",
    "request_id": "req_9f3ed3dc2e61733488a1db04"
  }
}
```

`type` is the class a client switches on: `authentication_error`, `invalid_request_error`, `rate_limit_error`, `idempotency_error`, `api_error`. `code` is the stable specific reason. `request_id` appears on every response as a header too, and identifies the request in our logs.

## Authentication

One credential type: a **merchant-issued API key**. The merchant generates it in Argo Books Settings and hands it to the developer.

- Format `ab_` plus 48 hex characters. Only the SHA-256 is stored, so a leaked database does not yield working keys, and a lost key is replaced rather than recovered.
- The `ab_` prefix exists for automated leak detection (GitHub secret scanning, log redaction), not for isolation.
- Sent as `Authorization: Bearer ab_...` or `X-Api-Key`.
- Scopes are `read` and `write`.

**There is no test mode.** Stripe needs one because it issues keys before any merchant relationship exists and because it simulates an external card network. Neither applies here: the account holder issues the key, and the merchant review queue already stops bad data reaching the books. A developer who wants a scratch space opens a second Argo Books company, which is free and behaves identically.

The `environment` column on every table is the deploy split (`sandbox` means dev.argorobots.com), not a user-facing test mode. Every query filters on it.

## Import lifecycle

The desktop drives it:

1. `GET /v1/<resource>?import_status=pending` to see what is waiting.
2. The merchant reviews the preview in Argo Books.
3. `POST /v1/import_batches` with the approved ids claims them in one transaction. A half-claimed batch would leave the books and the queue disagreeing, so any object that is not claimable rolls the whole batch back with 409 `object_not_claimable`.
4. `POST /v1/import_batches/<id>/revert` if the merchant undoes the import, returning the objects to `pending`.

A merchant can also decline a single object with `POST /v1/<resource>/<id>/reject`. That is distinct from delete: delete is the developer withdrawing their own push, reject tells the developer their data was seen and refused.

## Webhooks

Events describe what the **merchant** did, never what the developer did. There is deliberately no `<object>.created` event: the developer who created it already knows, and it would be noise on a channel whose whole value is telling them something they could not otherwise learn.

Types are `<object>.imported`, `<object>.rejected`, `import_batch.completed` and `import_batch.reverted`. The full list is `API_EVENT_TYPES` in `api/v1/lib/events.php`, which is also what validates a subscription.

- Endpoints are managed through `/v1/webhook_endpoints`. The signing secret is returned once, at creation.
- Bodies are signed `Argo-Signature: t=<unix>,v1=<hmac-sha256 of "t.body">`. The timestamp is inside the signed material, so a captured delivery cannot be replayed with a fresher one bolted on.
- Delivery is a cron (`cron/api_webhook_delivery.php`), not inline with the request that creates the event. A developer's hanging server must never become a merchant's hanging import.
- Six attempts spanning about 15 hours. An endpoint whose last 20 deliveries all failed is auto-disabled and the merchant re-enables it.
- `/v1/events` is a 90 day log, so an endpoint that was down catches up itself rather than needing us to replay anything.

**Endpoint URLs must be public HTTPS.** The host rules in `api/v1/lib/net.php` are not politeness: without them this endpoint is a server-side request forgery primitive that anyone holding a key can aim at our own network. They:

- resolve A and AAAA records and require EVERY address to be public, so a name answering with one public and one private address is refused
- strip the brackets off an IPv6 literal first, since `https://[::1]/` otherwise passes every check by being neither a parseable IP nor a resolvable name
- unwrap IPv4-mapped IPv6 (`::ffff:127.0.0.1`) before judging it
- fail closed on a name that does not resolve
- run again in the delivery cron immediately before every POST, because a name that resolved publicly at registration can be repointed afterwards

## Documentation

Public developer docs are at `/documentation/pages/api/`, listed on the documentation index and in the sidebar. Six pages: overview, authentication, resources, imports, webhooks, errors.

There is no prettier `/documentation/api` alias, and adding one back would break the pages. Documentation pages link their stylesheets and each other relatively, so serving one at a shallower URL leaves every asset and every next/previous link resolving from the wrong base.

Two of them generate themselves from the code, which is the only reason to trust them a year from now:

- `resources.php` renders its field tables from `api/v1/lib/definitions.php`, so adding a field updates the docs with no second edit.
- `errors.php` holds a row per error code, and `ContractTest` fails the build if the API can emit a code the page does not document. Every live error links to its anchor there, so a missing row is a broken link in a developer's face. That check skips itself while the page is absent, so it enforces nothing until the documentation ships alongside the API.

## Tests

`tests/Unit/PublicApi/` covers validation, the webhook and signing rules, and the wire contract (id format, error envelope, pagination bounds, definition integrity).

`tests/Integration/PublicApi/` covers the parts that fail expensively and cannot be reached by a pure-function test: authentication and scopes, the environment boundary (a key from one environment must not authenticate in the other, and no list may leak across), the idempotency claim (replay, body mismatch, in-flight, and release after a crashed handler), and import-batch atomicity.

`SampleCodeTest` **executes every code sample printed in the documentation**. It starts a PHP server, mints a real key, and runs each sample as written in cURL, PHP, C#, JavaScript and Python, asserting on what it prints. Signature verification samples are driven with four vectors: a genuine signature, a forged one, one correctly signed but too old, and a header that is not a signature.

Two things make it worth having rather than decorative:

- Samples are run verbatim. Only the host and the placeholder key are rewritten, so what executes is the code on the page.
- The assertions check the printed value, not just the exit code. Several samples exited zero while printing `undefined` or a PHP warning, and looked green until the expected shape was asserted.

Run just those with `./vendor/bin/phpunit --group doc-samples`. They add about 15 seconds to the suite, nearly all of it compiling the C# samples, which are built once into a single project and then invoked per sample.

A language that is not installed is skipped rather than failed, so the suite still runs on a machine without dotnet. `.env.testing` must exist: the sample server refuses to start against anything but `argo_books_test`, because the samples create records.

The bootstrap defines `API_TESTING` before loading the lib, which makes `api_json()` throw `ApiResponseSent` instead of ending the process. Without that, every validator would be untestable in process and the alternative would be duplicating validation logic somewhere test-shaped. Two consequences worth knowing before touching that code:

- `api_run_claimed_handler()` treats `ApiResponseSent` as the handler finishing, not failing, and persists the response itself. Read as a crash instead, it would release the claim and let a replay run the handler twice, which is the one outcome the whole mechanism exists to prevent.
- `api_request_body()` accepts `$GLOBALS['__api_test_body']` under the same flag, because `php://input` cannot be written from a test.

## Files

### Server

| Path | Role |
|---|---|
| `api/v1/index.php` | Front controller and router. Every route passes through it, so auth, rate limiting and versioning cannot be skipped |
| `api/v1/lib/bootstrap.php` | Request id, version pinning, body parsing, `api_env()` |
| `api/v1/lib/definitions.php` | The seven resources described as data |
| `api/v1/lib/resource.php` | Generic CRUD engine driven by those definitions |
| `api/v1/lib/validate.php` | Field validation and cross-field invariants |
| `api/v1/lib/auth.php` | Key lookup and scope enforcement |
| `api/v1/lib/idempotency.php` | Claim-then-run replay cache |
| `api/v1/lib/pagination.php` | Cursor paging |
| `api/v1/lib/ratelimit.php` | Fixed-window counters |
| `api/v1/lib/batches.php` | Import batches, revert, reject |
| `api/v1/lib/account.php` | `GET /v1/account` |
| `api/v1/lib/events.php` | Event types, fan-out, signing |
| `api/v1/lib/webhook_endpoints.php` | Endpoint CRUD and the event log |
| `cron/api_webhook_delivery.php` | Signed delivery with backoff |
| `api/developer/*.php` | Control plane: Argo Books creates and revokes keys here, authenticated by its licence identity |

Adding a field is one line in `definitions.php`. Adding a resource is one block.

### Desktop (`ArgoBooks.Core/Services/Integrations/`)

| File | Role |
|---|---|
| `ArgoApiClient.cs` | Both surfaces: control plane and `/v1` |
| `ArgoApiModels.cs` | Wire records and `ArgoMoney` |
| `ArgoApiSyncService.cs` | Preview, import, release |
| `ArgoApiImporter.cs` | Maps API objects into the books |
| `ArgoApiImportCreation.cs` | Single undo/redo for a whole import |

The merchant turns it on, mints and revokes keys, and reviews imports from **Settings, Integrations, Argo Books API** (`SettingsModalViewModel`, `#region Argo Books API`).

The desktop mints itself a key separate from any it hands a developer, so revoking a developer never locks the app out of its own review queue.

The import writes locally first, then claims server-side. That order is deliberate: a local write with no claim can be undone precisely from memory, whereas a claim with no local write would have told a developer their data landed in books that never received it.

## Related

- `read-me/Tool page standards.md` for the free tools, unrelated to this
- `api/portal/` is the customer payment portal, a different thing entirely
