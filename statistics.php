<?php
// Start a session if one doesn't exist so we can check admin status
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db_connect.php';

/**
 * Detects search engines, AI scrapers, social-preview fetchers, and HTTP-library
 * traffic via the User-Agent string. Empty UA is treated as a bot.
 *
 * @param string $user_agent Raw User-Agent header
 * @return bool True if the UA looks like a bot
 */
function is_likely_bot($user_agent)
{
    $ua = trim($user_agent);
    if ($ua === '') {
        return true;
    }

    // We list specific bot names rather than matching "bot" alone, because some
    // legitimate UAs contain "bot" as a substring (e.g. Cubot phones).
    static $patterns = [
        // Search / SEO crawlers
        'Googlebot', 'bingbot', 'DuckDuckBot', 'YandexBot', 'Baiduspider', 'Sogou',
        'Slurp', 'Applebot', 'AhrefsBot', 'SemrushBot', 'MJ12bot', 'DotBot', 'rogerbot',
        // Google's non-"Googlebot" crawlers. These don't contain the substring "Googlebot", so they slipped the line above and flooded referral_visits (esp.
        'GoogleOther', 'Google-InspectionTool', 'Storebot-Google', 'Feedfetcher-Google',
        'APIs-Google', 'GoogleProducer',
        // AI / dataset crawlers
        'GPTBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-Web', 'anthropic-ai',
        'PerplexityBot', 'Perplexity-User', 'Google-Extended', 'Applebot-Extended',
        'CCBot', 'Bytespider', 'Diffbot', 'Amazonbot', 'cohere-ai',
        // SEO / marketing-data crawlers (self-identified, so name-matching works).
        'DataForSeoBot', 'PetalBot', 'DataForSeo', 'BLEXBot', 'SeznamBot',
        'serpstatbot', 'ZoominfoBot', 'Barkrowler', 'SiteAuditBot', 'AwarioBot',
        // Social / link-preview fetchers
        'facebookexternalhit', 'meta-externalagent', 'Twitterbot', 'LinkedInBot',
        'Slackbot', 'Discordbot', 'TelegramBot', 'WhatsApp',
        // HTTP client libraries (real browsers don't send these)
        'curl/', 'wget/', 'python-requests', 'python-urllib', 'Go-http-client',
        'Java/', 'okhttp', 'libwww-perl', 'Apache-HttpClient', 'node-fetch',
        // Headless / automation
        'HeadlessChrome', 'PhantomJS', 'Selenium',
        // Generic crawler verbs
        'crawler', 'spider', 'scraper',
        // Archives
        'archive.org', 'Wayback',
    ];

    foreach ($patterns as $pattern) {
        if (stripos($ua, $pattern) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * True if $ip falls inside $range. $range is either a plain address (exact
 * match, works for IPv4 and IPv6) or an IPv4 CIDR like "66.249.64.0/19".
 */
function ip_in_cidr($ip, $range)
{
    if (strpos($range, '/') === false) {
        return $ip === $range;
    }

    list($subnet, $bits) = explode('/', $range, 2);
    $ip_long     = ip2long($ip);
    $subnet_long = ip2long($subnet);
    // ip2long returns false for non-IPv4 input; the CIDR math below is IPv4-only.
    if ($ip_long === false || $subnet_long === false) {
        return false;
    }

    $bits = (int)$bits;
    if ($bits < 0 || $bits > 32) {
        return false;
    }

    $mask = ($bits === 0) ? 0 : ((0xFFFFFFFF << (32 - $bits)) & 0xFFFFFFFF);
    return (($ip_long & $mask) === ($subnet_long & $mask));
}

/**
 * True if $ip belongs to a datacenter / cloud-hosting network rather than a
 * residential or mobile ISP. Real customers browse from consumer ISPs; traffic
 * originating inside AWS, Google Cloud, Azure, DigitalOcean, etc. is almost
 * always automated (scrapers, uptime monitors, AI crawlers) wearing a spoofed
 * browser User-Agent that is_likely_bot() can't catch by name. The June 2026
 * invoice-generator scrape came almost entirely from Google Cloud (34/8, 35/8).
 *
 * This is a STATIC list of the largest published cloud aggregates, not a live
 * IP-to-ASN lookup, so it is deliberately broad rather than exhaustive: a few
 * uncounted real visitors costs us nothing, but a missed scraper pollutes the
 * stats. Extend it via the DATACENTER_IP_RANGES env var (same format as
 * EXCLUDED_TRACKING_IPS: comma-separated plain IPs or CIDR ranges). IPv4 only:
 * an IPv6 visitor only matches if listed verbatim in the env var.
 *
 * This cannot catch scrapers renting residential-proxy exit nodes. Those show
 * up as a country whose page views divide almost exactly 1-to-1 into distinct
 * IPs (Vietnam, Argentina, Pakistan, Bangladesh). Only a client-side JS beacon
 * filters those out.
 */
function is_datacenter_ip($ip)
{
    if (empty($ip)) {
        return false;
    }

    // Parsed once per request into [network, mask] integer pairs.
    static $cidrs = null;
    // Plain addresses from the env var, matched verbatim. This is the only
    // path that can match an IPv6 client.
    static $exact = null;

    if ($cidrs === null) {
        $ranges = [
            // Google Cloud Platform, which is scattered well beyond 34/8 and 35/8; these extra blocks came from scraper traffic that slipped those two.
            '34.0.0.0/8', '35.0.0.0/8',
            '104.154.0.0/15', '104.196.0.0/14', '104.199.0.0/16',
            '130.211.0.0/16', '136.112.0.0/12', '146.148.0.0/17',
            '107.178.192.0/18', '108.170.192.0/18', '108.177.0.0/17',
            '162.216.148.0/22', '199.36.153.0/24',
            // Amazon Web Services.
            '3.0.0.0/8', '13.32.0.0/15', '15.177.0.0/18', '18.0.0.0/8',
            '52.0.0.0/8', '54.0.0.0/8',
            '23.20.0.0/14', '44.192.0.0/10', '46.51.128.0/18', '46.137.0.0/16',
            '50.16.0.0/15', '50.19.0.0/16', '63.32.0.0/14', '75.101.128.0/17',
            '79.125.0.0/17', '99.77.0.0/16', '99.80.0.0/15', '107.20.0.0/14',
            '174.129.0.0/16', '176.32.0.0/15', '176.34.0.0/15', '184.72.0.0/15',
            '184.169.128.0/17', '204.236.128.0/17',
            // Microsoft Azure. 191.232/13 is the Brazil South region.
            '13.64.0.0/11', '20.0.0.0/8', '40.64.0.0/10', '104.40.0.0/13',
            '51.4.0.0/15', '51.10.0.0/15', '51.12.0.0/15', '51.103.0.0/16',
            '51.104.0.0/15', '51.107.0.0/16', '51.116.0.0/16', '51.120.0.0/16',
            '51.124.0.0/16', '51.132.0.0/16', '51.136.0.0/15', '51.138.0.0/16',
            '51.140.0.0/14', '51.144.0.0/15', '65.52.0.0/14', '70.37.0.0/17',
            '102.133.0.0/16', '137.116.0.0/15', '137.135.0.0/16', '138.91.0.0/16',
            '157.55.0.0/16', '168.61.0.0/16', '168.62.0.0/15', '191.232.0.0/13',
            '207.46.0.0/16',
            // DigitalOcean. 128.199/16 and 139.59/16 are the Singapore regions.
            '104.131.0.0/16', '138.197.0.0/16', '159.65.0.0/16', '165.227.0.0/16',
            '167.71.0.0/16', '167.99.0.0/16', '178.62.0.0/16', '188.166.0.0/16',
            '24.144.64.0/18', '45.55.0.0/16', '64.225.0.0/16', '68.183.0.0/16',
            '128.199.0.0/16', '134.122.0.0/16', '134.209.0.0/16', '137.184.0.0/16',
            '139.59.0.0/16', '142.93.0.0/16', '143.110.0.0/16', '143.198.0.0/16',
            '144.126.192.0/18', '146.190.0.0/16', '147.182.0.0/16', '157.230.0.0/16',
            '157.245.0.0/16', '161.35.0.0/16', '162.243.0.0/16', '164.90.0.0/16',
            '164.92.0.0/16', '165.22.0.0/16', '170.64.0.0/16', '174.138.0.0/16',
            '198.199.64.0/18', '206.189.0.0/16', '209.38.0.0/16', '209.97.128.0/18',
            // OVH.
            '51.68.0.0/16', '51.75.0.0/16', '51.83.0.0/16', '51.91.0.0/16',
            '54.36.0.0/16', '145.239.0.0/16', '147.135.0.0/16',
            '5.135.0.0/16', '5.196.0.0/16', '15.204.0.0/16', '15.235.0.0/16',
            '37.59.0.0/16', '37.187.0.0/16', '46.105.0.0/16', '51.38.0.0/16',
            '51.161.0.0/16', '51.178.0.0/16', '51.195.0.0/16', '51.210.0.0/16',
            '51.222.0.0/16', '51.254.0.0/16', '51.255.0.0/16', '91.121.0.0/16',
            '92.222.0.0/16', '94.23.0.0/16', '137.74.0.0/16', '139.99.0.0/16',
            '141.94.0.0/16', '141.95.0.0/16', '149.202.0.0/16', '151.80.0.0/16',
            '158.69.0.0/16', '164.132.0.0/16', '167.114.0.0/16', '176.31.0.0/16',
            '178.32.0.0/15', '188.165.0.0/16', '192.99.0.0/16', '198.27.64.0/18',
            '213.32.0.0/17', '217.182.0.0/16',
            // Hetzner.
            '5.9.0.0/16', '88.99.0.0/16', '116.202.0.0/16', '116.203.0.0/16',
            '135.181.0.0/16', '157.90.0.0/16', '162.55.0.0/16', '167.235.0.0/16',
            '23.88.0.0/17', '37.27.0.0/16', '46.4.0.0/16', '49.12.0.0/16',
            '49.13.0.0/16', '65.21.0.0/16', '65.108.0.0/16', '65.109.0.0/16',
            '78.46.0.0/15', '91.107.0.0/16', '94.130.0.0/16', '95.216.0.0/16',
            '95.217.0.0/16', '128.140.0.0/17', '138.201.0.0/16', '142.132.128.0/17',
            '144.76.0.0/16', '148.251.0.0/16', '159.69.0.0/16', '168.119.0.0/16',
            '176.9.0.0/16', '178.63.0.0/16', '188.34.128.0/17', '188.40.0.0/16',
            '195.201.0.0/16', '213.133.96.0/19', '213.239.192.0/18',
            // Linode / Akamai. 139.162/16 covers the Singapore and Tokyo regions.
            '45.33.0.0/16', '45.56.0.0/16', '50.116.0.0/16', '66.175.208.0/20',
            '96.126.96.0/19', '139.144.0.0/16', '172.104.0.0/15', '173.255.192.0/18',
            '45.79.0.0/16', '69.164.192.0/18', '74.207.224.0/19', '97.107.128.0/18',
            '139.162.0.0/16', '143.42.0.0/16', '170.187.0.0/16', '176.58.96.0/19',
            '178.79.128.0/18', '198.58.96.0/19',
            // Alibaba Cloud, whose Singapore and Hong Kong regions are the single biggest reason
            // Singapore ranked second in the country breakdown.
            '8.208.0.0/12', '39.96.0.0/11',
            // /16 not /14: 47.53-47.55 is Shaw (Canadian consumer ISP).
            '47.52.0.0/16', '47.56.0.0/14', '47.74.0.0/15', '47.76.0.0/14',
            '47.88.0.0/14', '47.92.0.0/14', '47.96.0.0/11',
            '47.235.0.0/16', '47.236.0.0/14', '47.240.0.0/13', '47.250.0.0/15',
            '47.252.0.0/14',
            '59.110.0.0/15', '101.132.0.0/14', '106.14.0.0/15', '112.124.0.0/14',
            '114.55.0.0/16', '115.28.0.0/15', '116.62.0.0/16', '118.31.0.0/16',
            '118.178.0.0/16', '119.23.0.0/16', '120.24.0.0/14', '120.55.0.0/16',
            '120.76.0.0/14', '121.40.0.0/14', '123.56.0.0/15', '139.196.0.0/16',
            '139.224.0.0/16', '140.205.0.0/16', '147.139.0.0/16', '149.129.0.0/16',
            '161.117.0.0/16', '182.92.0.0/16', '203.107.0.0/16',
            // Tencent Cloud.
            '43.128.0.0/12', '43.152.0.0/13', '49.51.0.0/16', '62.234.0.0/16',
            '81.68.0.0/14', '82.156.0.0/15', '101.32.0.0/15', '101.34.0.0/15',
            '106.52.0.0/14', '109.244.0.0/16', '111.229.0.0/16', '114.132.0.0/16',
            '115.159.0.0/16', '118.24.0.0/15', '119.28.0.0/15', '119.45.0.0/16',
            '120.53.0.0/16', '122.51.0.0/16', '124.156.0.0/16', '129.28.0.0/16',
            '129.204.0.0/16', '129.211.0.0/16', '132.232.0.0/16', '134.175.0.0/16',
            '139.155.0.0/16', '139.186.0.0/16', '139.199.0.0/16', '148.70.0.0/16',
            '150.109.0.0/16', '152.136.0.0/16', '159.75.0.0/16', '162.14.0.0/16',
            '170.106.0.0/16', '175.24.0.0/16', '175.27.0.0/16', '182.254.0.0/16',
            '188.131.0.0/16', '193.112.0.0/16', '203.205.0.0/16', '211.159.0.0/16',
            // Huawei Cloud.
            '49.4.0.0/14', '114.115.0.0/16', '116.63.0.0/16', '119.3.0.0/16',
            '119.8.0.0/16', '121.36.0.0/15', '122.9.0.0/16', '122.112.0.0/16',
            '124.70.0.0/15', '139.9.0.0/16', '159.138.0.0/16',
            // Oracle Cloud. The always-free tier makes this a heavy scraper
            // source, especially out of the Singapore and Tokyo regions.
            '129.146.0.0/16', '129.150.0.0/15', '129.152.0.0/13', '129.213.0.0/16',
            '130.35.0.0/16', '130.61.0.0/16', '132.145.0.0/16', '132.226.0.0/16',
            '138.1.0.0/16', '140.91.0.0/16', '141.144.0.0/14', '143.47.0.0/16',
            '144.24.0.0/16', '146.56.0.0/16', '147.154.0.0/16', '150.136.0.0/16',
            '150.230.0.0/16', '152.67.0.0/16', '152.69.0.0/16', '152.70.0.0/16',
            '158.101.0.0/16', '158.178.0.0/15', '158.180.0.0/16', '168.138.0.0/16',
            '192.9.0.0/16', '192.18.0.0/16', '193.122.0.0/15',
            // Vultr.
            '45.32.0.0/16', '45.63.0.0/16', '45.76.0.0/15', '64.176.0.0/16',
            '66.42.0.0/18', '70.34.192.0/18', '95.179.0.0/16', '104.156.224.0/19',
            '108.61.0.0/16', '136.244.64.0/18', '139.180.128.0/18', '141.164.0.0/16',
            '149.28.0.0/16', '155.138.0.0/16', '158.247.0.0/16', '199.247.0.0/16',
            '207.148.0.0/18', '216.238.64.0/18',
            // Contabo.
            '5.189.128.0/17', '62.171.128.0/17', '75.119.128.0/17', '144.91.64.0/18',
            '161.97.64.0/18', '164.68.96.0/19', '167.86.64.0/18', '173.212.192.0/18',
            '173.249.0.0/18', '207.180.192.0/18', '213.136.64.0/18',
            // Scaleway / Online.net.
            '51.15.0.0/16', '51.158.0.0/16', '51.159.0.0/16', '62.210.0.0/16',
            '163.172.0.0/16', '195.154.0.0/16', '212.129.0.0/18',
            // IBM Cloud / SoftLayer. 119.81/16 is the Singapore region.
            '50.22.0.0/15', '108.168.0.0/16', '119.81.0.0/16', '158.85.0.0/16',
            '158.175.0.0/16', '158.176.0.0/16', '159.8.0.0/16', '161.202.0.0/16',
            '169.44.0.0/14', '169.48.0.0/13', '169.56.0.0/14', '169.60.0.0/14',
            '174.36.0.0/15', '184.172.0.0/15', '198.11.192.0/18', '208.43.0.0/16',
            // Leaseweb and UpCloud.
            '37.48.64.0/18', '85.17.0.0/16', '95.211.0.0/16', '178.162.128.0/17',
            '94.237.0.0/16',
            // Google crawler / proxy space, distinct from the GCP compute
            // ranges at the top of this list.
            '74.125.0.0/16', '209.85.128.0/17', '72.14.192.0/18',
            // Microsoft's slice of the old Level 3 space.
            '4.128.0.0/9',
            // AWS ca-central-1, and Azure.
            '15.222.0.0/16', '135.232.0.0/16',
            // OVH Canada.
            '149.56.0.0/16',
            // Alibaba: fills the gap between the 47.76 and 47.88 entries above.
            '47.80.0.0/13',
            // Sits between the two Tencent blocks above.
            '43.172.0.0/14',
            // Named internet-wide scanners (Censys and similar). Kept to the
            // /24s they announce rather than the enclosing /17.
            '162.142.125.0/24', '167.94.138.0/24', '167.94.145.0/24',
            '167.94.146.0/24', '167.248.133.0/24', '147.185.132.0/24',
            '198.235.24.0/24', '199.45.154.0/24', '199.244.88.0/24',
            '205.169.39.0/24', '205.210.31.0/24', '87.236.176.0/24',
            // --- Identified by CLUSTERING, not by ASN ownership: a /24 holding 10+ ---------------
            '5.133.192.0/24', '5.181.14.0/24', '8.231.32.0/24',
            '27.115.124.0/24', '40.160.252.0/24', '62.169.135.0/24',
            '66.132.172.0/24', '66.132.195.0/24', '91.231.89.0/24',
            '93.158.90.0/23', '103.4.251.0/24', '103.196.9.0/24',
            '104.164.173.0/24', '123.6.49.0/24', '162.120.184.0/22',
            '185.247.137.0/24', '192.36.109.0/24', '193.186.4.0/24',
            '195.64.119.0/24', '207.195.86.0/24', '220.196.160.0/24',
        ];

        $configured = $_ENV['DATACENTER_IP_RANGES'] ?? getenv('DATACENTER_IP_RANGES');
        if (!empty($configured)) {
            foreach (explode(',', $configured) as $entry) {
                $entry = trim($entry);
                if ($entry !== '') {
                    $ranges[] = $entry;
                }
            }
        }

        $cidrs = [];
        $exact = [];
        foreach ($ranges as $range) {
            if (strpos($range, '/') === false) {
                $exact[] = $range;
                continue;
            }

            list($subnet, $bits) = explode('/', $range, 2);
            $subnet_long = ip2long($subnet);
            $bits = (int)$bits;
            // Silently skip malformed or IPv6 entries rather than letting one
            // bad env value break tracking for every visitor.
            if ($subnet_long === false || $bits < 0 || $bits > 32) {
                continue;
            }

            $mask = ($bits === 0) ? 0 : ((0xFFFFFFFF << (32 - $bits)) & 0xFFFFFFFF);
            $cidrs[] = [$subnet_long & $mask, $mask];
        }
    }

    if (in_array($ip, $exact, true)) {
        return true;
    }

    $ip_long = ip2long($ip);
    if ($ip_long === false) {
        return false;
    }

    foreach ($cidrs as $cidr) {
        if (($ip_long & $cidr[1]) === $cidr[0]) {
            return true;
        }
    }
    return false;
}

/**
 * IPs we never record analytics for: the site owner's own connection(s), known
 * crawler netblocks whose UAs can't be trusted, and datacenter/cloud hosts.
 * Keeps the owner's casual (logged-out) browsing, Google's crawler, and
 * UA-spoofing scrapers out of page-view, referral-visit, and funnel-event tables.
 *
 * Configure owner/internal addresses via the EXCLUDED_TRACKING_IPS env var:
 * comma-separated, plain IPs or CIDR ranges (e.g. "64.201.195.108,203.0.113.0/24").
 */
function is_nontracked_ip($ip)
{
    if (empty($ip)) {
        return false;
    }

    // Cloud/datacenter source = automated traffic with a spoofed browser UA.
    if (is_datacenter_ip($ip)) {
        return true;
    }

    // Google's published crawler range. UA filtering catches the named bots
    // above; this is the backstop since a spoofed UA can't fake the source IP.
    static $ranges = ['66.249.64.0/19'];

    $configured = $_ENV['EXCLUDED_TRACKING_IPS'] ?? getenv('EXCLUDED_TRACKING_IPS');
    if (!empty($configured)) {
        foreach (explode(',', $configured) as $entry) {
            $entry = trim($entry);
            if ($entry !== '') {
                $ranges[] = $entry;
            }
        }
    }

    foreach ($ranges as $range) {
        if (ip_in_cidr($ip, $range)) {
            return true;
        }
    }
    return false;
}

/**
 * Track a statistical event
 *
 * @param string $event_type Type of event (download, page_view, etc.)
 * @param string $event_data Additional event data
 * @return bool Success status
 */
function track_event($event_type, $event_data = '')
{
    // Don't track statistics for logged in admins
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        return false;
    }

    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    if (is_likely_bot($user_agent)) {
        return false;
    }

    if (is_nontracked_ip($_SERVER['REMOTE_ADDR'] ?? null)) {
        return false;
    }

    global $pdo;
    if (!$pdo) {
        return false;
    }
    $ip_address = $_SERVER['REMOTE_ADDR'];

    try {
        // Only record one occurrence of an event per IP per day
        $today_start = date('Y-m-d 00:00:00');
        $exists_stmt = $pdo->prepare('SELECT 1 FROM statistics WHERE event_type = ? AND event_data = ? AND ip_address = ? AND created_at >= ? LIMIT 1');
        $exists_stmt->execute([$event_type, $event_data, $ip_address, $today_start]);
        if ($exists_stmt->fetch() !== false) {
            return false;
        }

        $country_code = null;

        // Check if we already have this IP's country code in our database
        $check_stmt = $pdo->prepare('SELECT country_code FROM statistics WHERE ip_address = ? AND country_code IS NOT NULL AND country_code != "" LIMIT 1');
        $check_stmt->execute([$ip_address]);
        $row = $check_stmt->fetch();

        if ($row !== false) {
            // We already have this IP's country code
            $country_code = $row['country_code'];
        } else {
            // New IP or no country code yet, use cURL to contact the API
            if (function_exists('curl_init')) {
                $ch = curl_init("https://ipinfo.io/{$ip_address}/country");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                curl_setopt($ch, CURLOPT_USERAGENT, 'ArgoSalesTracker/1.0');
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                if ($http_code == 200 && !empty($response)) {
                    $country_code = trim($response);
                }
            }
        }

        // Insert event
        $stmt = $pdo->prepare('INSERT INTO statistics (event_type, event_data, ip_address, user_agent, country_code) VALUES (?, ?, ?, ?, ?)');
        $result = $stmt->execute([$event_type, $event_data, $ip_address, $user_agent, $country_code]);

        return $result;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Track a page view
 *
 * @param string $page The page being viewed (e.g., 'homepage', 'download', 'documentation')
 * @return bool Success status
 */
function track_page_view($page)
{
    $result = track_event('page_view', $page);
    return $result;
}

/**
 * Register a page view to be recorded via a client-side JS beacon (emitted by
 * shared/layout.php) instead of recording it here, server-side. Because the
 * beacon only fires when the browser actually runs JavaScript, this excludes
 * headless scrapers that load the HTML but never execute JS, which otherwise
 * flood the stats with fake "views". The beacon posts back to
 * api/invoice-generator/track.php, which calls track_page_view() for real.
 */
function defer_client_page_view(string $page): void
{
    if (preg_match('/^[a-z0-9_-]+$/', $page)) {
        $GLOBALS['__client_page_view'] = $page;
    }
}

/**
 * Track a referral visit from a source parameter
 *
 * @param string $source_code The source code from URL parameter (e.g., 'google-ad', 'twitter-sponsor')
 * @param string $page_url The current page URL
 * @return bool Success status
 */
function track_referral_visit($source_code, $page_url = '')
{
    // Don't track statistics for logged in admins
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        return false;
    }

    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    if (is_likely_bot($user_agent)) {
        return false;
    }

    if (is_nontracked_ip($_SERVER['REMOTE_ADDR'] ?? null)) {
        return false;
    }

    global $pdo;
    $ip_address = $_SERVER['REMOTE_ADDR'];

    // Store source in session for conversion tracking (runs even if DB is down)
    if (!isset($_SESSION['referral_source'])) {
        $_SESSION['referral_source'] = $source_code;
    }

    if (!$pdo) {
        return false;
    }

    try {
        // Check if this IP already visited from this source today
        $today_start = date('Y-m-d 00:00:00');
        $exists_stmt = $pdo->prepare('SELECT 1 FROM referral_visits WHERE source_code = ? AND ip_address = ? AND visited_at >= ? LIMIT 1');
        $exists_stmt->execute([$source_code, $ip_address, $today_start]);
        if ($exists_stmt->fetch() !== false) {
            return false; // Already tracked this IP for this source today
        }

        $country_code = null;

        // Check if we already have this IP's country code
        $check_stmt = $pdo->prepare('SELECT country_code FROM referral_visits WHERE ip_address = ? AND country_code IS NOT NULL AND country_code != "" LIMIT 1');
        $check_stmt->execute([$ip_address]);
        $row = $check_stmt->fetch();

        if ($row !== false) {
            $country_code = $row['country_code'];
        } else {
            // New IP, get country code from API
            if (function_exists('curl_init')) {
                $ch = curl_init("https://ipinfo.io/{$ip_address}/country");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                curl_setopt($ch, CURLOPT_USERAGENT, 'ArgoSalesTracker/1.0');
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                if ($http_code == 200 && !empty($response)) {
                    $country_code = trim($response);
                }
            }
        }

        // Insert referral visit
        $stmt = $pdo->prepare('INSERT INTO referral_visits (source_code, page_url, ip_address, user_agent, country_code) VALUES (?, ?, ?, ?, ?)');
        $result = $stmt->execute([$source_code, $page_url, $ip_address, $user_agent, $country_code]);

        return $result;
    } catch (PDOException $e) {
        return false;
    }
}
