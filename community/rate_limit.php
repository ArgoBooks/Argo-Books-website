<?php

require_once __DIR__ . '/../rate_limit_helper.php';

/**
 * Check if a user has exceeded the posting or commenting limits, and count the attempt if not.
 *
 * Each action has a short burst limit and a longer hourly one (RL_COMMUNITY_POST_SHORT_MAX and
 * friends in .env), counted per user in the shared limiter.
 *
 * @param int    $user_id     User ID
 * @param string $action_type Action type ('post' or 'comment')
 * @return bool|string        False if within limits, HTML string if limit exceeded
 */
function check_rate_limit($user_id, $action_type)
{
    // Skip checks for admin users
    if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? 'user') === 'admin') {
        return false;
    }

    if (!in_array($action_type, ['post', 'comment'], true)) {
        return false;
    }

    $identifier = (string) $user_id;
    $long = 'community_' . $action_type . '_long';
    $short = 'community_' . $action_type . '_short';

    if (rate_limit_exceeded($long, $identifier)) {
        return build_rate_limit_message($action_type, community_rate_limit_wait($long, $identifier));
    }

    if (rate_limit_hit($short, $identifier)) {
        return build_rate_limit_message($action_type, community_rate_limit_wait($short, $identifier));
    }

    rate_limit_record($long, $identifier);

    return false;
}

/**
 * Seconds until this user's bucket opens again.
 */
function community_rate_limit_wait(string $name, string $identifier): int
{
    $startedAt = rate_limit_started_at($name, $identifier);
    if ($startedAt === null) {
        return rate_limit_window($name);
    }

    return max(1, $startedAt + rate_limit_window($name) - time());
}

/**
 * Build an HTML rate limit message
 *
 * @param string $action_type  Action type ('post' or 'comment')
 * @param int    $wait_seconds Seconds until the user can act again
 * @return string HTML message
 */
function build_rate_limit_message($action_type, $wait_seconds)
{
    $reset_timestamp = time() + $wait_seconds;

    $minutes = floor($wait_seconds / 60);
    $seconds = $wait_seconds % 60;
    $time_str = sprintf('%dm %02ds', $minutes, $seconds);

    return '<div class="rate-limit-message">'
        . 'You are ' . ($action_type === 'post' ? 'posting' : 'commenting') . ' too frequently. '
        . 'Please wait <span class="countdown-timer" data-reset-timestamp="' . $reset_timestamp . '">' . $time_str . '</span> before '
        . ($action_type === 'post' ? 'posting' : 'commenting') . ' again.</div>';
}
