<?php
// partials/tool-email-capture.php
//
// The "email me these results" box shared by the free tools. One markup and one
// script for every tool, so the tools cannot drift apart and there is a single
// place to change the wording or the consent line.
//
// Posts to api/tool-email.php, which owns the email body. A page here supplies
// only an address, a source, and (in results mode) label/value pairs.
//
// Usage, inside a tool's output buffer:
//
//     require_once __DIR__ . '/../partials/tool-email-capture.php';
//     tool_email_capture([
//         'source' => 'hourly_rate_calculator',
//         'title'  => 'Email these results to yourself',
//         'blurb'  => 'We will send the figures above so you have them to hand.',
//     ]);
//
// In results mode the rows are whatever the page tagged with data-tec-row:
//
//     <dd data-tec-row="Hourly rate" data-hr="rate">$82.00</dd>
//
// The attribute is the label and the rendered text is the value, read at submit
// time, so the email cannot disagree with the page and the currency formatting
// stays in the one place that already does it. A tool needing something other
// than its own markup can define window.toolEmailSummary() instead, returning
// an array of { label, value }.

if (!function_exists('tool_email_capture')) {

    /**
     * Render the capture box.
     *
     * @param array $opts source (required), title, blurb, cta, mode. Mode is
     *                    'results' (default) or 'optin', which drops the summary
     *                    callback and sends only the double opt-in confirmation.
     */
    function tool_email_capture(array $opts): void
    {
        $source = $opts['source'] ?? '';
        if ($source === '') {
            return;
        }

        $mode  = ($opts['mode'] ?? 'results') === 'optin' ? 'optin' : 'results';
        $title = $opts['title'] ?? 'Email these results to yourself';
        $blurb = $opts['blurb'] ?? 'We will send the figures above so you have them to hand.';
        $cta   = $opts['cta'] ?? 'Send';

        $id = 'tec-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($source));
        ?>
        <section class="tec" data-tool-email data-source="<?= htmlspecialchars($source) ?>" data-mode="<?= $mode ?>">
            <h2 class="tec-title"><?= htmlspecialchars($title) ?></h2>
            <p class="tec-blurb"><?= htmlspecialchars($blurb) ?></p>
            <form class="tec-form" novalidate>
                <label class="tec-sr" for="<?= $id ?>-email">Your email address</label>
                <input type="email"
                       id="<?= $id ?>-email"
                       class="tec-input"
                       data-tec-email
                       placeholder="you@business.com"
                       autocomplete="email"
                       required>
                <button type="submit" class="tec-send" data-tec-send><?= htmlspecialchars($cta) ?></button>
            </form>
            <?php if ($mode === 'optin'): ?>
                <?php /* No checkbox: signing up is the whole action here, so a ticked box that
                         cannot be unticked would only read as broken. */ ?>
                <p class="tec-consent" data-tec-consent>We will email you a link to confirm first. Unsubscribe anytime.</p>
            <?php else: ?>
                <label class="tec-check" data-tec-consent>
                    <input type="checkbox" data-tec-subscribe>
                    <span>Also email me occasional tips and product updates from Argo Books. Unsubscribe anytime.</span>
                </label>
            <?php endif; ?>
            <p class="tec-msg" data-tec-msg aria-live="polite"></p>
        </section>
        <?php
    }

    /**
     * The stylesheet and script tags, for a tool's $extra_head / $extra_scripts.
     * Kept beside the markup so a page cannot render the box without them.
     */
    function tool_email_capture_head(): string
    {
        return '<link rel="stylesheet" href="' . INVGEN_BASE . '/shared/styles/tool-email-capture.css">';
    }

    function tool_email_capture_scripts(): string
    {
        return '<script src="' . INVGEN_BASE . '/shared/scripts/tool-email-capture.js" defer></script>';
    }
}
