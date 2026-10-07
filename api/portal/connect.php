<?php
/**
 * Provider Connect/Disconnect API Endpoint
 *
 * POST /api/portal/connect/{provider} - Initiate OAuth flow for a payment provider
 * DELETE /api/portal/connect/{provider} - Disconnect a payment provider
 *
 * Requires API key authentication (Argo Books -> Server).
 * Provider must be one of: stripe, paypal, square.
 */

require_once __DIR__ . '/portal-helper.php';
require_once __DIR__ . '/../../vendor/autoload.php';

set_portal_headers();
require_method(['POST', 'DELETE']);

$company = authenticate_portal_request();
if (!$company) {
    send_error_response(401, 'Invalid or missing API key.', 'UNAUTHORIZED');
}

// Get provider from URL (set by .htaccess rewrite rule)
$provider = $_GET['provider'] ?? '';
if (!in_array($provider, ['stripe', 'paypal', 'square'])) {
    send_error_response(400, 'Invalid provider. Must be one of: stripe, paypal, square.', 'INVALID_PROVIDER');
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    disconnect_provider($company, $provider);
} else {
    initiate_connect($company, $provider);
}

/**
 * Initiate OAuth flow for a payment provider.
 * Generates a CSRF state token, stores it, and returns the authorization URL.
 */
function initiate_connect(array $company, string $provider): void
{
    $is_production = ($_ENV['APP_ENV'] ?? 'sandbox') === 'production';
    $callbackBase = rtrim(env('SITE_URL', 'https://argorobots.com'), '/');
    $callbackUrl = "$callbackBase/api/portal/connect/callback/$provider";

    // Generate CSRF state token
    $state = bin2hex(random_bytes(32));

    // Store state in DB with 10-minute expiry
    global $pdo;
    $expiresAt = date('Y-m-d H:i:s', time() + 600);
    $stmt = $pdo->prepare(
        'INSERT INTO portal_oauth_states (company_id, provider, state_token, expires_at)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$company['id'], $provider, $state, $expiresAt]);

    // Clean up expired states
    $pdo->query('DELETE FROM portal_oauth_states WHERE expires_at <= NOW()');

    $authUrl = '';

    switch ($provider) {
        case 'stripe':
            $secretKey = $is_production
                ? ($_ENV['STRIPE_LIVE_SECRET_KEY'] ?? '')
                : ($_ENV['STRIPE_SANDBOX_SECRET_KEY'] ?? '');
            if (empty($secretKey)) {
                send_error_response(500, 'Stripe Connect is not configured on the server.', 'PROVIDER_NOT_CONFIGURED');
            }

            try {
                \Stripe\Stripe::setApiKey($secretKey);

                // Either fully connected or still onboarding: stripe_account_id is written only by the callback, so a pending id means neither.
                $stripeAccountId = null;
                $stmtCheck = $pdo->prepare(
                    'SELECT stripe_account_id, stripe_pending_account_id FROM portal_companies WHERE id = ?'
                );
                $stmtCheck->execute([$company['id']]);
                $row = $stmtCheck->fetch();

                $storedId = !empty($row['stripe_account_id'])
                    ? $row['stripe_account_id']
                    : ($row['stripe_pending_account_id'] ?? null);

                if (!empty($storedId)) {
                    // Verify the stored account exists and matches the current mode (live vs test)
                    try {
                        $existingAccount = \Stripe\Account::retrieve($storedId);
                        // Verify the account was created in the same mode (live vs test)
                        $acctData = $existingAccount->toArray();
                        $acctLivemode = isset($acctData['livemode']) ? $acctData['livemode'] : null;
                        if ($acctLivemode === $is_production) {
                            $stripeAccountId = $storedId;
                        }
                    } catch (\Stripe\Exception\ApiErrorException $e) {
                        // Account doesn't exist or can't be accessed; the stored ID
                        // will be cleared and a new account created below
                    }

                    if (!$stripeAccountId) {
                        $stmtClear = $pdo->prepare(
                            'UPDATE portal_companies SET stripe_account_id = NULL, stripe_pending_account_id = NULL, stripe_email = NULL, updated_at = NOW() WHERE id = ?'
                        );
                        $stmtClear->execute([$company['id']]);
                    }
                }

                if (!$stripeAccountId) {
                    // Create a new Express connected account
                    $account = \Stripe\Account::create([
                        'type' => 'express',
                        'capabilities' => [
                            'card_payments' => ['requested' => true],
                            'transfers' => ['requested' => true],
                        ],
                    ]);
                    $stripeAccountId = $account->id;

                    // Stored as pending only, because the callback writes stripe_account_id once onboarding finishes.
                    $stmtStore = $pdo->prepare(
                        'UPDATE portal_companies SET stripe_pending_account_id = ?, updated_at = NOW() WHERE id = ?'
                    );
                    $stmtStore->execute([$stripeAccountId, $company['id']]);
                }

                // Create an Account Link for Express onboarding
                $accountLink = \Stripe\AccountLink::create([
                    'account' => $stripeAccountId,
                    'return_url' => $callbackUrl . '?state=' . $state,
                    'refresh_url' => $callbackUrl . '?state=' . $state . '&refresh=1',
                    'type' => 'account_onboarding',
                ]);
                $authUrl = $accountLink->url;
            } catch (\Stripe\Exception\ApiErrorException $e) {
                error_log('Stripe Connect error: ' . $e->getMessage());
                send_error_response(500, 'Failed to connect Stripe. Please try again.', 'STRIPE_API_ERROR');
            }
            break;

        case 'paypal':
            // PayPal portal Connect is intentionally disabled.
            send_error_response(503, 'PayPal portal Connect is not currently supported. Use Stripe or Square instead.', 'PROVIDER_UNSUPPORTED');
            break;

        case 'square':
            $appId = $is_production
                ? ($_ENV['SQUARE_LIVE_APP_ID'] ?? '')
                : ($_ENV['SQUARE_SANDBOX_APP_ID'] ?? '');
            if (empty($appId)) {
                send_error_response(500, 'Square is not configured on the server.', 'PROVIDER_NOT_CONFIGURED');
            }
            $squareBase = $is_production
                ? 'https://connect.squareup.com'
                : 'https://connect.squareupsandbox.com';
            $squareParams = [
                'client_id' => $appId,
                'redirect_uri' => $callbackUrl,
                'scope' => 'PAYMENTS_WRITE PAYMENTS_READ MERCHANT_PROFILE_READ',
                'state' => $state,
            ];
            // Square's sandbox OAuth does not support the `session` parameter.
            // Sending it there returns a blank authorize page. Production only.
            if ($is_production) {
                $squareParams['session'] = 'false';
            }
            $authUrl = "$squareBase/oauth2/authorize?" . http_build_query($squareParams, '', '&', PHP_QUERY_RFC3986);
            break;
    }

    send_json_response(200, [
        'success' => true,
        'authUrl' => $authUrl,
        'message' => 'Redirect to ' . ucfirst($provider) . ' to authorize.',
        'timestamp' => date('c')
    ]);
}

/**
 * Disconnect a payment provider by clearing its credentials from the database.
 */
function disconnect_provider(array $company, string $provider): void
{
    global $pdo;

    switch ($provider) {
        case 'stripe':
            $stmt = $pdo->prepare(
                'UPDATE portal_companies SET stripe_account_id = NULL, stripe_pending_account_id = NULL, stripe_email = NULL, updated_at = NOW() WHERE id = ?'
            );
            break;
        case 'paypal':
            $stmt = $pdo->prepare(
                'UPDATE portal_companies SET paypal_merchant_id = NULL, paypal_email = NULL, updated_at = NOW() WHERE id = ?'
            );
            break;
        case 'square':
            $stmt = $pdo->prepare(
                'UPDATE portal_companies SET square_merchant_id = NULL, square_access_token = NULL, square_location_id = NULL, square_email = NULL, updated_at = NOW() WHERE id = ?'
            );
            break;
    }

    try {
        $stmt->execute([$company['id']]);
    } catch (\PDOException $e) {
        error_log('Portal disconnect DB error (' . $provider . '): ' . $e->getMessage());
        send_error_response(500, 'Failed to disconnect ' . $provider . '. Please try again.', 'DISCONNECT_FAILED');
    }

    // Re-fetch company to return the updated connected providers state
    $stmtRefresh = $pdo->prepare(
        'SELECT stripe_account_id, stripe_email, paypal_merchant_id, paypal_email,
                square_merchant_id, square_email
         FROM portal_companies WHERE id = ? LIMIT 1'
    );
    $stmtRefresh->execute([$company['id']]);
    $updated = $stmtRefresh->fetch();

    $connectedProviders = [
        'stripeConnected' => !empty($updated['stripe_account_id']),
        'stripeEmail' => $updated['stripe_email'] ?? null,
        'paypalConnected' => !empty($updated['paypal_merchant_id']),
        'paypalEmail' => $updated['paypal_email'] ?? null,
        'squareConnected' => !empty($updated['square_merchant_id']),
        'squareEmail' => $updated['square_email'] ?? null,
    ];

    send_json_response(200, [
        'success' => true,
        'message' => ucfirst($provider) . ' has been disconnected.',
        'connectedProviders' => $connectedProviders,
        'payment_methods' => array_values(array_filter([
            !empty($updated['stripe_account_id']) ? 'stripe' : null,
            !empty($updated['paypal_merchant_id']) ? 'paypal' : null,
            !empty($updated['square_merchant_id']) ? 'square' : null,
        ])),
        'timestamp' => date('c')
    ]);
}
