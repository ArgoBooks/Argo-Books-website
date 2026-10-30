<?php
// Served at /documentation/agents.md for AI coding agents, as plain Markdown they can read in one fetch.
require_once __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/../')->safeLoad();
require_once __DIR__ . '/../config/rate_limits.php';
require_once __DIR__ . '/../api/v1/lib/ratelimit.php';

header('Content-Type: text/markdown; charset=UTF-8');

$perMinute = rate_limit_max('api_v1_per_minute') . ' requests per ' . api_rate_limit_window_phrase();
$perDay = number_format(rate_limit_max('api_v1_creates_per_day')) . ' creates per ' . api_rate_limit_window_phrase('api_v1_creates_per_day');
$docs = 'https://argorobots.com/documentation/pages/api';

echo <<<MD
# Add Argo Books to a project

These are instructions for an AI coding agent. Follow them in order to connect the project you are working in to the Argo Books API, which sends revenue, expenses, customers, suppliers, products, categories and refunds into a business's books.

## 1. Get an API key from the user

You cannot create a key yourself. Ask the user for one and tell them how to get it:

- They need Argo Books Premium, which the API is part of.
- In the Argo Books desktop app, they open **Settings**, then **Integrations**, then **Argo Books API**, turn it on, click **Create key**, and name it after this project.
- The key starts with `ab_` and is shown once.

Ask for a key with only the scopes the project needs: `read` for reporting, `write` for sending data, or both.

There is no test mode and no sandbox key. If the user wants somewhere safe to try things, they can create a separate company in Argo Books for free and make the key there.

## 2. Store the key

- Put it in the project's environment as `ARGO_BOOKS_API_KEY`, for example in `.env`.
- Make sure `.env` is in `.gitignore` before writing the key to it. Never commit the key and never log it.
- Use it from server code only. The API refuses browser requests, so a key in front-end code will not work and would leak.

## 3. Check the key works

```bash
curl https://argorobots.com/v1/account \
  -H "Authorization: Bearer \$ARGO_BOOKS_API_KEY"
```

A `200` with an `id` means it works. A `401` means the key is wrong or revoked, so ask the user for a new one rather than retrying.

## 4. Build the integration

The base URL is `https://argorobots.com/v1`. Send the key as `Authorization: Bearer <key>`.

Argo Books is desktop software and the business's books live on their own computer, so this API is a queue. Design around these rules from the start:

- **Nothing reaches the books until the user imports it.** Every object starts with `import.status` of `pending` and becomes `imported` or `rejected` when the user reviews it in the app. That can be days later.
- **Imported objects are frozen.** Update and delete return `409 object_not_pending`. To correct something, send a correcting object.
- **Amounts are integers in the currency's smallest unit.** `1999` is 19.99 USD. A decimal is rejected, not rounded.
- **Amounts are gross.** `tax_amount` is the tax included in `amount`.
- **Dates are `YYYY-MM-DD`.**
- **Every create needs an `Idempotency-Key` header.** Derive it from the project's own id for the record, such as `order-1042`, so a retry cannot record it twice. Retry with the same key.
- **Retry only `api_error` and `rate_limit_error`**, with backoff. Switch on the error's `code`, never its `message`.
- **Limits:** {$perMinute} per key, and {$perDay} per company.

Every resource has the same endpoints. `<resource>` is one of `customers`, `suppliers`, `categories`, `products`, `expenses`, `revenue` or `refunds`.

| Endpoint | Does |
|---|---|
| `POST /v1/<resource>` | Create |
| `GET /v1/<resource>` | List, newest first |
| `GET /v1/<resource>/<id>` | Retrieve |
| `POST /v1/<resource>/<id>` | Update, while pending |
| `DELETE /v1/<resource>/<id>` | Withdraw, while pending |

To hear when the user imports or rejects something, register a webhook instead of polling. Payloads are signed with the `Argo-Signature` header.

## 5. Read the details you need

- [Overview]({$docs}/overview.php): conventions, pagination, versioning
- [Authentication]({$docs}/authentication.php): keys and scopes
- [Resources]({$docs}/resources.php): every object and field
- [Imports]({$docs}/imports.php): what happens after you send something
- [Webhooks]({$docs}/webhooks.php): events and verifying signatures
- [Errors]({$docs}/errors.php): every error code and what to do about it

When you are done, tell the user that what the project sends waits in Argo Books until they import it.

MD;
