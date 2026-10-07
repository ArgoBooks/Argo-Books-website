<?php
// invoice-generator/doc-config.php
//
// Single source of truth for the document-type configuration that drives the
// shared generator engine. The same editor surface (_fragment.php), scripts
// (scripts/*), and styles (tool.css) power two tools:
//   - the free invoice generator  (/invoice-generator/)
//   - the free estimate generator (/estimate-generator/)
//
// Each tool's index.php picks a config via invgen_doc_config($type) and:
//   - reads it in PHP to swap page copy / aria labels and to conditionally
//     render the invoice-only money fields (Payment Terms, Amount Paid,
//     Balance Due), and
//   - mirrors the JS-relevant subset to window.DOC_CONFIG (see
//     invgen_doc_config_js()) so the client modules know the document type.
//
// The 'invoice' entry MUST reproduce the engine's historical hardcoded values
// exactly (page copy, the 'argobooks.invoiceGenerator.draft' storage key, the
// 'invgen' event prefix, the 'INVOICE' document title). When window.DOC_CONFIG
// is absent (the invoice standalone page relies on JS fallbacks, and every
// niche page embeds _fragment.php without a $doc_type), behavior is unchanged.

if (!function_exists('invgen_doc_config')) {

    /**
     * Return the configuration for a document type. Unknown types fall back to
     * 'invoice' so a misconfigured caller degrades to the original tool.
     */
    function invgen_doc_config(string $type): array
    {
        $configs = [
            'invoice' => [
                'type'                => 'invoice',
                // Page metadata (used by the standalone index.php)
                'page_title'          => 'Free Invoice Generator | Argo Books',
                'page_description'    => 'Free online invoice generator. No signup required. Download PDF or Word.',
                'schema_name'         => 'Free Invoice Generator',
                'canonical_url'       => 'https://argorobots.com/invoice-generator/',
                // Editor surface copy + accessibility labels
                'hero_title'          => 'Free Invoice Generator',
                'hero_tagline'        => 'Create professional invoices with one click. No signup required. Download as PDF or Word.',
                'banner_text'         => 'Want to handle payments, refunds, and track everything?',
                'toolbar_aria'        => 'Invoice tools',
                'template_aria'       => 'Invoice template',
                'editor_aria'         => 'Invoice editor',
                'number_aria'         => 'Invoice number',
                'totals_aria'         => 'Invoice totals',
                'modal_heading'       => 'Your invoice is downloading',
                // Default editable labels (mirrored to JS)
                'document_title'      => 'INVOICE',
                'due_date_label'      => 'Due Date',
                // Placeholder for the recipient (Bill To) address box. Echoed raw so the &#10; line breaks survive; values are developer- defined, never user input.
                'recipient_placeholder' => 'Client name&#10;Address&#10;City, State ZIP',
                // Extra default-label overrides merged over the engine defaults
                // (keyed by data-label name). Empty for the invoice baseline.
                'label_overrides'     => [],
                // Field visibility
                'show_payment_terms'  => true,   // "Payment Terms" meta row
                'show_payment_fields' => true,   // "Amount Paid" + "Balance Due" totals rows
                'show_signature'      => false,  // optional acceptance/signature block
                // Export + analytics + persistence (mirrored to JS)
                'filename_prefix'     => 'invoice',
                'storage_key'         => 'argobooks.invoiceGenerator.draft',
                'event_prefix'        => 'invgen',
                'tool_path'           => '/invoice-generator/',
                // Conversion CTA attribution
                'utm_source'          => 'invoice-generator',
                'default_ref'         => 'invgen-tool',
                'cta_href'            => '/features/invoicing/',
            ],
            'estimate' => [
                'type'                => 'estimate',
                'page_title'          => 'Free Estimate Generator | Argo Books',
                'page_description'    => 'Free online estimate generator. No signup required. Download PDF or Word.',
                'schema_name'         => 'Free Estimate Generator',
                'canonical_url'       => 'https://argorobots.com/estimate-generator/',
                'hero_title'          => 'Free Estimate Generator',
                'hero_tagline'        => 'Create professional estimates with one click. No signup required. Download as PDF or Word.',
                'banner_text'         => 'Want to turn estimates into invoices and get paid faster?',
                'toolbar_aria'        => 'Estimate tools',
                'template_aria'       => 'Estimate template',
                'editor_aria'         => 'Estimate editor',
                'number_aria'         => 'Estimate number',
                'totals_aria'         => 'Estimate totals',
                'modal_heading'       => 'Your estimate is downloading',
                'document_title'      => 'ESTIMATE',
                'due_date_label'      => 'Valid Until',
                'recipient_placeholder' => 'Client name&#10;Address&#10;City, State ZIP',
                'label_overrides'     => [],
                'show_payment_terms'  => false,
                'show_payment_fields' => false,
                'show_signature'      => true,
                'filename_prefix'     => 'estimate',
                'storage_key'         => 'argobooks.estimateGenerator.draft',
                'event_prefix'        => 'estgen',
                'tool_path'           => '/estimate-generator/',
                'utm_source'          => 'estimate-generator',
                'default_ref'         => 'estgen-tool',
                'cta_href'            => '/features/invoicing/',
            ],
            'purchase-order' => [
                'type'                => 'purchase-order',
                'page_title'          => 'Free Purchase Order Generator | Argo Books',
                'page_description'    => 'Free online purchase order generator. No signup required. Download PDF or Word.',
                'schema_name'         => 'Free Purchase Order Generator',
                'canonical_url'       => 'https://argorobots.com/purchase-order-generator/',
                'hero_title'          => 'Free Purchase Order Generator',
                'hero_tagline'        => 'Create professional purchase orders with one click. No signup required. Download as PDF or Word.',
                'banner_text'         => 'Want to track expenses, bills, and orders in one place?',
                'toolbar_aria'        => 'Purchase order tools',
                'template_aria'       => 'Purchase order template',
                'editor_aria'         => 'Purchase order editor',
                'number_aria'         => 'Purchase order number',
                'totals_aria'         => 'Purchase order totals',
                'modal_heading'       => 'Your purchase order is downloading',
                'document_title'      => 'PURCHASE ORDER',
                'due_date_label'      => 'Delivery Date',
                // The recipient of a PO is the supplier you're ordering from.
                'recipient_placeholder' => 'Vendor name&#10;Address&#10;City, State ZIP',
                // "Bill To" becomes "Vendor", and a PO's own number is the PO number, so that meta field becomes a free Reference.
                'label_overrides'     => [
                    'billTo'         => 'Vendor',
                    'poNumber'       => 'Reference',
                    'signatureLabel' => 'Authorized by',
                ],
                // POs commonly state payment terms; they are not a payment
                // record, so Amount Paid / Balance Due are dropped.
                'show_payment_terms'  => true,
                'show_payment_fields' => false,
                'show_signature'      => true,
                'filename_prefix'     => 'purchase-order',
                'storage_key'         => 'argobooks.purchaseOrderGenerator.draft',
                'event_prefix'        => 'pogen',
                'tool_path'           => '/purchase-order-generator/',
                'utm_source'          => 'purchase-order-generator',
                'default_ref'         => 'pogen-tool',
                // A PO is about buying, so the pitch points at expense tracking.
                'cta_href'            => '/features/expense-revenue-tracking/',
            ],
        ];

        return $configs[$type] ?? $configs['invoice'];
    }

    /**
     * The JS-relevant subset of a config, ready to inject as window.DOC_CONFIG.
     * Keys are camelCased to match how the client modules read them.
     * showPaymentTerms / showPaymentFields gate, respectively, the Payment
     * Terms meta line and the Amount Paid + Balance Due totals rows in the Word
     * output (kept separate because a PO keeps terms but drops paid/balance).
     */
    function invgen_doc_config_js(array $dc): array
    {
        return [
            'type'              => $dc['type'],
            'documentTitle'     => $dc['document_title'],
            'dueDateLabel'      => $dc['due_date_label'],
            'filenamePrefix'    => $dc['filename_prefix'],
            'storageKey'        => $dc['storage_key'],
            'eventPrefix'       => $dc['event_prefix'],
            'toolPath'          => $dc['tool_path'],
            'showPaymentTerms'  => (bool) $dc['show_payment_terms'],
            'showPaymentFields' => (bool) $dc['show_payment_fields'],
            'labelOverrides'    => $dc['label_overrides'] ?? [],
        ];
    }
}
