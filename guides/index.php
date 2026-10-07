<?php
// guides/index.php
//
// Editorial hub for the informational articles. Served at
// argorobots.com/guides/ via Apache DirectoryIndex (no rewrite needed).
// Globs every article in guides/data/, groups them by `category`, and
// renders the categories in a fixed display order, sorted within each by
// `hub_weight`. Add an article with a category and it appears automatically.
//
// Heading structure (strict, for SEO):
//   <h1>     Guides for small businesses
//   no H2s on the index entries themselves (each list item is a link, not
//   a section). Group dividers carry small-caps labels but no headings.

require_once __DIR__ . '/../partials/schema.php';
require_once __DIR__ . '/../shared/_base.php';
require_once __DIR__ . '/../partials/fonts.php';

if (PHP_SAPI !== 'cli') {
    // Referral tracking: capture ?source so a direct landing on the guides hub
    // (from YouTube, an ad, a newsletter) is attributed in the funnel.
    require_once __DIR__ . '/../track_referral.php';
    require_once __DIR__ . '/../statistics.php';
    track_page_view('guides_hub');
}

// Category display order + labels. Articles whose category is not listed here
// fall into a trailing "More" group so nothing is ever silently dropped.
$category_labels = [
    'invoicing'         => 'Invoicing',
    'receipts-expenses' => 'Receipts & Expenses',
    'bookkeeping'       => 'Bookkeeping',
    'spreadsheets'      => 'Spreadsheets & Importing',
    'choosing-software' => 'Choosing Software',
    'engineering'       => 'Behind the Scenes',
];

// Load every article, keyed by category.
$by_category = [];
foreach (glob(__DIR__ . '/data/*.php') as $file) {
    $slug = basename($file, '.php');
    if ($slug === '_template') {
        continue;
    }
    $d = require $file;
    $cat = $d['category'] ?? 'more';
    $by_category[$cat][] = [
        'slug' => $d['slug'] ?? $slug,
        'h1' => $d['h1'] ?? $slug,
        'meta_description' => $d['meta_description'] ?? '',
        'reading_time_min' => $d['reading_time_min'] ?? null,
        'schema_type' => $d['schema_type'] ?? 'Article',
        'hub_weight' => $d['hub_weight'] ?? 1000,
    ];
}

// Sort within each category: hub_weight ascending, then headline.
foreach ($by_category as &$list) {
    usort($list, function ($a, $b) {
        return [$a['hub_weight'], $a['h1']] <=> [$b['hub_weight'], $b['h1']];
    });
}
unset($list);

// Build the ordered render groups: known categories first (in display order),
// then any leftover categories under a "More" label.
$groups = [];
foreach ($category_labels as $key => $label) {
    if (!empty($by_category[$key])) {
        $groups[] = ['key' => $key, 'label' => $label, 'items' => $by_category[$key]];
        unset($by_category[$key]);
    }
}
foreach ($by_category as $items) {
    $groups[] = ['key' => 'more', 'label' => 'More', 'items' => $items];
}

// Flat list for numbering + the ItemList schema.
$all = [];
foreach ($groups as $g) {
    foreach ($g['items'] as $it) {
        $all[] = $it;
    }
}

// Search index for the shared SiteSearch engine (resources/scripts/site-search.js):
// every guide keyed by title, section, and meta description, linking to its page.
$search_items = [];
foreach ($groups as $g) {
    foreach ($g['items'] as $it) {
        $search_items[] = [
            'title' => $it['h1'],
            'category' => $g['label'],
            'keywords' => $it['meta_description'] ?? '',
            'url' => INVGEN_BASE . '/' . $it['slug'] . '/',
        ];
    }
}

// Filter pills: one per distinct category group, in render order (deduped by
// key so multiple leftover "More" groups collapse into a single pill).
$filter_pills = [];
$seen_pill = [];
foreach ($groups as $g) {
    if (isset($seen_pill[$g['key']])) {
        continue;
    }
    $seen_pill[$g['key']] = true;
    $filter_pills[] = ['key' => $g['key'], 'label' => $g['label']];
}

$page_title = 'Guides for Small Businesses | Argo Books';
$page_description = 'Plain-language guides on invoicing, receipts and expenses, bookkeeping, and choosing the right software. Written for small businesses doing their own books.';
$canonical_url = 'https://argorobots.com/guides/';

$item_list = [];
foreach ($all as $i => $a) {
    $item_list[] = [
        '@type' => 'ListItem',
        'position' => $i + 1,
        'url' => 'https://argorobots.com/' . $a['slug'] . '/',
        'name' => $a['h1'],
    ];
}

$page_schema_json = json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'CollectionPage',
            '@id' => $canonical_url,
            'name' => 'Guides for Small Businesses',
            'url' => $canonical_url,
            'description' => $page_description,
        ],
        [
            '@type' => 'ItemList',
            'itemListElement' => $item_list,
            'numberOfItems' => count($item_list),
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$breadcrumb_schema_json = argo_breadcrumb_schema(['Home' => '/', 'Guides' => $canonical_url]);

// This hub is the only page on the lighter Fraunces weights, so it asks for the 'editorial-light' set.
$extra_head = argo_font_links('editorial-light', '')
    . '<link rel="stylesheet" href="' . INVGEN_BASE . '/guides/styles/hub.css">'
    . '<link rel="stylesheet" href="' . INVGEN_BASE . '/resources/styles/site-search.css">'
    . '<script src="' . INVGEN_BASE . '/resources/scripts/levenshtein.js"></script>'
    . '<script src="' . INVGEN_BASE . '/resources/scripts/site-search.js"></script>';

$invgen_ref = 'guides-hub';

// The hub carries the same header controls as the articles it links to, so a
// reader who picks dark on an article does not land back on a white page.
$theme_toggle = true;
$header_nav = [
  ['label' => 'Guides',        'href' => 'guides/'],
  ['label' => 'Docs',          'href' => 'documentation/'],
  ['label' => 'About',         'href' => 'about-us/'],
];

ob_start();
?>
<main class="guides-hub">

  <header class="guides-hub-head">
    <p class="guides-hub-eyebrow">Argo Books <span aria-hidden="true">&middot;</span> Guides</p>
    <h1 class="guides-hub-title">Guides for small <em>businesses</em>.</h1>
    <p class="guides-hub-lede">Plain-language guides for people who do their own books: getting invoices out the door, scanning receipts and tracking expenses, the bookkeeping basics for your trade, and picking software that fits without overpaying.</p>
  </header>

  <div class="guides-search">
    <div class="guides-search-wrap">
      <svg class="guides-search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
      <input type="text" id="guidesSearchInput" placeholder="Search the guides..." aria-label="Search guides" autocomplete="off">
    </div>
    <div id="guidesSearchResults" class="search-results"></div>
  </div>

  <script>
    (function () {
      var items = <?= json_encode($search_items, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
      document.addEventListener('DOMContentLoaded', function () {
        if (window.SiteSearch) {
          new SiteSearch({ inputId: 'guidesSearchInput', resultsId: 'guidesSearchResults', items: items });
        }
      });
    })();
  </script>

  <?php if (count($filter_pills) > 1): ?>
    <div class="guides-hub-filter" role="group" aria-label="Filter guides by category">
      <button type="button" class="guides-hub-pill is-active" data-filter="all" aria-pressed="true">All</button>
      <?php foreach ($filter_pills as $p): ?>
        <button type="button" class="guides-hub-pill" data-filter="<?= htmlspecialchars($p['key']) ?>" aria-pressed="false"><?= htmlspecialchars($p['label']) ?></button>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <ol class="guides-hub-list" role="list">
    <?php $position = 0; ?>
    <?php foreach ($groups as $group): ?>
      <li class="guides-hub-group" role="presentation" data-category="<?= htmlspecialchars($group['key']) ?>">
        <h2 class="guides-hub-group-label"><?= htmlspecialchars($group['label']) ?></h2>
        <span class="guides-hub-group-rule" aria-hidden="true"></span>
      </li>

      <?php foreach ($group['items'] as $a): $position++; ?>
        <li class="guides-hub-entry" data-category="<?= htmlspecialchars($group['key']) ?>">
          <a class="guides-hub-link"
             href="<?= INVGEN_BASE ?>/<?= htmlspecialchars($a['slug']) ?>/"
             <?php /* Stagger only the entries that can be on screen at load. Past that
                     the delay is dead time: at 45ms across 96 entries the last one waited
                     over four seconds, so refreshing near the bottom showed a blank page. */ ?>
             style="--entry-delay: <?= (min($position - 1, 11) * 45) ?>ms;">
            <span class="guides-hub-num" aria-hidden="true"><?= sprintf('%02d', $position) ?></span>
            <span class="guides-hub-body">
              <span class="guides-hub-headline"><?= htmlspecialchars($a['h1']) ?></span>
              <span class="guides-hub-desc"><?= htmlspecialchars($a['meta_description']) ?></span>
              <span class="guides-hub-meta">
                <?php if (!empty($a['reading_time_min'])): ?>
                  <span class="guides-hub-chip"><?= (int)$a['reading_time_min'] ?> min read</span>
                <?php endif; ?>
                <?php if ($a['schema_type'] === 'HowTo'): ?>
                  <span class="guides-hub-chip guides-hub-chip--howto">Step by step</span>
                <?php endif; ?>
              </span>
            </span>
            <span class="guides-hub-arrow" aria-hidden="true">&rarr;</span>
          </a>
        </li>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </ol>

  <script>
    (function () {
      var filter = document.querySelector('.guides-hub-filter');
      if (!filter) { return; }
      var list = document.querySelector('.guides-hub-list');
      var pills = filter.querySelectorAll('.guides-hub-pill');

      function pad(n) { return (n < 10 ? '0' : '') + n; }

      function apply(cat) {
        var count = 0;
        list.querySelectorAll('[data-category]').forEach(function (el) {
          var show = (cat === 'all' || el.getAttribute('data-category') === cat);
          el.hidden = !show;
          // Re-number the visible entries so the count reads 01, 02, 03...
          if (show && el.classList.contains('guides-hub-entry')) {
            var num = el.querySelector('.guides-hub-num');
            if (num) { num.textContent = pad(++count); }
          }
        });
      }

      pills.forEach(function (pill) {
        pill.addEventListener('click', function () {
          pills.forEach(function (p) {
            p.classList.remove('is-active');
            p.setAttribute('aria-pressed', 'false');
          });
          pill.classList.add('is-active');
          pill.setAttribute('aria-pressed', 'true');
          apply(pill.getAttribute('data-filter'));
        });
      });
    })();
  </script>

  <aside class="guides-hub-banner" role="complementary">
    <img class="guides-hub-banner-image"
         src="../resources/images/desk-workspace-800.webp"
         srcset="../resources/images/desk-workspace-800.webp 800w, ../resources/images/desk-workspace-1200.webp 1200w"
         sizes="(max-width: 900px) 100vw, 320px"
         width="1200" height="800"
         alt="Argo Books open on a laptop on a desk, showing the dashboard with revenue, expenses and recent transactions"
         loading="lazy" decoding="async">
    <div class="guides-hub-banner-copy">
      <p class="guides-hub-banner-eyebrow">From the guides into your books</p>
      <p class="guides-hub-banner-text">If you want to handle payments, refunds, and track everything, Argo Books is the accounting app these guides are based on.</p>
    </div>
    <a class="guides-hub-banner-link"
       data-pitch-placement="guides-hub-footer"
       href="<?= INVGEN_BASE ?>/?source=<?= htmlspecialchars($invgen_ref) ?>&amp;utm_source=guides&amp;utm_medium=hub&amp;utm_campaign=phase1&amp;placement=footer">
      Visit Argo Books <span aria-hidden="true">&rarr;</span>
    </a>
  </aside>

</main>
<?php
$body_content = ob_get_clean();

include __DIR__ . '/../shared/layout.php';
