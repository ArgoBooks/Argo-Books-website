<?php
require_once __DIR__ . '/../../../resources/icons.php';
require_once __DIR__ . '/../../../partials/code-block.php';
require_once __DIR__ . '/../../../api/v1/lib/events.php';

$pageTitle = 'API Webhooks';
$pageDescription = 'Receive a signed notification when a merchant imports, rejects, or undoes data your integration sent to Argo Books.';
$currentPage = 'webhooks';
$pageCategory = 'api';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>Webhooks tell you when the merchant imports, rejects, or undoes the import of data you sent. Polling <code>import_status</code> works, but a webhook means you find out within a minute instead of on your next sweep.</p>

            <h2>Event Types</h2>
            <table>
                <thead><tr><th>Type</th><th>Fires when</th></tr></thead>
                <tbody>
<?php
$rows = [];
foreach (API_EVENT_TYPES as $type) {
    [$object, $verb] = explode('.', $type);
    if ($object === 'import_batch') {
        $rows[$type] = $verb === 'completed'
            ? 'A merchant imported a batch of objects.'
            : 'A merchant undid an import. Its objects are pending again.';
        continue;
    }
    $rows[$type] = $verb === 'imported'
        ? 'A ' . $object . ' you sent reached the merchant\'s books.'
        : 'A merchant reviewed a ' . $object . ' you sent and declined it.';
}
foreach ($rows as $type => $when): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($type); ?></code></td>
                        <td><?php echo htmlspecialchars($when); ?></td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>

            <p>An import fires one event per object <em>and</em> one <code>import_batch.completed</code>. Subscribe to whichever granularity suits you; most integrations want the per-object events, since a batch may also contain fifty objects from somebody else's app.</p>

            <h2>Registering an Endpoint</h2>
            <?= argo_code_tabs([
    'cURL' => ['lang' => 'bash', 'code' => <<<'CODE'
curl https://argorobots.com/v1/webhook_endpoints \
  -H "Authorization: Bearer ab_..." \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: hook-1" \
  -d '{
    "url": "https://example.com/hooks/argo",
    "enabled_events": ["revenue.imported", "revenue.rejected"],
    "description": "Production"
  }'
CODE],
    'PHP' => ['lang' => 'php', 'code' => <<<'CODE'
$payload = json_encode([
    'url'            => 'https://example.com/hooks/argo',
    'enabled_events' => ['revenue.imported', 'revenue.rejected'],
    'description'    => 'Production',
]);

$ch = curl_init('https://argorobots.com/v1/webhook_endpoints');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ab_...',
        'Content-Type: application/json',
        'Idempotency-Key: hook-1',
    ],
]);

$endpoint = json_decode(curl_exec($ch), true);

// The only time signing_secret is ever returned. Store it now.
echo $endpoint['signing_secret'];
CODE],
    'C#' => ['lang' => 'csharp', 'code' => <<<'CODE'
using var http = new HttpClient();
http.DefaultRequestHeaders.Authorization =
    new AuthenticationHeaderValue("Bearer", "ab_...");

using var request = new HttpRequestMessage(
    HttpMethod.Post, "https://argorobots.com/v1/webhook_endpoints")
{
    Content = JsonContent.Create(new
    {
        url = "https://example.com/hooks/argo",
        enabled_events = new[] { "revenue.imported", "revenue.rejected" },
        description = "Production",
    }),
};
request.Headers.Add("Idempotency-Key", "hook-1");

using var response = await http.SendAsync(request);
var endpoint = await response.Content.ReadFromJsonAsync<JsonElement>();

// The only time signing_secret is ever returned. Store it now.
Console.WriteLine(endpoint.GetProperty("signing_secret").GetString());
CODE],
    'JavaScript' => ['lang' => 'js', 'code' => <<<'CODE'
const res = await fetch("https://argorobots.com/v1/webhook_endpoints", {
  method: "POST",
  headers: {
    Authorization: "Bearer ab_...",
    "Content-Type": "application/json",
    "Idempotency-Key": "hook-1",
  },
  body: JSON.stringify({
    url: "https://example.com/hooks/argo",
    enabled_events: ["revenue.imported", "revenue.rejected"],
    description: "Production",
  }),
});

const endpoint = await res.json();

// The only time signing_secret is ever returned. Store it now.
console.log(endpoint.signing_secret);
CODE],
    'Python' => ['lang' => 'python', 'code' => <<<'CODE'
import requests

endpoint = requests.post(
    "https://argorobots.com/v1/webhook_endpoints",
    headers={
        "Authorization": "Bearer ab_...",
        "Idempotency-Key": "hook-1",
    },
    json={
        "url": "https://example.com/hooks/argo",
        "enabled_events": ["revenue.imported", "revenue.rejected"],
        "description": "Production",
    },
).json()

# The only time signing_secret is ever returned. Store it now.
print(endpoint["signing_secret"])
CODE],
], null) ?>

            <p>Omit <code>enabled_events</code>, or pass <code>["*"]</code>, to receive everything.</p>

            <p>The response contains <code>signing_secret</code>. <strong>That is the only time it is returned.</strong> Store it before you close the terminal.</p>

            <p>The URL must be public HTTPS. Plain HTTP, <code>localhost</code>, and anything resolving to a private or link-local address are refused, because otherwise this endpoint would let anyone with a key aim signed requests at our internal network.</p>

            <p>Manage endpoints with <code>GET</code>, <code>POST</code> and <code>DELETE</code> on <code>/v1/webhook_endpoints</code> and <code>/v1/webhook_endpoints/&lt;id&gt;</code>. Pass <code>status</code> as <code>enabled</code> or <code>disabled</code> to pause one without deleting it.</p>

            <h2>The Payload</h2>
            <?= argo_code_block(<<<'CODE'
{
  "id": "evt_37dfeee7e5d47d2eac5346b9",
  "object": "event",
  "type": "revenue.imported",
  "created": 1787091029,
  "data": {
    "object": {
      "id": "rev_136ace96eaf4d428c8248b8f",
      "object": "revenue",
      "description": "Order #1042",
      "amount": 11300,
      "currency": "USD",
      "occurred_on": "2026-08-14",
      "import": {
        "status": "imported",
        "batch": "imb_21cf047240398e8c8f1c661f",
        "local_ref": "REV-2026-00087"
      }
    }
  }
}
CODE, 'json') ?>
            <p><code>data.object</code> is the full object exactly as the API would return it, captured at the moment of the event.</p>

            <h2>Verifying the Signature</h2>
            <p>Every delivery carries a header:</p>
            <?= argo_code_block(<<<'CODE'
Argo-Signature: t=1787091029,v1=5f2c...9ab1
CODE, 'http') ?>

            <p><code>v1</code> is <code>HMAC-SHA256(secret, "&lt;t&gt;.&lt;raw body&gt;")</code>. Sign the <strong>raw body bytes</strong>, before any JSON parsing; re-serialising first will not match.</p>

            <?= argo_code_tabs([
    'PHP' => ['lang' => 'php', 'code' => <<<'CODE'
function argo_signature_is_valid(string $body, string $header, string $secret): bool
{
    if (!preg_match('/t=(\d+),v1=([0-9a-f]+)/', $header, $m)) {
        return false;
    }

    $expected = hash_hmac('sha256', $m[1] . '.' . $body, $secret);

    // hash_equals, not ===, so the comparison cannot be timed.
    return hash_equals($expected, $m[2])
        && abs(time() - (int) $m[1]) <= 300;
}
CODE],
    'C#' => ['lang' => 'csharp', 'code' => <<<'CODE'
static bool ArgoSignatureIsValid(string body, string header, string secret)
{
    var match = Regex.Match(header, @"t=(\d+),v1=([0-9a-f]+)");
    if (!match.Success) return false;

    var timestamp = long.Parse(match.Groups[1].Value);

    using var hmac = new HMACSHA256(Encoding.UTF8.GetBytes(secret));
    var expected = Convert.ToHexString(
        hmac.ComputeHash(Encoding.UTF8.GetBytes($"{timestamp}.{body}"))).ToLowerInvariant();

    // Fixed-time comparison, so the check cannot be timed.
    var signatureOk = CryptographicOperations.FixedTimeEquals(
        Encoding.UTF8.GetBytes(expected),
        Encoding.UTF8.GetBytes(match.Groups[2].Value));

    var age = Math.Abs(DateTimeOffset.UtcNow.ToUnixTimeSeconds() - timestamp);
    return signatureOk && age <= 300;
}
CODE],
    'JavaScript' => ['lang' => 'js', 'code' => <<<'CODE'
import crypto from "node:crypto";

export function argoSignatureIsValid(body, header, secret) {
  const match = /t=(\d+),v1=([0-9a-f]+)/.exec(header);
  if (!match) return false;

  const expected = crypto
    .createHmac("sha256", secret)
    .update(`${match[1]}.${body}`)
    .digest("hex");

  // timingSafeEqual, so the comparison cannot be timed. It throws when the
  // lengths differ, which is why they are checked first.
  const signatureOk =
    expected.length === match[2].length &&
    crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(match[2]));

  const age = Math.abs(Date.now() / 1000 - Number(match[1]));
  return signatureOk && age <= 300;
}
CODE],
    'Python' => ['lang' => 'python', 'code' => <<<'CODE'
import hashlib
import hmac
import re
import time


def argo_signature_is_valid(body: str, header: str, secret: str) -> bool:
    match = re.search(r"t=(\d+),v1=([0-9a-f]+)", header)
    if not match:
        return False

    expected = hmac.new(
        secret.encode(),
        f"{match.group(1)}.{body}".encode(),
        hashlib.sha256,
    ).hexdigest()

    # compare_digest, so the comparison cannot be timed.
    if not hmac.compare_digest(expected, match.group(2)):
        return False

    return abs(time.time() - int(match.group(1))) <= 300
CODE],
], 'Verifying a delivery') ?>

            <p>Check the timestamp as well as the signature. The timestamp is inside the signed material, so it cannot be edited, and rejecting anything older than a few minutes stops a captured delivery being replayed at you later.</p>

            <h2>Retries</h2>
            <p>Any 2xx is success. Anything else is retried up to six times over about 15 hours: immediately, then after 1 minute, 5 minutes, 30 minutes, 2 hours, and 12 hours.</p>
            <p>An endpoint whose last 20 deliveries all failed is disabled automatically. Re-enable it with <code>POST /v1/webhook_endpoints/&lt;id&gt;</code> and <code>{"status":"enabled"}</code> once your receiver is fixed.</p>
            <p>Redirects are never followed. The signature belongs to the URL the merchant approved.</p>

            <h2>Writing a Receiver That Behaves</h2>
            <ul>
                <li><strong>Reply 200 immediately, work afterwards.</strong> The delivery times out after 10 seconds. Queue the event and return.</li>
                <li><strong>Expect duplicates.</strong> A delivery that succeeds after your server has already processed it but before the response reaches us will arrive again. Deduplicate on the event <code>id</code>.</li>
                <li><strong>Do not assume order.</strong> Retries mean an older event can land after a newer one. Use <code>created</code> if sequence matters.</li>
                <li><strong>Remember imports can be undone.</strong> An <code>import_batch.reverted</code> can follow an <code>imported</code> event for the same object.</li>
            </ul>

            <h2>Catching Up After an Outage</h2>
            <p>You do not need us to replay anything. Every event is readable from the log for 90 days:</p>
            <?= argo_code_tabs([
    'cURL' => ['lang' => 'bash', 'code' => <<<'CODE'
curl "https://argorobots.com/v1/events?type=revenue.imported&limit=100" \
  -H "Authorization: Bearer ab_..."
CODE],
    'PHP' => ['lang' => 'php', 'code' => <<<'CODE'
$url = 'https://argorobots.com/v1/events?type=revenue.imported&limit=100';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ab_...'],
]);

$events = json_decode(curl_exec($ch), true)['data'];
echo count($events), ' events';
CODE],
    'C#' => ['lang' => 'csharp', 'code' => <<<'CODE'
using var http = new HttpClient();
http.DefaultRequestHeaders.Authorization =
    new AuthenticationHeaderValue("Bearer", "ab_...");

var page = await http.GetFromJsonAsync<JsonElement>(
    "https://argorobots.com/v1/events?type=revenue.imported&limit=100");

var events = page.GetProperty("data").GetArrayLength();
Console.WriteLine($"{events} events");
CODE],
    'JavaScript' => ['lang' => 'js', 'code' => <<<'CODE'
const res = await fetch(
  "https://argorobots.com/v1/events?type=revenue.imported&limit=100",
  { headers: { Authorization: "Bearer ab_..." } },
);

const { data: events } = await res.json();
console.log(`${events.length} events`);
CODE],
    'Python' => ['lang' => 'python', 'code' => <<<'CODE'
import requests

events = requests.get(
    "https://argorobots.com/v1/events",
    params={"type": "revenue.imported", "limit": 100},
    headers={"Authorization": "Bearer ab_..."},
).json()["data"]

print(len(events), "events")
CODE],
], null) ?>
            <p>It paginates like any other list, so you can walk back to wherever you stopped.</p>

            <div class="page-navigation">
                <a href="imports.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; Imports</span>
                </a>
                <a href="errors.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Errors &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
