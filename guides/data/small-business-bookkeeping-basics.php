<?php
// guides/data/small-business-bookkeeping-basics.php
// See guides/data/_template.php for schema.

return [

  'slug' => 'small-business-bookkeeping-basics',

  'h1' => 'Small business bookkeeping basics: a beginner\'s guide',

  'meta_title' => 'Small Business Bookkeeping Basics: a Beginner\'s Guide | Argo Books',

  'meta_description' => 'Bookkeeping basics for a new small business, in plain language: what to track, how to start, cash vs accrual, and a simple monthly routine that works.',

  'schema_type' => 'Article',

  // Guides hub: category + ordering (lower hub_weight lists first).
  'category' => 'bookkeeping',
  'hub_weight' => 5,

  'published' => '2026-06-02',

  'updated' => '2026-06-26',

  'reading_time_min' => 10,

  'total_time_iso8601' => null,

  'intro_html' => <<<'HTML'
<p>Bookkeeping sounds like the part of running a business you need a qualification to do. You don't. At its core, bookkeeping is just keeping an organised record of the money coming in and going out, so you know whether you're making money and so tax time isn't a panic. The jargon makes it look harder than it is.</p>
<p>This guide is the plain-language starting point. It covers what bookkeeping actually is, the handful of things every set of books needs to track, the few decisions you'll make at the start, and a simple monthly routine that keeps everything current. None of it is tax advice, and the specific rules vary by country, so treat this as the foundation and your accountant as the authority on your situation.</p>
HTML,

  'sections' => [

    [
      'h2' => 'What bookkeeping actually is',
      'anchor' => 'what-it-is',
      'html' => <<<'HTML'
<p>Bookkeeping is the day-to-day record of your business's money: every sale, every expense, who owes you, and what you owe. That's it. Keep that record accurate and current, and you can answer the two questions that matter: am I making a profit, and how much tax will I owe?</p>
<p>People mix up bookkeeping and accounting, so it's worth a quick line. <strong>Bookkeeping</strong> is recording the transactions as they happen, the ongoing job. <strong>Accounting</strong> is interpreting those records, producing the formal reports, and filing the tax return, usually done by an accountant at year-end. Good bookkeeping is what makes accounting cheap, because an accountant handed clean, organised records bills you for a fraction of what they'd charge to untangle a mess. You do the bookkeeping through the year; the accountant does the accounting at the end. The cleaner your half, the smaller their bill.</p>
HTML,
    ],

    [
      'h2' => 'The things every set of books tracks',
      'anchor' => 'what-to-track',
      'html' => <<<'HTML'
<p>Whatever tool you use, bookkeeping comes down to tracking a few things. If your records cover these, you have working books:</p>
<ul>
<li><strong>Income.</strong> Every payment that comes in, what it was for, and when. This is the top line of your business.</li>
<li><strong>Expenses.</strong> Every business cost going out, grouped into categories, with the receipt kept. This is what you subtract from income to find your real profit and your deductions. The <a href="/how-to-track-business-expenses-without-spreadsheets/">guide on tracking expenses</a> covers this in depth.</li>
<li><strong>Who owes you (receivables).</strong> Invoices you've sent that haven't been paid yet. Money you've earned but not collected. A business can look busy and still run short of cash if it loses track of this.</li>
<li><strong>What you owe (payables).</strong> Bills and suppliers you haven't paid yet. The other side of the same coin.</li>
<li><strong>Cash on hand.</strong> What's actually in the business account right now. Profit on paper and money in the bank are not the same thing, and cash is what keeps the doors open.</li>
</ul>
<p>That's the whole job. Every bookkeeping tool, from a spreadsheet to a full accounting package, is just a way of recording these five things more or less automatically. Start by understanding what you're tracking, and the tool is a detail.</p>
HTML,
    ],

    [
      'h2' => 'Step one: separate business and personal money',
      'anchor' => 'separate-money',
      'html' => <<<'HTML'
<p>Before any tool or technique, do this: open a separate bank account, and ideally a separate card for the business. Run all business income and costs through it, and keep personal spending on personal accounts.</p>
<p>This is the single highest-value thing a new business owner can do for their books, and it costs nothing. When business and personal money share one account, every tax season becomes a forensic exercise of sorting hundreds of transactions into business and personal. Mixed accounts also hide whether the business is actually profitable, because the rent and the grocery bills sit in the same list. With a separate account, the business statement is your business record. You're no longer sorting; you're just categorizing.</p>
{{illustration:coins}}
<p>You don't need a fancy business account with fees. A second basic account in the business name is enough. Get this in place first, and half the difficulty of bookkeeping disappears before you've learned a single term.</p>
HTML,
    ],

    [
      'h2' => 'Two choices you\'ll hear about: single vs double entry, cash vs accrual',
      'anchor' => 'the-choices',
      'html' => <<<'HTML'
<p>Two pieces of jargon trip up beginners. Here they are in plain language, so they stop being scary.</p>
<p><strong>Single-entry vs double-entry.</strong> Single-entry is a simple running list: money in, money out, like a chequebook. It's enough for a very small, simple business. Double-entry records every transaction twice, once as where the money came from and once as where it went, which catches errors and is what proper accounting software does for you automatically. Most easy-to-use accounting software does double-entry behind the scenes without you ever needing to think about it. You don't need to learn the mechanics; you just need to know the term exists.</p>
<p><strong>Cash basis vs accrual basis.</strong> This is about <em>when</em> you record a sale or cost. On a cash basis, you record income when the money actually lands and an expense when you actually pay it. On an accrual basis, you record income when you send the invoice (even if it's not paid yet) and a cost when you receive the bill. Cash basis is simpler and is what most small businesses start on; accrual gives a truer picture as you grow and is sometimes required above a certain size. Which one you should use depends on your country and your size, so it's a good early question for your accountant. For most people starting out, cash basis is the best answer.</p>
HTML,
    ],

    [
      'h2' => 'A simple monthly routine',
      'anchor' => 'monthly-routine',
      'html' => <<<'HTML'
<p>Bookkeeping fails when it's left for the end of the year and becomes a mountain. It works when it's a small habit. Here's a routine that keeps the books current in well under an hour a month:</p>
<ol>
<li><strong>Capture as you go.</strong> Record income when it arrives and photograph or scan each expense receipt the moment you get it, tagging the category. This is the part that has to happen continuously, not monthly, because receipts you don't capture now are gone later. A <a href="/best-free-ai-receipt-scanner/">receipt-scanning app</a> takes most of the typing out of this, especially for higher volumes.</li>
<li><strong>Once a month, check against the bank.</strong> Run down the business account and confirm every transaction is recorded and categorized. Most accounting apps have a bank-matching feature that does this for you, or at least flags anything that doesn't line up, so it's usually a quick review rather than manual work. Either way, catch a missing receipt or a miscategorized cost while it's fresh, in seconds, instead of as a mystery ten months later.</li>
<li><strong>Look at who owes you.</strong> Check which invoices are unpaid and follow up on anything overdue. This is how you actually get paid, and it's part of the books.</li>
<li><strong>Set tax money aside.</strong> Move a percentage of what you earned into a separate account so the tax bill isn't a shock. Your accountant can suggest a sensible percentage.</li>
</ol>
{{illustration:checklist}}
<p>Done monthly, the year-end is just handing your accountant tidy totals. Skipped all year, it's a two-week scramble and a bigger accounting bill. The habit is the whole game.</p>
HTML,
    ],

    [
      'h2' => 'Spreadsheet or software?',
      'anchor' => 'spreadsheet-or-software',
      'html' => <<<'HTML'
<p>You can start with either, and honestly, a spreadsheet is a fine beginning. A separate bank account, a simple sheet of income and expenses by category, and a folder of receipt photos is a complete, valid system for a new or very small business. Don't let anyone make you feel you must buy software on day one.</p>
<p>Software earns its place as the volume grows. When you're sending lots of invoices, capturing dozens of receipts a month, chasing payments, and wanting reports at tax time, the manual entry of a spreadsheet becomes the bottleneck and the errors start costing you. At that point a tool that records income, scans receipts, tracks who owes you, and produces the year-end report saves more time than it costs. If you want to compare options, the guide on the <a href="/best-free-accounting-software-for-small-business/">best free accounting software</a> is a good place to start, and there are genuinely free tools to grow on.</p>
<p>The honest rule: use whatever you'll actually keep current. A spreadsheet updated every week beats powerful software you ignore. Start simple, and upgrade when the simple version starts costing you time.</p>
HTML,
    ],

  ],

  'callout_after_section_index' => 2,

  'tool_callout_text' => 'Argo Books is free to download and does the basics for you: record income, scan receipts, track who owes you, and stay ready for tax time.',
  'tool_callout_cta' => 'Try Argo Books for free',
  'tool_callout_url' => '/downloads/',

  'faqs' => [
    [
      'q' => 'Do I need an accountant if I do my own bookkeeping?',
      'a' => 'For most small businesses, the two work together rather than one replacing the other. You do the bookkeeping through the year, the ongoing record of income and expenses, and an accountant handles the year-end accounting and tax return, where the rules get specific and the stakes are higher. Good bookkeeping makes the accountant cheap, because clean records take them far less time than untangling a mess. Some very small or simple businesses do file their own returns, but even then, a one-off conversation with an accountant when you start, to set up your categories and confirm cash versus accrual and what you can claim, usually pays for itself many times over.',
    ],
    [
      'q' => 'What is the difference between bookkeeping and accounting?',
      'a' => 'Bookkeeping is recording the day-to-day transactions, every sale, every expense, who owes you, and what you owe, kept current as the business runs. Accounting is the layer on top: interpreting those records, producing formal financial statements, and preparing and filing the tax return, usually done by an accountant. In short, bookkeeping is the ongoing record-keeping, and accounting is the analysis and reporting built on it. You can do the bookkeeping yourself with a little discipline; the accounting is where most people bring in a professional. The cleaner your bookkeeping, the easier and cheaper the accounting.',
    ],
    [
      'q' => 'Should I use cash basis or accrual basis bookkeeping?',
      'a' => 'Most small businesses start on cash basis, where you record income when the money lands and expenses when you pay them. It\'s simpler and tracks the cash you can actually see. Accrual basis records income when you invoice and costs when you are billed, which gives a truer picture of profit but is more work, and it is sometimes required once a business passes a certain size or in certain industries. The right answer depends on your country and your situation, so it is a good early question for an accountant. If you are small and just starting, cash basis is almost always the sensible, correct place to begin.',
    ],
    [
      'q' => 'How often should I do my bookkeeping?',
      'a' => 'Capture continuously and review monthly. Income and expense receipts need to be recorded as they happen, because a receipt you don\'t capture now is usually lost, and a payment you don\'t log is easy to forget. Then once a month, sit down for under an hour to check your records against the bank, see which invoices are unpaid, and set tax money aside. That rhythm, constant capture plus a monthly check, keeps the books current with very little effort. The alternative, leaving it all for year-end, turns a small ongoing habit into a stressful two-week project and usually means lost receipts and missed deductions.',
    ],
    [
      'q' => 'Is this article tax advice?',
      'a' => 'No. It\'s a plain-language introduction to bookkeeping to help a new business owner get started and ask an accountant the right questions. The specific rules, including which accounting basis you can use, what records you must keep, and what you can claim, vary by country and by your circumstances, and only someone who knows your situation and your local law can advise you. Use this to set up good habits; use a qualified accountant for decisions about your tax. This guide is on the Argo Books site, which sells bookkeeping software, so read the single product mention with that in mind.',
    ],
  ],

  'related_niche_slugs' => [
    'freelance',
    'consultant',
    'contractor',
  ],

  'related_article_slugs' => [
    'how-to-track-business-expenses-without-spreadsheets',
    'best-free-accounting-software-for-small-business',
    'small-business-tax-deductions',
  ],
];
