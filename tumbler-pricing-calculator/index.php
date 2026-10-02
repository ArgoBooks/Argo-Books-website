<?php
// tumbler-pricing-calculator/index.php
// Pricing calculator for sublimation, vinyl, and epoxy tumbler makers. Tier 1
// (see read-me/Tool-page-standards.md). Shares the craft calculator surface and
// math; this file owns the material rows, the wording, and the article.

require_once __DIR__ . '/../shared/_base.php';
require_once __DIR__ . '/../partials/craft-calculator.php';
require_once __DIR__ . '/../partials/faq.php';
require_once __DIR__ . '/../partials/schema.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../statistics.php';
    track_page_view('tumblercalc_tool');
}

$page_title = 'Tumbler Pricing Calculator: What to Charge for Custom Tumblers | Argo Books';
$page_description = 'Free pricing calculator for sublimation, vinyl, and epoxy tumblers. Add your blank, wrap, and time to get the price that actually pays you, with profit and margin worked out.';
$canonical_url = 'https://argorobots.com/tumbler-pricing-calculator/';

$tools_back = ['href' => INVGEN_BASE . '/tools/', 'label' => 'All tools'];
$ref_qs = '?source=tumblercalc-tool&amp;utm_source=tumbler-pricing-calculator&amp;utm_medium=tool&amp;utm_campaign=phase1';

$craft = [
    'unit' => 'tumbler',
    'unit_plural' => 'tumblers',
    'materials' => [
        ['label' => 'Blanks', 'hint' => 'The tumblers themselves, for however many you are doing at once.'],
        ['label' => 'Vinyl, sublimation paper, or epoxy', 'hint' => 'The decoration itself: HTV, permanent vinyl, sublimation paper and ink, or resin and glitter.'],
        ['label' => 'Transfer tape, tape, and consumables', 'hint' => 'Transfer tape, heat tape, butcher paper, gloves, mixing cups, stir sticks.'],
        ['label' => 'Packaging', 'hint' => 'Boxes, bubble wrap, care cards, mailers.'],
    ],
    'yield' => [
        'label' => 'Tumblers in this run',
        'hint' => 'How many you make in one go. Doing one at a time? Leave this at 1.',
        'default' => 1,
    ],
    'time' => [
        'label' => 'Time for the whole run',
        'hint' => 'Designing, weeding, pressing or wrapping, cleaning up, for the whole run. Epoxy makers: count only the time you are actually working, not the hours on the cup turner.',
        'default' => 45,
    ],
    'rate' => ['default' => 22],
    'overhead' => [
        'label' => 'Selling costs per tumbler',
        'hint' => 'Listing fees, card fees, shipping. Tumblers are heavy, so postage bites harder than it does on flat goods.',
    ],
    'channels' => [
        ['name' => 'Direct or social sales', 'markup' => 150],
        ['name' => 'Craft fairs and markets', 'markup' => 200],
        ['name' => 'Etsy or online shop', 'markup' => 175],
        ['name' => 'Bulk and team orders', 'markup' => 90],
    ],
    'channel_note' => 'Bulk orders take a lower markup because the design work is done once and spread across the whole run.',
];

$faqs = [
    [
        'q' => 'How much should I charge for a custom tumbler?',
        'a' => 'Start from your own cost rather than what you see in Facebook groups. Add the blank, the vinyl or sublimation supplies, your packaging, and your time, then apply a markup. Most makers selling direct use 150% to 200%, which on a typical 20oz sublimation tumbler usually lands somewhere in the $30 to $45 range once time is properly counted.',
    ],
    [
        'q' => 'How do I price my time on tumblers?',
        'a' => 'Count only hands-on time: designing, weeding, taping, pressing, and cleanup. Do not count the hours an epoxy tumbler spins on the turner or the time a press runs unattended, because you can do other things then. Pick an hourly rate you would accept from an employer and multiply it by the minutes you actually work.',
    ],
    [
        'q' => 'Why do my tumblers feel like they barely make money?',
        'a' => 'Almost always the design time. A one-off custom design can take longer than the pressing does, and if you are charging the same as a repeat design you are giving that work away. Charge a separate design fee for custom artwork, or set a higher markup on one-off orders and a lower one on designs you can sell repeatedly.',
    ],
    [
        'q' => 'Should I charge extra for custom or personalised designs?',
        'a' => 'Yes. A name and a date added to an existing template is a small surcharge. Original artwork built from a customer description is a different job, and the fair way to price it is your hourly rate against the design time, on top of the tumbler price. Put the design time into the time field to see what it is really costing you.',
    ],
    [
        'q' => 'How do I price bulk or team orders?',
        'a' => 'Set up the run as one batch. Enter the blanks and supplies for the whole order, put the real quantity in the batch field, and reduce the per-tumbler time, because the design work happens once and the pressing gets faster after the first few. A lower markup on bulk is normal and still profitable, since your time per unit has dropped.',
    ],
    [
        'q' => 'What is a good profit margin on tumblers?',
        'a' => 'Selling direct, aim for a margin of about 60% to 67%, which is a markup of 150% to 200%. Bulk and team orders usually sit closer to 47%, a 90% markup. Markup and margin are different numbers, and the calculator shows both so you can see which you are actually setting.',
    ],
    [
        'q' => 'Do I need to include shipping in my price?',
        'a' => 'Include what you pay, either in your price or as a separate shipping charge, but never absorb it silently. Tumblers are heavy and awkward, and the box, bubble wrap, and postage on a single 20oz tumbler can be more than the vinyl on it. If you offer free shipping, that cost belongs in the selling costs line.',
    ],
    [
        'q' => 'Can I use licensed characters or team logos on tumblers I sell?',
        'a' => 'Not without a licence. Copyrighted characters, sports team logos, and brand marks are owned by someone, and selling them is what gets shops shut down rather than merely warned. Original designs and properly licensed artwork are the only safe ground, and they are also the ones you can price higher.',
    ],
];

$page_schema_json = json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'SoftwareApplication',
            'name' => 'Tumbler Pricing Calculator',
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
            'creator' => ['@id' => 'https://argorobots.com/#organization'],
            'url' => $canonical_url,
        ],
        argo_faq_schema_node($faqs),
    ],
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$breadcrumb_schema_json = argo_breadcrumb_schema([
    'Home' => '/',
    'Free Tools' => '/tools/',
    'Tumbler Pricing Calculator' => $canonical_url,
]);

$extra_head = craft_calculator_head();
$extra_scripts = craft_calculator_scripts();

ob_start();
?>
<div class="calc-app">

  <section class="site-hero">
    <h1 class="site-hero-title">Tumbler Pricing Calculator</h1>
    <p class="site-hero-tagline">Sublimation, vinyl, or epoxy. Work out what a tumbler actually costs you once your time is in the number, then what to charge for it.</p>
  </section>

  <aside class="page-banner" role="complementary">
    <span class="page-banner-text">Taking orders regularly? Argo Books tracks your blanks, supplies, and real profit per order.</span>
    <a class="page-banner-link" data-pitch-placement="banner" href="<?= INVGEN_BASE ?>/features/expense-revenue-tracking/<?= $ref_qs ?>&amp;placement=banner">See how <span aria-hidden="true">&rarr;</span></a>
  </aside>

  <?= craft_calculator_render($craft) ?>

  <article class="calc-content">

    <section>
      <h2>What should you charge for a tumbler?</h2>
      <p>The number people quote each other in Facebook groups is usually "blank cost times three". That is a fine starting point and a terrible finishing point, because it silently assumes your time is free and your shipping is free.</p>
      <p class="calc-formula">Price = (blank + supplies + packaging + your time + selling costs) &times; (1 + markup)</p>
      <p>Once the time is in, most makers selling direct land on a markup of <strong>150% to 200%</strong>. The tumbler that felt like it should be $25 usually needs to be $35 or more, and that gap is almost entirely the hour you spent weeding and pressing it.</p>
    </section>

    <section>
      <h2>Time is the whole game on tumblers</h2>
      <p>Unlike candles or soap, where materials dominate, a decorated tumbler is mostly labour. A single custom design can take 45 minutes of genuine hands-on work between designing, weeding, taping, pressing, and cleanup, on a blank that cost $6.</p>
      <p>Two rules make this manageable:</p>
      <ul>
        <li><strong>Only count hands-on time.</strong> An epoxy tumbler might spin on the turner for twelve hours, but you are not standing there. Count the mixing, pouring, sanding, and finishing. Count press time only if you are tied to the machine.</li>
        <li><strong>Separate design time from production time.</strong> Making the same design twenty times is a completely different cost to making twenty different ones. If you sell a design repeatedly, its setup time gets spread; if every order is bespoke, it does not.</li>
      </ul>
    </section>

    <section>
      <h2>A worked example</h2>
      <div class="calc-example">
        <p>One custom 20oz sublimation tumbler:</p>
        <ul>
          <li>Blank: <strong>$6.50</strong>. Sublimation paper and ink: <strong>$1.80</strong>. Heat tape and butcher paper: <strong>$0.60</strong>. Box and bubble wrap: <strong>$2.10</strong>.</li>
          <li>Materials: <strong>$11.00</strong>.</li>
          <li>45 minutes of real work at $22 an hour: <strong>$16.50</strong>.</li>
          <li>Etsy fees and postage: <strong>$8.40</strong>.</li>
          <li><strong>True cost: $35.90.</strong></li>
        </ul>
        <p>At the $30 a lot of makers charge, that order <strong>loses money</strong> once your time is counted, even though it feels like a $19 profit against the blank. At a 150% markup the price is <strong>$89.75</strong>, which is more than the market will bear for a plain design, and that is genuinely useful information: it tells you this design needs to be sold more than once, or made faster, or sold at a market where you are not paying shipping.</p>
      </div>
    </section>

    <section>
      <h2>What to do when the honest price is too high</h2>
      <p>This calculator will sometimes hand you a number nobody will pay. That is not a reason to ignore it. It is the tool telling you the current setup does not work, and there are only four real levers:</p>
      <ol class="calc-list">
        <li><strong>Sell the design more than once.</strong> Spread the design time across ten sales instead of one and the per-tumbler cost collapses. This is why the makers who do well have a catalogue, not an endless queue of one-offs.</li>
        <li><strong>Get faster.</strong> Batch the weeding, cut several designs at once, press back to back while the machine is hot.</li>
        <li><strong>Cut a cost that is not the product.</strong> Cheaper mailers and a better postage rate move the number more than switching to worse vinyl.</li>
        <li><strong>Charge a design fee.</strong> Separate the artwork from the tumbler so custom buyers pay for custom work, and repeat buyers do not.</li>
      </ol>
      <p>What is not on that list is cutting your hourly rate to make the maths work. That is not a price change, it is a pay cut.</p>
    </section>

    <section>
      <h2>Bulk and team orders</h2>
      <p>Twenty tumblers for a cheer squad is not twenty individual jobs. Enter the whole run as one batch: the blanks and vinyl for all twenty, the real quantity in the batch field, and a much lower per-tumbler time, because the design is done once and pressing gets quicker after the first three.</p>
      <p>A 90% markup on a bulk run frequently pays you better per hour than a 200% markup on a one-off, which surprises people. Volume works when setup time is shared.</p>
    </section>

    <section>
      <h2>When one order stops being the question</h2>
      <p>A calculator prices one tumbler. It cannot tell you whether last month was profitable, which designs actually sell, or how much you spent on blanks over the year. Once orders are steady, those questions matter more than any single price.</p>
      <p><a class="calc-link" href="<?= INVGEN_BASE ?>/features/expense-revenue-tracking/<?= $ref_qs ?>&amp;placement=content">Argo Books</a> records your supply orders and sales, keeps fees as their own category, and shows your real profit month by month. It runs on your own computer and it is free to start.</p>
    </section>

  </article>

  <section class="calc-faqs">
    <h2>Frequently asked questions</h2>
    <?= argo_faq_grid($faqs) ?>
  </section>

</div>
<?php
$body_content = ob_get_clean();

include __DIR__ . '/../shared/layout.php';
