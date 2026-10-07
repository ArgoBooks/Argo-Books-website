<?php
// compare/compare-lib.php
//
// Small helpers shared by compare-page.php and the data files under
// compare/data/.

/**
 * Every comparison page, slug => the label other pages link to it by.
 *
 * Held once here, so renaming a page does not mean finding every page that links to it.
 */
function argo_compare_index(): array
{
    return [
        'argo-books-vs-quickbooks'     => 'Argo Books vs. QuickBooks',
        'argo-books-vs-wave'           => 'Argo Books vs. Wave',
        'argo-books-vs-freshbooks'     => 'Argo Books vs. FreshBooks',
        'argo-books-vs-xero'           => 'Argo Books vs. Xero',
        'argo-books-vs-spreadsheet'    => 'Argo Books vs. spreadsheets',
        // Payroll providers. Priced per employee rather than per plan, so they
        // are compared on /payroll/ as well as here.
        'argo-books-vs-wagepoint'          => 'Argo Books vs. Wagepoint',
        'quickbooks-payroll-alternatives'  => 'QuickBooks Payroll alternatives',
        'payworks-alternatives'            => 'Payworks alternatives',
        'bonsai-alternatives'          => 'Bonsai alternatives',
        'gnucash-alternatives'         => 'GnuCash alternatives',
        'honeybook-alternatives'       => 'HoneyBook alternatives',
        'invoice2go-alternatives'      => 'Invoice2go alternatives',
        'manager-io-alternatives'      => 'Manager.io alternatives',
        'odoo-accounting-alternatives' => 'Odoo accounting alternatives',
        'sage-50-alternatives'         => 'Sage 50 alternatives',
        'square-invoices-alternatives' => 'Square Invoices alternatives',
        'zipbooks-alternatives'        => 'ZipBooks alternatives',
        'zoho-books-alternatives'      => 'Zoho Books alternatives',
    ];
}

/**
 * One cell of the side-by-side table.
 *
 * 'yes' and 'no' render the tick and the cross; any other string renders as the
 * grey "partial" pill carrying that text ("Limited", "Add-on", "Paid tiers").
 */
function argo_compare_cell(string $value): string
{
    if ($value === 'yes') {
        return '<span class="check-yes">' . svg_icon('check', 18) . '</span>';
    }
    if ($value === 'no') {
        return '<span class="check-no">' . svg_icon('x', 18) . '</span>';
    }
    return '<span class="check-partial">' . $value . '</span>';
}
