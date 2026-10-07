<?php

/**
 * Parse and render formatted text from user input
 * 
 * @param string $text The user input text with formatting
 * @return string HTML with formatting applied
 */
function render_formatted_text($text)
{
    // Escape first, so the only HTML in the result is what the formatter builds.
    $text = htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    $text = process_formatting($text);
    $text = final_cleanup($text);

    // Wrap the formatted text in a div so we can apply a specific class
    return '<div class="formatted-text">' . $text . '</div>';
}

/**
 * Process all formatting on already-escaped input
 */
function process_formatting($text)
{
    // Links go first, behind placeholders, so the rules below can't reach into a URL
    $links = [];
    $text = process_links($text, $links);

    $text = format_inline($text);

    // Blockquotes (multi-line support). ">" arrives escaped as "&gt;".
    $text = preg_replace_callback('/(^(?:&gt;)+\s*.*(\n(?:&gt;)+\s*.*)*)/m', function ($matches) {
        $content = preg_replace('/^(?:&gt;)+\s*/m', '', $matches[0]);
        return "<blockquote>" . trim($content) . "</blockquote>";
    }, $text);

    $text = process_lists($text);
    return strtr($text, $links);
}

/**
 * Bold, italic and code
 */
function format_inline($text)
{
    // Bold: **text** to <strong>text</strong>
    $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);

    // Italic: _text_ to <em>text</em>
    $text = preg_replace('/_([^_\n]+)_/', '<em>$1</em>', $text);

    // Code: `code` to <code>code</code>
    return preg_replace('/`(.+?)`/s', '<code>$1</code>', $text);
}

/**
 * Process ordered/unordered lists
 */
function process_lists($text)
{
    // Unordered lists
    $text = preg_replace_callback('/(^- .+(\n- .+)*)/m', function ($matches) {
        $items = preg_replace('/^- (.+)/m', '<li>$1</li>', $matches[0]);
        return "<ul>\n$items\n</ul>";
    }, $text);

    // Ordered lists
    $text = preg_replace_callback('/(^\d+\. .+(\n\d+\. .+)*)/m', function ($matches) {
        $items = preg_replace('/^\d+\. (.+)/m', '<li>$1</li>', $matches[0]);
        return "<ol>\n$items\n</ol>";
    }, $text);

    return $text;
}

/**
 * Process links with Markdown syntax [text](url)
 * 
 * @param string $text The already-escaped text to process
 * @param array $links Receives placeholder => link HTML, for process_formatting() to put back
 * @return string Text with each link replaced by its placeholder
 */
function process_links($text, array &$links = [])
{
    // A fresh random token per call, so a user can't type a placeholder
    $token = bin2hex(random_bytes(8));

    return preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($matches) use (&$links, $token) {
        $link_text = format_inline($matches[1]);
        $url = trim(htmlspecialchars_decode($matches[2], ENT_QUOTES));

        if (is_allowed_url($url)) {
            $html = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . $link_text . '</a>';
        } else {
            // Return just the text if link is not allowed
            $html = $link_text . ' <span class="invalid-link-warning">(Link to disallowed domain removed)</span>';
        }

        $placeholder = 'LINK' . $token . 'N' . count($links) . 'E';
        $links[$placeholder] = $html;
        return $placeholder;
    }, $text);
}

/**
 * Validates if a URL is allowed
 *
 * @param string $url The URL to validate
 * @return bool True if URL is allowed, false otherwise
 */
function is_allowed_url($url)
{
    // Parse the URL to get the host
    $parsed_url = parse_url($url);

    if ($parsed_url === false || !isset($parsed_url['host'])) {
        return false;
    }

    // The host list means nothing if the scheme can be javascript: or data:
    $scheme = strtolower($parsed_url['scheme'] ?? '');
    if ($scheme !== 'http' && $scheme !== 'https') {
        return false;
    }

    $host = strtolower($parsed_url['host']);

    // Define allowed domains organized by category
    $allowed_domains = [
        // Company domain
        'argorobots.com',

        // Educational & Reference
        'wikipedia.org',
        'w3schools.com',
        'developer.mozilla.org', // MDN Web Docs
        'docs.microsoft.com',
        'dev.to',
        'medium.com',

        // Developer Communities & Q&A
        'stackoverflow.com',
        'stackexchange.com',
        'superuser.com',
        'serverfault.com',
        'askubuntu.com',
        'mathoverflow.net',
        'reddit.com',
        'news.ycombinator.com', // Hacker News

        // Code Repositories & Tools
        'github.com',
        'gitlab.com',
        'bitbucket.org',
        'codepen.io',
        'jsfiddle.net',
        'replit.com',
        'codesandbox.io',

        // Google Services
        'google.com',
        'youtube.com',
        'googledocs.com',
        'googleusercontent.com',
        'google.dev', // Google for Developers

        // Microsoft Services
        'microsoft.com',
        'visualstudio.com',
        'azure.microsoft.com',

        // Programming Language Official Sites
        'php.net',
        'python.org',
        'nodejs.org',
        'reactjs.org',
        'vuejs.org',
        'angular.io',
        'laravel.com',
        'symfony.com',
        'wordpress.org',
        'jquery.com',

        // Package Managers & Libraries
        'npmjs.com',
        'packagist.org', // PHP packages
        'pypi.org', // Python packages
        'nuget.org', // .NET packages
        'mvnrepository.com', // Maven (Java)
        'crates.io', // Rust packages

        // Cloud Platforms & Services
        'aws.amazon.com',
        'cloud.google.com',
        'digitalocean.com',
        'heroku.com',
        'netlify.com',
        'vercel.com',

        // Development Tools
        'postman.com',
        'insomnia.rest',
        'docker.com',
        'kubernetes.io',
        'jenkins.io',
        'atlassian.com', // Jira, Confluence, etc.

        // Tech News & Blogs
        'techcrunch.com',
        'arstechnica.com',
        'wired.com',
        'theverge.com',

        // Standards & Specifications
        'w3.org',
        'ietf.org',
        'whatwg.org',
        'ecma-international.org',

        // Security & Best Practices
        'owasp.org',
        'cve.mitre.org',
        'nvd.nist.gov',
    ];

    // Check for exact domain matches
    if (in_array($host, $allowed_domains)) {
        return true;
    }

    // Check for subdomain matches (e.g., subdomain.example.com)
    foreach ($allowed_domains as $allowed_domain) {
        if (str_ends_with($host, '.' . $allowed_domain)) {
            return true;
        }
    }

    // Special cases for domains with country codes or variations
    $special_patterns = [
        // Wikipedia in different languages (e.g., en.wikipedia.org, fr.wikipedia.org)
        '/^[a-z]{2,3}\.wikipedia\.org$/',

        // Stack Exchange network sites (e.g., meta.stackoverflow.com, gaming.stackexchange.com)
        '/^[a-z0-9\-]+\.stack(overflow|exchange)\.com$/',

        // Google country domains (e.g., google.co.uk, google.ca)
        '/^google\.(com?\.)?[a-z]{2,3}$/',

        // GitHub user/org pages (e.g., username.github.io)
        '/^[a-z0-9\-]+\.github\.io$/',

        // GitLab pages (e.g., username.gitlab.io)
        '/^[a-z0-9\-]+\.gitlab\.io$/',

        // Microsoft domains (e.g., docs.microsoft.com, learn.microsoft.com)
        '/^[a-z0-9\-]+\.microsoft\.com$/',

        // AWS documentation subdomains
        '/^[a-z0-9\-]+\.aws\.amazon\.com$/',

        // Netlify app domains (e.g., app-name.netlify.app)
        '/^[a-z0-9\-]+\.netlify\.app$/',

        // Vercel app domains (e.g., app-name.vercel.app)
        '/^[a-z0-9\-]+\.vercel\.app$/',
    ];

    // Check against special patterns
    foreach ($special_patterns as $pattern) {
        if (preg_match($pattern, $host)) {
            return true;
        }
    }

    return false;
}

/**
 * Final cleanup and whitespace handling
 */
function final_cleanup($text)
{
    // Normalize line endings
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    // Split into lines
    $lines = explode("\n", $text);
    $output = [];

    // Variable to track consecutive empty lines
    $consecutiveEmptyLines = 0;

    foreach ($lines as $line) {
        $trimmedLine = trim($line);

        if ($trimmedLine === '') {
            // Only add <br> if we haven't already added one consecutively
            if ($consecutiveEmptyLines < 1) {
                $output[] = '<br>';
                $consecutiveEmptyLines++;
            }
            // If we already have a blank line, skip this one
        } else if (preg_match('/^<(blockquote|ul|ol|p|h[1-6]|hr)/i', $trimmedLine)) {
            // Block-level elements - don't wrap in <p>
            $output[] = $line;
            // Reset consecutive empty lines counter
            $consecutiveEmptyLines = 0;
        } else if (preg_match('/<(code|strong|em|a)/i', $trimmedLine)) {
            // Line contains inline elements - add the line with a <br> for proper line breaks
            $output[] = $line . '<br>';
            // Reset consecutive empty lines counter
            $consecutiveEmptyLines = 0;
        } else {
            $output[] = '<p>' . $line . '</p>';
            // Reset consecutive empty lines counter
            $consecutiveEmptyLines = 0;
        }
    }

    // Cleanup empty paragraphs and nested tags
    $text = implode("\n", $output);
    $text = preg_replace('/<p>\s*<\/p>/', '', $text);
    $text = str_replace(['<p><p>', '</p></p>'], ['<p>', '</p>'], $text);

    return $text;
}

/**
 * Add the formatting toolbar to a textarea
 * 
 * @param string $textarea_id The ID of the textarea element
 * @param bool $include_preview Whether to include a live preview area
 */
function add_formatting_toolbar($textarea_id, $include_preview = true)
{
?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.getElementById('<?= $textarea_id ?>');
            if (textarea) {
                textarea.classList.add('formattable');
                <?php if ($include_preview): ?>
                    textarea.dataset.enablePreview = 'true';
                <?php endif; ?>
            }
        });
    </script>
<?php
}
?>