# Referral links API

Lists and adds referral links from a script, so they don't have to be typed into `/admin/referral-links/` one at a time.

It is separate from the marketing agent's endpoint on purpose. The agent can only make links starting `ag-`, which keeps it away from the links you make yourself. This API can add any code, so it has its own token, and the agent never sees it.

## Setup

Add this line to the server's `.env`, in cPanel's File Manager. The value is already in `.env - production` on your computer, so copy the line from there:

```
REFERRAL_API_TOKEN="<64 random characters>"
```

The endpoint answers 503 `NOT_CONFIGURED` until the token is at least 32 characters long. Production and dev share one database, so links added through either site appear on both.

## Listing links

```
GET https://argorobots.com/api/referral-links/
Authorization: Bearer <REFERRAL_API_TOKEN>
```

Returns every link, newest first, with its visits and its installs for that site's environment:

```json
{"ok": true, "links": [{"source_code": "x", "name": "X posts", "category": "social", "target_url": "https://argorobots.com/downloads/", "is_active": 1, "created_at": "2026-10-30 16:48:50", "visits": 12, "installs": 1}]}
```

## Adding links

```
POST https://argorobots.com/api/referral-links/
Authorization: Bearer <REFERRAL_API_TOKEN>
Content-Type: application/json

{"links": [{"source_code": "x", "name": "X posts", "category": "social", "target_url": "https://argorobots.com/downloads/"}]}
```

One request takes 1 to 50 links.

| Field | Required | Notes |
|---|---|---|
| `source_code` | Yes | Letters, digits, hyphens and underscores, up to 100 characters. |
| `name` | Yes | What the admin page shows. |
| `category` | No | A key from `referral_categories()`. Left out, it is worked out from the code's prefix, such as `youtube-`. |
| `target_url` | No | A page on `https://argorobots.com`. Left out, it is the home page. |

Each link gets its own result, in the order sent, so one bad link does not stop the rest:

| `status` | Meaning |
|---|---|
| `created` | The link was added. `url` is the address to share. |
| `exists` | The code was already there and nothing changed. `url` uses the saved target. |
| `refused` | The link was not valid. `message` says why. |

Because a code that exists is left alone, a script can be run again safely. To change or delete a link, use the admin page.

Visits to a `?source=` code are recorded even before the code has a link, and they appear on the admin page once the link exists.

## Where the code lives

- `api/referral-links/index.php` checks the token and answers requests.
- `referral_links.php` holds the rules, and the insert that the admin page and the marketing agent also use.
