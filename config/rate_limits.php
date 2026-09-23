<?php
/**
 * Rate limits, in one place, overridable from .env.
 *
 * Every abuse-facing limit on the site is named here with its ceiling, its window in seconds, and
 * the two .env variables that override them, so tightening one is an .env change rather than a
 * code change. Both variable names are written out in full so searching for RL_ACCOUNTANT_EMAIL_MAX
 * lands on the entry it belongs to. Anything missing, empty or below 1 falls back to the default
 * beside it, so a typo cannot silently remove a limit.
 *
 * Use rate_limit_exceeded() / rate_limit_record() rather than calling is_rate_limited() with
 * literals: they read the ceiling and the window from the same entry, which is what stops a check
 * and its record from disagreeing about the window. Prefer rate_limit_hit() wherever every
 * request counts, since it checks and counts in one step. A JSON endpoint answers a limit with
 * send_rate_limited_response(), which sets Retry-After and says how long the wait is.
 *
 * Not here on purpose: monthly plan quotas (config/pricing.php), HTTP timeouts, batch sizes and
 * page sizes. Those are product or performance settings, not defences against abuse.
 */

/**
 * An integer from the environment, falling back to the default when it is missing, empty,
 * not a number, or below 1.
 */
function _rl_env(string $key, int $default): int
{
    $raw = $_ENV[$key] ?? null;
    if ($raw === null || $raw === '' || !is_numeric($raw)) {
        return $default;
    }

    $value = (int) $raw;
    return $value >= 1 ? $value : $default;
}

/**
 * Every limit as [max attempts, window in seconds], with its .env overrides applied.
 * Cached for the request.
 *
 * @return array<string, array{max: int, window: int}>
 */
function rate_limits(): array
{
    static $limits = null;
    if ($limits !== null) {
        return $limits;
    }

    $table = [
        // Email the desktop app asks us to send. The sending domain's reputation is what invoice
        // delivery depends on, so the free paths are deliberately tighter than the licensed ones.
        'invoice_email' => [_rl_env('RL_INVOICE_EMAIL_MAX', 500), _rl_env('RL_INVOICE_EMAIL_WINDOW', 3600)],
        'purchase_order_email' => [_rl_env('RL_PURCHASE_ORDER_EMAIL_MAX', 500), _rl_env('RL_PURCHASE_ORDER_EMAIL_WINDOW', 3600)],
        'purchase_order_email_device' => [_rl_env('RL_PURCHASE_ORDER_EMAIL_DEVICE_MAX', 50), _rl_env('RL_PURCHASE_ORDER_EMAIL_DEVICE_WINDOW', 3600)],
        'purchase_order_email_ip' => [_rl_env('RL_PURCHASE_ORDER_EMAIL_IP_MAX', 20), _rl_env('RL_PURCHASE_ORDER_EMAIL_IP_WINDOW', 3600)],
        'accountant_email' => [_rl_env('RL_ACCOUNTANT_EMAIL_MAX', 30), _rl_env('RL_ACCOUNTANT_EMAIL_WINDOW', 3600)],
        'accountant_email_device' => [_rl_env('RL_ACCOUNTANT_EMAIL_DEVICE_MAX', 10), _rl_env('RL_ACCOUNTANT_EMAIL_DEVICE_WINDOW', 3600)],
        'accountant_email_ip' => [_rl_env('RL_ACCOUNTANT_EMAIL_IP_MAX', 20), _rl_env('RL_ACCOUNTANT_EMAIL_IP_WINDOW', 3600)],
        'quote_email' => [_rl_env('RL_QUOTE_EMAIL_MAX', 500), _rl_env('RL_QUOTE_EMAIL_WINDOW', 3600)],
        'quote_email_company' => [_rl_env('RL_QUOTE_EMAIL_COMPANY_MAX', 50), _rl_env('RL_QUOTE_EMAIL_COMPANY_WINDOW', 3600)],
        'quote_email_ip' => [_rl_env('RL_QUOTE_EMAIL_IP_MAX', 20), _rl_env('RL_QUOTE_EMAIL_IP_WINDOW', 3600)],

        // The hourly IP ceilings above still allow hundreds of messages a day from one
        // connection, and a spammer runs around the clock, so each free path also has a
        // daily one.
        'quote_email_ip_daily' => [_rl_env('RL_QUOTE_EMAIL_IP_DAILY_MAX', 100), _rl_env('RL_QUOTE_EMAIL_IP_DAILY_WINDOW', 86400)],
        'purchase_order_email_ip_daily' => [_rl_env('RL_PURCHASE_ORDER_EMAIL_IP_DAILY_MAX', 100), _rl_env('RL_PURCHASE_ORDER_EMAIL_IP_DAILY_WINDOW', 86400)],
        'accountant_email_ip_daily' => [_rl_env('RL_ACCOUNTANT_EMAIL_IP_DAILY_MAX', 100), _rl_env('RL_ACCOUNTANT_EMAIL_IP_DAILY_WINDOW', 86400)],

        // AI work we pay for per call. Sized for the largest legitimate import: spreadsheet
        // import sends up to 100 rows per call, ten at a time, and the rescue path can reach
        // ~500 calls for one 10,000-row sheet. Image scans also spend a monthly quota per call,
        // but text calls do not, so for those these two ceilings are the only bound on cost.
        'ai_completions' => [_rl_env('RL_AI_COMPLETIONS_MAX', 600), _rl_env('RL_AI_COMPLETIONS_WINDOW', 900)],
        'ai_completions_ip' => [_rl_env('RL_AI_COMPLETIONS_IP_MAX', 600), _rl_env('RL_AI_COMPLETIONS_IP_WINDOW', 900)],
        'ai_priors' => [_rl_env('RL_AI_PRIORS_MAX', 120), _rl_env('RL_AI_PRIORS_WINDOW', 900)],
        'bank_extract' => [_rl_env('RL_BANK_EXTRACT_MAX', 30), _rl_env('RL_BANK_EXTRACT_WINDOW', 900)],

        // Exchange rates: a wide ceiling, because the app asks for single dates while transactions
        // wait on a rate, on top of the one large batch a multi-year import sends.
        'exchange_rates' => [_rl_env('RL_EXCHANGE_RATES_MAX', 1000), _rl_env('RL_EXCHANGE_RATES_WINDOW', 900)],

        // Licence endpoints. Guessing a key is the thing being slowed down here.
        'license_validate' => [_rl_env('RL_LICENSE_VALIDATE_MAX', 60), _rl_env('RL_LICENSE_VALIDATE_WINDOW', 600)],
        'license_redeem' => [_rl_env('RL_LICENSE_REDEEM_MAX', 20), _rl_env('RL_LICENSE_REDEEM_WINDOW', 600)],
        'license_capture_email' => [_rl_env('RL_LICENSE_CAPTURE_EMAIL_MAX', 20), _rl_env('RL_LICENSE_CAPTURE_EMAIL_WINDOW', 600)],

        'sheets_export' => [_rl_env('RL_SHEETS_EXPORT_MAX', 30), _rl_env('RL_SHEETS_EXPORT_WINDOW', 900)],
        'invoice_usage' => [_rl_env('RL_INVOICE_USAGE_MAX', 30), _rl_env('RL_INVOICE_USAGE_WINDOW', 900)],
        'api_auth_failure' => [_rl_env('RL_API_AUTH_FAILURE_MAX', 20), _rl_env('RL_API_AUTH_FAILURE_WINDOW', 900)],

        // Payment portal.
        'portal_register' => [_rl_env('RL_PORTAL_REGISTER_MAX', 10), _rl_env('RL_PORTAL_REGISTER_WINDOW', 900)],
        'portal_lookup' => [_rl_env('RL_PORTAL_LOOKUP_MAX', 10), _rl_env('RL_PORTAL_LOOKUP_WINDOW', 900)],
        'portal_revert_email' => [_rl_env('RL_PORTAL_REVERT_EMAIL_MAX', 10), _rl_env('RL_PORTAL_REVERT_EMAIL_WINDOW', 900)],
        'quote_respond' => [_rl_env('RL_QUOTE_RESPOND_MAX', 20), _rl_env('RL_QUOTE_RESPOND_WINDOW', 900)],

        // Uninstall survey. One person uninstalling answers once; the ceiling is only here so the
        // open form cannot be used to fill the table.
        'uninstall_feedback' => [_rl_env('RL_UNINSTALL_FEEDBACK_MAX', 5), _rl_env('RL_UNINSTALL_FEEDBACK_WINDOW', 3600)],
        'payment' => [_rl_env('RL_PAYMENT_MAX', 20), _rl_env('RL_PAYMENT_WINDOW', 900)],

        // Mobile sync pairing.
        'sync_push' => [_rl_env('RL_SYNC_PUSH_MAX', 120), _rl_env('RL_SYNC_PUSH_WINDOW', 900)],
        'sync_redeem' => [_rl_env('RL_SYNC_REDEEM_MAX', 30), _rl_env('RL_SYNC_REDEEM_WINDOW', 900)],
        'sync_pair' => [_rl_env('RL_SYNC_PAIR_MAX', 30), _rl_env('RL_SYNC_PAIR_WINDOW', 900)],
        'sync_claim' => [_rl_env('RL_SYNC_CLAIM_MAX', 10), _rl_env('RL_SYNC_CLAIM_WINDOW', 900)],

        // The free web receipt scanner. The per-visitor and site-wide daily caps stay in
        // config/pricing.php, since they are what the marketing pages quote.
        'web_receipt_ip' => [_rl_env('RL_WEB_RECEIPT_IP_MAX', 200), _rl_env('RL_WEB_RECEIPT_IP_WINDOW', 86400)],
        'web_receipt_export' => [_rl_env('RL_WEB_RECEIPT_EXPORT_MAX', 120), _rl_env('RL_WEB_RECEIPT_EXPORT_WINDOW', 900)],

        'profit_analyzer' => [_rl_env('RL_PROFIT_ANALYZER_MAX', 5), _rl_env('RL_PROFIT_ANALYZER_WINDOW', 86400)],
        'profit_analyzer_email' => [_rl_env('RL_PROFIT_ANALYZER_EMAIL_MAX', 5), _rl_env('RL_PROFIT_ANALYZER_EMAIL_WINDOW', 3600)],

        // Shared by every free tool's "email me these results" box (api/tool-email.php).
        'tool_email' => [_rl_env('RL_TOOL_EMAIL_MAX', 5), _rl_env('RL_TOOL_EMAIL_WINDOW', 3600)],

        // Sign in and anything that emails a code. Five wrong tries in fifteen minutes.
        'admin_login' => [_rl_env('RL_ADMIN_LOGIN_MAX', 5), _rl_env('RL_ADMIN_LOGIN_WINDOW', 900)],
        'admin_2fa' => [_rl_env('RL_ADMIN_2FA_MAX', 5), _rl_env('RL_ADMIN_2FA_WINDOW', 900)],
        'community_login' => [_rl_env('RL_COMMUNITY_LOGIN_MAX', 5), _rl_env('RL_COMMUNITY_LOGIN_WINDOW', 900)],
        'community_password_reset' => [_rl_env('RL_COMMUNITY_PASSWORD_RESET_MAX', 5), _rl_env('RL_COMMUNITY_PASSWORD_RESET_WINDOW', 900)],
        'community_password_reset_email' => [_rl_env('RL_COMMUNITY_PASSWORD_RESET_EMAIL_MAX', 3), _rl_env('RL_COMMUNITY_PASSWORD_RESET_EMAIL_WINDOW', 3600)],
        'community_email_change' => [_rl_env('RL_COMMUNITY_EMAIL_CHANGE_MAX', 5), _rl_env('RL_COMMUNITY_EMAIL_CHANGE_WINDOW', 3600)],

        // Anything else a stranger can make us send email for.
        'community_register' => [_rl_env('RL_COMMUNITY_REGISTER_MAX', 5), _rl_env('RL_COMMUNITY_REGISTER_WINDOW', 3600)],
        'community_resend_verification' => [_rl_env('RL_COMMUNITY_RESEND_VERIFICATION_MAX', 3), _rl_env('RL_COMMUNITY_RESEND_VERIFICATION_WINDOW', 900)],
        'community_resend_key' => [_rl_env('RL_COMMUNITY_RESEND_KEY_MAX', 3), _rl_env('RL_COMMUNITY_RESEND_KEY_WINDOW', 3600)],
        'community_report' => [_rl_env('RL_COMMUNITY_REPORT_MAX', 10), _rl_env('RL_COMMUNITY_REPORT_WINDOW', 3600)],
        'contact_form' => [_rl_env('RL_CONTACT_FORM_MAX', 3), _rl_env('RL_CONTACT_FORM_WINDOW', 600)],

        // Telemetry and crash uploads, which keep their own counters in temp files. Every run of
        // the app uploads on start and on close as well as every 20 minutes, and a flush over
        // 500 events splits into several requests, so a free ceiling in single digits drops data
        // from anyone who restarts a few times in an hour.
        'telemetry_upload_premium' => [_rl_env('RL_TELEMETRY_UPLOAD_PREMIUM_MAX', 100), _rl_env('RL_TELEMETRY_UPLOAD_PREMIUM_WINDOW', 3600)],
        'telemetry_upload_free' => [_rl_env('RL_TELEMETRY_UPLOAD_FREE_MAX', 30), _rl_env('RL_TELEMETRY_UPLOAD_FREE_WINDOW', 3600)],
        'telemetry_upload_free_ip' => [_rl_env('RL_TELEMETRY_UPLOAD_FREE_IP_MAX', 120), _rl_env('RL_TELEMETRY_UPLOAD_FREE_IP_WINDOW', 3600)],
        'crash_upload_premium' => [_rl_env('RL_CRASH_UPLOAD_PREMIUM_MAX', 60), _rl_env('RL_CRASH_UPLOAD_PREMIUM_WINDOW', 3600)],
        'crash_upload_free' => [_rl_env('RL_CRASH_UPLOAD_FREE_MAX', 20), _rl_env('RL_CRASH_UPLOAD_FREE_WINDOW', 3600)],
        'crash_upload_free_ip' => [_rl_env('RL_CRASH_UPLOAD_FREE_IP_MAX', 120), _rl_env('RL_CRASH_UPLOAD_FREE_IP_WINDOW', 3600)],

        // Community posting, per user.
        'community_post_short' => [_rl_env('RL_COMMUNITY_POST_SHORT_MAX', 1), _rl_env('RL_COMMUNITY_POST_SHORT_WINDOW', 300)],
        'community_post_long' => [_rl_env('RL_COMMUNITY_POST_LONG_MAX', 5), _rl_env('RL_COMMUNITY_POST_LONG_WINDOW', 3600)],
        'community_comment_short' => [_rl_env('RL_COMMUNITY_COMMENT_SHORT_MAX', 3), _rl_env('RL_COMMUNITY_COMMENT_SHORT_WINDOW', 300)],
        'community_comment_long' => [_rl_env('RL_COMMUNITY_COMMENT_LONG_MAX', 20), _rl_env('RL_COMMUNITY_COMMENT_LONG_WINDOW', 3600)],

        // The public API, counted per key per minute and advertised in the account endpoint.
        'api_v1_per_minute' => [_rl_env('RL_API_V1_PER_MINUTE_MAX', 120), _rl_env('RL_API_V1_PER_MINUTE_WINDOW', 60)],
    ];

    $limits = [];
    foreach ($table as $name => [$max, $window]) {
        $limits[$name] = ['max' => $max, 'window' => $window];
    }

    return $limits;
}

/**
 * @return array{max: int, window: int}
 */
function rate_limit(string $name): array
{
    $limits = rate_limits();
    if (!isset($limits[$name])) {
        // A name that is not defined would otherwise mean no limit at all, so fail closed on a
        // tight default and leave a trace.
        error_log('Unknown rate limit name: ' . $name);
        return ['max' => 5, 'window' => 900];
    }

    return $limits[$name];
}

function rate_limit_max(string $name): int
{
    return rate_limit($name)['max'];
}

function rate_limit_window(string $name): int
{
    return rate_limit($name)['window'];
}
