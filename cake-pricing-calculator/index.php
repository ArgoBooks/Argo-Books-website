<?php
// cake-pricing-calculator/index.php
// Pricing calculator for home bakers and cottage food sellers. Tier 1 (see
// read-me/Tool-page-standards.md). Shares the craft calculator surface and math;
// this file owns the ingredient rows, the wording, and the article.

require_once __DIR__ . '/../shared/_base.php';
require_once __DIR__ . '/../partials/craft-calculator.php';
require_once __DIR__ . '/../partials/faq.php';
require_once __DIR__ . '/../partials/schema.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../statistics.php';
    track_page_view('cakecalc_tool');
}

$page_title = 'Cake Pricing Calculator: What to Charge for a Cake | Argo Books';
$page_description = 'Free cake pricing calculator for home bakers. Add your ingredients, decorating time, and delivery to get a price that pays you properly, with profit and margin worked out.';
$canonical_url = 'https://argorobots.com/cake-pricing-calculator/';

$tools_back = ['href' => INVGEN_BASE . '/tools/', 'label' => 'All tools'];
$ref_qs = '?source=cakecalc-tool&amp;utm_source=cake-pricing-calculator&amp;utm_medium=tool&amp;utm_campaign=phase1';

$craft = [
    'unit' => 'cake',
    'unit_plural' => 'cakes',
    'materials' => [
        ['label' => 'Batter ingredients', 'hint' => 'Flour, sugar, eggs, butter, milk, flavourings. Cost what this cake used, not the price of the bag.'],
        ['label' => 'Filling and frosting', 'hint' => 'Buttercream, ganache, curd, fruit, jam.'],
        ['label' => 'Decorations', 'hint' => 'Fondant, sprinkles, toppers, fresh flowers, edible print, dowels.'],
        ['label' => 'Board, box, and supplies', 'hint' => 'Cake board, box, ribbon, cupcake liners, greaseproof.'],
        ['label' => 'Delivery and setup', 'hint' => 'Fuel and time if you deliver. Leave blank for collection.'],
    ],
    'yield' => [
        'label' => 'Cakes this makes',
        'hint' => 'One cake at a time? Leave this at 1. Baking a dozen cupcakes or several cakes from one bake? Put the count here.',
        'default' => 1,
    ],
    'time' => [
        'label' => 'Time for the whole bake',
        'hint' => 'Prep, baking, cooling checks, filling, crumb coat, final decorating, and cleanup. Decorating almost always takes longer than the baking.',
        'default' => 180,
    ],
    'rate' => ['default' => 25],
    'overhead' => [
        'label' => 'Kitchen and selling costs',
        'hint' => 'Oven electricity, licence and insurance spread across your cakes, card fees, and consultation time.',
    ],
    'channels' => [
        ['name' => 'Custom celebration cakes', 'markup' => 150],
        ['name' => 'Wedding and tiered cakes', 'markup' => 200],
        ['name' => 'Market and stall sales', 'markup' => 120],
        ['name' => 'Wholesale to a cafe', 'markup' => 70],
    ],
    'channel_note' => 'Wedding work carries a higher markup because of the consultation, the tasting, the delivery, and the fact that it cannot be late.',
];

$faqs = [
    [
        'q' => 'How much should I charge for a cake?',
        'a' => 'Price from your own costs rather than from a per-serving figure you found online. Add up your ingredients, your board and box, your decorating time, and your kitchen costs, then apply a markup. Most home bakers use 120% to 200%. A custom 8-inch celebration cake that takes three hours to decorate is rarely under $80 once the time is honestly counted.',
    ],
    [
        'q' => 'How do I work out the ingredient cost for one cake?',
        'a' => 'Use what the cake consumed, not the price of the packet. A $4 bag of flour holding roughly 16 cups means a cake using 3 cups costs $0.75 in flour. Do that for every ingredient once, write it down, and reuse it. The whole job takes half an hour and you will never have to guess again.',
    ],
    [
        'q' => 'Should I charge for decorating time?',
        'a' => 'Yes, and it is usually the largest single cost. Baking a sponge takes minutes of attention; piping, fondant work, and a hand-painted design take hours. If you charge for ingredients alone you are effectively decorating for free, which is why so many bakers feel busy and broke at the same time.',
    ],
    [
        'q' => 'How much should I charge per serving?',
        'a' => 'Per-serving pricing is a sanity check, not a pricing method. Work out your real price from cost and time first, then divide by servings to see where you land. If your number is wildly above local rates, the cake is probably taking too long to decorate for the design; if it is far below, you are undercharging.',
    ],
    [
        'q' => 'Why do wedding cakes cost so much more?',
        'a' => 'Because you are not only selling cake. There is the consultation, the tasting, the design work, the structural engineering of tiers, the delivery and on-site setup, and the fact that there is no second chance if something goes wrong on the day. A higher markup on wedding work reflects real extra cost and real extra risk.',
    ],
    [
        'q' => 'Should I include delivery in the cake price?',
        'a' => 'Charge it separately so buyers can see it and choose collection instead. Delivery is fuel plus your time plus the stress of transporting something fragile, and a 40-minute round trip can easily be $30 of real cost. Hiding it inside the cake price makes your cakes look expensive and your delivery look free.',
    ],
    [
        'q' => 'Do I need a licence to sell cakes from home?',
        'a' => 'In most places yes, in some form. Cottage food rules vary widely by country, state, and even county, covering what you may sell, where you may sell it, how it must be labelled, and how much you may earn before a commercial kitchen is required. Check your local food authority before your first sale, because the rules are usually easy to satisfy and expensive to ignore.',
    ],
    [
        'q' => 'What if my calculated price is higher than local bakeries?',
        'a' => 'That is useful information rather than a problem with the calculator. It usually means the design takes longer than the market will pay for. The options are to simplify the design, get faster at the decorating, or aim at customers who want bespoke work and will pay for it. Dropping your hourly rate to match a supermarket is not a pricing strategy.',
    ],
];

$page_schema_json = json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'SoftwareApplication',
            'name' => 'Cake Pricing Calculator',
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
    'Cake Pricing Calculator' => $canonical_url,
]);

$extra_head = craft_calculator_head();
$extra_scripts = craft_calculator_scripts();

ob_start();
?>
<div class="calc-app">

  <section class="site-hero">
    <h1 class="site-hero-title">Cake Pricing Calculator</h1>
    <p class="site-hero-tagline">Ingredients, decorating time, board and box, delivery. Work out what a cake really costs you, then what to charge for it.</p>
  </section>

  <aside class="page-banner" role="complementary">
    <span class="page-banner-text">Taking cake orders regularly? Argo Books tracks your ingredient costs and real profit per order.</span>
    <a class="page-banner-link" data-pitch-placement="banner" href="<?= INVGEN_BASE ?>/features/expense-revenue-tracking/<?= $ref_qs ?>&amp;placement=banner">See how <span aria-hidden="true">&rarr;</span></a>
  </aside>

  <?= craft_calculator_render($craft) ?>

  <article class="calc-content">

    <section>
      <h2>How much should you charge for a cake?</h2>
      <p>Most home bakers price a cake by feel, land somewhere near what the supermarket charges, and then wonder why an order that took a whole Saturday left them with $30.</p>
      <p class="calc-formula">Price = (ingredients + board and box + your time + kitchen costs) &times; (1 + markup)</p>
      <p>The decorating time is what separates a home baker's cake from a supermarket one, and it is exactly the thing that gets left out of the price. A three-hour decorating job at a modest $25 an hour is <strong>$75 of labour</strong> before a single ingredient is counted.</p>
    </section>

    <section>
      <h2>Costing your ingredients once, properly</h2>
      <p>You only need to do this once. Sit down with your usual recipe and work out the cost of what it actually consumes, not what the packets cost.</p>
      <ul>
        <li>A $4 bag of flour holding about 16 cups is <strong>$0.25 a cup</strong>.</li>
        <li>A dozen eggs at $5 is <strong>$0.42 an egg</strong>.</li>
        <li>A $6 block of butter at 4 sticks is <strong>$1.50 a stick</strong>.</li>
      </ul>
      <p>Write the per-unit costs on a card and keep it with your recipes. A standard two-layer 8-inch sponge usually comes to somewhere between $8 and $14 of batter and buttercream, which is almost always less than bakers guess, and almost always dwarfed by the decorating time they forgot to count.</p>
    </section>

    <section>
      <h2>A worked example</h2>
      <div class="calc-example">
        <p>An 8-inch two-layer birthday cake with buttercream and a simple piped design:</p>
        <ul>
          <li>Batter: <strong>$9.20</strong>. Filling and frosting: <strong>$7.40</strong>. Decorations and topper: <strong>$5.00</strong>. Board and box: <strong>$3.80</strong>.</li>
          <li>Materials: <strong>$25.40</strong>.</li>
          <li>Three hours of prep, baking, and decorating at $25 an hour: <strong>$75.00</strong>.</li>
          <li>Oven electricity, insurance share, and card fees: <strong>$6.00</strong>.</li>
          <li><strong>True cost: $106.40.</strong></li>
        </ul>
        <p>At a 150% markup the cake is <strong>$266</strong>, which is well above the market for a simple birthday cake. That is the calculator doing its job: it is telling you three hours is too long for this design at this price point. Either the design has to get faster, or it has to be sold as bespoke work to someone who wants exactly that. At <strong>$130</strong> the cake still covers costs and pays you your $25 an hour, but leaves almost nothing on top, which is a decision you should make knowingly rather than by accident.</p>
      </div>
    </section>

    <section>
      <h2>The costs bakers leave out</h2>
      <ol class="calc-list">
        <li><strong>Consultation and messaging time.</strong> Twenty messages about shades of pink is real work. Build it into your markup or charge a design fee on bespoke orders.</li>
        <li><strong>Tastings and samples.</strong> Especially for weddings. Someone pays for those, and right now it is you.</li>
        <li><strong>The cake that failed.</strong> A collapsed sponge on the morning of the order costs you the ingredients twice and the time twice.</li>
        <li><strong>Equipment wear.</strong> Tips, moulds, cutters, and pans get replaced. Spread a little of that into your kitchen costs.</li>
        <li><strong>Licensing and insurance.</strong> An annual cost that belongs spread across your cakes, not absorbed silently.</li>
      </ol>
    </section>

    <section>
      <h2>Per-serving pricing, and why it misleads</h2>
      <p>"Charge $4 to $6 a serving" is the most repeated advice in cake groups and the least useful, because a serving is not a unit of work. A sheet cake serving 40 people with a plain frosted top is far less work than a two-tier serving 30 with hand-piped detail, yet per-serving pricing charges more for the easy one.</p>
      <p>Work out your real price from cost and time, then divide by servings afterwards to sanity check where you have landed against local rates. Use the per-serving number as a mirror, never as the method.</p>
    </section>

    <section>
      <h2>Cottage food rules are worth ten minutes of your time</h2>
      <p>Selling food from a home kitchen is regulated nearly everywhere, and the rules differ by country, state, and sometimes county. They typically cover which foods you may sell, whether you can post them or only sell in person, what has to be on the label, and the earnings ceiling before a commercial kitchen is required.</p>
      <p>Look up your local food authority before your first paid order. The requirements are usually modest, and the consequences of skipping them are not.</p>
    </section>

    <section>
      <h2>When one cake stops being the question</h2>
      <p>A calculator prices one order. It cannot tell you whether the month made money, which cakes are worth the effort, or what you spent on ingredients across the year. Once orders are regular, those are the questions that decide whether this is a business or an expensive hobby.</p>
      <p><a class="calc-link" href="<?= INVGEN_BASE ?>/features/expense-revenue-tracking/<?= $ref_qs ?>&amp;placement=content">Argo Books</a> records your shopping and your orders, keeps fees separate, and shows your real profit month by month. It runs on your own computer and it is free to start.</p>
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
