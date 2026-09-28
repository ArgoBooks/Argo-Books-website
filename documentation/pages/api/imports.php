<?php
require_once __DIR__ . '/../../../resources/icons.php';
require_once __DIR__ . '/../../../partials/code-block.php';
$pageTitle = 'API Imports';
$pageDescription = 'How data you send through the Argo Books API reaches a merchant\'s books: the pending queue, import batches, reverts, and rejections.';
$currentPage = 'imports';
$pageCategory = 'api';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>This page covers what happens to data after you send it. Nothing goes straight into the merchant's books. Each object waits until the merchant opens Argo Books, reviews it, and either imports it or rejects it. Below: the statuses an object moves through, how to check them, and how to build your integration so the wait does not cause problems.</p>

            <h2>The Lifecycle</h2>
            <table>
                <thead><tr><th>Status</th><th>Meaning</th></tr></thead>
                <tbody>
                    <tr><td><code>pending</code></td><td>Waiting for the merchant. You can still update or delete it.</td></tr>
                    <tr><td><code>imported</code></td><td>In their books. Frozen here. <code>import.local_ref</code> holds the id their copy assigned.</td></tr>
                    <tr><td><code>rejected</code></td><td>The merchant looked at it and declined it.</td></tr>
                </tbody>
            </table>

            <p>Check where your data stands:</p>
            <?= argo_code_tabs([
    'cURL' => ['lang' => 'bash', 'code' => <<<'CODE'
curl "https://argorobots.com/v1/revenue?import_status=pending&limit=100" \
  -H "Authorization: Bearer ab_..."
CODE],
    'PHP' => ['lang' => 'php', 'code' => <<<'CODE'
$url = 'https://argorobots.com/v1/revenue?import_status=pending&limit=100';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ab_...'],
]);

$page = json_decode(curl_exec($ch), true);
echo count($page['data']), ' still waiting';
CODE],
    'C#' => ['lang' => 'csharp', 'code' => <<<'CODE'
using var http = new HttpClient();
http.DefaultRequestHeaders.Authorization =
    new AuthenticationHeaderValue("Bearer", "ab_...");

var page = await http.GetFromJsonAsync<JsonElement>(
    "https://argorobots.com/v1/revenue?import_status=pending&limit=100");

var waiting = page.GetProperty("data").GetArrayLength();
Console.WriteLine($"{waiting} still waiting");
CODE],
    'JavaScript' => ['lang' => 'js', 'code' => <<<'CODE'
const res = await fetch(
  "https://argorobots.com/v1/revenue?import_status=pending&limit=100",
  { headers: { Authorization: "Bearer ab_..." } },
);

const page = await res.json();
console.log(`${page.data.length} still waiting`);
CODE],
    'Python' => ['lang' => 'python', 'code' => <<<'CODE'
import requests

page = requests.get(
    "https://argorobots.com/v1/revenue",
    params={"import_status": "pending", "limit": 100},
    headers={"Authorization": "Bearer ab_..."},
).json()

print(len(page["data"]), "still waiting")
CODE],
], null) ?>

            <p>Or get the whole picture at once, which is cheaper than seven list calls:</p>
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
            <?= argo_code_block(<<<'CODE'
{
  "id": "acct_7a73294c25e3a6de3d4bb998",
  "object": "account",
  "pending": {
    "customer": 3,
    "supplier": 0,
    "category": 1,
    "product": 0,
    "expense": 12,
    "revenue": 40,
    "refund": 2
  },
  "last_import_at": 1787091029
}
CODE, 'json') ?>

            <h2>Import Batches</h2>
            <p>When the merchant approves an import, Argo Books creates a batch that claims every approved object in one transaction. You will normally only read these, but the endpoints are public because seeing them makes your own state easier to reason about.</p>
            <table>
                <thead><tr><th>Endpoint</th><th>Does</th></tr></thead>
                <tbody>
                    <tr><td><code>GET /v1/import_batches</code></td><td>List batches, newest first. Filter with <code>status=open|completed|reverted</code>.</td></tr>
                    <tr><td><code>GET /v1/import_batches/&lt;id&gt;</code></td><td>Retrieve one, including per-type counts.</td></tr>
                    <tr><td><code>POST /v1/import_batches</code></td><td>Claim objects. This is what Argo Books calls on approval.</td></tr>
                    <tr><td><code>POST /v1/import_batches/&lt;id&gt;/revert</code></td><td>Release a batch, returning its objects to <code>pending</code>.</td></tr>
                </tbody>
            </table>
            <p>A batch is all-or-nothing. If any object in it is not claimable, the whole batch rolls back with <code>409 object_not_claimable</code>. A half-imported batch would leave the merchant's books and this queue disagreeing about what was taken, which is a far worse problem than a failed import.</p>

            <h2>Imports Can Be Undone</h2>
            <p>The merchant can undo an import in Argo Books like any other action. When they do, the batch is reverted and its objects go back to <code>pending</code>.</p>
            <p>So <code>imported</code> is not necessarily permanent, and an object you saw as imported yesterday can legitimately be pending today. If you mirror status on your side, re-read it rather than assuming it only moves forward.</p>

            <h2>Rejection Is Not Deletion</h2>
            <p>Two different things, and the difference is who acted:</p>
            <ul>
                <li><code>DELETE /v1/&lt;resource&gt;/&lt;id&gt;</code> is <strong>you</strong> withdrawing something you sent. Use it when you pushed by mistake.</li>
                <li><code>POST /v1/&lt;resource&gt;/&lt;id&gt;/reject</code> records that <strong>the merchant</strong> saw it and said no.</li>
            </ul>
            <p>A rejection is worth surfacing to your user. It usually means your mapping is producing something they do not want, and it is the only signal you get.</p>

            <h2>Designing Around the Delay</h2>
            <p>Some practical advice, learned from the shape of the system rather than invented:</p>
            <ul>
                <li><strong>Do not block on import.</strong> There is no timeline. A merchant might open Argo Books once a week.</li>
                <li><strong>Set <code>reference</code> to your own document number.</strong> It is shown to the merchant during review and carried into their books, so it is how a human connects a row in their accounts to a row in your system.</li>
                <li><strong>Push referenced objects first.</strong> Create the customer, then the revenue that points at it. A dangling reference is rejected immediately.</li>
                <li><strong>Correct with a new object, not an edit.</strong> Once something is imported it is frozen, so build for that from the start rather than discovering it at <code>409</code>.</li>
                <li><strong>Use a stable <code>Idempotency-Key</code>.</strong> Deriving it from your own order id means a retry after a timeout cannot create a second copy of a sale.</li>
            </ul>

            <div class="page-navigation">
                <a href="resources.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; Resources</span>
                </a>
                <a href="webhooks.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Webhooks &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
