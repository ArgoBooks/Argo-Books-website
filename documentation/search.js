// Documentation search: page index + init.
// The search engine is the shared resources/scripts/site-search.js (SiteSearch).
// NOTE: keep this list in sync when adding or removing documentation pages.
// One entry per file under documentation/pages/, matching sidebar.php.

(function () {
    const pages = [
            // Getting Started
            { id: 'system-requirements', title: 'System Requirements', category: 'Getting Started', folder: 'getting-started', keywords: 'windows macos linux requirements specs hardware disk space ram memory' },
            { id: 'installation', title: 'Installation Guide', category: 'Getting Started', folder: 'getting-started', keywords: 'install download setup installer wizard run appimage smartscreen' },
            { id: 'quick-start', title: 'Quick Start Tutorial', category: 'Getting Started', folder: 'getting-started', keywords: 'tutorial getting started begin first steps currency company sidebar category product supplier sample company' },
            { id: 'version-comparison', title: 'Free vs. Paid Version', category: 'Getting Started', folder: 'getting-started', keywords: 'free paid premium upgrade pricing features comparison limits biometric login fingerprint payroll forecasting priority support' },
            { id: 'redeem-license-key', title: 'Redeem a License Key', category: 'Getting Started', folder: 'getting-started', keywords: 'license key redeem activate activation code serial subscription id receipt lost key resend promo promotional retailer voucher enter a key verify key premium transfer another computer move license reinstall invalid key wrong device expired' },

            // Core Features
            { id: 'dashboard', title: 'Dashboard', category: 'Core Features', folder: 'features', keywords: 'dashboard home overview widgets customize edit layout stat card chart quick actions setup checklist low stock upcoming due dates overdue rentals drag resize' },
            { id: 'analytics', title: 'Analytics', category: 'Core Features', folder: 'features', keywords: 'analytics charts tabs products geographic map performance customers taxes returns losses refunds date range column line area scatter zoom export' },
            { id: 'predictive-analytics', title: 'Predictive Analytics', category: 'Core Features', folder: 'features', keywords: 'ai predictive analytics insights forecast revenue expenses profit scenario conservative baseline optimistic anomaly detection seasonal patterns recommendations confidence premium' },
            { id: 'report-generator', title: 'Report Generator', category: 'Core Features', folder: 'features', keywords: 'report generate pdf png jpeg chart template layout designer income statement balance sheet cash flow general ledger aging tax summary sales by product multi-page' },
            { id: 'sales-tracking', title: 'Expense/Revenue Tracking', category: 'Core Features', folder: 'features', keywords: 'expense revenue transaction order tracking add quantity price shipping tax line items receipt' },
            { id: 'invoicing', title: 'Invoicing & Payments', category: 'Core Features', folder: 'features', keywords: 'invoice payment billing stripe square online payment portal processing fee credit card recurring draft sent viewed paid overdue refunded' },
            { id: 'payroll', title: 'Payroll', category: 'Core Features', folder: 'features', keywords: 'payroll canada cra employees pay run cpp ei income tax td1 quebec qpp qpip deduction tables net pay remittance premium' },
            { id: 'payroll-year-end', title: 'Payroll Year-End', category: 'Core Features', folder: 'features', keywords: 'payroll year end t4 t4 summary xml t619 rl-1 revenu quebec roe record of employment remittance amended cancelled slips filing' },
            { id: 'bank-statement-import', title: 'Bank Statement Import', category: 'Core Features', folder: 'features', keywords: 'bank statement import csv excel pdf categorize ai rules merchant catch up bookkeeping create transactions' },
            { id: 'bank-matching', title: 'Bank Matching', category: 'Core Features', folder: 'features', keywords: 'bank matching statement match verify books csv excel unmatched suggested ignored duplicate missing from statement calendar' },
            { id: 'rental', title: 'Rental Management', category: 'Core Features', folder: 'features', keywords: 'rental booking reservation reserve check out availability calendar equipment return deposit refund extra charges late fee damage daily weekly monthly rate maintenance overdue' },
            { id: 'customers', title: 'Customer Management', category: 'Core Features', folder: 'features', keywords: 'customer client profile contact history notes active inactive banned crm' },
            { id: 'product-management', title: 'Product Management', category: 'Core Features', folder: 'features', keywords: 'products services categories expense products revenue products sku unit price cost price tax rate item type reorder point overstock' },
            { id: 'suppliers', title: 'Supplier Management', category: 'Core Features', folder: 'features', keywords: 'supplier vendor purchase source contact manage' },
            { id: 'inventory', title: 'Inventory Management', category: 'Core Features', folder: 'features', keywords: 'inventory stock levels tracking track inventory add item initial quantity reorder point low stock overstock out of stock alert adjustments add remove set transfer quantity cost of goods sold' },
            { id: 'locations', title: 'Locations', category: 'Core Features', folder: 'features', keywords: 'locations warehouse store storage facility factory distribution center stock location transfer address' },
            { id: 'purchase-orders', title: 'Purchase Orders', category: 'Core Features', folder: 'features', keywords: 'purchase order supplier restock receive items approved on order partially received' },
            { id: 'returns', title: 'Returns', category: 'Core Features', folder: 'features', keywords: 'return refund exchange expense return customer return reason defective damaged undo' },
            { id: 'lost-damaged', title: 'Lost & Damaged Inventory', category: 'Core Features', folder: 'features', keywords: 'lost damaged stolen expired shrinkage write off loss value inventory tracking' },
            { id: 'receipts', title: 'Receipt Management', category: 'Core Features', folder: 'features', keywords: 'receipt digital attach microsoft lens export archive' },
            { id: 'receipt-scanning', title: 'AI Receipt Scanning', category: 'Core Features', folder: 'features', keywords: 'ai receipt scanning ocr photo image pdf extract vendor date line items totals tax premium' },
            { id: 'spreadsheet-import', title: 'AI Spreadsheet Import', category: 'Core Features', folder: 'features', keywords: 'import excel spreadsheet xlsx csv column mapping confidence validation customers suppliers products expenses revenue' },
            { id: 'spreadsheet-export', title: 'Spreadsheet Export', category: 'Core Features', folder: 'features', keywords: 'export excel spreadsheet xlsx backup data currency conversion chart google sheets' },
            { id: 'history-modal', title: 'Version History', category: 'Core Features', folder: 'features', keywords: 'version history audit log timeline changes added modified deleted undone redone search filter' },

            // Integrations
            { id: 'stripe-integration', title: 'Stripe Integration', category: 'Integrations', folder: 'integrations', keywords: 'stripe integration connect restricted key read only sync charges payouts fees refunds customers import revenue' },

            // Developer API
            { id: 'overview', title: 'API Overview', category: 'Developer API', folder: 'api', keywords: 'api developer v1 rest inbound queue pending imported idempotency pagination expansion versioning rate limit request id' },
            { id: 'authentication', title: 'API Authentication', category: 'Developer API', folder: 'api', keywords: 'api key authentication bearer x-api-key scopes read write revoke test mode sandbox' },
            { id: 'resources', title: 'API Resources', category: 'Developer API', folder: 'api', keywords: 'api resources customers suppliers categories products expenses revenue refunds line items fields endpoints filters' },
            { id: 'imports', title: 'API Imports', category: 'Developer API', folder: 'api', keywords: 'api import batch pending imported rejected superseded revert claim local ref merchant approval' },
            { id: 'webhooks', title: 'API Webhooks', category: 'Developer API', folder: 'api', keywords: 'api webhook endpoint signing secret signature hmac retry events event log delivery' },
            { id: 'errors', title: 'API Errors', category: 'Developer API', folder: 'api', keywords: 'api error codes status 400 401 403 404 409 429 500 retry idempotency rate limit parameter invalid' },

            // Reference
            { id: 'how-numbers-are-calculated', title: 'How Numbers Are Calculated', category: 'Reference', folder: 'reference', keywords: 'revenue profit net tax sales tax shipping refund refunds cash basis accrual currency calculations formula gross subtotal total discount fee invoice status pending conversion sales by product' },
            { id: 'supported-currencies', title: 'Supported Currencies', category: 'Reference', folder: 'reference', keywords: 'currency currencies usd eur gbp cad aud exchange rate convert historical pending' },
            { id: 'supported-languages', title: 'Supported Languages', category: 'Reference', folder: 'reference', keywords: 'language languages english spanish french german chinese arabic localization translation' },
            { id: 'keyboard_shortcuts', title: 'Keyboard Shortcuts', category: 'Reference', folder: 'reference', keywords: 'keyboard shortcuts hotkeys ctrl cmd command mac k save as quick actions full screen fullscreen f11 escape esc title bar report designer undo redo duplicate delete move align' },

            // Security
            { id: 'encryption', title: 'Encryption', category: 'Security', folder: 'security', keywords: 'encryption aes-256 gcm pbkdf2 security protect data recovery key' },
            { id: 'password', title: 'Password Protection', category: 'Security', folder: 'security', keywords: 'password protection biometric login fingerprint face windows hello touch id mac keychain linux auto-lock locked out recovery security' },
            { id: 'backups', title: 'Regular Backups', category: 'Security', folder: 'security', keywords: 'backup argobk export restore save data loss protection cloud' }
    ];

    document.addEventListener("DOMContentLoaded", function () {
        if (typeof SiteSearch === "undefined") return;
        const input = document.getElementById("docSearchInput");
        const basePath = input ? (input.dataset.basePath || "") : "";
        const items = pages.map(function (p) {
            return {
                id: p.id,
                title: p.title,
                category: p.category,
                keywords: p.keywords,
                url: basePath + "pages/" + p.folder + "/" + p.id + ".php"
            };
        });
        new SiteSearch({ inputId: "docSearchInput", resultsId: "searchResults", items: items });
    });
})();
