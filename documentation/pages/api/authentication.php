<?php
require_once __DIR__ . '/../../../resources/icons.php';
require_once __DIR__ . '/../../../partials/code-block.php';
$pageTitle = 'API Authentication';
$pageDescription = 'How to authenticate against the Argo Books API using a merchant-issued key, what the scopes mean, and why there is no test mode.';
$currentPage = 'authentication';
$pageCategory = 'api';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>There is one credential type: a key the merchant creates and gives you.</p>

            <h2>Getting a Key</h2>
            <p>You cannot create a key yourself. The merchant opens Argo Books, goes to <strong>Settings</strong>, then <strong>Integrations</strong>, then <strong>Argo Books API</strong>, turns it on, and clicks <strong>Create key</strong>. They name it after your app and send you the value.</p>
            <p>Keys look like <code>ab_</code> followed by 48 hex characters. The merchant sees the full value exactly once. We store only a SHA-256 fingerprint, so if they lose it nobody can recover it, and they simply revoke and create another.</p>
            <p>Ask for your own key rather than sharing one. The merchant can then switch your app off without breaking everything else they have connected.</p>

            <h2>Sending It</h2>
            <p>Either header works:</p>
            <?= argo_code_block(<<<'CODE'
Authorization: Bearer ab_...
X-Api-Key: ab_...
CODE, 'http', 'Either header') ?>

            <h2>Scopes</h2>
            <p>A key carries <code>read</code>, <code>write</code>, or both.</p>
            <ul>
                <li><code>read</code> covers every <code>GET</code>.</li>
                <li><code>write</code> covers <code>POST</code> and <code>DELETE</code>.</li>
            </ul>
            <p>A request outside your scopes returns <code>403 insufficient_scope</code>. If your integration only reports on data, ask for a read-only key; a merchant is far more likely to say yes.</p>

            <h2>Revocation</h2>
            <p>The merchant can revoke a key at any moment from the same screen. It stops working on the next request, which returns <code>401 api_key_revoked</code>. Handle that as a permanent failure and tell your user to reconnect, rather than retrying.</p>

            <h2>There Is No Test Mode</h2>
            <p>This is a deliberate difference from most payment APIs, and worth explaining so you do not go looking for a sandbox key that does not exist.</p>
            <p>Test credentials exist when an API hands out keys before anyone has an account, or when it simulates something external you cannot safely poke in production. Neither applies here. The account holder issues the key, and nothing you send reaches anyone's books without them approving it by hand.</p>
            <p><strong>If you want somewhere safe to build against, create your own Argo Books company.</strong> It is free, it is completely isolated, and it behaves identically to a real one. That is a better sandbox than a flag on a key.</p>

            <h2>Keeping the Key Safe</h2>
            <ul>
                <li>Keep it on your server. Never put it in a web page or browser code: the API answers no CORS preflight precisely so a key cannot end up in browser JavaScript.</li>
                <li>Do not commit it. The <code>ab_</code> prefix exists so automated secret scanners recognise one that leaks into a public repository.</li>
                <li>Do not log it. Log the <code>Request-Id</code> from the response instead; it identifies the request without exposing the credential.</li>
                <li>If a key is ever exposed, ask the merchant to revoke it. Rotation is a 10 second job for them.</li>
            </ul>

            <div class="page-navigation">
                <a href="overview.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; API Overview</span>
                </a>
                <a href="resources.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Resources &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
