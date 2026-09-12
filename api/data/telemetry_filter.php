<?php

/**
 * Telemetry allowlist filter.
 *
 * Server-side enforcement: rebuilds the uploaded payload from scratch using only
 * the fields listed in the allowlist. Anything not listed is silently dropped
 * before the file is written to admin/data-logs/. Applied to both free and
 * premium uploads, so the on-the-wire shape is identical across tiers.
 */

const TELEMETRY_PLATFORMS = ['Windows', 'macOS', 'Linux', 'Other'];

const TELEMETRY_SESSION_ACTIONS = ['SessionStart', 'SessionEnd'];

const TELEMETRY_EXPORT_TYPES = ['Excel', 'GoogleSheets', 'Pdf', 'Csv', 'Backup'];

const TELEMETRY_API_NAMES = ['Gemini', 'OpenExchangeRates', 'OpenExchangeRatesBatch', 'ReceiptScanProxy'];

const TELEMETRY_ERROR_CATEGORIES = [
    'Unknown', 'Network', 'FileSystem', 'Parsing', 'Validation', 'UI', 'Api',
    'Export', 'Import', 'License', 'Authentication', 'Encryption'
];

// How serious an Error event is. The app has stamped this since v2.0.12; anything
// without it is read as an error by the dashboard rather than guessed at.
const TELEMETRY_ERROR_SEVERITIES = ['Error', 'Warning'];

const TELEMETRY_FEATURE_NAMES = [
    'ReportGenerated',
    'ReceiptScanned',
    'DataImported', 'BackupCreated', 'BackupRestored',
    'InvoiceCreated', 'ExpenseCreated', 'RevenueCreated', 'PaymentRecorded',
    'BankMatchConfirmed',
    'ProductCreated', 'CategoryCreated', 'LocationCreated', 'StockAdjusted',
    'PurchaseOrderCreated', 'ReturnRecorded', 'LostDamagedRecorded',
    'CustomerCreated', 'SupplierCreated',
    'RentalItemCreated', 'RentalRecordCreated',
    'ChartExportedToGoogleSheets', 'ChartExportedToExcel',
    'ThemeChanged', 'LanguageChanged',
    'CompanyCreated', 'ChecklistStepCompleted', 'OnboardingCompleted', 'OnboardingSkipped',
    'SampleCompanyOpened',
    'CompanyCreateOpened', 'ReceiptScanOpened', 'InvoiceCreateOpened',
    'ExpenseCreateOpened', 'RevenueCreateOpened', 'ReportOpened',
    // The import funnel
    'ImportOpened', 'ImportPreviewShown', 'ImportAbandoned', 'ImportFailed',
    'ReceiptScanFailed', 'EmptyStateShown', 'WelcomeShown',
    // The paywall
    'UpgradePromptShown', 'UpgradeModalOpened',
    // Payroll
    'PayRunDrafted',
    'PayRunApproved',
    'PayStubsExported',
    'T4SlipsGenerated',
    'T4XmlGenerated',
    'RoeWorksheetGenerated',
    'RoeXmlGenerated',
    // The dashboard editor.
    'DashboardCustomized',
    'DashboardReset'
];

// Business descriptors on a CompanyProfile event. Free text, not enum-checked: these
// come from a combo box the user can type into, and the point is to learn what people
// actually enter. Length-capped like every other string field.
const TELEMETRY_COMPANY_PROFILE_MAX = 96;

/**
 * Bound a string field: must be a string, length-cap at $maxLen, drop control chars.
 * Returns null if not a usable string.
 */
function telemetry_clean_string($value, int $maxLen = 64): ?string
{
    if (!is_string($value) || $value === '') {
        return null;
    }
    // Drop control characters except tab/newline/CR
    $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
    if ($clean === null || $clean === '') {
        return null;
    }
    return mb_substr($clean, 0, $maxLen);
}

/**
 * Validate a value against an allowed-enum list. Returns "Unknown" if invalid.
 */
function telemetry_validate_enum($value, array $allowed): string
{
    if (is_string($value) && in_array($value, $allowed, true)) {
        return $value;
    }
    return 'Unknown';
}

/**
 * Cast to int with a max bound. Returns null if not numeric.
 */
function telemetry_clean_int($value, int $max = PHP_INT_MAX): ?int
{
    if (!is_numeric($value)) {
        return null;
    }
    $i = (int)$value;
    if ($i < 0) {
        return 0;
    }
    return min($i, $max);
}

/**
 * Validate an ISO 8601 timestamp string; return it back if usable, else server-now.
 */
function telemetry_clean_timestamp($value): string
{
    if (is_string($value)) {
        $ts = strtotime($value);
        if ($ts !== false) {
            return gmdate('Y-m-d\TH:i:s\Z', $ts);
        }
    }
    return gmdate('Y-m-d\TH:i:s\Z');
}

/**
 * Filter a single telemetry event to its allowlist.
 * Returns null if the dataType is unknown.
 */
function filter_telemetry_event(array $event): ?array
{
    $dataType = $event['dataType'] ?? null;
    if (!is_string($dataType)) {
        return null;
    }

    // Base fields for every event type
    $base = [
        'dataId' => telemetry_clean_string($event['dataId'] ?? null, 32),
        'timestamp' => telemetry_clean_timestamp($event['timestamp'] ?? null),
        'dataType' => $dataType,
    ];

    switch ($dataType) {
        // One visit to one screen. Its own type rather than a feature: navigating is not a
        // feature, and mixed into that list it would bury the events that mean something.
        case 'PageView':
            return $base + [
                'pageName' => telemetry_clean_string($event['pageName'] ?? null, 64),
                // Idle-aware, so a page left open contributes nothing to this.
                'activeSeconds' => telemetry_clean_int($event['activeSeconds'] ?? null),
                // Wall clock. A large gap against activeSeconds is someone who walked away
                // on this screen rather than someone stuck on it.
                'durationSeconds' => telemetry_clean_int($event['durationSeconds'] ?? null),
            ];

        case 'Session':
            return $base + [
                'action' => telemetry_validate_enum($event['action'] ?? null, TELEMETRY_SESSION_ACTIONS),
                'durationSeconds' => telemetry_clean_int($event['durationSeconds'] ?? null),
                // Wall clock from launch to quit, so it counts a window left open
                // overnight as use. Kept because every past session is measured that
                // way, but activeSeconds below is the one to chart.
                //
                // Seconds the app was actually being driven: input gaps longer than the
                // client's idle threshold are excluded. Absent on SessionStart and on
                // ends from builds predating the field, where null means "not measured"
                // rather than zero.
                'activeSeconds' => telemetry_clean_int($event['activeSeconds'] ?? null),
                // The screen they were on when they quit.
                'lastPage' => telemetry_clean_string($event['lastPage'] ?? null, 64),
                // Whether the app shut down normally. False marks a SessionEnd the app
                // reconstructed on its next launch after a force-quit, OS restart, or
                // power loss. Absent on SessionStart, and on ends from builds predating
                // the flag, so readers must treat a missing value as clean.
                'clean' => isset($event['clean']) ? (bool)$event['clean'] : null,
            ];

        case 'FeatureUsage':
            return $base + [
                'featureName' => telemetry_validate_enum($event['featureName'] ?? null, TELEMETRY_FEATURE_NAMES),
                // Free-form detail the app attaches to a feature event (checklist
                // step id, import source, chart type). Cleaned, not enum-checked,
                // so new context values don't need a server change to survive.
                'context' => telemetry_clean_string($event['context'] ?? null, 64),
                'durationMs' => telemetry_clean_int($event['durationMs'] ?? null),
            ];

        case 'Error':
            $severity = telemetry_validate_enum($event['severity'] ?? null, TELEMETRY_ERROR_SEVERITIES);
            // "Unknown" is not a severity the app can send. Builds older than v2.0.12
            // omit the field entirely, and the dashboard's rule is that a missing
            // severity means Error rather than a guess from the error code.
            if ($severity === 'Unknown') {
                $severity = 'Error';
            }

            $out = $base + [
                'severity' => $severity,
                'errorCategory' => telemetry_validate_enum($event['errorCategory'] ?? null, TELEMETRY_ERROR_CATEGORIES),
                'errorCode' => telemetry_clean_string($event['errorCode'] ?? null, 128),
                'sourceFile' => telemetry_clean_string($event['sourceFile'] ?? null, 128),
                'lineNumber' => telemetry_clean_int($event['lineNumber'] ?? null),
                'methodName' => telemetry_clean_string($event['methodName'] ?? null, 128),
            ];

            // Warning text is authored by us at the call site, so it is safe to keep and
            // it is the only thing that makes a warning actionable: the code alone rarely
            // says what happened. Error text is an exception's own Message, which we do
            // not control and which can quote a filename, a company name or a server
            // response, so it stays dropped. sourceFile + lineNumber locate an error
            // precisely enough without it.
            if ($severity === 'Warning') {
                $out['message'] = telemetry_clean_string($event['message'] ?? null, 300);
            }

            // Written by us at the call site, so it carries the same guarantee as warning
            // text above. On an error it is often the only field saying what was attempted.
            $out['context'] = telemetry_clean_string($event['context'] ?? null, 200);

            return $out;

        case 'Export':
            return $base + [
                'exportType' => telemetry_validate_enum($event['exportType'] ?? null, TELEMETRY_EXPORT_TYPES),
                'durationMs' => telemetry_clean_int($event['durationMs'] ?? null),
                'fileSize' => telemetry_clean_int($event['fileSize'] ?? null),
            ];

        case 'ApiUsage':
            return $base + [
                'apiName' => telemetry_validate_enum($event['apiName'] ?? null, TELEMETRY_API_NAMES),
                'durationMs' => telemetry_clean_int($event['durationMs'] ?? null),
                'success' => isset($event['success']) ? (bool)$event['success'] : null,
            ];

        case 'CompanyProfile':
            // Who the user actually is, sent once per session for the open company.
            // Unlike every other event type this is not anonymous: a sole trader's
            // company name is frequently their own name. It is disclosed in
            // /legal/privacy.php under "Business Profile Data" and it is why that page
            // no longer calls desktop telemetry anonymous. Do not widen this list
            // without updating that page in the same change.
            return $base + [
                'companyName' => telemetry_clean_string($event['companyName'] ?? null, TELEMETRY_COMPANY_PROFILE_MAX),
                'businessType' => telemetry_clean_string($event['businessType'] ?? null, TELEMETRY_COMPANY_PROFILE_MAX),
                'industry' => telemetry_clean_string($event['industry'] ?? null, TELEMETRY_COMPANY_PROFILE_MAX),
                'country' => telemetry_clean_string($event['country'] ?? null, 64),
                // ISO 4217, so three letters is the real bound; 8 leaves room for a
                // malformed value to arrive intact rather than truncated into a
                // different currency's code.
                'currency' => telemetry_clean_string($event['currency'] ?? null, 8),
                // The language the app is displayed in, as its English name. Not the same
                // question as country: an English app in a non-English country is what
                // tells us which translations are actually used rather than just shipped.
                'language' => telemetry_clean_string($event['language'] ?? null, 64),
            ];

        case 'CompanyScale':
            // How much is in the open company file, so a file someone is evaluating is
            // separable from one they run a business on. Counts only: unlike CompanyProfile
            // above, nothing here identifies anyone, so it needs no separate disclosure.
            // Capped well above any plausible file so a corrupt payload cannot skew a chart.
            return $base + [
                'expenses'   => telemetry_clean_int($event['expenses'] ?? null, 10000000),
                'revenues'   => telemetry_clean_int($event['revenues'] ?? null, 10000000),
                'invoices'   => telemetry_clean_int($event['invoices'] ?? null, 10000000),
                'payments'   => telemetry_clean_int($event['payments'] ?? null, 10000000),
                'customers'  => telemetry_clean_int($event['customers'] ?? null, 10000000),
                'suppliers'  => telemetry_clean_int($event['suppliers'] ?? null, 10000000),
                'products'   => telemetry_clean_int($event['products'] ?? null, 10000000),
                'categories' => telemetry_clean_int($event['categories'] ?? null, 10000000),
                'receipts'   => telemetry_clean_int($event['receipts'] ?? null, 10000000),
                'employees'  => telemetry_clean_int($event['employees'] ?? null, 10000000),
                'bankLines'  => telemetry_clean_int($event['bankLines'] ?? null, 10000000),
            ];

        case 'Startup':
            // Launch timing, one event per run. toFirstPaintMs covers everything before
            // the app can draw anything (runtime load, assembly mapping, first-run AV
            // scan) and is the part a splash screen cannot cover. Capped at ten minutes:
            // a machine resumed from sleep mid-launch can otherwise report hours.
            return $base + [
                'toFirstPaintMs' => telemetry_clean_int($event['toFirstPaintMs'] ?? null, 600000),
                // Two marks between the splash and the window, so the gap between them can
                // be attributed instead of guessed at: services first, then view models.
                // All four are measured from process start, so they nest rather than sum.
                'toServicesReadyMs' => telemetry_clean_int($event['toServicesReadyMs'] ?? null, 600000),
                'toViewModelsReadyMs' => telemetry_clean_int($event['toViewModelsReadyMs'] ?? null, 600000),
                'toReadyMs' => telemetry_clean_int($event['toReadyMs'] ?? null, 600000),
                'coldStart' => isset($event['coldStart']) ? (bool)$event['coldStart'] : null,
            ];

        default:
            return null;
    }
}

/**
 * Filter the full upload payload to the allowlist.
 * Rebuilds the payload from scratch. Fields not listed are dropped.
 */
function filter_telemetry_payload(array $payload): array
{
    $events = [];
    if (isset($payload['events']) && is_array($payload['events'])) {
        foreach ($payload['events'] as $event) {
            if (!is_array($event)) {
                continue;
            }
            $filtered = filter_telemetry_event($event);
            if ($filtered !== null) {
                $events[] = $filtered;
            }
        }
    }

    $out = [
        'uploadTime' => telemetry_clean_timestamp($payload['uploadTime'] ?? null),
        'appVersion' => null,
        'platform' => telemetry_validate_enum($payload['platform'] ?? null, TELEMETRY_PLATFORMS),
        'eventCount' => count($events),
        'events' => $events,
    ];

    // appVersion: strict charset (alphanumerics, dot, dash, underscore)
    $appVersion = $payload['appVersion'] ?? null;
    if (is_string($appVersion) && preg_match('/^[\w.\-]{1,32}$/', $appVersion)) {
        $out['appVersion'] = $appVersion;
    }

    // Geo: country/countryCode/region/timezone only. No city, no hashedIp.
    $geo = $payload['geoLocation'] ?? null;
    if (is_array($geo)) {
        $out['geoLocation'] = [
            'country' => telemetry_clean_string($geo['country'] ?? null, 64),
            'countryCode' => telemetry_clean_string($geo['countryCode'] ?? null, 8),
            'region' => telemetry_clean_string($geo['region'] ?? null, 64),
            'timezone' => telemetry_clean_string($geo['timezone'] ?? null, 64),
        ];
    }

    return $out;
}
