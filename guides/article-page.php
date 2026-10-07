<?php
// guides/article-page.php
//
// Shared template for every informational article under argorobots.com.
// Driven entirely by data files in guides/data/{slug}.php.
//
// Routing:
//   /how-to-invoice-clients/  -> guides/article-page.php?slug=how-to-invoice-clients
//   (etc., one RewriteRule per article slug in .htaccess)
//
// Heading structure (strict, for SEO):
//   <h1>            article title (from $data['h1'])
//   <h2> ...        one per section in $data['sections']
//   <h2>            Frequently asked questions (when $data['faqs'] non-empty)
//     <h3> ...        one per FAQ question
//   <h2>            Related guides
//   <h2>            Related articles (when $data['related_article_slugs'] non-empty)

require_once __DIR__ . '/../partials/schema.php';
require_once __DIR__ . '/../partials/faq.php';
require_once __DIR__ . '/../partials/code-block.php';
require_once __DIR__ . '/../shared/_base.php';
require_once __DIR__ . '/../config/pricing.php';
require_once __DIR__ . '/illustrations.php';

// --- 1. Sanitize the slug -----------------------------------------------------

$slug_raw = $_GET['slug'] ?? '';
$slug = is_string($slug_raw) ? strtolower($slug_raw) : '';

if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
    article_render_404();
    exit;
}

// --- 2. Load the data file ----------------------------------------------------

$data_file = __DIR__ . '/data/' . $slug . '.php';
if (!is_file($data_file)) {
    article_render_404();
    exit;
}

$data = require $data_file;

if (!is_array($data) || empty($data['h1']) || empty($data['sections'])) {
    article_render_404();
    exit;
}

// --- 3. Server-side page view (post-404) --------------------------------------

if (PHP_SAPI !== 'cli') {
    // Referral tracking: capture ?source so a direct landing on this article
    // (from YouTube, an ad, a newsletter) is attributed in the funnel.
    require_once __DIR__ . '/../track_referral.php';
    require_once __DIR__ . '/../statistics.php';
    $safe_slug_for_event = preg_replace('/[^a-z0-9_-]/', '', $slug);
    defer_client_page_view('invgen_article_' . $safe_slug_for_event);
}

// Capped at 50 because referral source_code is VARCHAR(50), and truncated the same way every time so the visit and the auto-registered link still match on one value.
$invgen_ref = substr('guide-' . $slug, 0, 50);
$utm_qs = '?source=' . htmlspecialchars($invgen_ref)
        . '&amp;utm_source=invoice-generator&amp;utm_medium=article&amp;utm_campaign=phase1';

// --- 4. Page metadata ---------------------------------------------------------

$page_title = $data['meta_title'] ?? ($data['h1'] . ' | Argo Books');
$page_description = $data['meta_description'] ?? '';
$canonical_url = 'https://argorobots.com/' . $slug . '/';

// Worked out from the body text at about 220 words a minute and rounded up, so the badge always matches the real length of the article.
$reading_text = (string) ($data['intro_html'] ?? '');
foreach ($data['sections'] as $sec) {
    $reading_text .= ' ' . (string) ($sec['html'] ?? '');
}
foreach (($data['faqs'] ?? []) as $faq) {
    $reading_text .= ' ' . (string) ($faq['q'] ?? '') . ' ' . (string) ($faq['a'] ?? '');
}
$reading_text = preg_replace('/\{\{illustration:[a-z0-9-]+\}\}/', ' ', $reading_text);
// Pricing placeholders are resolved first: {argo_premium_monthly} counts as three words where the "$15" it becomes counts as none, which pushed every estimate up.
$reading_text = pricing_substitute($reading_text);
$reading_time_min = max(1, (int) ceil(str_word_count(strip_tags($reading_text)) / 220));

// --- 5. JSON-LD ---------------------------------------------------------------

$schema_type = $data['schema_type'] ?? 'Article';
$published = $data['published'] ?? ($data['updated'] ?? date('Y-m-d'));
$updated = $data['updated'] ?? $published;

$base_schema = [
  '@context' => 'https://schema.org',
  '@type' => $schema_type,
  'headline' => $data['h1'],
  'description' => $data['meta_description'] ?? '',
  'datePublished' => $published,
  'dateModified' => $updated,
  'author' => [
    '@type' => 'Person',
    'name' => 'Evan',
    'url' => 'https://argorobots.com/about-us/',
    'jobTitle' => 'Founder',
    'image' => 'https://argorobots.com/resources/images/founder.jpg',
    'worksFor' => ['@type' => 'Organization', 'name' => 'Argo Books', 'url' => 'https://argorobots.com/'],
  ],
  'publisher' => [
    '@type' => 'Organization',
    'name' => 'Argo Books',
    'logo' => [
      '@type' => 'ImageObject',
      'url' => 'https://argorobots.com/resources/images/argo-logo/argo-logo-black.png',
    ],
  ],
  'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical_url],
];

if ($schema_type === 'HowTo') {
    $steps = [];
    $position = 1;
    foreach ($data['sections'] as $section) {
        if (!empty($section['step_name'])) {
            $steps[] = [
                '@type' => 'HowToStep',
                'position' => $position++,
                'name' => $section['step_name'],
                'text' => $section['step_text'] ?? strip_tags($section['html'] ?? ''),
            ];
        }
    }
    if (!empty($steps)) {
        $base_schema['step'] = $steps;
    }
    if (!empty($data['total_time_iso8601'])) {
        $base_schema['totalTime'] = $data['total_time_iso8601'];
    }
}

$page_schema_json = json_encode($base_schema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$breadcrumb_schema_json = argo_breadcrumb_schema([
  'Home' => '/',
  'Guides' => '/guides/',
  $data['h1'] => $canonical_url,
]);

// Built from the same question and answer pairs the body renders, because Google requires the structured data to match what the reader sees. Answers are stripped to plain text.
$faq_schema_json = null;
if (!empty($data['faqs'])) {
    $faq_entities = [];
    foreach ($data['faqs'] as $faq) {
        if (empty($faq['q']) || empty($faq['a'])) {
            continue;
        }
        $faq_entities[] = [
            '@type' => 'Question',
            'name' => strip_tags(pricing_substitute($faq['q'])),
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => strip_tags(pricing_substitute($faq['a'])),
            ],
        ];
    }
    if (!empty($faq_entities)) {
        $faq_schema_json = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $faq_entities,
        ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}

// Layout has dedicated slots only for the primary + breadcrumb schema, so the
// FAQ block rides along in $extra_head (echoed inside <head>).
$extra_head = $extra_head ?? '';
if ($faq_schema_json !== null) {
    $extra_head .= "\n<script type=\"application/ld+json\">" . $faq_schema_json . "</script>";
}

// An article built around code blocks or comparison panels brings its own rules rather than pushing them into the sheet every other article loads. A filename only, never a URL.
if (!empty($data['stylesheet']) && preg_match('/^[a-z0-9-]+\.css$/', (string)$data['stylesheet'])) {
    $extra_head .= "\n<link rel=\"stylesheet\" href=\"" . INVGEN_BASE
        . '/guides/styles/' . $data['stylesheet'] . "\">";
}

// --- 6. Body ------------------------------------------------------------------

$callout_after = isset($data['callout_after_section_index']) ? (int)$data['callout_after_section_index'] : -1;
$tool_callout_text = $data['tool_callout_text'] ?? 'Open the free invoice generator and fill in your details now.';
$tool_callout_cta = $data['tool_callout_cta'] ?? 'Open the invoice generator';

// Carries ?source either way, so the funnel credits the click to this article. The href holds encoded ampersands and is echoed raw, since htmlspecialchars would double-encode them.
$tool_callout_url = $data['tool_callout_url'] ?? null;
if ($tool_callout_url !== null) {
    $callout_sep = strpos($tool_callout_url, '?') !== false ? '&amp;' : '?';
    $tool_callout_href = INVGEN_BASE . $tool_callout_url . $callout_sep . 'source=' . htmlspecialchars($invgen_ref);
} else {
    $tool_callout_href = INVGEN_BASE . '/invoice-generator/' . $utm_qs . '&amp;placement=inline';
}

// Every internal link in the body carries ?source, so a click through to downloads, features or the generator is credited to this article. External, anchor and tagged links are left alone.
if (!function_exists('article_tag_source')) {
    function article_tag_source(string $html, string $source): string
    {
        // An article or the guides hub is content navigation rather than a main site destination, so those links stay clean, which is better for search as well.
        static $skip = null;
        if ($skip === null) {
            $skip = ['guides' => true];
            foreach (glob(__DIR__ . '/data/*.php') as $af) {
                $as = basename($af, '.php');
                if ($as !== '_template') {
                    $skip[$as] = true;
                }
            }
        }
        return preg_replace_callback('/href="([^"]+)"/i', function ($m) use ($source, $skip) {
            $url = $m[1];
            $internal = (str_starts_with($url, '/') && !str_starts_with($url, '//'))
                || stripos($url, 'argorobots.com') !== false;
            if (!$internal || preg_match('/[?&](amp;)?source=/i', $url)) {
                return $m[0];
            }
            $path = parse_url($url, PHP_URL_PATH);
            $seg = $path !== null ? trim($path, '/') : '';
            if ($seg !== '' && strpos($seg, '/') === false && isset($skip[$seg])) {
                return $m[0];
            }
            $frag = '';
            if (($h = strpos($url, '#')) !== false) {
                $frag = substr($url, $h);
                $url = substr($url, 0, $h);
            }
            $sep = strpos($url, '?') !== false ? '&amp;' : '?';
            return 'href="' . $url . $sep . 'source=' . $source . $frag . '"';
        }, $html);
    }
}

ob_start();
?>
<article class="article-page">

  <?php /* The grid spans the whole page so the contents rail can start level
           with the headline rather than with the first section. Three blocks in
           DOM order: header, contents, body. That order is also the correct
           reading order when the layout collapses to one column. */ ?>
  <div class="article-layout">

  <div class="article-intro-block">

  <nav class="article-breadcrumb" aria-label="Breadcrumb">
    <a class="article-breadcrumb-link" href="<?= INVGEN_BASE ?>/guides/">
      <span aria-hidden="true">&larr;</span> All guides
    </a>
  </nav>

  <header class="article-head">
    <h1><?= htmlspecialchars($data['h1']) ?></h1>
      <p class="article-meta">
        <?php if (!empty($data['updated'])): ?>
          <span class="article-updated">Updated <?= htmlspecialchars($data['updated']) ?></span>
        <?php endif; ?>
        <span class="article-reading-time"><?= $reading_time_min ?> min read</span>
      </p>
    <div class="article-byline">
      <img class="article-byline-avatar"
           src="<?= INVGEN_BASE ?>/resources/images/founder.jpg"
           alt="Evan, founder of Argo Books" width="44" height="44" loading="lazy">
      <span class="article-byline-text">
        By <a class="article-byline-name" href="<?= INVGEN_BASE ?>/about-us/">Evan</a>
        <span class="article-byline-role">Founder, Argo Books</span>
      </span>
    </div>
  </header>

  <?php
    // An article whose whole point is one number can lead with it rather than bury it in the intro. `footnote` is trusted author HTML so it can carry links; the rest is escaped.
    $hero = is_array($data['hero_stat'] ?? null) ? $data['hero_stat'] : null;
  ?>
  <?php if ($hero !== null && !empty($hero['value'])): ?>
    <aside class="article-hero-stat" role="complementary">
      <?php if (!empty($hero['label'])): ?>
        <p class="article-hero-stat-label"><?= htmlspecialchars(pricing_substitute($hero['label'])) ?></p>
      <?php endif; ?>
      <p class="article-hero-stat-value">
        <?= htmlspecialchars(pricing_substitute($hero['value'])) ?>
        <?php if (!empty($hero['unit'])): ?>
          <span class="article-hero-stat-unit"><?= htmlspecialchars(pricing_substitute($hero['unit'])) ?></span>
        <?php endif; ?>
      </p>
      <?php if (!empty($hero['footnote'])): ?>
        <p class="article-hero-stat-footnote"><?= article_tag_source(pricing_substitute($hero['footnote']), $invgen_ref) ?></p>
      <?php endif; ?>
    </aside>
  <?php endif; ?>

  <section class="article-intro">
    <?= article_tag_source(pricing_substitute($data['intro_html'] ?? ''), $invgen_ref) ?>
  </section>

  <?php
    // Built from the section headings, with the anchor fallback mirroring the section loop below so every link resolves. A very short article gets none.
    $toc = [];
    foreach ($data['sections'] as $i => $section) {
        if (empty($section['h2'])) {
            continue;
        }
        $toc[] = [
            'anchor' => $section['anchor'] ?? ('section-' . ($i + 1)),
            'label'  => $section['h2'],
        ];
    }
  ?>
  </div><?php /* .article-intro-block */ ?>

  <?php if (count($toc) >= 2): ?>
    <?php /* The rail is sticky inside this wrapper, not as the grid item itself.
             A sticky grid item resolves its containing block from the grid area,
             which browsers handle inconsistently and which made the rail jitter
             on every scroll frame in Edge. A plain stretched block is stable. */ ?>
    <div class="article-toc-rail">
      <nav class="article-toc" aria-label="Table of contents">
        <p class="article-toc-title">In this guide</p>
        <ol class="article-toc-list">
          <?php foreach ($toc as $t): ?>
            <li><a href="#<?= htmlspecialchars($t['anchor']) ?>"><?= htmlspecialchars($t['label']) ?></a></li>
          <?php endforeach; ?>
        </ol>
      </nav>
    </div>
  <?php endif; ?>

  <div class="article-main">

  <?php foreach ($data['sections'] as $i => $section): ?>
    <section class="article-section" id="<?= htmlspecialchars($section['anchor'] ?? ('section-' . ($i + 1))) ?>">
      <?php if (!empty($section['h2'])): ?>
        <h2><?= htmlspecialchars($section['h2']) ?></h2>
      <?php endif; ?>
      <?= article_tag_source(pricing_substitute($section['html'] ?? ''), $invgen_ref) ?>
    </section>

    <?php if ($i === $callout_after): ?>
      <aside class="tool-callout" role="complementary">
        <p class="tool-callout-text"><?= htmlspecialchars($tool_callout_text) ?></p>
        <a class="tool-callout-link"
           data-pitch-placement="article-inline"
           href="<?= $tool_callout_href ?>">
          <?= htmlspecialchars($tool_callout_cta) ?> <span aria-hidden="true">&rarr;</span>
        </a>
      </aside>
    <?php endif; ?>
  <?php endforeach; ?>

  <?php if (!empty($data['faqs'])): ?>
    <section class="article-faqs">
      <h2>Frequently asked questions</h2>
      <?= argo_faq_grid(array_map(static fn($f) => ['q' => pricing_substitute($f['q'] ?? ''), 'a' => pricing_substitute($f['a'] ?? '')], $data['faqs'])) ?>
    </section>
  <?php endif; ?>

  <?php
    $related_niche_slugs = array_values(array_filter(
      $data['related_niche_slugs'] ?? [],
      fn($s) => is_string($s) && preg_match('/^[a-z0-9-]+$/', $s)
    ));

    // An explicit empty array opts out, because "Free invoice generators" under a piece with nothing to do with invoicing reads as a stray advert. A missing key still warns.
    $niches_opted_out = array_key_exists('related_niche_slugs', $data)
      && count($related_niche_slugs) === 0;
  ?>
  <?php if (!$niches_opted_out): ?>
    <section class="article-related-niches">
      <h2>Free invoice generators</h2>
      <?php if (count($related_niche_slugs) < 3 && function_exists('current_environment') && current_environment() === 'sandbox'): ?>
        <p class="article-dev-warning" style="border:2px solid #c00;background:#fff5f5;color:#900;padding:10px 14px;font-weight:600;">
          MISSING INTERNAL LINKS: <?= count($related_niche_slugs) ?>/3 (visible in sandbox only)
        </p>
      <?php endif; ?>
      <ul class="article-related-list">
        <?php foreach ($related_niche_slugs as $rs): ?>
          <li>
            <a href="<?= INVGEN_BASE ?>/free-invoice-generator/<?= htmlspecialchars($rs) ?>/">
              <?= htmlspecialchars(ucwords(str_replace('-', ' ', $rs))) ?> invoice generator
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <?php
    $related_article_slugs = array_values(array_filter(
      $data['related_article_slugs'] ?? [],
      fn($s) => is_string($s) && preg_match('/^[a-z0-9-]+$/', $s)
    ));
  ?>
  <?php if (!empty($related_article_slugs)): ?>
    <section class="article-related-articles">
      <h2>Related articles</h2>
      <ul class="article-related-list">
        <?php foreach ($related_article_slugs as $as): ?>
          <li>
            <a href="<?= INVGEN_BASE ?>/<?= htmlspecialchars($as) ?>/">
              <?= htmlspecialchars(ucfirst(str_replace('-', ' ', $as))) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <aside class="page-banner" role="complementary">
    <span class="page-banner-text">Argo Books is the accounting app behind these guides.</span>
    <a class="link page-banner-link"
       data-pitch-placement="article-footer"
       href="<?= INVGEN_BASE ?>/<?= $utm_qs ?>&amp;placement=footer">
      Try it free <span aria-hidden="true">&rarr;</span>
    </a>
  </aside>

  </div><?php /* .article-main */ ?>
  </div><?php /* .article-layout */ ?>

</article>
<?php
// Code blocks expand first, because their contents are raw source and must be escaped by the component before anything else walks the body looking for markup.
$body_content = article_expand_code_blocks(ob_get_clean());
$body_content = article_expand_illustrations(article_apply_link_class(article_prefix_internal_links($body_content)));

// Guides opt into the light/dark toggle. Their stylesheets go through the
// colour tokens, so the dark palette reaches them without per-rule overrides.
$theme_toggle = true;

$extra_scripts = '';

// Detected from the rendered body, so an article that calls argo_code_block() pulls in the shared styling and its copy button while one without code pays nothing.
if (strpos($body_content, 'class="code-block') !== false) {
    $extra_head .= "\n<link rel=\"stylesheet\" href=\"" . INVGEN_BASE . '/resources/styles/code-block.css">';
    $extra_scripts .= '<script src="' . INVGEN_BASE . '/resources/scripts/code-block.js" defer></script>';
}

// Highlights the contents entry for the section on screen. Only articles long
// enough to have rendered a contents list get it.
if (strpos($body_content, 'class="article-toc"') !== false) {
    $extra_scripts .= '<script src="' . INVGEN_BASE . '/resources/scripts/toc-active.js" defer></script>';
}

// Group each email example (an optional "Subject:" paragraph + the <pre> body)
// into one card and add a copy-to-clipboard button that copies subject + body.
$extra_scripts .= <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.article-section pre').forEach(function (pre) {
    if (pre.closest('.email-example')) return;
    // Email examples are a bare <pre>. A <pre><code> is a code listing, which
    // is not something a reader pastes into an email client.
    if (pre.querySelector('code')) return;

    var subject = null;
    var prev = pre.previousElementSibling;
    if (prev && prev.tagName === 'P' && /^\s*Subject:/i.test(prev.textContent)) {
      subject = prev;
    }

    var wrap = document.createElement('div');
    wrap.className = 'email-example';
    pre.parentNode.insertBefore(wrap, subject || pre);
    if (subject) { subject.classList.add('email-subject'); wrap.appendChild(subject); }
    wrap.appendChild(pre);

    // Build copy text before adding the button so its label isn't included.
    var copyText = (subject ? subject.textContent.trim() + '\n\n' : '') + pre.innerText;

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'pre-copy-btn';
    btn.setAttribute('aria-label', 'Copy to clipboard');
    btn.textContent = 'Copy';
    btn.addEventListener('click', function () {
      var done = function () {
        btn.textContent = 'Copied';
        btn.classList.add('copied');
        setTimeout(function () { btn.textContent = 'Copy'; btn.classList.remove('copied'); }, 1500);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(copyText).then(done).catch(function () {});
      } else {
        var ta = document.createElement('textarea');
        ta.value = copyText;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); } catch (e) {}
        document.body.removeChild(ta);
      }
    });
    wrap.appendChild(btn);
  });
});
</script>
HTML;

// Content and credibility links only, with no pricing or buy button, so a guide reads as a blog rather than a funnel. The shared tool layout renders this only when it is set.
$header_nav = [
  ['label' => 'Guides',        'href' => 'guides/'],
  ['label' => 'Docs',          'href' => 'documentation/'],
  ['label' => 'About',         'href' => 'about-us/'],
];

include __DIR__ . '/../shared/layout.php';

// -----------------------------------------------------------------------------

/**
 * Add `class="link"` to any inline <a> tag that has no class attribute
 * yet. Article data files write plain anchors like
 * `<a href="/foo/">bar</a>`; this brings them into the project's standard
 * link styling without forcing each anchor to be authored with the class.
 * Anchors that already carry a class (breadcrumb, tool callout, page
 * banner, etc.) are left untouched.
 */
function article_apply_link_class(string $html): string
{
    return preg_replace_callback(
        '/<a (?![^>]*\bclass=)([^>]*?)>/i',
        fn($m) => '<a class="link" ' . $m[1] . '>',
        $html
    );
}

/**
 * Prefix INVGEN_BASE onto any root-absolute internal href or img src that does
 * not already carry it. Lets article data files write friendly root paths
 * like `<a href="/net-30-vs-due-on-receipt/">` or
 * `<img src="/resources/images/foo.webp">` and have them resolve correctly
 * under Laragon's `/argo-books-website/...` mount point. On production
 * INVGEN_BASE is empty so this function is a no-op.
 *
 * Skipped: protocol-relative (`//cdn...`), absolute (`https://`),
 * fragment (`#x`), mailto, tel, and already-prefixed paths.
 */
function article_prefix_internal_links(string $html): string
{
    if (INVGEN_BASE === '') {
        return $html;
    }
    return preg_replace_callback(
        '/\b(href|src)="(\/[^"\/][^"]*)"/i',
        function ($m) {
            $attr = $m[1];
            $path = $m[2];
            if (strpos($path, INVGEN_BASE . '/') === 0 || $path === INVGEN_BASE) {
                return $m[0];
            }
            return $attr . '="' . INVGEN_BASE . $path . '"';
        },
        $html
    );
}

/**
 * Expand {{illustration:name}} tokens in article body HTML to the reusable
 * SVG figures defined in guides/illustrations.php. Runs as a final pass over
 * the assembled body, mirroring the link-class / link-prefix passes.
 */
/**
 * Expand fenced code blocks in article bodies to the shared component.
 *
 *   {{code:csharp:before|WinForms · Theme/ThemeManager.cs}}
 *   ...raw source, unescaped...
 *   {{endcode}}
 *
 * The language is any partials/code-block.php supports. The side is `before`
 * or `after`, which tints the block's rail so a migration article can show an
 * old and a new version of the same thing without the reader having to read
 * the label to tell them apart. Both the side and the label are optional.
 */
function article_expand_code_blocks(string $html): string
{
    return preg_replace_callback(
        '/\{\{code:([a-z]+)(?::(before|after))?(?:\|([^}]*))?\}\}\n(.*?)\n\{\{endcode\}\}/s',
        static function (array $m): string {
            $lang  = $m[1];
            $side  = $m[2] ?? '';
            $label = ($m[3] ?? '') !== '' ? $m[3] : null;
            $block = argo_code_block(html_entity_decode($m[4], ENT_QUOTES | ENT_HTML5, 'UTF-8'), $lang, $label);

            if ($side !== '') {
                $block = str_replace('<div class="code-block"', '<div class="code-block code-block-' . $side . '"', $block);
            }
            return $block;
        },
        $html
    ) ?? $html;
}

function article_expand_illustrations(string $html): string
{
    if (strpos($html, '{{illustration:') === false) {
        return $html;
    }
    return preg_replace_callback(
        '/\{\{illustration:([a-z0-9-]+)\}\}/',
        fn($m) => article_illustration($m[1]),
        $html
    );
}

function article_render_404(): void
{
    invgen_render_404(
        'Article not found',
        '<p>The page you asked for does not exist. Try the <a href="' . INVGEN_BASE . '/free-invoice-generator/">free invoice generator</a>.</p>'
    );
}
