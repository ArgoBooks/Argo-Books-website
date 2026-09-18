<?php
require_once __DIR__ . '/../../../resources/icons.php';
require_once __DIR__ . '/../../../partials/code-block.php';
// The published limit has to be the enforced one, so read it from the same config.
require_once __DIR__ . '/../../../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../')->safeLoad();
require_once __DIR__ . '/../../../config/rate_limits.php';
require_once __DIR__ . '/../../../api/v1/lib/ratelimit.php';
$pageTitle = 'API Errors';
$pageDescription = 'Every error the Argo Books API can return, what causes it, and what to do about it.';
$currentPage = 'errors';
$pageCategory = 'api';

/**
 * Every code the API emits, grouped by the error type a client switches on.
 * Anchors here are what the doc_url on a live error points at, so a code must
 * never be added to the API without a row appearing here.
 */
$errorGroups = [
    'authentication_error' => [
        'blurb' => 'The key is missing, wrong, or no longer valid. Always a 401. Do not retry; the same request will fail the same way.',
        'codes' => [
            'missing_api_key' => ['status' => 401, 'cause' => 'No key on the request.', 'fix' => 'Send <code>Authorization: Bearer ab_...</code> or <code>X-Api-Key</code>.'],
            'invalid_api_key' => ['status' => 401, 'cause' => 'The key is not one of ours, or does not exist.', 'fix' => 'Check for a truncated or whitespace-padded copy and paste. Ask the merchant for a fresh key.'],
            'api_key_revoked' => ['status' => 401, 'cause' => 'The merchant revoked this key.', 'fix' => 'Treat as permanent. Prompt your user to reconnect rather than retrying.'],
        ],
    ],
    'invalid_request_error' => [
        'blurb' => 'Something about the request itself is wrong. The <code>param</code> field names the offending parameter whenever one is to blame.',
        'codes' => [
            'account_inactive' => ['status' => 403, 'cause' => 'The Argo Books account behind the key is not active.', 'fix' => 'The merchant needs to re-enable the API in Settings.'],
            'insufficient_scope' => ['status' => 403, 'cause' => 'The key lacks <code>read</code> or <code>write</code> for this call.', 'fix' => 'Ask the merchant for a key with the scope you need.'],
            'unknown_route' => ['status' => 404, 'cause' => 'No such path.', 'fix' => 'Check the resource name against the list in Resources.'],
            'resource_missing' => ['status' => 404, 'cause' => 'No object with that id on this account.', 'fix' => 'Confirm the id, and that it belongs to the same merchant as the key.'],
            'method_not_allowed' => ['status' => 405, 'cause' => 'Wrong verb for a valid path.', 'fix' => 'Read the <code>Allow</code> header. Note updates are <code>POST</code>, not <code>PUT</code> or <code>PATCH</code>.'],
            'unknown_api_version' => ['status' => 400, 'cause' => 'An <code>Argo-Version</code> we do not recognise.', 'fix' => 'Use the version named in the message, or omit the header.'],
            'invalid_json' => ['status' => 400, 'cause' => 'The body did not parse.', 'fix' => 'Check <code>Content-Type</code> matches what you actually sent.'],
            'unknown_parameter' => ['status' => 400, 'cause' => 'A field we do not accept.', 'fix' => 'Usually a typo. The <code>param</code> field is the name we received.'],
            'parameter_missing' => ['status' => 400, 'cause' => 'A required field was absent on create.', 'fix' => 'See the required column in Resources.'],
            'parameter_invalid_empty' => ['status' => 400, 'cause' => 'A required field was sent as null or empty.', 'fix' => 'Required fields can be changed but not cleared.'],
            'parameter_invalid_type' => ['status' => 400, 'cause' => 'Wrong JSON type.', 'fix' => 'Check the type column in Resources.'],
            'parameter_invalid_value' => ['status' => 400, 'cause' => 'Right type, unacceptable value. Includes <code>tax_amount</code> exceeding <code>amount</code>.', 'fix' => 'The message states the rule.'],
            'parameter_invalid_amount' => ['status' => 400, 'cause' => 'A money field was not an integer.', 'fix' => 'Send minor units. <code>1999</code>, not <code>19.99</code>. We reject rather than round.'],
            'parameter_invalid_currency' => ['status' => 400, 'cause' => 'Not a three-letter code.', 'fix' => 'Use ISO 4217. Case does not matter.'],
            'parameter_invalid_country' => ['status' => 400, 'cause' => 'Not a two-letter code.', 'fix' => 'Use ISO 3166-1 alpha-2.'],
            'parameter_invalid_date' => ['status' => 400, 'cause' => 'Not a real <code>YYYY-MM-DD</code> date.', 'fix' => 'No timestamps, no locale formats. This also catches dates like 2026-02-30.'],
            'parameter_invalid_email' => ['status' => 400, 'cause' => 'Not a valid address.', 'fix' => 'Send null rather than a placeholder if you do not have one.'],
            'parameter_too_long' => ['status' => 400, 'cause' => 'Over the field maximum.', 'fix' => 'See the type column in Resources for the limit.'],
            'parameter_out_of_range' => ['status' => 400, 'cause' => 'A numeric parameter outside its bounds, typically <code>limit</code>.', 'fix' => '<code>limit</code> is 1 to 100. Batches take at most 1000 objects.'],
            'parameter_conflict' => ['status' => 400, 'cause' => 'Two parameters that cannot be combined.', 'fix' => 'Pass <code>starting_after</code> or <code>ending_before</code>, not both.'],
            'parameter_invalid_reference' => ['status' => 400, 'cause' => 'An id of the wrong type, for example a <code>cat_</code> where a <code>cus_</code> belongs.', 'fix' => 'Check the expected prefix in Resources.'],
            'reference_not_found' => ['status' => 400, 'cause' => 'The referenced object does not exist on this account.', 'fix' => 'Create it first. References are validated when you send them, not at import.'],
            'parameter_invalid_expand' => ['status' => 400, 'cause' => 'Tried to expand something that is not a reference.', 'fix' => 'Only reference fields and <code>line_items</code> expand, one level deep.'],
            'cursor_not_found' => ['status' => 400, 'cause' => 'The pagination cursor names an object we cannot find.', 'fix' => 'Use an id from the previous page of the same list.'],
            'parameter_invalid_metadata' => ['status' => 400, 'cause' => 'Metadata was not a flat object of strings.', 'fix' => 'Nesting is refused rather than flattened, so what comes back is what went in.'],
            'metadata_too_large' => ['status' => 400, 'cause' => 'More than 50 keys.', 'fix' => 'Metadata is for lookup handles, not payload storage.'],
            'metadata_key_too_long' => ['status' => 400, 'cause' => 'A key over 40 characters.', 'fix' => 'Shorten the key.'],
            'metadata_value_too_long' => ['status' => 400, 'cause' => 'A value over 500 characters.', 'fix' => 'Store the bulk on your side and keep a reference here.'],
            'currency_mismatch' => ['status' => 400, 'cause' => 'A refund in a different currency from its revenue.', 'fix' => 'Match the original. The message names it.'],
            'refund_exceeds_revenue' => ['status' => 400, 'cause' => 'Refunds against one sale would exceed what was taken.', 'fix' => 'The message states how much remains refundable.'],
            'object_not_pending' => ['status' => 409, 'cause' => 'The merchant already imported or rejected this object.', 'fix' => 'It is frozen. Push a correcting object instead of editing.'],
            'object_not_claimable' => ['status' => 409, 'cause' => 'A batch named an object that is not pending. The whole batch rolled back.', 'fix' => 'Re-read pending status and rebuild the batch.'],
            'idempotency_key_required' => ['status' => 400, 'cause' => 'A create without an <code>Idempotency-Key</code>.', 'fix' => 'Derive one from your own record id so a retry cannot duplicate.'],
            'idempotency_key_too_long' => ['status' => 400, 'cause' => 'Over 128 characters.', 'fix' => 'A UUID or your own id is plenty.'],
        ],
    ],
    'idempotency_error' => [
        'blurb' => 'The <code>Idempotency-Key</code> conflicts with an earlier request. Keys are remembered for 24 hours.',
        'codes' => [
            'idempotency_key_reused' => ['status' => 409, 'cause' => 'Same key, different body.', 'fix' => 'Almost always a bug: a key was reused for genuinely new data. Use a fresh key.'],
            'idempotency_key_in_flight' => ['status' => 409, 'cause' => 'An identical request is still running.', 'fix' => 'Retry shortly. The retry replays the cached response.'],
        ],
    ],
    'rate_limit_error' => [
        'blurb' => 'Too many requests on one key.',
        'codes' => [
            'rate_limit_exceeded' => ['status' => 429, 'cause' => 'Over ' . rate_limit_max('api_v1_per_minute') . ' requests in a ' . api_rate_limit_window_phrase() . '.', 'fix' => 'Wait for <code>Retry-After</code>. Watch <code>X-RateLimit-Remaining</code> and slow down before you hit this.'],
        ],
    ],
    'api_error' => [
        'blurb' => 'Our fault. Safe to retry with the same <code>Idempotency-Key</code>, which is exactly what the key is for.',
        'codes' => [
            'internal_error' => ['status' => 500, 'cause' => 'An unhandled failure on our side.', 'fix' => 'Retry with backoff. If it persists, send us the <code>request_id</code>.'],
            'field_spec_error' => ['status' => 500, 'cause' => 'A validator is missing for a field we advertise.', 'fix' => 'A bug on our side. Please report it with the <code>request_id</code>.'],
            'unknown_object' => ['status' => 500, 'cause' => 'An object type was referenced that we cannot resolve.', 'fix' => 'A bug on our side. Please report it with the <code>request_id</code>.'],
        ],
    ],
];

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>Every failure returns the same envelope, whatever went wrong.</p>

            <?= argo_code_block(<<<'CODE'
{
  "error": {
    "type": "invalid_request_error",
    "code": "parameter_missing",
    "message": "Missing required parameter 'name'.",
    "param": "name",
    "doc_url": "https://argorobots.com/documentation/api/errors#parameter_missing",
    "request_id": "req_9f3ed3dc2e61733488a1db04"
  }
}
CODE, 'json') ?>

            <p>Switch on <code>type</code> for broad handling and <code>code</code> for specific handling. <strong>Do not parse <code>message</code>.</strong> It is written for a human reading a log and its wording will change; <code>code</code> is the part we keep stable.</p>
            <p><code>param</code> appears whenever a single parameter is to blame. <code>request_id</code> also comes back as a header on every response, successful or not.</p>

            <h2>Deciding Whether to Retry</h2>
            <ul>
                <li><strong>Retry</strong> <code>api_error</code> and <code>rate_limit_error</code>, with backoff, reusing the same <code>Idempotency-Key</code>.</li>
                <li><strong>Do not retry</strong> <code>authentication_error</code> or <code>invalid_request_error</code>. Nothing about the request will change.</li>
                <li><code>idempotency_key_in_flight</code> is the one exception worth a short retry: the original is still running and the second attempt will replay its result.</li>
            </ul>

<?php foreach ($errorGroups as $type => $group): ?>
            <h2 id="<?php echo htmlspecialchars($type); ?>"><code><?php echo htmlspecialchars($type); ?></code></h2>
            <p><?php echo $group['blurb']; ?></p>
            <table>
                <thead><tr><th>Code</th><th>Status</th><th>Cause</th><th>What to do</th></tr></thead>
                <tbody>
<?php foreach ($group['codes'] as $code => $detail): ?>
                    <tr id="<?php echo htmlspecialchars($code); ?>">
                        <td><code><?php echo htmlspecialchars($code); ?></code></td>
                        <td><?php echo (int) $detail['status']; ?></td>
                        <td><?php echo $detail['cause']; ?></td>
                        <td><?php echo $detail['fix']; ?></td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
<?php endforeach; ?>

            <div class="page-navigation">
                <a href="webhooks.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; Webhooks</span>
                </a>
                <a href="../reference/how-numbers-are-calculated.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">How Numbers Are Calculated &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
