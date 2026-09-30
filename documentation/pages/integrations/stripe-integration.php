<?php
require_once __DIR__ . '/../../../resources/icons.php';
$pageTitle = 'Stripe Integration';
$pageDescription = 'Connect your Stripe account to Argo Books to automatically import your revenue, processing fees, tax, discounts, customers, and refunds with a read-only key.';
$currentPage = 'stripe-integration';
$pageCategory = 'integrations';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>Connect your Stripe account to Argo Books to automatically import your revenue, processing fees, tax, discounts, customers, and refunds. You connect with a read-only key, so Argo can read your Stripe activity but can never move money.</p>

            <h2>Create a Read-Only Stripe Key</h2>
            <p>In your Stripe dashboard, turn on Test mode first if you want to try it safely. Go to <strong>Developers</strong>, then <strong>API keys</strong>. Click <strong>Create restricted key</strong>. Choose "Providing this key to a third-party application", name it "Argo Books", or whatever you would like. Check "Customise permissions" and grant <strong>Read</strong> access to Balance transactions, Charges, and Payouts (leave everything else at None). Create the key and copy the value (it starts with <code>rk_</code>).</p>

            <h2>Connect It in Argo Books</h2>
            <p>Open <strong>Settings</strong>, then <strong>Integrations</strong>. On the Stripe card, paste your key and click <strong>Connect</strong>. Argo validates it and shows Connected.</p>

            <h2>Sync Your Activity</h2>
            <p>Click <strong>Sync now</strong> on the Stripe card, or use the banner on the Revenue page. Argo shows a summary (revenue and fees) for you to confirm before anything is imported. Nothing is imported until you confirm.</p>

            <h2>What Gets Imported</h2>
            <p>Each Stripe charge becomes a revenue entry with the product, the customer (created automatically), sales tax, and any discount. Processing fees are recorded as expenses linked to the sale. Refunds mark the original sale as returned. Payouts are remembered so that when you later import your bank statement, the matching deposit is skipped and your revenue is never double-counted.</p>

            <h2>Good to Know</h2>
            <ul>
                <li>Revenue is recorded gross (the fee is a separate expense), so your books stay standard and accurate.</li>
                <li>Sync is on-demand and always reviewed, never automatic.</li>
                <li>A sync can be undone in one step.</li>
            </ul>

            <div class="page-navigation">
                <a href="../features/history-modal.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; Version History</span>
                </a>
                <a href="../api/overview.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">API Overview &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
