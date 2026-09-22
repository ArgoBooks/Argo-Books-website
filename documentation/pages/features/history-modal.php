<?php
require_once __DIR__ . '/../../../resources/icons.php';
$pageTitle = 'Version History';
$pageDescription = 'Learn how to use the Version History modal in Argo Books to review every change made to your company data, and to search and filter past events.';
$currentPage = 'history-modal';
$pageCategory = 'features';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>The Version History modal gives you a complete timeline of every change made to your company data. It is a read-only record: you can review and search everything that has happened, but changes are made and reversed elsewhere in Argo Books, not from this modal.</p>

            <h2>Opening Version History</h2>
            <p>Click the clock icon in the header bar, labelled <strong>Version History</strong>, to open the modal.</p>

            <h2>Event Timeline</h2>
            <p>The modal displays a chronological list of all changes. Each event shows:</p>
            <ul>
                <li><strong>Timestamp:</strong> When the change was made</li>
                <li><strong>Action Type:</strong> The kind of change: Added, Modified, Deleted, Undone, or Redone</li>
                <li><strong>Entity Details:</strong> The type and name of the item that was changed (e.g., a customer, product, or expense)</li>
                <li><strong>Description:</strong> A summary of what happened</li>
            </ul>

            <h3>Undo and Redo Entries</h3>
            <p>Undo and redo are done with the undo and redo buttons in Argo Books, not from this modal. When you use them, the reversal is recorded here as its own entry in the timeline, listed newest first alongside everything else. So a change and its later reversal both appear, each with its own timestamp.</p>

            <h2>Searching and Filtering History</h2>
            <p>Use the search bar to find events by name or description. You can also narrow the list by the type of item that changed, such as customers or products, or by the kind of action: Added, Modified, or Deleted. A clear button resets the search and both filters at once.</p>

            <div class="info-box">
                <strong>Tip:</strong> Version History is a permanent record rather than a control panel. Nothing you do in this modal changes your data, so you can search and filter freely without any risk of altering your books.
            </div>

            <div class="page-navigation">
                <a href="send-to-accountant.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; Send to Accountant</span>
                </a>
                <a href="../integrations/stripe-integration.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Stripe Integration &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
