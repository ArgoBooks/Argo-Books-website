<?php
require_once __DIR__ . '/../../../resources/icons.php';
require_once __DIR__ . '/../../../partials/code-block.php';
// The published limit has to be the enforced one, so read it from the same config.
require_once __DIR__ . '/../../../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../')->safeLoad();
require_once __DIR__ . '/../../../config/rate_limits.php';
require_once __DIR__ . '/../../../api/v1/lib/ratelimit.php';
$pageTitle = 'API Overview';
$pageDescription = 'The Argo Books API lets your app send sales, expenses, customers, suppliers, products, categories and refunds into a merchant\'s books, with their permission.';
$currentPage = 'overview';
$pageCategory = 'api';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>The Argo Books API lets your application send accounting data into a merchant's books. It lives at <code>https://argorobots.com/v1</code>.</p>

            <h2>The One Thing to Understand First</h2>
            <p>Argo Books is desktop software. A merchant's books live in a file on their own machine, not on our servers. So this API is an <strong>inbound queue</strong>, not a copy of their accounts.</p>
            <p>When you create an object here, it is a <em>proposal</em>. It waits until the merchant opens Argo Books, reviews what you sent, and imports it. Three things follow from that, and they will save you time if you design around them from the start:</p>
            <ul>
                <li>Every object has an <code>import.status</code> of <code>pending</code>, <code>imported</code>, or <code>rejected</code>. Poll it to find out what happened to your data.</li>
                <li>Our ids are not their ids. After an import, <code>import.local_ref</code> holds the id the merchant's copy of Argo Books assigned.</li>
                <li><strong>An object freezes once it is imported.</strong> Update and delete return <code>409 object_not_pending</code>. The merchant already has a copy, so changing the original here would leave two versions of one fact. To correct something after the fact, push a correcting object.</li>
            </ul>
            <p>There is no fixed timeline for step two. A merchant who opens Argo Books weekly will import your data weekly. <code>GET /v1/account</code> reports how much of yours is still waiting.</p>

            <h2>Getting Started</h2>
            <p>Ask the merchant for a key. They create one in Argo Books under <strong>Settings</strong>, then <strong>Integrations</strong>, then <strong>Argo Books API</strong>. Keys start with <code>ab_</code>. See <a href="authentication.php" class="link">Authentication</a>.</p>

            <p>Confirm it works:</p>
            <?= argo_code_tabs([
    'cURL' => ['lang' => 'bash', 'code' => <<<'CODE'
curl https://argorobots.com/v1/account \
  -H "Authorization: Bearer ab_..."
CODE],
    'PHP' => ['lang' => 'php', 'code' => <<<'CODE'
$ch = curl_init('https://argorobots.com/v1/account');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ab_...'],
]);

$account = json_decode(curl_exec($ch), true);
echo $account['id'];
CODE],
    'C#' => ['lang' => 'csharp', 'code' => <<<'CODE'
using var http = new HttpClient();
http.DefaultRequestHeaders.Authorization =
    new AuthenticationHeaderValue("Bearer", "ab_...");

var account = await http.GetFromJsonAsync<JsonElement>(
    "https://argorobots.com/v1/account");

Console.WriteLine(account.GetProperty("id").GetString());
CODE],
    'JavaScript' => ['lang' => 'js', 'code' => <<<'CODE'
const res = await fetch("https://argorobots.com/v1/account", {
  headers: { Authorization: "Bearer ab_..." },
});

const account = await res.json();
console.log(account.id);
CODE],
    'Python' => ['lang' => 'python', 'code' => <<<'CODE'
import requests

account = requests.get(
    "https://argorobots.com/v1/account",
    headers={"Authorization": "Bearer ab_..."},
).json()

print(account["id"])
CODE],
], null) ?>

            <p>Then send something:</p>
            <?= argo_code_tabs([
    'cURL' => ['lang' => 'bash', 'code' => <<<'CODE'
curl https://argorobots.com/v1/revenue \
  -H "Authorization: Bearer ab_..." \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: order-1042" \
  -d '{
    "description": "Order #1042",
    "amount": 11300,
    "currency": "usd",
    "tax_amount": 1300,
    "occurred_on": "2026-08-14",
    "reference": "1042"
  }'
CODE],
    'PHP' => ['lang' => 'php', 'code' => <<<'CODE'
$payload = json_encode([
    'description' => 'Order #1042',
    'amount'      => 11300,
    'currency'    => 'usd',
    'tax_amount'  => 1300,
    'occurred_on' => '2026-08-14',
    'reference'   => '1042',
]);

$ch = curl_init('https://argorobots.com/v1/revenue');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ab_...',
        'Content-Type: application/json',
        // Derived from your own order id, so a retry cannot record it twice.
        'Idempotency-Key: order-1042',
    ],
]);

$revenue = json_decode(curl_exec($ch), true);
echo $revenue['id'];
CODE],
    'C#' => ['lang' => 'csharp', 'code' => <<<'CODE'
using var http = new HttpClient();
http.DefaultRequestHeaders.Authorization =
    new AuthenticationHeaderValue("Bearer", "ab_...");

using var request = new HttpRequestMessage(
    HttpMethod.Post, "https://argorobots.com/v1/revenue")
{
    Content = JsonContent.Create(new
    {
        description = "Order #1042",
        amount = 11300,
        currency = "usd",
        tax_amount = 1300,
        occurred_on = "2026-08-14",
        reference = "1042",
    }),
};

// Derived from your own order id, so a retry cannot record it twice.
request.Headers.Add("Idempotency-Key", "order-1042");

using var response = await http.SendAsync(request);
var revenue = await response.Content.ReadFromJsonAsync<JsonElement>();

Console.WriteLine(revenue.GetProperty("id").GetString());
CODE],
    'JavaScript' => ['lang' => 'js', 'code' => <<<'CODE'
const res = await fetch("https://argorobots.com/v1/revenue", {
  method: "POST",
  headers: {
    Authorization: "Bearer ab_...",
    "Content-Type": "application/json",
    // Derived from your own order id, so a retry cannot record it twice.
    "Idempotency-Key": "order-1042",
  },
  body: JSON.stringify({
    description: "Order #1042",
    amount: 11300,
    currency: "usd",
    tax_amount: 1300,
    occurred_on: "2026-08-14",
    reference: "1042",
  }),
});

const revenue = await res.json();
console.log(revenue.id);
CODE],
    'Python' => ['lang' => 'python', 'code' => <<<'CODE'
import requests

revenue = requests.post(
    "https://argorobots.com/v1/revenue",
    headers={
        "Authorization": "Bearer ab_...",
        # Derived from your own order id, so a retry cannot record it twice.
        "Idempotency-Key": "order-1042",
    },
    json={
        "description": "Order #1042",
        "amount": 11300,
        "currency": "usd",
        "tax_amount": 1300,
        "occurred_on": "2026-08-14",
        "reference": "1042",
    },
).json()

print(revenue["id"])
CODE],
], null) ?>

            <h2>Conventions</h2>

            <h3>Money is an integer</h3>
            <p>Amounts are in the currency's smallest unit, as on Stripe. <code>1999</code> means 19.99 USD. Zero-decimal currencies such as JPY have no minor unit, so <code>1000</code> means 1000 JPY.</p>
            <p>A decimal is <strong>rejected, not rounded</strong>. Sending <code>19.99</code> returns <code>400 parameter_invalid_amount</code>. Silently rounding somebody's accounting data is not a favour.</p>

            <h3>Dates and times</h3>
            <p>Dates you send are <code>YYYY-MM-DD</code>. Timestamps we return are unix integers, so you never have to guess our timezone.</p>

            <h3>Pagination</h3>
            <p>Cursor-based. <code>limit</code> is 1 to 100 and defaults to 10. Pass <code>starting_after=&lt;id&gt;</code> for the next page or <code>ending_before=&lt;id&gt;</code> for the previous one. Lists are newest first, and every list has <code>has_more</code>.</p>
            <p>There is no offset parameter, deliberately. The merchant's copy of Argo Books drains this queue while you write to it, and an offset would silently skip rows.</p>

            <h3>Expansion</h3>
            <p>Reference fields hold an id by default. Pass <code>expand[]=customer</code> to get the whole object instead. Expenses and revenue also accept <code>expand[]=line_items</code>. One level only.</p>

            <h3>Idempotency</h3>
            <p>Every create requires an <code>Idempotency-Key</code> header. Retry with the same key and you get the original response back with <code>Idempotent-Replayed: true</code>, for 24 hours. Reuse a key with a different body and you get <code>409 idempotency_key_reused</code>, because that is a bug on your side rather than a retry.</p>

            <h3>Versioning</h3>
            <p>Send <code>Argo-Version: 2026-08-18</code> to pin. Omit it to track the current version. An unrecognised value is a <code>400</code> rather than a silent fallback to something you did not ask for.</p>

            <h3>Rate limits</h3>
            <p><?= rate_limit_max('api_v1_per_minute') ?> requests per <?= api_rate_limit_window_phrase() ?> per key. Every response carries <code>X-RateLimit-Limit</code>, <code>X-RateLimit-Remaining</code> and <code>X-RateLimit-Reset</code>. Over the limit returns <code>429</code> with <code>Retry-After</code>.</p>

            <h3>Server-side only</h3>
            <p>The API answers no CORS preflight, and <code>OPTIONS</code> returns <code>405</code>. A secret key must never be in a browser, and refusing cross-origin requests is the cheapest way to stop that happening by accident.</p>

            <h3>Request ids</h3>
            <p>Every response carries a <code>Request-Id</code> header, and every error repeats it in the body. Quote it in a support email and we can find the exact request.</p>

            <div class="page-navigation">
                <a href="../integrations/stripe-integration.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; Stripe Integration</span>
                </a>
                <a href="authentication.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Authentication &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
