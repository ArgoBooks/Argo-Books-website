<?php
// guides/data/how-to-switch-from-quickbooks.php
// See guides/data/_template.php for schema.

return [

  'slug' => 'how-to-switch-from-quickbooks',

  'h1' => 'How to switch from QuickBooks: a step-by-step guide',

  'meta_title' => 'Switch From QuickBooks: a Step-by-Step Guide | Argo Books',

  'meta_description' => 'How to switch from QuickBooks step by step: what data to bring, which reports to save as a permanent archive, when to start, and how to check the numbers.',

  'schema_type' => 'HowTo',

  // Guides hub: category + ordering (lower hub_weight lists first).
  'category' => 'choosing-software',
  'hub_weight' => 20,

  'published' => '2026-07-22',

  'updated' => '2026-09-30',

  'reading_time_min' => 11,

  'total_time_iso8601' => null,

  'intro_html' => <<<'HTML'
<p>Switching accounting software feels riskier than it is. Your books hold years of invoices, expenses, and reports, and the thought of moving all of that somewhere new is enough to keep plenty of people paying for a subscription they've outgrown, or outgrown paying for. The quiet truth is that most switches are far smaller jobs than they look, because you don't need to move most of that history. You need a clean starting point, a handful of open items, and a permanent copy of the past you can open any time.</p>
<p>This guide walks through the whole process in seven steps, ending with bringing everything into Argo Books: deciding what actually needs to come with you, exporting from QuickBooks (Online or Desktop), saving the reports that become your archive, picking a start date, importing into the new tool, running a short overlap to check the numbers, and finally cancelling. No horror stories, no pressure. Just the calm, boring way to move your books without losing anything that matters.</p>
HTML,

  'sections' => [

    [
      'h2' => 'Step 1: decide what you actually need to bring',
      'anchor' => 'what-to-bring',
      'step_name' => 'Decide what you actually need to bring',
      'step_text' => 'List the data that must move with you: open invoices, your customer and vendor lists, this year\'s transactions, and closing balances. Old history stays behind as an archive export rather than moving into the new tool.',
      'html' => <<<'HTML'
<p>This is the step that shrinks the whole project. People imagine they have to move every transaction since the day they opened, and that idea alone is what makes switching feel impossible. In practice, the list of things that genuinely need to live inside your new software is short:</p>
{{illustration:checklist}}
<ul>
<li><strong>Open invoices.</strong> Anything a customer still owes you. These need to exist in the new tool so you can chase them and record the payments when they arrive.</li>
<li><strong>Your customer and vendor lists.</strong> Names, emails, addresses. You'll invoice these people again next month, so bring them along.</li>
<li><strong>This year's transactions.</strong> Income and expenses from your chosen start date onward, so the current year's books are complete in one place for tax time.</li>
<li><strong>Closing balances.</strong> The bank balance and any amounts owed to you or by you as of your switch date, so the new books start from reality instead of zero.</li>
<li><strong>Items or products,</strong> if you sell from a list of set products or services.</li>
</ul>
<p>Everything else, five years of paid invoices, old expense detail, ancient reports, does not need to be re-entered anywhere. It needs to be saved, which is a different and much easier job, and it's exactly what steps 2 and 3 handle. Old history belongs in an archive you keep, not in the working books you use every day.</p>
HTML,
    ],

    [
      'h2' => 'Step 2: export your data from QuickBooks',
      'anchor' => 'export-from-quickbooks',
      'step_name' => 'Export your data from QuickBooks',
      'step_text' => 'Use QuickBooks\' export options, found under its reports and list screens, to save your customers, vendors, items, and transaction reports as Excel or CSV files in one folder.',
      'html' => <<<'HTML'
<p>QuickBooks lets you export your lists and reports to Excel or CSV files, and those files are the raw material for everything that follows. Both QuickBooks Online and QuickBooks Desktop can do this, though the menus differ between the two and Intuit rearranges them from time to time, so rather than memorizing exact clicks, look for the export options under the reports and lists screens. Most list pages and most reports have an export button somewhere near the top, often behind a small spreadsheet or download icon.</p>
<p>Work through your lists first: customers, vendors, and your products and services. Export each one to Excel or CSV. Then export transaction detail for the current year, a transaction list or general ledger style report covering your chosen date range usually does the job. If you run payroll or track inventory in QuickBooks, export those reports too, even if you're not sure you'll need them.</p>
<p>Save everything into one folder with a clear name, something like "QuickBooks export 2026", and don't tidy or edit the files yet. The goal at this stage is simply to get complete copies out while you still have full access to your account. Messy but complete beats neat but missing, and you can clean up columns later when you import.</p>
HTML,
    ],

    [
      'h2' => 'Step 3: download key reports as your permanent archive',
      'anchor' => 'permanent-archive',
      'step_name' => 'Download key reports as your permanent archive',
      'step_text' => 'Save profit and loss, balance sheet, open invoice, and aged receivables reports as PDF and spreadsheet files for every year you have. This archive means you keep your history even after the subscription ends.',
      'html' => <<<'HTML'
<p>Here's the honest answer to the biggest fear about switching: no, you don't lose your history, but only if you save it before you cancel. Once a QuickBooks subscription ends, your access to the data eventually goes with it, and at the time of writing, read-only grace periods exist but have limits. So treat this step as non-negotiable. Before you cancel anything, download a set of reports that captures the story of your business:</p>
<ul>
<li><strong>Profit and loss</strong>, for each year you've been on QuickBooks, plus the current year to date.</li>
<li><strong>Balance sheet</strong>, as of each year-end and as of your switch date.</li>
<li><strong>Open invoices</strong>, so you have an independent record of who owed you what on the day you moved.</li>
<li><strong>Aged receivables</strong> (and aged payables if you owe suppliers), for the same reason.</li>
<li><strong>Sales tax reports</strong>, if you collect sales tax, GST, or VAT, since tax authorities can ask about past periods years later.</li>
</ul>
<p>Save each one as a PDF, and where QuickBooks offers it, as a spreadsheet too. The PDF is the human-readable record; the spreadsheet is the version you can dig into later. Put them in the same folder as your step 2 exports, back that folder up somewhere safe, ideally two places, and you now hold your history outright. No subscription required to read your own past, ever again. That folder is what makes the rest of the switch low-stakes.</p>
HTML,
    ],

    [
      'h2' => 'Step 4: pick your start date',
      'anchor' => 'pick-start-date',
      'step_name' => 'Pick your start date',
      'step_text' => 'Choose the first day of a month, quarter, or year as the dividing line. Everything before it lives in your archive; everything from that day onward lives in the new tool.',
      'html' => <<<'HTML'
<p>Your start date is the dividing line: everything before it lives in your archive, everything on or after it lives in your new software. Picking a clean line keeps the books simple, and the cleanest lines are the natural ones. The start of a month is good. The start of a quarter is better. The start of your financial year is best of all, because then an entire tax year sits in one system and your accountant never has to stitch two tools together for a single return.</p>
<p>That said, don't wait half a year just to get a perfect date. If it's August and you're done with QuickBooks, the first of next month is a perfectly good line. You'd enter this year's transactions from January through your start date into the new tool (or import them, see the next step) so the full year ends up in one place, and let the archive cover everything earlier.</p>
<p>Whatever date you pick, write down your closing balances as of the day before: bank accounts, total owed by customers, total owed to suppliers. Those numbers are the handshake between the old books and the new ones, and you'll use them again in step 6 to prove the switch worked.</p>
HTML,
    ],

    [
      'h2' => 'Step 5: import into Argo Books',
      'anchor' => 'import-new-tool',
      'step_name' => 'Import your exported files into Argo Books',
      'step_text' => 'Open Import, choose QuickBooks, and add every file you exported. Argo Books reads the columns itself, so there is no mapping spreadsheet to build first. ',
      'html' => <<<'HTML'
<p>Now the files you exported come back to life. In Argo Books, open the <strong>Import</strong> menu and choose <strong>QuickBooks</strong>, say whether you came from QuickBooks Online or Desktop, and add every file at once. There is no order to get right and no mapping spreadsheet to build first: the importer reads each file and works out what the columns mean, including when QuickBooks has named them something only QuickBooks uses.</p>
{{illustration:spreadsheet-to-books}}
<p><strong>Export as Excel rather than CSV wherever QuickBooks offers both.</strong> QuickBooks reports are not plain tables. They carry heading lines at the top, group headings in the middle, and subtotal rows mixed in with the data. Argo Books reads around all of that in an Excel file. A CSV is treated as a plain table, so those subtotal rows can be read as if they were transactions, which quietly inflates your totals.</p>
<p>What arrives: your customers, suppliers, products, employees, open invoices, and the revenue and expenses for whatever period you chose in step 4. What does not arrive is your chart of accounts and journal entries, and that is by design rather than a shortfall. Argo Books works out your Income Statement, Balance Sheet and General Ledger from the transactions themselves, so there is no separate list of accounts for them to land in. Export transaction detail reports instead of the General Ledger or Journal.</p>
<p>Be realistic about what "import" means. This is an import, not an automatic migration: no tool waves a wand over a QuickBooks account and rebuilds it perfectly somewhere else, and any service claiming otherwise still involves cleanup. Nothing is written to your books until you confirm, so look over what was understood before committing to it.</p>
<p>The <a href="/documentation/pages/features/quickbooks-import.php">Import from QuickBooks documentation</a> lists exactly which reports to export from each version and what each one becomes. If your data lives partly in spreadsheets already, our guide on <a href="/how-to-convert-excel-spreadsheet-to-accounting-software/">converting a spreadsheet into accounting software</a> covers that side in detail.</p>
HTML,
    ],

    [
      'h2' => 'Step 6: run a short overlap and check the numbers',
      'anchor' => 'overlap-check',
      'step_name' => 'Run a short overlap and check the numbers match',
      'step_text' => 'Keep both tools available for a few weeks. Compare bank balances, amounts owed by customers, and the current year\'s profit between old and new, and fix any differences before relying on the new books.',
      'html' => <<<'HTML'
<p>Don't cancel QuickBooks the same day you import. Give yourself a short overlap, a few weeks or one full month is usually plenty, where the old account still exists and the new tool is doing the real work. During that window, check three numbers between the two systems:</p>
<ol>
<li><strong>Bank balance.</strong> The balance in your new books as of the switch date should match your closing balance from step 4, and both should match up with what your actual bank statement says.</li>
<li><strong>Amounts owed by customers.</strong> The open invoices you re-created should add up to the same total as the open invoice report you archived. If a customer pays an old invoice during the overlap, record it in the new tool.</li>
<li><strong>Year-to-date profit.</strong> If you brought in the current year's transactions, run a profit and loss in the new tool and compare it to the one you saved from QuickBooks. Small differences usually trace back to a duplicated or missed transaction, and they're much easier to find now than at tax time.</li>
</ol>
<p>Once those three agree, you have real evidence the switch worked, not just a feeling. Do all new invoicing and expense entry in the new tool during the overlap; the old account is a reference, not a workspace. Entering things in both places doubles the work and invites the two systems to drift apart.</p>
HTML,
    ],

    [
      'h2' => 'Step 7: cancel, and keep your archive forever',
      'anchor' => 'cancel-keep-archive',
      'step_name' => 'Cancel QuickBooks and keep your archive',
      'step_text' => 'Once the numbers agree, cancel the subscription, confirm the cancellation in writing, and store your archive folder in two safe places for future tax questions.',
      'html' => <<<'HTML'
<p>When the numbers agree and you've stopped opening QuickBooks for anything but reference, it's time to finish the job. Before you press cancel, do one last sweep: confirm your archive folder actually contains the reports from step 3, open a couple of the files to make sure they're readable, and check you've exported anything you added since, like invoices created during the overlap. Then cancel the subscription and keep the confirmation email.</p>
<p>Your archive folder is now a permanent part of your business records. Store it in at least two places, for example your computer plus a cloud drive or an external drive, and treat it with the same care as your tax filings. Tax authorities in most countries can ask about past years long after the fact, often six or seven years back, so those PDFs may earn their keep one day. The exact retention rules vary by country, so check with your local tax authority or accountant.</p>
<p>And that's the whole switch. If you're still weighing up where to land, our guide to the <a href="/best-quickbooks-alternatives/">best QuickBooks alternatives</a> compares the realistic options, including where Argo Books fits and where it doesn't. None of this is a criticism of QuickBooks; it's a capable tool. But plenty of people are simply done paying for more than they use, and moving on is a smaller, calmer job than it looks from the outside.</p>
HTML,
    ],

  ],

  'callout_after_section_index' => 2,

  'tool_callout_text' => 'Argo Books imports the reports QuickBooks already knows how to export. Choose QuickBooks in the Import menu, add the files, and your customers, suppliers, products, employees and transactions come across without a mapping step.',
  'tool_callout_cta' => 'See which reports to export',
  'tool_callout_url' => '/documentation/pages/features/quickbooks-import.php',

  'faqs' => [
    [
      'q' => 'Will I lose my QuickBooks history when I cancel?',
      'a' => 'Not if you save it first, and that\'s the whole point of building an archive before you cancel. Export your lists and transaction reports to spreadsheets, and download your key reports (profit and loss, balance sheet, open invoices, aged receivables, and sales tax reports) as PDFs for every year you were on QuickBooks. Once those files are saved on your own computer and backed up somewhere second, your history belongs to you outright and no subscription is needed to read it. At the time of writing QuickBooks offers some read-only access after cancelling, but it has limits, so the safe habit is simple: download everything while your account is fully active, then cancel.',
    ],
    [
      'q' => 'Do I need to move every old transaction into my new software?',
      'a' => 'No, and trying to is the main reason switches feel overwhelming. Your new software only needs what you\'ll actively use: open invoices, customer and vendor lists, this year\'s transactions, and your closing balances as of the switch date. Everything older belongs in your archive, the folder of exports and PDF reports you saved before cancelling. You almost never need a five-year-old paid invoice inside your day-to-day books; if a question ever comes up, you open the archive and look it up there. Keeping old history out of the new tool also means you start with clean, current books instead of spending days re-entering data you\'ll never touch again.',
    ],
    [
      'q' => 'Can I switch from QuickBooks Desktop, or only QuickBooks Online?',
      'a' => 'Both work, and the process in this guide is the same for either. QuickBooks Desktop and QuickBooks Online can each export lists (customers, vendors, items) and reports to Excel or CSV files, which is all you need. The menus sit in different places between the two products and Intuit moves them around over time, so look for export options on the reports and list screens rather than following exact click paths from an old tutorial. Desktop users have one extra consideration: because the software runs locally, your access doesn\'t vanish the moment a subscription lapses in the same way, but you should still build a complete archive of exports and PDF reports rather than relying on keeping an old program installed forever.',
    ],
    [
      'q' => 'When is the best time to switch accounting software?',
      'a' => 'The start of your financial year is the cleanest moment, because a full tax year then lives in one system and your accountant never has to combine two tools for one return. The start of a quarter is next best, and the start of any month is still perfectly workable. What matters more than the perfect date is a clean line: pick a day, record your closing balances as of the day before, and put everything from that day forward in the new tool. If you\'re mid-year, you can import the year\'s transactions so far so the whole year still ends up in one place. Don\'t stay on software you\'re done with for months just to wait for January.',
    ],
    [
      'q' => 'How long does switching from QuickBooks actually take?',
      'a' => 'For a small business doing its own books, the hands-on work is usually a few hours spread over a week or two, not the giant project people fear. Exporting lists and reports might take an hour. Downloading your archive reports takes another hour, mostly waiting for PDFs. Importing into the new tool depends on how much cleanup your files need, but with a tool that reads spreadsheets intelligently it\'s often an afternoon at most. The overlap period runs a few weeks by design, but that\'s mostly waiting and a short numbers check, not daily effort. The bigger your business and the more open invoices you carry, the longer the import step takes, but the shape of the job stays the same.',
    ],
  ],

  'related_niche_slugs' => [
    'generic',
    'freelance',
    'consultant',
  ],

  'related_article_slugs' => [
    'best-quickbooks-alternatives',
    'quickbooks-self-employed-discontinued',
    'how-to-convert-excel-spreadsheet-to-accounting-software',
    'is-quickbooks-worth-it-for-small-business',
  ],
];
