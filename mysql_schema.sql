-- License keys table
CREATE TABLE IF NOT EXISTS license_keys (
    id INT PRIMARY KEY AUTO_INCREMENT,
    license_key VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL,
    user_id INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    activated TINYINT(1) DEFAULT 0,
    activation_date DATETIME DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    transaction_id VARCHAR(100),
    order_id VARCHAR(100),
    payment_method VARCHAR(50),
    payment_intent VARCHAR(100),
    review_email_sent_at DATETIME DEFAULT NULL,
    review_email_variant VARCHAR(20) DEFAULT NULL COMMENT 'active, inactive, or manually_skipped',
    review_email_token CHAR(48) DEFAULT NULL,
    INDEX idx_license_keys_user_id (user_id),
    UNIQUE KEY uk_review_email_token (review_email_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin users table
CREATE TABLE IF NOT EXISTS admin_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    two_factor_secret VARCHAR(100),
    two_factor_enabled TINYINT(1) DEFAULT 0,
    last_2fa_counter BIGINT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- For existing installs, add the TOTP replay-prevention column:
--   ALTER TABLE admin_users
--     ADD COLUMN last_2fa_counter BIGINT NOT NULL DEFAULT 0 AFTER two_factor_enabled;

-- Trusted admin devices (skip TOTP step on opted-in devices for 30 days).
-- Split-token pattern: cookie holds "selector.validator"; DB stores selector
-- in plaintext (for O(1) lookup) and a SHA-256 hash of the validator so a DB
-- read cannot impersonate the user. The trust cookie ONLY bypasses TOTP;
-- the password step is still required on every login.
CREATE TABLE IF NOT EXISTS admin_trusted_devices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    selector CHAR(16) NOT NULL UNIQUE,
    validator_hash CHAR(64) NOT NULL,
    label VARCHAR(120),
    user_agent VARCHAR(255),
    ip_address VARCHAR(45),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    CONSTRAINT fk_atd_user FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    INDEX idx_atd_user_id (user_id),
    INDEX idx_atd_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create users table
CREATE TABLE IF NOT EXISTS community_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    bio TEXT,
    avatar VARCHAR(255),
    role VARCHAR(20) DEFAULT 'user',
    email_verified BOOLEAN DEFAULT 0,
    verification_code VARCHAR(10),
    reset_token VARCHAR(100),
    reset_token_expiry DATETIME,
    email_change_new_email VARCHAR(100) DEFAULT NULL,
    email_change_code VARCHAR(10) DEFAULT NULL,
    email_change_expires_at DATETIME DEFAULT NULL,
    email_change_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_login DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deletion_scheduled_at DATETIME DEFAULT NULL,
    email_pref_product_updates BOOLEAN NOT NULL DEFAULT 0,
    email_pref_tips_onboarding BOOLEAN NOT NULL DEFAULT 0,
    email_pref_reviews BOOLEAN NOT NULL DEFAULT 0,
    email_pref_promotions BOOLEAN NOT NULL DEFAULT 0,
    email_pref_community_digest BOOLEAN NOT NULL DEFAULT 0,
    email_pref_unsubscribe_token CHAR(48) DEFAULT NULL,
    UNIQUE KEY uk_email_pref_unsubscribe_token (email_pref_unsubscribe_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- For existing installs, add the email-change code columns (pending address,
-- code, expiry, guess count):
--   ALTER TABLE community_users
--     ADD COLUMN email_change_new_email VARCHAR(100) DEFAULT NULL AFTER reset_token_expiry,
--     ADD COLUMN email_change_code VARCHAR(10) DEFAULT NULL AFTER email_change_new_email,
--     ADD COLUMN email_change_expires_at DATETIME DEFAULT NULL AFTER email_change_code,
--     ADD COLUMN email_change_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER email_change_expires_at;

-- Create posts table
CREATE TABLE IF NOT EXISTS community_posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    user_name VARCHAR(50) NOT NULL,
    user_email VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    post_type VARCHAR(10) NOT NULL,
    status VARCHAR(20) DEFAULT 'open',
    votes INT DEFAULT 0,
    views INT DEFAULT 0,
    metadata TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES community_users(id) ON DELETE SET NULL,
    CHECK (post_type IN ('bug', 'feature')),
    CHECK (status IN ('open', 'in_progress', 'completed', 'declined'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create comments table
CREATE TABLE IF NOT EXISTS community_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    user_id INT,
    user_name VARCHAR(50) NOT NULL,
    user_email VARCHAR(100) NOT NULL,
    content TEXT NOT NULL,
    votes INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES community_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create votes table
CREATE TABLE IF NOT EXISTS community_votes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    user_id INT,
    user_email VARCHAR(100) NOT NULL,
    vote_type TINYINT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES community_users(id) ON DELETE SET NULL,
    UNIQUE KEY (post_id, user_email),
    CHECK (vote_type IN (-1, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- In-app notifications (e.g. @mentions in posts/comments). Written by
-- community/mentions/mentions.php when a user is mentioned.
CREATE TABLE IF NOT EXISTS user_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255),
    is_read BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES community_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create comment votes table
CREATE TABLE IF NOT EXISTS comment_votes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    comment_id INT NOT NULL,
    user_id INT,
    user_email VARCHAR(100) NOT NULL,
    vote_type TINYINT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (comment_id) REFERENCES community_comments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES community_users(id) ON DELETE SET NULL,
    UNIQUE KEY (comment_id, user_email),
    CHECK (vote_type IN (-1, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create post edit history
CREATE TABLE IF NOT EXISTS post_edit_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    user_id INT,
    title VARCHAR(255),
    content TEXT,
    metadata TEXT,
    edited_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES community_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create remember tokens table
CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES community_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin notification preferences (single global row, id is always 1).
-- One destination email plus a set of on/off toggles that every admin alert
-- consults. Managed from admin/settings/ (Notifications tab).
CREATE TABLE IF NOT EXISTS admin_notification_prefs (
    id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    notification_email VARCHAR(255) NOT NULL,
    notify_new_posts BOOLEAN NOT NULL DEFAULT 1,
    notify_new_comments BOOLEAN NOT NULL DEFAULT 1,
    notify_new_reports BOOLEAN NOT NULL DEFAULT 1,
    notify_new_customer BOOLEAN NOT NULL DEFAULT 1,
    notify_subscription_cancelled BOOLEAN NOT NULL DEFAULT 1,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_admin_notification_prefs_single_row CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create a view for user profiles
CREATE OR REPLACE VIEW community_user_profiles AS
SELECT 
    u.id,
    u.username,
    u.email,
    u.bio,
    u.avatar,
    u.role,
    u.created_at,
    COUNT(DISTINCT p.id) AS post_count,
    COUNT(DISTINCT c.id) AS comment_count
FROM
    community_users u
LEFT JOIN
    community_posts p ON u.id = p.user_id
LEFT JOIN
    community_comments c ON u.id = c.user_id
GROUP BY
    u.id, u.username, u.email, u.bio, u.avatar, u.role, u.created_at;

-- Create statistics table for more detailed tracking
CREATE TABLE IF NOT EXISTS statistics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_type VARCHAR(50) NOT NULL, -- 'download_avalonia', 'page_view', etc.
    event_data VARCHAR(255), -- Additional data like version, page, etc.
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    country_code VARCHAR(2),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at),
    INDEX idx_country_code (country_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create referral_links table for tracking ad/sponsor sources
CREATE TABLE IF NOT EXISTS referral_links (
    id INT PRIMARY KEY AUTO_INCREMENT,
    source_code VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(20) NOT NULL DEFAULT 'other' COMMENT 'A key from referral_categories() in referral_categories.php',
    description TEXT,
    target_url VARCHAR(500) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    INDEX idx_source_code (source_code),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create referral_visits table to track visits from referral sources
CREATE TABLE IF NOT EXISTS referral_visits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    source_code VARCHAR(100) NOT NULL,
    page_url VARCHAR(500),
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    country_code VARCHAR(2),
    visited_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    converted TINYINT(1) DEFAULT 0,
    license_key VARCHAR(255),
    INDEX idx_source_code (source_code),
    INDEX idx_visited_at (visited_at),
    INDEX idx_converted (converted),
    INDEX idx_country_code (country_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Content reports table
CREATE TABLE IF NOT EXISTS content_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reporter_user_id INT,
    reporter_email VARCHAR(100) NOT NULL,
    content_type ENUM('post', 'comment', 'user') NOT NULL,
    content_id INT NOT NULL,
    violation_type VARCHAR(50) NOT NULL,
    additional_info TEXT,
    status ENUM('pending', 'resolved', 'dismissed') DEFAULT 'pending',
    resolved_by INT,
    resolved_at DATETIME,
    resolution_action VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reporter_user_id) REFERENCES community_users(id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES community_users(id) ON DELETE SET NULL,
    INDEX idx_content_type_id (content_type, content_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User bans table
CREATE TABLE IF NOT EXISTS user_bans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    banned_by INT,
    ban_reason TEXT NOT NULL,
    ban_duration VARCHAR(20) NOT NULL,
    banned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    is_active BOOLEAN DEFAULT 1,
    unbanned_at DATETIME,
    unbanned_by INT,
    FOREIGN KEY (user_id) REFERENCES community_users(id) ON DELETE CASCADE,
    FOREIGN KEY (banned_by) REFERENCES community_users(id) ON DELETE SET NULL,
    FOREIGN KEY (unbanned_by) REFERENCES community_users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_is_active (is_active),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Premium Subscriptions table
CREATE TABLE IF NOT EXISTS premium_subscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subscription_id VARCHAR(50) NOT NULL UNIQUE,
    user_id INT DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    billing_cycle ENUM('monthly', 'yearly') NOT NULL DEFAULT 'monthly',
    amount DECIMAL(10,2) NOT NULL,
    signup_base_price DECIMAL(10,2) DEFAULT NULL COMMENT 'Base price (pre-processing-fee) locked at signup/cycle-switch. Renewals charge this so raising the env price never re-prices existing customers. NULL falls back to the current env price.',
    currency VARCHAR(3) NOT NULL DEFAULT 'CAD',
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    status ENUM('active', 'cancelled', 'expired', 'past_due', 'payment_failed') NOT NULL DEFAULT 'active',
    payment_method VARCHAR(50),
    transaction_id VARCHAR(100),
    payment_token VARCHAR(255) COMMENT 'Stored payment method token for recurring billing',
    stripe_customer_id VARCHAR(255) COMMENT 'Stripe customer ID for recurring billing',
    auto_renew TINYINT(1) DEFAULT 1 COMMENT 'Whether to auto-renew the subscription',
    paypal_subscription_id VARCHAR(100) COMMENT 'PayPal subscription ID for recurring billing',
    previous_paypal_subscription_id VARCHAR(100) DEFAULT NULL COMMENT 'Old PayPal sub-id during in-flight cycle switch; cancel webhook for this ID is ignored',
    discount_applied TINYINT(1) DEFAULT 0,
    credit_balance DECIMAL(10,2) DEFAULT 0 COMMENT 'Remaining credit balance from premium discount',
    original_credit DECIMAL(10,2) DEFAULT 0 COMMENT 'Original credit amount (to track if credit was used)',
    cancelled_at DATETIME DEFAULT NULL,
    last_cycle_change_at DATETIME DEFAULT NULL COMMENT 'Timestamp of most recent billing-cycle switch (used for cooldown + audit)',
    environment ENUM('production', 'sandbox') NOT NULL DEFAULT 'production' COMMENT 'Which environment created this row (APP_ENV at insert time). Admin dashboards filter by current env so dev test data does not pollute prod stats.',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_subscription_id (subscription_id),
    INDEX idx_user_id (user_id),
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_end_date (end_date),
    INDEX idx_renewal (status, end_date, auto_renew)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Premium Subscription Payments table
CREATE TABLE IF NOT EXISTS premium_subscription_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subscription_id VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'CAD',
    payment_method VARCHAR(50) NOT NULL,
    transaction_id VARCHAR(100),
    status ENUM('pending', 'completed', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    payment_type ENUM('initial', 'renewal', 'manual', 'credit', 'retry', 'cycle_change') DEFAULT 'initial' COMMENT 'Type of payment (credit = covered by credit balance, cycle_change = mid-cycle billing-cycle switch)',
    error_message TEXT NULL COMMENT 'Error message if payment failed',
    environment ENUM('production', 'sandbox') NOT NULL DEFAULT 'production' COMMENT 'Which environment created this row (APP_ENV at insert time). Admin dashboards filter by current env so dev test data does not pollute prod stats.',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_subscription_id (subscription_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_payment_type (payment_type),
    INDEX idx_env_status_created (environment, status, created_at),
    -- Prevents a concurrent duplicate gateway/webhook delivery from recording
    -- the same transaction twice (and double-extending end_date). NULLs allowed
    -- (failed/credit rows use NULL), and MySQL permits multiple NULLs.
    UNIQUE KEY uq_transaction_id (transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Premium Subscription Keys table (free/promo keys)
CREATE TABLE IF NOT EXISTS premium_subscription_keys (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subscription_key VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) DEFAULT NULL COMMENT 'Optional: restrict to specific email',
    duration_months INT NOT NULL DEFAULT 1 COMMENT 'Duration in months (0 = permanent)',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    redeemed_at DATETIME DEFAULT NULL,
    device_id VARCHAR(255) DEFAULT NULL COMMENT 'Hashed machine identifier of redeeming device',
    subscription_id VARCHAR(50) DEFAULT NULL COMMENT 'Link to created subscription',
    notes TEXT DEFAULT NULL COMMENT 'Admin notes about this key',
    customer_email VARCHAR(255) DEFAULT NULL COMMENT 'Buyer email captured in the app at redemption. Deliberately NOT the email column above: that one restricts who may redeem, this one is contact detail collected after the fact',
    customer_email_captured_at DATETIME DEFAULT NULL,
    customer_email_verified_at DATETIME DEFAULT NULL COMMENT 'Data quality flag only. Premium is never gated on this',
    customer_email_token CHAR(64) DEFAULT NULL COMMENT 'One-click verification link token',
    customer_email_source VARCHAR(30) DEFAULT NULL COMMENT 'Where the address came from, e.g. app_redemption',
    batch_label VARCHAR(60) DEFAULT NULL COMMENT 'Reseller batch this key was minted for, e.g. StackSocial Aug 2026. NULL for a hand-issued promo key',
    INDEX idx_subscription_key (subscription_key),
    INDEX idx_email (email),
    INDEX idx_redeemed (redeemed_at),
    INDEX idx_psk_customer_email (customer_email),
    INDEX idx_psk_batch_label (batch_label),
    UNIQUE KEY uk_psk_customer_email_token (customer_email_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes for license_keys table
CREATE INDEX idx_license_keys_transaction_id ON license_keys(transaction_id);
CREATE INDEX idx_license_keys_email ON license_keys(email);
CREATE INDEX idx_license_keys_payment_intent ON license_keys(payment_intent);

-- Add indexes for community tables
CREATE INDEX idx_users_username ON community_users(username);
CREATE INDEX idx_users_email ON community_users(email);
CREATE INDEX idx_posts_user_id ON community_posts(user_id);
CREATE INDEX idx_posts_user_email ON community_posts(user_email);
CREATE INDEX idx_posts_post_type ON community_posts(post_type);
CREATE INDEX idx_posts_status ON community_posts(status);
CREATE INDEX idx_posts_created_at ON community_posts(created_at);
CREATE INDEX idx_comments_post_id ON community_comments(post_id);
CREATE INDEX idx_comments_user_id ON community_comments(user_id);
CREATE INDEX idx_comments_user_email ON community_comments(user_email);
CREATE INDEX idx_comments_created_at ON community_comments(created_at);
CREATE INDEX idx_votes_post_id ON community_votes(post_id);
CREATE INDEX idx_votes_user_id ON community_votes(user_id);
CREATE INDEX idx_votes_user_email ON community_votes(user_email);
CREATE INDEX idx_comment_votes_comment_id ON comment_votes(comment_id);
CREATE INDEX idx_comment_votes_user_id ON comment_votes(user_id);
CREATE INDEX idx_comment_votes_user_email ON comment_votes(user_email);
CREATE INDEX idx_post_edit_history_post_id ON post_edit_history(post_id);
CREATE INDEX idx_remember_tokens_token ON remember_tokens(token);
CREATE INDEX idx_remember_tokens_user_id ON remember_tokens(user_id);

-- Receipt scan usage tracking table (for rate limiting Premium tier: 500 scans/month)
-- environment is part of the unique key: the free-tier row key is device_<hash>
-- with no environment component, so without it a scan made while testing against
-- dev would eat the same monthly allowance as real production usage on that device.
CREATE TABLE IF NOT EXISTS receipt_scan_usage (
    id INT PRIMARY KEY AUTO_INCREMENT,
    license_key VARCHAR(255) NOT NULL,
    usage_month DATE NOT NULL COMMENT 'First day of the month (e.g., 2025-01-01)',
    scan_count INT NOT NULL DEFAULT 0,
    monthly_limit INT NOT NULL DEFAULT 500,
    environment VARCHAR(10) NOT NULL DEFAULT 'production' COMMENT 'sandbox or production',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_license_month_env (license_key, usage_month, environment),
    INDEX idx_license_key (license_key),
    INDEX idx_usage_month (usage_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI import usage tracking (api/ai-import/usage.php). One row per identity, per
-- month, per environment. license_key holds either a real key or device_<hash>
-- for free users; bank-statement imports use the same row shape with a "bank:"
-- prefix on the identifier so they get a counter separate from spreadsheet imports.
CREATE TABLE IF NOT EXISTS ai_import_usage (
    id INT PRIMARY KEY AUTO_INCREMENT,
    license_key VARCHAR(255) NOT NULL COMMENT 'License key, device_<hash>, or either prefixed with "bank:"',
    usage_month DATE NOT NULL COMMENT 'First day of the month (e.g., 2025-01-01)',
    scan_count INT NOT NULL DEFAULT 0,
    monthly_limit INT NOT NULL DEFAULT 100,
    environment VARCHAR(10) NOT NULL DEFAULT 'production' COMMENT 'sandbox or production',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_license_month_env (license_key, usage_month, environment),
    INDEX idx_license_key (license_key),
    INDEX idx_usage_month (usage_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Customer Payment Portal Tables
-- ============================================

-- Companies (Argo Books businesses) registered for the payment portal
CREATE TABLE IF NOT EXISTS portal_companies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    api_key_hash VARCHAR(64) DEFAULT NULL COMMENT 'SHA-256 hash of the API key for secure lookup',
    company_name VARCHAR(255) NOT NULL,
    company_logo_url VARCHAR(500) DEFAULT NULL,
    -- Connected payment provider accounts (money goes to these, not to ArgoRobots)
    stripe_account_id VARCHAR(255) DEFAULT NULL COMMENT 'Stripe Connect account ID (set only once Express onboarding is complete)',
    stripe_pending_account_id VARCHAR(255) DEFAULT NULL COMMENT 'Stripe Express account created at Connect initiation; promoted to stripe_account_id when onboarding completes',
    stripe_email VARCHAR(255) DEFAULT NULL COMMENT 'Email on the connected Stripe account',
    paypal_merchant_id VARCHAR(255) DEFAULT NULL COMMENT 'PayPal merchant ID for marketplace',
    paypal_email VARCHAR(255) DEFAULT NULL COMMENT 'Email on the connected PayPal account',
    square_merchant_id VARCHAR(255) DEFAULT NULL COMMENT 'Square merchant ID',
    square_access_token VARCHAR(500) DEFAULT NULL COMMENT 'Square OAuth access token (encrypted at rest)',
    square_location_id VARCHAR(255) DEFAULT NULL COMMENT 'Square location ID',
    square_email VARCHAR(255) DEFAULT NULL COMMENT 'Email on the connected Square account',
    -- Metadata
    owner_email VARCHAR(100) DEFAULT NULL,
    email_verified_at DATETIME DEFAULT NULL COMMENT 'Set when the owner verifies the registration code; required for refunds/email-change',
    environment VARCHAR(10) DEFAULT 'sandbox' COMMENT 'sandbox or production',
    is_active TINYINT(1) DEFAULT 1,
    locked TINYINT(1) DEFAULT 0 COMMENT 'Auto-locked by the refund velocity engine on hard-block; manual review required to unlock',
    lock_reason VARCHAR(255) DEFAULT NULL,
    locked_at DATETIME DEFAULT NULL,
    -- Email preferences (set from Argo Books via PUT /api/portal/preferences)
    reminders_enabled TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Merchant opted in to automatic overdue-invoice reminders to their customers. Opt-in: defaulting this on would start emailing every existing company''s customers on deploy day',
    reminders_enabled_at DATETIME DEFAULT NULL COMMENT 'Re-stamped to NOW() on EVERY 0->1 transition of reminders_enabled. Only invoices whose due_date falls after this are ever chased, so enabling (or re-enabling after a long pause) never releases a backlog of old overdue invoices',
    notify_owner_on_payment TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Email the owner when a customer pays a portal invoice. Defaults on, so existing rows inherit it without a backfill. Also requires a verified owner_email',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_api_key_hash (api_key_hash),
    INDEX idx_is_active (is_active),
    INDEX idx_environment (environment),
    INDEX idx_locked (locked),
    INDEX idx_reminders_enabled (reminders_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- For existing installs, add the email preference columns:
--   ALTER TABLE portal_companies
--     ADD COLUMN reminders_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER locked_at,
--     ADD COLUMN reminders_enabled_at DATETIME DEFAULT NULL AFTER reminders_enabled,
--     ADD COLUMN notify_owner_on_payment TINYINT(1) NOT NULL DEFAULT 1 AFTER reminders_enabled_at,
--     ADD INDEX idx_reminders_enabled (reminders_enabled);

-- Google OAuth tokens (free feature, keyed by device ID)
CREATE TABLE IF NOT EXISTS google_oauth_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    device_id_hash VARCHAR(64) NOT NULL UNIQUE COMMENT 'SHA-256 hash of the device ID',
    google_refresh_token TEXT DEFAULT NULL,
    google_access_token TEXT DEFAULT NULL,
    google_token_expires DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Google OAuth state tokens for CSRF protection during Google auth flow
CREATE TABLE IF NOT EXISTS google_oauth_states (
    id INT PRIMARY KEY AUTO_INCREMENT,
    state_token VARCHAR(255) NOT NULL UNIQUE,
    device_id_hash VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_device_id_hash (device_id_hash),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OAuth state tokens for CSRF protection during provider connect flows
CREATE TABLE IF NOT EXISTS portal_oauth_states (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    provider VARCHAR(20) NOT NULL COMMENT 'stripe, paypal, or square',
    state_token VARCHAR(64) NOT NULL UNIQUE COMMENT 'CSRF state parameter',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL COMMENT 'State tokens expire after 10 minutes',
    INDEX idx_state_token (state_token),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (company_id) REFERENCES portal_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invoices published to the portal by Argo Books businesses
CREATE TABLE IF NOT EXISTS portal_invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    invoice_id VARCHAR(100) NOT NULL COMMENT 'Invoice number/ID from Argo Books (e.g., INV-0001)',
    invoice_token VARCHAR(48) NOT NULL UNIQUE COMMENT '48-char hex token for direct invoice access',
    customer_token VARCHAR(48) NOT NULL COMMENT '48-char hex token for customer portal access',
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) DEFAULT NULL,
    invoice_data JSON COMMENT 'Full invoice data (line items, addresses, tax, notes)',
    status ENUM('draft', 'sent', 'viewed', 'pending', 'partial', 'paid', 'overdue', 'cancelled') NOT NULL DEFAULT 'sent',
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    balance_due DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    due_date DATE DEFAULT NULL,
    pass_processing_fee TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Whether to add processing fee to online payments',
    external_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Total paid against this invoice OUTSIDE the portal (cash/cheque/bank, recorded in Argo Books). Stored server-side so POST /api/portal/invoices/balance can apply an idempotent relative delta instead of overwriting balance_due, which would race the atomic decrement in record_portal_payment()',
    environment VARCHAR(10) DEFAULT 'sandbox' COMMENT 'sandbox or production',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_company_invoice (company_id, invoice_id),
    INDEX idx_invoice_token (invoice_token),
    INDEX idx_customer_token (customer_token),
    INDEX idx_company_id (company_id),
    INDEX idx_status (status),
    INDEX idx_customer_email (customer_email),
    INDEX idx_due_date (due_date),
    INDEX idx_environment (environment),
    FOREIGN KEY (company_id) REFERENCES portal_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quotes published to the portal by Argo Books businesses.
-- Deliberately not rows in portal_invoices: a quote is not money owed, and the reminder
-- cron, the customer portal totals and the admin payment pages all read that table.
CREATE TABLE IF NOT EXISTS portal_quotes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    quote_id VARCHAR(100) NOT NULL COMMENT 'Quote ID from Argo Books (e.g., QUO-2026-00001)',
    quote_token VARCHAR(48) NOT NULL UNIQUE COMMENT '48-char hex token for the customer link',
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) DEFAULT NULL,
    quote_data JSON COMMENT 'Full quote data (line items, totals, notes, rendered HTML)',
    status ENUM('sent', 'accepted', 'declined', 'cancelled') NOT NULL DEFAULT 'sent',
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    valid_until DATE DEFAULT NULL,
    responded_at DATETIME DEFAULT NULL COMMENT 'When the customer accepted or declined',
    response_note TEXT DEFAULT NULL COMMENT 'Optional reason the customer gave when declining',
    synced_to_argo TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Set to 0 on a customer answer, back to 1 once the desktop app confirms it',
    environment VARCHAR(10) DEFAULT 'sandbox' COMMENT 'sandbox or production',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_company_quote (company_id, quote_id),
    INDEX idx_quote_token (quote_token),
    INDEX idx_company_id (company_id),
    INDEX idx_status (status),
    INDEX idx_synced_to_argo (synced_to_argo),
    INDEX idx_environment (environment),
    FOREIGN KEY (company_id) REFERENCES portal_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Answers to the one-question survey shown when Argo Books is uninstalled.
-- Nothing identifies the person: no account, no licence, no device id, and the page asks for
-- none. The version and platform come from the uninstaller's link.
CREATE TABLE IF NOT EXISTS uninstall_feedback (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reason VARCHAR(40) NOT NULL COMMENT 'One of the offered choices',
    comment TEXT DEFAULT NULL COMMENT 'Optional free text, capped at 1000 characters',
    app_version VARCHAR(20) DEFAULT NULL COMMENT 'Version being uninstalled, from the link',
    platform VARCHAR(20) DEFAULT NULL COMMENT 'windows or mac, from the link',
    environment VARCHAR(10) DEFAULT 'sandbox' COMMENT 'sandbox or production',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_reason (reason),
    INDEX idx_environment (environment)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments received through the portal
CREATE TABLE IF NOT EXISTS portal_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    invoice_id VARCHAR(100) NOT NULL COMMENT 'Invoice number/ID that was paid',
    customer_name VARCHAR(255) DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL,
    processing_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    payment_method VARCHAR(50) NOT NULL COMMENT 'stripe, paypal, or square',
    provider_payment_id VARCHAR(255) DEFAULT NULL COMMENT 'Payment ID from the payment provider',
    provider_transaction_id VARCHAR(255) DEFAULT NULL COMMENT 'Transaction/charge ID from the provider',
    reference_number VARCHAR(50) NOT NULL COMMENT 'Human-readable reference (PAY-YYYYMMDD-XXXXXX)',
    status ENUM('pending', 'completed', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    synced_to_argo TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether Argo Books has pulled this payment',
    payment_environment VARCHAR(10) DEFAULT NULL COMMENT 'sandbox or production',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_company_id (company_id),
    INDEX idx_invoice_id (invoice_id),
    INDEX idx_reference_number (reference_number),
    UNIQUE INDEX idx_provider_payment_id (provider_payment_id),
    INDEX idx_status (status),
    INDEX idx_synced (synced_to_argo),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (company_id) REFERENCES portal_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Overdue-invoice reminders sent to a merchant's customers by
-- cron/portal_invoice_reminders.php. Fixed cadence, three touches, then stop.
--
-- UNIQUE (portal_invoice_id, stage) IS the idempotency mechanism: the cron
-- claims a stage by INSERT IGNORE before sending, and a zero rowCount means
-- another run already owns it. A counter column on portal_invoices would need
-- read-modify-write plus a CAS guard and would still lose to an overlapping
-- run in the window between the send and the counter bump.
--
-- Rows are created lazily at send time, not pre-seeded when an invoice is
-- published: most invoices are paid on time and would never need their rows.
CREATE TABLE IF NOT EXISTS portal_invoice_reminders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    portal_invoice_id INT NOT NULL,
    company_id INT NOT NULL,
    stage TINYINT UNSIGNED NOT NULL COMMENT '1 = due+3 days, 2 = due+7, 3 = due+14. Fixed cadence, not user-configurable',
    status ENUM('sending', 'sent', 'failed', 'skipped') NOT NULL DEFAULT 'sending' COMMENT 'Claimed as sending, then finalized. A failed row is never retried: the next stage still fires on schedule, so a transient SMTP failure costs one touch rather than the whole sequence',
    halt_reason VARCHAR(100) DEFAULT NULL COMMENT 'Why a claimed row was skipped at send time: paid, cancelled, zero_balance, no_email, suppressed',
    error_message VARCHAR(255) DEFAULT NULL,
    due_date_at_send DATE DEFAULT NULL COMMENT 'Frozen for support: what the customer was actually chased about',
    balance_at_send DECIMAL(12,2) DEFAULT NULL,
    recipient_email VARCHAR(255) DEFAULT NULL,
    sent_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_invoice_stage (portal_invoice_id, stage),
    INDEX idx_company_sent (company_id, sent_at),
    FOREIGN KEY (portal_invoice_id) REFERENCES portal_invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES portal_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- For existing installs, add the external-payment column that the balance
-- reconciliation endpoint needs, then create the table above:
--   ALTER TABLE portal_invoices
--     ADD COLUMN external_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER pass_processing_fee;

-- ============================================
-- Refund Flow Tables
-- ============================================

-- A refund a merchant has initiated through Argo Books. State machine:
--   pending_code → code_verified → (cooling_off →)? processing → completed | failed
--   any state → cancelled (by owner or admin)
-- Webhooks finalize completed/failed transitions when the provider call is
-- async; the synchronous path can also finalize completed/failed for
-- providers that return a terminal status inline.
CREATE TABLE IF NOT EXISTS refund_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    invoice_id VARCHAR(100) NOT NULL,
    invoice_number VARCHAR(100) NOT NULL,
    customer_name VARCHAR(255) DEFAULT NULL,
    provider VARCHAR(20) NOT NULL COMMENT 'stripe, paypal, or square',
    provider_payment_id VARCHAR(255) NOT NULL COMMENT 'Original payment ID being refunded',
    provider_refund_id VARCHAR(255) DEFAULT NULL COMMENT 'Provider-returned refund ID; set on processing/completed',
    amount_cents INT NOT NULL,
    currency VARCHAR(3) NOT NULL,
    line_items_json JSON DEFAULT NULL COMMENT 'Snapshot of which invoice items the merchant chose to refund',
    reason VARCHAR(500) DEFAULT NULL,
    state ENUM('pending_code','code_verified','cooling_off','processing','completed','failed','cancelled') NOT NULL DEFAULT 'pending_code',
    state_reason VARCHAR(100) DEFAULT NULL COMMENT 'Free-text reason for the current state (e.g. hard_block, too_many_code_attempts)',
    velocity_tier ENUM('normal','soft_warn','delayed','hard_block') DEFAULT NULL,
    cooling_off_until DATETIME DEFAULT NULL,
    cancel_token CHAR(64) DEFAULT NULL COMMENT 'Token for the public cancel link in the cooling-off email',
    completed_at DATETIME DEFAULT NULL,
    requested_ip VARCHAR(45) DEFAULT NULL,
    requested_user_agent VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES portal_companies(id) ON DELETE CASCADE,
    INDEX idx_refund_company (company_id),
    INDEX idx_refund_state (state),
    INDEX idx_refund_company_state (company_id, state),
    INDEX idx_refund_created (created_at),
    INDEX idx_refund_cooling_off (state, cooling_off_until),
    INDEX idx_refund_cancel_token (cancel_token),
    INDEX idx_refund_provider_refund_id (provider_refund_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6-digit verification codes emailed to the company owner before a refund
-- can advance past pending_code. Codes are hashed (HMAC keyed by request id)
-- and capped at 5 attempts + 10-minute expiry.
CREATE TABLE IF NOT EXISTS refund_email_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    refund_request_id INT NOT NULL,
    code_hash CHAR(64) NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (refund_request_id) REFERENCES refund_requests(id) ON DELETE CASCADE,
    INDEX idx_refund_code_request (refund_request_id),
    INDEX idx_refund_code_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cached response bodies for Idempotency-Key replay on refund mutation
-- endpoints. Lookups are scoped per (company_id, idempotency_key); a match
-- with the same body_hash returns the cached response, a match with a
-- different body_hash returns 409. Rows expire 24h after creation
-- (enforced in the query, swept by the daily cron).
CREATE TABLE IF NOT EXISTS refund_idempotency_cache (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    idempotency_key VARCHAR(128) NOT NULL,
    body_hash CHAR(64) NOT NULL,
    response_status SMALLINT UNSIGNED NOT NULL,
    response_body LONGTEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES portal_companies(id) ON DELETE CASCADE,
    UNIQUE KEY uk_idempotency_company_key (company_id, idempotency_key),
    INDEX idx_idempotency_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-company (or global default with company_id IS NULL) thresholds for
-- the refund velocity engine. One row with company_id IS NULL provides
-- the global default; per-company rows override.
CREATE TABLE IF NOT EXISTS refund_velocity_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT DEFAULT NULL COMMENT 'NULL = global default',
    soft_warn_multiplier DECIMAL(6,2) NOT NULL DEFAULT 3.00 COMMENT 'Daily total >= multiplier * baseline daily avg → soft_warn',
    cooling_multiplier DECIMAL(6,2) NOT NULL DEFAULT 10.00 COMMENT 'Daily total >= multiplier * baseline daily avg → delayed',
    cooling_revenue_pct DECIMAL(6,4) NOT NULL DEFAULT 0.2500 COMMENT 'Daily total >= pct * 30d revenue → delayed',
    hard_revenue_pct DECIMAL(6,4) NOT NULL DEFAULT 0.5000 COMMENT 'Daily total >= pct * 30d revenue → hard_block',
    cooling_off_minutes INT NOT NULL DEFAULT 15 COMMENT 'Minutes the refund waits in cooling_off before promotion',
    new_account_floor_cents INT NOT NULL DEFAULT 500000 COMMENT 'First-week hard-block floor ($5000 default; must be > cooling)',
    new_account_soft_cents INT NOT NULL DEFAULT 50000 COMMENT 'First-week soft-warn floor ($500 default)',
    new_account_cooling_cents INT NOT NULL DEFAULT 100000 COMMENT 'First-week delayed floor ($1000 default; must be < hard)',
    young_account_floor_cents INT NOT NULL DEFAULT 1000000 COMMENT 'Days 7-30 hard-block floor ($10000 default; must be > cooling)',
    young_account_soft_cents INT NOT NULL DEFAULT 100000 COMMENT 'Days 7-30 soft-warn floor ($1000 default)',
    young_account_cooling_cents INT NOT NULL DEFAULT 300000 COMMENT 'Days 7-30 delayed floor ($3000 default; must be < hard)',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES portal_companies(id) ON DELETE CASCADE,
    UNIQUE KEY uk_velocity_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Materialized baseline stats per company that the velocity engine compares
-- against on established accounts (>30 days old). Refreshed nightly by cron.
CREATE TABLE IF NOT EXISTS refund_velocity_baselines (
    company_id INT PRIMARY KEY,
    daily_avg_refund_cents BIGINT NOT NULL DEFAULT 0 COMMENT 'Trailing-90d daily average refund amount in cents',
    revenue_30d_cents BIGINT NOT NULL DEFAULT 0 COMMENT 'Trailing-30d gross payment revenue in cents',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES portal_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only audit log for every state-mutating refund + email-change event.
-- Used by the admin payments page (recent activity), the refund cron
-- (stale-processing detection), and the customer-portal owner page (history).
CREATE TABLE IF NOT EXISTS refund_audit_log (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    refund_request_id INT DEFAULT NULL,
    email_change_request_id INT DEFAULT NULL,
    event_type VARCHAR(60) NOT NULL,
    payload_json JSON DEFAULT NULL,
    actor_type VARCHAR(20) NOT NULL COMMENT 'owner | admin | system | webhook',
    actor_id VARCHAR(64) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES portal_companies(id) ON DELETE CASCADE,
    INDEX idx_audit_company (company_id),
    INDEX idx_audit_refund_request (refund_request_id),
    INDEX idx_audit_email_change (email_change_request_id),
    INDEX idx_audit_event_type (event_type),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email-change requests for the portal owner. 4-step flow:
--   pending → (verify old email code) → old_verified
--          → (verify new email code) → completed (+ 30-day revert window)
--          → reverted (anytime within revert_until window)
-- Verification codes are stored on this row directly (one per leg) with
-- per-leg expiry + attempt counters that mirror the refund code flow.
CREATE TABLE IF NOT EXISTS email_change_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    old_email VARCHAR(255) NOT NULL,
    new_email VARCHAR(255) NOT NULL,
    password_verified TINYINT(1) NOT NULL DEFAULT 0,
    state ENUM('pending','old_verified','completed','cancelled','reverted') NOT NULL DEFAULT 'pending',
    old_email_code_hash CHAR(64) DEFAULT NULL,
    old_email_code_expires_at DATETIME DEFAULT NULL,
    old_email_code_attempts INT NOT NULL DEFAULT 0,
    old_email_verified_at DATETIME DEFAULT NULL,
    new_email_code_hash CHAR(64) DEFAULT NULL,
    new_email_code_expires_at DATETIME DEFAULT NULL,
    new_email_code_attempts INT NOT NULL DEFAULT 0,
    new_email_verified_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    cancel_token CHAR(64) DEFAULT NULL COMMENT 'Revert token for the 30-day window after completion',
    revert_until DATETIME DEFAULT NULL,
    reverted_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES portal_companies(id) ON DELETE CASCADE,
    INDEX idx_echange_company (company_id),
    INDEX idx_echange_state (state),
    INDEX idx_echange_cancel_token (cancel_token),
    INDEX idx_echange_completed (completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the global default for the velocity engine on a fresh install.
INSERT IGNORE INTO refund_velocity_config
    (company_id, soft_warn_multiplier, cooling_multiplier, cooling_revenue_pct, hard_revenue_pct,
     cooling_off_minutes, new_account_floor_cents, new_account_soft_cents, new_account_cooling_cents,
     young_account_floor_cents, young_account_soft_cents, young_account_cooling_cents)
VALUES
    (NULL, 3.00, 10.00, 0.2500, 0.5000, 15, 500000, 50000, 100000, 1000000, 100000, 300000);

-- Verification codes used during portal registration and "set initial email" flows.
-- One-row-per-issued-code; the most-recent unconsumed row for a given
-- (company_id, purpose) is the active code. Capped at 5 attempts + 10-minute
-- expiry, with a rolling rate-limit enforced in PHP.
CREATE TABLE IF NOT EXISTS email_verifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    purpose VARCHAR(40) NOT NULL DEFAULT 'registration',
    code_hash CHAR(64) NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES portal_companies(id) ON DELETE CASCADE,
    INDEX idx_emailver_company_purpose (company_id, purpose),
    INDEX idx_emailver_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invoice send usage tracking for free-tier limits
-- environment is part of the unique key for the same reason as receipt_scan_usage
-- and ai_import_usage: the free-tier row key is device_<hash> with no environment
-- component, so without it a send made while testing against dev would eat the
-- same monthly allowance as real production usage on that device.
CREATE TABLE IF NOT EXISTS invoice_send_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(255) NOT NULL COMMENT 'License key or device_<hash> for free users',
    usage_month DATE NOT NULL,
    send_count INT NOT NULL DEFAULT 0,
    monthly_limit INT NOT NULL DEFAULT 5,
    environment VARCHAR(10) NOT NULL DEFAULT 'production' COMMENT 'sandbox or production',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_license_month_env (license_key, usage_month, environment)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exchange rates cache (persistent: historical rates never change)
CREATE TABLE IF NOT EXISTS exchange_rates (
    rate_date DATE NOT NULL,
    rates JSON NOT NULL COMMENT 'All currency rates relative to USD for this date',
    fetched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (rate_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Outreach CRM Tables
-- ============================================

-- Outreach leads for business discovery and outreach tracking
CREATE TABLE IF NOT EXISTS outreach_leads (
    id INT PRIMARY KEY AUTO_INCREMENT,
    business_name VARCHAR(255) NOT NULL,
    contact_name VARCHAR(255) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    website VARCHAR(500) DEFAULT NULL,
    address VARCHAR(500) DEFAULT NULL,
    category VARCHAR(100) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    source VARCHAR(100) DEFAULT 'manual',
    status ENUM('new','draft_generated','approved','contacted','replied','interested','not_interested','onboarded','email_bounced','disqualified') DEFAULT 'new',
    disqualified_reason VARCHAR(80) DEFAULT NULL,
    response_status ENUM('no_response','positive','neutral','negative') DEFAULT 'no_response',
    approval_status ENUM('not_drafted','draft_ready','needs_review','approved','sent') DEFAULT 'not_drafted',
    date_added DATETIME DEFAULT CURRENT_TIMESTAMP,
    first_contact_date DATETIME DEFAULT NULL,
    last_contact_date DATETIME DEFAULT NULL,
    followup_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_followup_at DATETIME DEFAULT NULL,
    next_followup_due_at DATETIME DEFAULT NULL,
    original_message_id VARCHAR(255) DEFAULT NULL,
    offer_sent TINYINT(1) DEFAULT 0,
    notes TEXT DEFAULT NULL,
    feedback_summary TEXT DEFAULT NULL,
    draft_subject VARCHAR(500) DEFAULT NULL,
    draft_body TEXT DEFAULT NULL,
    drafted_at DATETIME DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    sent_at DATETIME DEFAULT NULL,
    unsubscribe_token VARCHAR(64) UNIQUE DEFAULT NULL,
    contact_page_url VARCHAR(500) DEFAULT NULL,
    places_id VARCHAR(255) DEFAULT NULL,
    company_size ENUM('small','medium','large') DEFAULT NULL,
    country CHAR(2) DEFAULT 'CA',
    business_summary TEXT DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_outreach_status (status),
    INDEX idx_outreach_city (city),
    INDEX idx_outreach_approval (approval_status),
    INDEX idx_outreach_company_size (company_size),
    INDEX idx_unsubscribe_token (unsubscribe_token),
    INDEX idx_outreach_followup_due (next_followup_due_at, status, followup_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
--
-- Existing installs also need 'email_bounced' added to the status ENUM
-- so the Resend webhook can flag bounced/complained recipients:
--   ALTER TABLE outreach_leads
--     MODIFY COLUMN status ENUM('new','draft_generated','approved','contacted','replied','interested','not_interested','onboarded','email_bounced') DEFAULT 'new';
--
-- For existing installs, add the auto-filter "disqualified" status + reason tag.
-- The outreach pipeline marks leads disqualified when a chain-domain / place-type
-- / role-mailbox / AI size-gate filter fires, so they never get drafted or sent:
--   ALTER TABLE outreach_leads
--     MODIFY COLUMN status ENUM('new','draft_generated','approved','contacted','replied','interested','not_interested','onboarded','email_bounced','disqualified') DEFAULT 'new',
--     ADD COLUMN disqualified_reason VARCHAR(80) DEFAULT NULL AFTER status;

-- Email suppression list (unsubscribes, opt-outs across all email contexts)
-- Known context values:
--   'outreach'           - cold outreach campaign
--   'reviews'            - review-request emails to license-key holders
--   'product_updates'    - release notes / feature announcements
--   'tips_onboarding'    - how-to / getting-started nudges
--   'promotions'         - discount codes / upsells
--   'community_digest'   - replies / activity digest
--   'newsletter'         - opt-in list for no-account subscribers (marketing_subscribers)
--   'all_marketing'      - blanket suppression of all marketing contexts
--   'portal'             - a merchant's customer, suppressed from overdue-invoice
--                          reminders (cron/portal_invoice_reminders.php). Read but
--                          not yet written: wiring the Resend webhook to record hard
--                          bounces here is the intended follow-up.
CREATE TABLE IF NOT EXISTS email_suppressions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    context VARCHAR(50) NOT NULL DEFAULT 'outreach',
    reason VARCHAR(255) DEFAULT NULL,
    source_id INT DEFAULT NULL,
    suppressed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_email_context (email, context),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log of marketing emails actually sent (for debugging and de-duplication)
CREATE TABLE IF NOT EXISTS email_marketing_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    context VARCHAR(50) NOT NULL,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    related_id INT DEFAULT NULL COMMENT 'license_keys.id for review emails, etc.',
    INDEX idx_email_context (email, context),
    INDEX idx_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inbound, opt-in marketing subscribers WITHOUT a community account (e.g. people
-- who tick the "email me tips & updates" box on the free Profit Analyzer). Double
-- opt-in: a row starts 'pending' and only becomes 'confirmed' after the visitor
-- clicks the confirm link. Broadcasts go to 'confirmed' rows only. Community-user
-- marketing still lives in community_users.email_pref_* (this table is for people
-- with no account). The send-time gate (should_send_marketing_email) treats the
-- 'newsletter' context as: confirmed row here AND not in email_suppressions.
CREATE TABLE IF NOT EXISTS marketing_subscribers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    context VARCHAR(50) NOT NULL DEFAULT 'newsletter',
    status ENUM('pending','confirmed','unsubscribed') NOT NULL DEFAULT 'pending',
    source VARCHAR(50) NOT NULL DEFAULT 'profit_analyzer',
    confirm_token VARCHAR(64) DEFAULT NULL,
    unsubscribe_token VARCHAR(64) DEFAULT NULL,
    ip VARCHAR(45) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    confirmed_at DATETIME DEFAULT NULL,
    unsubscribed_at DATETIME DEFAULT NULL,
    UNIQUE KEY unique_email_context (email, context),
    INDEX idx_confirm_token (confirm_token),
    INDEX idx_unsubscribe_token (unsubscribe_token),
    INDEX idx_status_context (status, context)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin-composed one-off broadcasts (the "email my list" tool). One row per send.
-- audience names which list this went to: 'newsletter' (marketing_subscribers) or a
-- community context like 'product_updates' / 'promotions' / 'tips_onboarding'
-- (community_users.email_pref_*). Recipients are snapshotted into
-- marketing_broadcast_recipients at queue time; a cron drains them in batches.
CREATE TABLE IF NOT EXISTS marketing_broadcasts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subject VARCHAR(500) NOT NULL,
    html_body MEDIUMTEXT NOT NULL,
    audience VARCHAR(50) NOT NULL,
    status ENUM('queued','sending','sent','canceled') NOT NULL DEFAULT 'queued',
    total_recipients INT NOT NULL DEFAULT 0,
    sent_count INT NOT NULL DEFAULT 0,
    failed_count INT NOT NULL DEFAULT 0,
    skipped_count INT NOT NULL DEFAULT 0,
    created_by VARCHAR(100) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    INDEX idx_broadcast_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-recipient rows for a broadcast. Snapshotted at queue time so the audience is
-- frozen even if the underlying lists change mid-send. The cron picks 'pending'
-- rows, re-checks the send-time gate (so a since-unsubscribed person is skipped),
-- sends, then marks the row. UNIQUE(broadcast_id,email) makes re-queues idempotent.
CREATE TABLE IF NOT EXISTS marketing_broadcast_recipients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    broadcast_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    status ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
    error VARCHAR(255) DEFAULT NULL,
    sent_at DATETIME DEFAULT NULL,
    UNIQUE KEY unique_broadcast_email (broadcast_id, email),
    INDEX idx_recipient_pending (broadcast_id, status),
    FOREIGN KEY (broadcast_id) REFERENCES marketing_broadcasts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity log for outreach leads
CREATE TABLE IF NOT EXISTS outreach_activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lead_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    details TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES outreach_leads(id) ON DELETE CASCADE,
    INDEX idx_outreach_activity_lead (lead_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-event delivery / engagement records from the Resend webhook
-- (webhooks/resend.php). One row per (message_id, event_type); the unique key
-- gives idempotency on Svix retries.
CREATE TABLE IF NOT EXISTS outreach_email_events (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    lead_id INT NOT NULL,
    event_type VARCHAR(40) NOT NULL,
    message_id VARCHAR(255) DEFAULT NULL,
    occurred_at DATETIME NOT NULL,
    raw_payload TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES outreach_leads(id) ON DELETE CASCADE,
    UNIQUE KEY unique_message_event (message_id, event_type),
    INDEX idx_email_events_lead_type (lead_id, event_type),
    INDEX idx_email_events_occurred (occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────
-- outreach_followups
-- One row per scheduled follow-up touch (touch 1 = original first-touch
-- email, lives in outreach_leads). Created in bulk when first-touch send
-- succeeds (one row per configured touch in followup_sequence_config).
--
-- State machine:
--   scheduled  →  drafted  →  approved  →  sent
--      └─→ halted (replied/unsubscribed/bounced/manual/max_reached)
--      └─→ skipped (admin clicked skip on the drafted row)
--      └─→ failed  (Gemini call failed 3 times)
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS outreach_followups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lead_id INT NOT NULL,
    touch_number TINYINT UNSIGNED NOT NULL,
    scheduled_for DATETIME NOT NULL,
    draft_subject VARCHAR(500) DEFAULT NULL,
    draft_body TEXT DEFAULT NULL,
    drafted_at DATETIME DEFAULT NULL,
    draft_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('scheduled','drafted','approved','sent','halted','skipped','failed') NOT NULL DEFAULT 'scheduled',
    halt_reason VARCHAR(100) DEFAULT NULL,
    sent_at DATETIME DEFAULT NULL,
    message_id VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES outreach_leads(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_lead_touch (lead_id, touch_number),
    INDEX idx_status_scheduled (status, scheduled_for)
    -- (no separate idx_lead; uniq_lead_touch already covers lookups by lead_id
    -- via the leftmost-prefix rule, and InnoDB implements UNIQUE as a B-tree)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- For existing installs (run alongside the ALTER above):
--   (the CREATE TABLE IF NOT EXISTS above is safe to re-run.)

-- Cache of email-scrape results keyed by website URL. The same business often
-- shows up under multiple search categories (e.g. "spa" and "massage
-- therapists"), and many sites have no scrape-able email at all. Without this
-- cache the cron re-downloads the same dead-ends every run. A NULL email is a
-- valid cached result meaning "we tried, found nothing"; entries refresh after
-- 30 days so sites that later add an email get re-checked.
CREATE TABLE IF NOT EXISTS outreach_scrape_cache (
    id INT PRIMARY KEY AUTO_INCREMENT,
    url VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) DEFAULT NULL,
    last_attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_scrape_attempted (last_attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tracks Shopify storefronts discovered via SerpAPI dorking.
-- Acts as a dedup cache + reject-reason audit log so SerpAPI quota
-- isn't wasted re-evaluating known stores. Fit candidates are imported
-- into outreach_leads (source='shopify_auto') and linked via lead_id.
CREATE TABLE IF NOT EXISTS outreach_shopify_candidates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    canonical_url VARCHAR(500) NOT NULL,
    myshopify_url VARCHAR(500) DEFAULT NULL,
    status ENUM('imported','rejected','error','pending') NOT NULL DEFAULT 'pending',
    reject_reason VARCHAR(100) DEFAULT NULL,
    reject_detail VARCHAR(500) DEFAULT NULL,
    products_count INT DEFAULT NULL,
    first_product_created_at DATETIME DEFAULT NULL,
    detected_country VARCHAR(8) DEFAULT NULL,
    harvested_email VARCHAR(255) DEFAULT NULL,
    lead_id INT DEFAULT NULL,
    last_query VARCHAR(255) DEFAULT NULL,
    checked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_canonical_url (canonical_url),
    INDEX idx_status_checked (status, checked_at),
    INDEX idx_lead (lead_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Candidate roundup/listicle articles for the Editorial outreach channel. One
-- row per article URL (identity is the article, not the outlet), tracking
-- fitness + dedup so discovery re-runs skip already-imported articles. Mirrors
-- outreach_shopify_candidates.
CREATE TABLE IF NOT EXISTS outreach_editorial_candidates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    canonical_url VARCHAR(500) NOT NULL,
    status ENUM('imported','rejected','error','pending') NOT NULL DEFAULT 'pending',
    reject_reason VARCHAR(100) DEFAULT NULL,
    reject_detail VARCHAR(500) DEFAULT NULL,
    outlet_name VARCHAR(150) DEFAULT NULL,
    author_name VARCHAR(120) DEFAULT NULL,
    harvested_email VARCHAR(255) DEFAULT NULL,
    lead_id INT DEFAULT NULL,
    last_query VARCHAR(255) DEFAULT NULL,
    checked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_canonical_url (canonical_url),
    INDEX idx_status_checked (status, checked_at),
    INDEX idx_lead (lead_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dedup/audit table for the Creators / affiliate-partner discovery channel
-- (YouTubers, newsletter writers, niche bloggers, and LinkedIn profiles found by
-- cron/lib/creator_discovery.php). Mirrors outreach_editorial_candidates: one row
-- per canonical creator URL, status tracks imported/rejected so repeat runs skip
-- already-handled candidates. platform records youtube/newsletter/blog/linkedin.
CREATE TABLE IF NOT EXISTS outreach_creator_candidates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    canonical_url VARCHAR(500) NOT NULL,
    status ENUM('imported','rejected','error','pending') NOT NULL DEFAULT 'pending',
    reject_reason VARCHAR(100) DEFAULT NULL,
    reject_detail VARCHAR(500) DEFAULT NULL,
    platform VARCHAR(20) DEFAULT NULL,
    creator_name VARCHAR(150) DEFAULT NULL,
    harvested_email VARCHAR(255) DEFAULT NULL,
    lead_id INT DEFAULT NULL,
    last_query VARCHAR(255) DEFAULT NULL,
    checked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_canonical_url (canonical_url),
    INDEX idx_status_checked (status, checked_at),
    INDEX idx_lead (lead_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Key/value state for the outreach pipeline: master kill-switch
-- (outreach_enabled), auto-send mode, follow-up sequence config, and other
-- runtime flags. Read/written via getState/setState in cron/outreach_pipeline.php
-- and the admin Settings tab (admin/outreach/tabs/settings.php).
CREATE TABLE IF NOT EXISTS outreach_pipeline_state (
    id INT AUTO_INCREMENT PRIMARY KEY,
    state_key VARCHAR(100) NOT NULL UNIQUE,
    state_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14-day cache of SerpAPI organic-results responses. The Shopify discovery
-- cron rotates through ~12 dork queries on a daily schedule, and Google's
-- site:myshopify.com results for these queries change slowly. Caching cuts
-- SerpAPI credit burn substantially with no measurable impact on lead
-- discovery. Cache hits do NOT increment serpapi_calls_today.
CREATE TABLE IF NOT EXISTS serpapi_response_cache (
    id INT PRIMARY KEY AUTO_INCREMENT,
    query_hash CHAR(64) NOT NULL,
    query_text VARCHAR(500) NOT NULL,
    response_json MEDIUMTEXT NOT NULL,
    fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_query_hash (query_hash),
    INDEX idx_fetched_at (fetched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-run audit trail for every cron job. Each cron calls cron_run_start
-- at the top, increments named metrics throughout its work
-- (cron_metric_incr / cron_metric_set in cron/lib/run_tracker.php), then
-- calls cron_run_finish on exit. The /admin/crons page reads this table
-- to render a dashboard of cron activity over a chosen time range.
CREATE TABLE IF NOT EXISTS cron_runs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cron_name VARCHAR(100) NOT NULL,
    started_at DATETIME NOT NULL,
    completed_at DATETIME DEFAULT NULL,
    status ENUM('running','ok','error') NOT NULL DEFAULT 'running',
    error_message TEXT DEFAULT NULL,
    metrics JSON DEFAULT NULL,
    INDEX idx_cron_started (cron_name, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-visitor funnel event log. Captures every step of the referral funnel
-- (landing -> downloads_page -> download_click -> app_first_run ->
-- premium_signup -> premium_paid -> premium_churned). The argo_visitor_id
-- cookie ties events together across sessions; on Premium signup all prior
-- events for that visitor get backfilled with subscription_id + user_id so
-- attribution survives a logged-out browse-to-buy flow.
CREATE TABLE IF NOT EXISTS referral_events (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    visitor_id CHAR(36) DEFAULT NULL COMMENT 'UUID from argo_visitor_id cookie; NULL for unattributed app_first_run events',
    source_code VARCHAR(100) DEFAULT NULL,
    event_type ENUM(
        'landing','downloads_page','download_click','app_first_run',
        'premium_signup','premium_paid','premium_churned'
    ) NOT NULL,
    event_data JSON DEFAULT NULL,
    subscription_id VARCHAR(50) DEFAULT NULL,
    user_id INT DEFAULT NULL,
    page_url VARCHAR(500) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    country_code VARCHAR(2) DEFAULT NULL,
    region VARCHAR(100) DEFAULT NULL COMMENT 'Geo region/state resolved from IP via ipinfo.io (best-effort).',
    city VARCHAR(100) DEFAULT NULL COMMENT 'Geo city resolved from IP via ipinfo.io (best-effort).',
    keyword VARCHAR(150) DEFAULT NULL COMMENT 'Search/campaign keyword captured from ?utm_term on the landing event.',
    source_survey_answer VARCHAR(20) DEFAULT NULL COMMENT 'On app_first_run rows only: user answer to "Where did you hear about us?". One of: google, bing, youtube, reddit, friend, email, other.',
    source_survey_other_text VARCHAR(200) DEFAULT NULL COMMENT 'Freeform user input when source_survey_answer = "other".',
    source_survey_answered_at DATETIME DEFAULT NULL,
    js_confirmed TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Page-view events (landing, downloads_page) start 0 and are set to 1 by a client-side JS beacon (api/referral/confirm.php), which filters out headless bots that never run JS. Non-page-view events are inserted as 1.',
    environment ENUM('production','sandbox') NOT NULL DEFAULT 'production',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_visitor (visitor_id),
    INDEX idx_source_event_created (source_code, event_type, created_at),
    INDEX idx_event_created (event_type, created_at),
    INDEX idx_visitor_confirm (visitor_id, event_type, js_confirmed),
    INDEX idx_subscription (subscription_id),
    INDEX idx_env_created (environment, created_at),
    INDEX idx_ip (ip_address),
    INDEX idx_survey_answer (source_survey_answer, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Manual monthly ad-spend entry per source. Used to compute Customer
-- Acquisition Cost (CAC) and LTV:CAC ratio in the admin funnel UI. Period
-- granularity is one calendar month; period_start is always YYYY-MM-01.
CREATE TABLE IF NOT EXISTS campaign_spend (
    id INT PRIMARY KEY AUTO_INCREMENT,
    source_code VARCHAR(100) NOT NULL,
    period_start DATE NOT NULL COMMENT 'First day of month (YYYY-MM-01)',
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'CAD',
    notes TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_source_period (source_code, period_start),
    INDEX idx_period (period_start),
    FOREIGN KEY (source_code) REFERENCES referral_links(source_code) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- Affiliate program
-- ============================================================

-- Affiliates: an existing community_users member who owns a referral source_code
-- and earns commission on subscriptions attributed to it. The referral_links row
-- for source_code is created on approval, so the link is dead until approved.
CREATE TABLE IF NOT EXISTS affiliates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    source_code VARCHAR(100) NOT NULL UNIQUE COMMENT 'The referral_links.source_code this affiliate owns (e.g. aff-username)',
    status ENUM('pending', 'approved', 'rejected', 'suspended') NOT NULL DEFAULT 'pending',
    commission_rate DECIMAL(5,4) NOT NULL DEFAULT 0.5000 COMMENT '0.5000 = 50%. Per-affiliate so terms can be overridden later.',
    commission_window_months INT NOT NULL DEFAULT 12 COMMENT 'Months from each subscription start_date during which payments earn commission',
    payout_method VARCHAR(20) NOT NULL DEFAULT 'paypal',
    payout_email VARCHAR(255) DEFAULT NULL COMMENT 'PayPal (or other) email for external payout',
    application_reason TEXT DEFAULT NULL COMMENT 'Why they want to join / audience info',
    promo_url VARCHAR(500) DEFAULT NULL COMMENT 'Where they will promote (channel/site)',
    applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME DEFAULT NULL,
    reviewed_by VARCHAR(50) DEFAULT NULL COMMENT 'admin username that approved or rejected',
    review_notes TEXT DEFAULT NULL,
    environment ENUM('production', 'sandbox') NOT NULL DEFAULT 'production' COMMENT 'APP_ENV at insert time; reads filter by current env so sandbox test data does not pollute prod totals.',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_env (user_id, environment),
    INDEX idx_status (status),
    INDEX idx_source_code (source_code),
    FOREIGN KEY (user_id) REFERENCES community_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Affiliate payouts: manual ledger of money actually paid out to an affiliate.
-- Commission earned is computed on the fly from premium_subscription_payments;
-- owed = earned - SUM(affiliate_payouts.amount).
CREATE TABLE IF NOT EXISTS affiliate_payouts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    affiliate_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'CAD',
    paid_at DATE NOT NULL COMMENT 'Date the payout was actually sent externally',
    method VARCHAR(20) NOT NULL DEFAULT 'paypal',
    reference VARCHAR(255) DEFAULT NULL COMMENT 'External txn id / PayPal reference',
    notes TEXT DEFAULT NULL,
    recorded_by VARCHAR(50) DEFAULT NULL COMMENT 'admin username who recorded it',
    environment ENUM('production', 'sandbox') NOT NULL DEFAULT 'production',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_affiliate (affiliate_id),
    INDEX idx_paid_at (paid_at),
    FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-call timing of desktop AI operations (receipt scan, bank categorize, supplier
-- suggestion, spreadsheet analysis/processing via /api/ai/completions.php, and bank
-- PDF extraction via /api/bank/extract.php). elapsed_ms is the SERVER-measured Gemini
-- wall time, isolated from the user's network. The desktop app fetches aggregated
-- p50/p90 priors from /api/ai/timing-priors.php to drive accurate, smooth progress
-- bars. No user/device identity is stored (not needed for duration priors). Also
-- created lazily by api/ai/_timing.php so a fresh server works without a migration.
CREATE TABLE IF NOT EXISTS ai_call_timings (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    operation VARCHAR(40) NOT NULL COMMENT 'receipt_scan, bank_categorize, supplier_category, spreadsheet_analysis, spreadsheet_process, bank_pdf_extract, completion',
    model VARCHAR(50) NOT NULL,
    size_feature INT DEFAULT NULL COMMENT 'Op-specific size hint: image bytes, line count, column count, pdf bytes',
    page_count INT DEFAULT NULL COMMENT 'PDFs only',
    input_bytes INT DEFAULT NULL,
    mime VARCHAR(30) DEFAULT NULL,
    prompt_tokens INT DEFAULT NULL,
    output_tokens INT DEFAULT NULL,
    max_output_tokens INT DEFAULT NULL,
    finish_reason VARCHAR(20) DEFAULT NULL COMMENT 'Non-STOP rows are excluded from priors',
    elapsed_ms INT NOT NULL COMMENT 'Server-measured Gemini generate wall time',
    poll_count INT DEFAULT NULL COMMENT 'PDF Files-API polls until ACTIVE',
    success TINYINT(1) NOT NULL DEFAULT 1,
    app_platform VARCHAR(20) DEFAULT NULL,
    environment ENUM('production','sandbox') NOT NULL DEFAULT 'production',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_op_model_created (operation, model, created_at),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== Mobile sync (phone pairing + E2E-encrypted snapshot/queue) =====

-- Short-lived pairing tokens shown in the desktop QR, redeemed once by the phone.
-- Also supports the short-code pairing flow: a phone-entered code (short_code) claims
-- the row, uploads its public key, and the desktop delivers the RSA-encrypted sync key
-- back down (phone_public_key / encrypted_sync_key are opaque ciphertext to the server).
CREATE TABLE IF NOT EXISTS mobile_sync_pairings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pairing_token VARCHAR(64) NOT NULL UNIQUE COMMENT 'bin2hex(random_bytes(16))',
    short_code VARCHAR(16) NULL COMMENT '8 chars from 23456789ABCDEFGHJKMNPQRSTVWXYZ, entered manually by the phone',
    owner_identity_hash VARCHAR(64) NOT NULL COMMENT 'sha256 license key hash (premium) or device id hash (free)',
    company_uid VARCHAR(64) NOT NULL COMMENT 'opaque per-company id generated by the desktop',
    company_label VARCHAR(255) NOT NULL DEFAULT '',
    phone_public_key TEXT NULL COMMENT 'ephemeral RSA public key uploaded by the phone on claim',
    encrypted_sync_key TEXT NULL COMMENT 'RSA-OAEP ciphertext of the sync key, opaque to the server',
    device_token_hash VARCHAR(64) NULL COMMENT 'sha256 of the device token minted on claim',
    device_label VARCHAR(255) NOT NULL DEFAULT '',
    status VARCHAR(16) NOT NULL DEFAULT 'pending' COMMENT 'pending -> claimed -> delivered',
    claimed_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_expires_at (expires_at),
    UNIQUE KEY uq_short_code (short_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Paired phones. One row per phone per company.
CREATE TABLE IF NOT EXISTS mobile_sync_devices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    device_token_hash VARCHAR(64) NOT NULL UNIQUE COMMENT 'sha256 of the long-lived device token',
    owner_identity_hash VARCHAR(64) NOT NULL,
    company_uid VARCHAR(64) NOT NULL,
    device_label VARCHAR(255) NOT NULL DEFAULT '',
    last_seen_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_company_uid (company_uid),
    INDEX idx_owner_identity_hash (owner_identity_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One current encrypted read-model snapshot per company. Ciphertext is opaque to the server.
CREATE TABLE IF NOT EXISTS mobile_sync_snapshots (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_uid VARCHAR(64) NOT NULL,
    owner_identity_hash VARCHAR(64) NOT NULL,
    ciphertext LONGTEXT NOT NULL COMMENT 'opaque base64 blob, encrypted on the desktop',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_owner_company (owner_identity_hash, company_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pending phone-scanned transactions waiting for the desktop to ingest. Ciphertext is opaque.
CREATE TABLE IF NOT EXISTS mobile_sync_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_uid VARCHAR(64) NOT NULL,
    owner_identity_hash VARCHAR(64) NOT NULL,
    from_device_id INT NOT NULL,
    ciphertext LONGTEXT NOT NULL COMMENT 'opaque base64 blob (scanned transaction + image), encrypted on the phone',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_company_uid (company_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Argo Books public API (v1)
--
-- An ingest store, NOT a mirror of anyone's books. Third-party developers push
-- objects here with a merchant-issued key; the desktop app pulls them, shows the
-- merchant a preview, and imports the approved ones into the local company file
-- as a single undoable action (the same shape as the Stripe importer).
--
-- Consequences baked into these tables:
--   * Every resource row carries an import lifecycle. A row is a proposal
--     until the desktop says otherwise.
--   * Public ids (cus_, exp_, ...) are API ids. They are NOT the ids the desktop
--     assigns; local_ref carries that back once known.
--   * Money is stored in integer minor units, like Stripe, so nothing rounds.
--   * environment is the deploy split (sandbox = dev.argorobots.com), not a
--     user-facing test mode. Every query MUST filter on it.
-- ============================================================================

-- One row per Argo Books company that has turned the API on.
CREATE TABLE IF NOT EXISTS api_accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    public_id VARCHAR(40) NOT NULL COMMENT 'acct_<24 hex>, returned to callers',
    owner_identity_hash CHAR(64) NOT NULL COMMENT 'Same identity as api/sync: sha256 of license key (premium) or device id (free)',
    company_uid VARCHAR(64) NOT NULL COMMENT 'Which company file on the desktop this account feeds',
    display_name VARCHAR(255) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_api_accounts_public_id (public_id),
    UNIQUE KEY uk_api_accounts_owner_company (owner_identity_hash, company_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Merchant-issued credentials. Multiple live keys per account so a merchant can
-- hand a distinct one to each developer and revoke them independently.
CREATE TABLE IF NOT EXISTS api_keys (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    public_id VARCHAR(40) NOT NULL COMMENT 'key_<24 hex>',
    key_hash CHAR(64) NOT NULL COMMENT 'sha256 of the secret. The secret itself is shown once at creation and never stored',
    key_hint VARCHAR(24) NOT NULL COMMENT 'ab_1a2b...wxyz, safe to display in Settings and logs',
    label VARCHAR(100) NOT NULL DEFAULT '',
    scopes VARCHAR(255) NOT NULL DEFAULT 'read,write' COMMENT 'Comma-separated: read, write',
    last_used_at DATETIME DEFAULT NULL,
    revoked_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES api_accounts(id) ON DELETE CASCADE,
    UNIQUE KEY uk_api_keys_hash (key_hash),
    UNIQUE KEY uk_api_keys_public_id (public_id),
    INDEX idx_api_keys_account (account_id, revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A desktop import run. Groups the objects the merchant approved together so the
-- app can undo the whole thing, and so a developer can see when their data landed.
CREATE TABLE IF NOT EXISTS api_import_batches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    public_id VARCHAR(40) NOT NULL COMMENT 'imb_<24 hex>',
    status ENUM('open','completed','reverted') NOT NULL DEFAULT 'open',
    object_counts JSON DEFAULT NULL COMMENT 'Per-resource counts, e.g. {"expense":4,"customer":2}',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL,
    FOREIGN KEY (account_id) REFERENCES api_accounts(id) ON DELETE CASCADE,
    UNIQUE KEY uk_api_batches_public_id (public_id),
    INDEX idx_api_batches_account (account_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    public_id VARCHAR(40) NOT NULL COMMENT 'cus_<24 hex>',
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    company VARCHAR(255) DEFAULT NULL,
    tax_number VARCHAR(60) DEFAULT NULL,
    address_line1 VARCHAR(255) DEFAULT NULL,
    address_line2 VARCHAR(255) DEFAULT NULL,
    city VARCHAR(120) DEFAULT NULL,
    state VARCHAR(120) DEFAULT NULL,
    postal_code VARCHAR(30) DEFAULT NULL,
    country CHAR(2) DEFAULT NULL COMMENT 'ISO 3166-1 alpha-2',
    notes TEXT DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    import_status ENUM('pending','imported','rejected') NOT NULL DEFAULT 'pending',
    import_batch_id INT DEFAULT NULL,
    imported_at DATETIME DEFAULT NULL,
    local_ref VARCHAR(120) DEFAULT NULL COMMENT 'Id the desktop assigned after import. NULL until then',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    FOREIGN KEY (account_id) REFERENCES api_accounts(id) ON DELETE CASCADE,
    UNIQUE KEY uk_api_customers_public_id (public_id),
    INDEX idx_api_customers_list (account_id, deleted_at, id),
    INDEX idx_api_customers_pending (account_id, import_status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_suppliers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    public_id VARCHAR(40) NOT NULL COMMENT 'sup_<24 hex>',
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    website VARCHAR(255) DEFAULT NULL,
    tax_number VARCHAR(60) DEFAULT NULL,
    address_line1 VARCHAR(255) DEFAULT NULL,
    address_line2 VARCHAR(255) DEFAULT NULL,
    city VARCHAR(120) DEFAULT NULL,
    state VARCHAR(120) DEFAULT NULL,
    postal_code VARCHAR(30) DEFAULT NULL,
    country CHAR(2) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    import_status ENUM('pending','imported','rejected') NOT NULL DEFAULT 'pending',
    import_batch_id INT DEFAULT NULL,
    imported_at DATETIME DEFAULT NULL,
    local_ref VARCHAR(120) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    FOREIGN KEY (account_id) REFERENCES api_accounts(id) ON DELETE CASCADE,
    UNIQUE KEY uk_api_suppliers_public_id (public_id),
    INDEX idx_api_suppliers_list (account_id, deleted_at, id),
    INDEX idx_api_suppliers_pending (account_id, import_status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    public_id VARCHAR(40) NOT NULL COMMENT 'cat_<24 hex>',
    name VARCHAR(255) NOT NULL,
    kind ENUM('expense','revenue') NOT NULL,
    parent VARCHAR(40) DEFAULT NULL COMMENT 'public_id of another api_categories row',
    description TEXT DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    import_status ENUM('pending','imported','rejected') NOT NULL DEFAULT 'pending',
    import_batch_id INT DEFAULT NULL,
    imported_at DATETIME DEFAULT NULL,
    local_ref VARCHAR(120) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    FOREIGN KEY (account_id) REFERENCES api_accounts(id) ON DELETE CASCADE,
    UNIQUE KEY uk_api_categories_public_id (public_id),
    INDEX idx_api_categories_list (account_id, deleted_at, id),
    INDEX idx_api_categories_pending (account_id, import_status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    public_id VARCHAR(40) NOT NULL COMMENT 'prd_<24 hex>',
    name VARCHAR(255) NOT NULL,
    sku VARCHAR(120) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    unit VARCHAR(40) DEFAULT NULL COMMENT 'each, hour, kg, ...',
    unit_amount BIGINT DEFAULT NULL COMMENT 'Minor units of currency, like Stripe. 1999 = 19.99 USD',
    currency CHAR(3) DEFAULT NULL,
    tax_rate DECIMAL(7,4) DEFAULT NULL COMMENT 'Percent, e.g. 13.0000',
    category VARCHAR(40) DEFAULT NULL COMMENT 'public_id of an api_categories row',
    metadata JSON DEFAULT NULL,
    import_status ENUM('pending','imported','rejected') NOT NULL DEFAULT 'pending',
    import_batch_id INT DEFAULT NULL,
    imported_at DATETIME DEFAULT NULL,
    local_ref VARCHAR(120) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    FOREIGN KEY (account_id) REFERENCES api_accounts(id) ON DELETE CASCADE,
    UNIQUE KEY uk_api_products_public_id (public_id),
    INDEX idx_api_products_list (account_id, deleted_at, id),
    INDEX idx_api_products_pending (account_id, import_status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_expenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    public_id VARCHAR(40) NOT NULL COMMENT 'exp_<24 hex>',
    description VARCHAR(500) NOT NULL,
    amount BIGINT NOT NULL COMMENT 'Total including tax, in minor units',
    currency CHAR(3) NOT NULL,
    tax_amount BIGINT NOT NULL DEFAULT 0,
    occurred_on DATE NOT NULL,
    supplier VARCHAR(40) DEFAULT NULL COMMENT 'public_id of an api_suppliers row',
    category VARCHAR(40) DEFAULT NULL COMMENT 'public_id of an api_categories row',
    payment_method VARCHAR(40) DEFAULT NULL,
    reference VARCHAR(120) DEFAULT NULL COMMENT 'Developer-side document number, shown to the merchant during review',
    notes TEXT DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    import_status ENUM('pending','imported','rejected') NOT NULL DEFAULT 'pending',
    import_batch_id INT DEFAULT NULL,
    imported_at DATETIME DEFAULT NULL,
    local_ref VARCHAR(120) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    FOREIGN KEY (account_id) REFERENCES api_accounts(id) ON DELETE CASCADE,
    UNIQUE KEY uk_api_expenses_public_id (public_id),
    INDEX idx_api_expenses_list (account_id, deleted_at, id),
    INDEX idx_api_expenses_pending (account_id, import_status, id),
    INDEX idx_api_expenses_occurred (account_id, occurred_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_revenue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    public_id VARCHAR(40) NOT NULL COMMENT 'rev_<24 hex>',
    description VARCHAR(500) NOT NULL,
    amount BIGINT NOT NULL COMMENT 'Gross including tax, in minor units',
    currency CHAR(3) NOT NULL,
    tax_amount BIGINT NOT NULL DEFAULT 0,
    discount_amount BIGINT NOT NULL DEFAULT 0,
    fee_amount BIGINT NOT NULL DEFAULT 0 COMMENT 'Processing fee withheld upstream, so the desktop can book it as an expense',
    occurred_on DATE NOT NULL,
    customer VARCHAR(40) DEFAULT NULL COMMENT 'public_id of an api_customers row',
    category VARCHAR(40) DEFAULT NULL COMMENT 'public_id of an api_categories row',
    payment_method VARCHAR(40) DEFAULT NULL,
    reference VARCHAR(120) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    import_status ENUM('pending','imported','rejected') NOT NULL DEFAULT 'pending',
    import_batch_id INT DEFAULT NULL,
    imported_at DATETIME DEFAULT NULL,
    local_ref VARCHAR(120) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    FOREIGN KEY (account_id) REFERENCES api_accounts(id) ON DELETE CASCADE,
    UNIQUE KEY uk_api_revenue_public_id (public_id),
    INDEX idx_api_revenue_list (account_id, deleted_at, id),
    INDEX idx_api_revenue_pending (account_id, import_status, id),
    INDEX idx_api_revenue_occurred (account_id, occurred_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A refund always points at a revenue row, so the desktop can book it as a return
-- against the original sale rather than a free-floating negative amount.
CREATE TABLE IF NOT EXISTS api_refunds (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    public_id VARCHAR(40) NOT NULL COMMENT 're_<24 hex>',
    revenue VARCHAR(40) NOT NULL COMMENT 'public_id of the api_revenue row being refunded',
    amount BIGINT NOT NULL COMMENT 'Positive minor units. The desktop applies the sign',
    currency CHAR(3) NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    occurred_on DATE NOT NULL,
    reference VARCHAR(120) DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    import_status ENUM('pending','imported','rejected') NOT NULL DEFAULT 'pending',
    import_batch_id INT DEFAULT NULL,
    imported_at DATETIME DEFAULT NULL,
    local_ref VARCHAR(120) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    FOREIGN KEY (account_id) REFERENCES api_accounts(id) ON DELETE CASCADE,
    UNIQUE KEY uk_api_refunds_public_id (public_id),
    INDEX idx_api_refunds_list (account_id, deleted_at, id),
    INDEX idx_api_refunds_pending (account_id, import_status, id),
    INDEX idx_api_refunds_revenue (account_id, revenue)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Line detail for expenses and revenue. Separate table rather than a JSON column
-- so it can be listed and expanded like any other sub-resource.
CREATE TABLE IF NOT EXISTS api_line_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    public_id VARCHAR(40) NOT NULL COMMENT 'li_<24 hex>',
    parent_type ENUM('expense','revenue') NOT NULL,
    parent_public_id VARCHAR(40) NOT NULL,
    product VARCHAR(40) DEFAULT NULL COMMENT 'public_id of an api_products row',
    description VARCHAR(500) NOT NULL,
    quantity DECIMAL(15,4) NOT NULL DEFAULT 1.0000,
    unit_amount BIGINT NOT NULL COMMENT 'Minor units, excluding tax',
    tax_amount BIGINT NOT NULL DEFAULT 0,
    discount_amount BIGINT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES api_accounts(id) ON DELETE CASCADE,
    UNIQUE KEY uk_api_line_items_public_id (public_id),
    INDEX idx_api_line_items_parent (account_id, parent_type, parent_public_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Idempotency-Key cache, same claim-then-run contract as refund_idempotency_cache
-- but keyed on api_accounts instead of portal_companies.
CREATE TABLE IF NOT EXISTS api_idempotency_cache (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    idempotency_key VARCHAR(128) NOT NULL,
    body_hash CHAR(64) NOT NULL,
    response_status SMALLINT UNSIGNED NOT NULL COMMENT '0 = claimed, handler still running',
    response_body LONGTEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES api_accounts(id) ON DELETE CASCADE,
    UNIQUE KEY uk_api_idem_account_key (account_id, idempotency_key),
    INDEX idx_api_idem_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fixed-window rate limit counters, one row per (key, minute).
CREATE TABLE IF NOT EXISTS api_rate_limits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    api_key_id INT NOT NULL,
    window_started_at DATETIME NOT NULL,
    request_count INT NOT NULL DEFAULT 0,
    FOREIGN KEY (api_key_id) REFERENCES api_keys(id) ON DELETE CASCADE,
    UNIQUE KEY uk_api_rate_key_window (api_key_id, window_started_at),
    INDEX idx_api_rate_window (window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Site-wide rate limit counters (rate_limit_helper.php)
--
-- Backs every anonymous, IP-keyed limit on the site: admin and community
-- login, portal token lookups, license validate/redeem, the paid AI and OCR
-- endpoints, outbound email, sync pairing. Replaced a flat JSON file, which
-- forced every rate-limited request across the whole site to queue behind one
-- exclusive file lock. One row per bucket means requests only contend when
-- they are the same bucket.
--
-- Distinct from api_rate_limits, which keys off a real API key. This one keys
-- off whatever the caller has, usually an IP with no account behind it (or a
-- community user id for posting limits), so there is no foreign key to hang it on.
--
-- bucket_key is '<environment>:<prefix>_<sha256(identifier)>'. The identifier
-- is normally an IP but can be any opaque string (a license key, a browser
-- fingerprint, the literal 'GLOBAL' for a site-wide cap). ascii_bin because
-- the value is hex plus an ASCII prefix, which keeps the primary key narrow
-- and the comparison exact.
--
-- Windows are anchored at first_attempt_at rather than clock-aligned, so a
-- bucket stays tripped until its own window elapses. Rows are deleted
-- opportunistically once they are over two days old; stale rows are harmless
-- before then because reads filter on the window.
CREATE TABLE IF NOT EXISTS rate_limit_counters (
    bucket_key VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    first_attempt_at DATETIME NOT NULL COMMENT 'UTC. Start of the current window for this bucket',
    PRIMARY KEY (bucket_key),
    INDEX idx_rlc_first_attempt (first_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Argo Books public API: webhooks
--
-- Events describe what the MERCHANT did, not what the developer did. A
-- developer already knows they created an expense; what they cannot see is the
-- moment a human accepted, declined, or undid it. That is the whole value here,
-- and it is why there is no <object>.created event.
-- ============================================================================

CREATE TABLE IF NOT EXISTS api_webhook_endpoints (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    public_id VARCHAR(40) NOT NULL COMMENT 'whe_<24 hex>',
    url VARCHAR(500) NOT NULL,
    signing_secret VARCHAR(80) NOT NULL COMMENT 'whsec_..., shown once at creation and used to sign every delivery',
    enabled_events JSON DEFAULT NULL COMMENT 'Array of event types, or NULL for all',
    description VARCHAR(255) NOT NULL DEFAULT '',
    status ENUM('enabled','disabled') NOT NULL DEFAULT 'enabled',
    disabled_reason VARCHAR(255) DEFAULT NULL COMMENT 'Set when auto-disabled after sustained failure',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    FOREIGN KEY (account_id) REFERENCES api_accounts(id) ON DELETE CASCADE,
    UNIQUE KEY uk_api_whe_public_id (public_id),
    INDEX idx_api_whe_account (account_id, status, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The event log. Readable through GET /v1/events so a developer whose endpoint
-- was down can catch up without needing us to replay anything.
CREATE TABLE IF NOT EXISTS api_events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    public_id VARCHAR(40) NOT NULL COMMENT 'evt_<24 hex>',
    type VARCHAR(60) NOT NULL COMMENT 'e.g. revenue.imported, import_batch.reverted',
    object_id VARCHAR(40) DEFAULT NULL COMMENT 'The object the event is about',
    data JSON NOT NULL COMMENT 'The serialized object at the time of the event',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES api_accounts(id) ON DELETE CASCADE,
    UNIQUE KEY uk_api_events_public_id (public_id),
    INDEX idx_api_events_account (account_id, id),
    INDEX idx_api_events_type (account_id, type, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per (event, endpoint). Separate from api_events because the same
-- event fans out to every matching endpoint and each retries independently.
CREATE TABLE IF NOT EXISTS api_webhook_deliveries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    endpoint_id INT NOT NULL,
    event_id INT NOT NULL,
    status ENUM('pending','succeeded','failed') NOT NULL DEFAULT 'pending',
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    next_attempt_at DATETIME NOT NULL,
    last_status_code SMALLINT UNSIGNED DEFAULT NULL,
    last_error VARCHAR(500) DEFAULT NULL,
    delivered_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (endpoint_id) REFERENCES api_webhook_endpoints(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES api_events(id) ON DELETE CASCADE,
    UNIQUE KEY uk_api_delivery_endpoint_event (endpoint_id, event_id),
    INDEX idx_api_delivery_due (status, next_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Remembers which API object each local row was sent as, so cron/argo_books_sync.php
-- can tell a row it has never sent from one that has merely changed. Without it the
-- sync could only ever create, and a corrected amount would arrive as a second entry
-- beside the wrong one rather than replacing it.
--
-- content_hash is the payload as it was last accepted. An unchanged row is skipped
-- without a request at all, which is what keeps a daily run over years of history down
-- to the few rows that actually moved.
CREATE TABLE IF NOT EXISTS argo_books_sync_map (
    id INT PRIMARY KEY AUTO_INCREMENT,
    source_type VARCHAR(40) NOT NULL COMMENT 'category, customer, supplier, revenue, expense, refund',
    source_key VARCHAR(191) NOT NULL COMMENT 'Stable local identity, e.g. payment:1234 or email:someone@example.com',
    api_object_id VARCHAR(64) NOT NULL COMMENT 'The cus_/rev_/exp_ id the API returned',
    content_hash CHAR(64) DEFAULT NULL COMMENT 'sha256 of the payload last accepted, so unchanged rows cost no request',
    status ENUM('sent', 'rejected') NOT NULL DEFAULT 'sent' COMMENT 'rejected means the owner declined it in the app. Without this third state a refusal is indistinguishable from never having sent it, so the row would either be posted again or vanish silently',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_abs_source (source_type, source_key),
    INDEX idx_abs_api_object (api_object_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
