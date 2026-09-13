# Tool page standards

Conventions every free tool under `/tools/` follows, so a visitor moving between two tools cannot tell they were built months apart, and adding a new one means filling in a known shape rather than re-deciding everything.

## Currency

### The canonical list

One supported currency list, 29 codes. On the website it lives in `shared/currencies.php`, which every page offering a currency choice reads from. Nothing else on the site defines a currency list.

```
ALL AUD BAM BGN BRL BYN CAD CHF CNY CZK DKK EUR GBP HUF INR
ISK JPY KRW MKD NOK PLN RON RSD RUB SEK TRY TWD UAH USD
```

USD, EUR, CAD, AUD are pinned to the top of every picker as the "Common" group, in that order.

The website list mirrors two files in the desktop app, which must agree with each other: `ArgoBooks.Core/Models/Common/CurrencyInfo.cs` (code, symbol, name, decimals, used for parsing) and `ArgoBooks/Data/Currencies.cs` (what appears in a dropdown). Adding a currency means editing all three. They had already drifted once, with INR present in the parser and missing from every dropdown.

### Which tools offer which

Every tool is one of three tiers. Pick the tier when you start the tool and name it in the file header comment.

**Tier 1, the full list.** The default: the user types their own numbers and currency is presentation only. All 29 codes, Common group on top. Invoice, estimate, and purchase order generators, craft pricing calculator, profit analyzer.

**Tier 2, data-constrained.** The math depends on published jurisdiction figures (tax rates, platform fees, thresholds), so only countries with verified numbers are offered. The picker is a *country* selector and the currency follows from it. Adding a country means adding verified rates and a source URL to the tool's data file, never a UI-only change. Etsy fee calculator (US, Canada, UK, Australia), self-employed tax calculator (US, Canada).

**Tier 3, inferred.** The currency is read from what the user supplies, not asked for. Receipt scanner only: it takes the code off the receipt and displays it so the user can correct it.

The invoice template library sits outside all three. Static files, no currency logic.

### Formatting

- Format with `Intl.NumberFormat`, never `toFixed(2)` plus a hardcoded symbol. `toFixed` truncates on binary floating point, so `(3.585).toFixed(2)` gives `3.58` where `Intl` correctly gives `$3.59`. Two places showing the same number must not disagree by a cent.
- Always pass a locale matched to the currency, falling back to `en-US`. Emit the map with `argo_currency_locales()` into `window.ARGO_CURRENCY_LOCALES` and read it from there. Never hardcode a second copy in JS.
- Render pickers with `argo_currency_options()`. Do not hand-write `<option>` lists.
- Measure the input's left padding off the rendered symbol rather than fixing it in CSS. Symbols run from one glyph (`$`) to three (`CHF`, `NT$`).
- Use `currencyDisplay: 'narrowSymbol'` so CAD and AUD render as `$`, not `CA$` and `A$`. Wrap in a `try`, since a few older engines throw on it.
- Show the ISO code beside the amount where two dollar currencies could be confused.
- Never hardcode `$` in markup. Input affixes get their symbol set from JS on currency change.
- Any figure that updates gets `font-variant-numeric: tabular-nums` so digits do not jitter as the user types.
- Annual and aggregate figures round to whole units (`maximumFractionDigits: 0`). Per-item figures keep cents.

### In the page copy

Worked examples use USD. Do not mix currencies between an example and a table on the same page. Any number written into prose must match what the calculator outputs for those inputs: check it, do not work it out by hand.

## Page shell

Tools use `shared/layout.php`, not the marketing header and footer. It supplies the slim header, "All tools" breadcrumb, canonical and Open Graph tags, and the sitewide Organization schema.

```php
require_once __DIR__ . '/../shared/_base.php';
$tools_back = ['href' => INVGEN_BASE . '/tools/', 'label' => 'All tools'];
// ... set $page_title, $page_description, $canonical_url, $extra_head, $extra_scripts
$body_content = ob_get_clean();
include __DIR__ . '/../shared/layout.php';
```

Internal links take the `INVGEN_BASE` prefix so they resolve under a local Laragon subfolder mount. The profit analyzer and receipt scanner predate this shell and roll their own HTML; do not copy them.

## File layout

```
<tool-slug>/
  index.php                page meta, schema, markup, article content, FAQ
  data/<name>.php          rates, thresholds, anything with a shelf life
  scripts/calc.js          pure math, no DOM, no globals
  scripts/calc.test.js     node --test
  scripts/main.js          DOM wiring and formatting
  styles/<name>.css        scoped to one class prefix
```

The slug is what people search: `/etsy-fee-calculator/`, not `/fees/`.

Three stylesheets load in order: `invoice-generator/styles/tool.css` (site chrome, `.faq-*` accordion), `shared/styles/calculator.css` (the calculator component: `.calc-app`, `.calc-field`, `.calc-money`, `.calc-results`, `.calc-breakdown-row`, `.calc-content`, `.calc-table`, `.calc-faqs`), then the per-tool sheet holding only what is genuinely its own, behind one prefix (`.etsy-`, `.craft-`). Use the `.calc-*` classes rather than redefining them; add a tool-prefixed override only where the value truly differs. Colours come from `custom-colors.css` variables, never hex literals, so dark mode is inherited.

Shared JS lives in `shared/scripts/`. `currency-format.js` supplies `currencyFormatter()`, `symbolFor()`, and `applyCurrencyAffixes()`; do not rebuild `Intl.NumberFormat` setup in a tool.

## Rates and anything with a shelf life

Tax brackets, platform fees, statutory thresholds: all live in `data/` and nowhere else.

- One file per tool, returning a PHP array, with a `verified` date at the top that the page prints near the calculator.
- Source URLs in a comment beside the rates.
- The same array feeds the on-page tables and is emitted to the client as JSON. Never hand-write a rate into the copy, interpolate it. A rate change must be a one-file edit.
- Keep `calc.js` free of rates. It takes them as an argument, which lets the tests supply their own fixtures.

## Calculation code

`calc.js` exports pure functions: no DOM, no globals, no formatting. `main.js` does the reading, formatting, and painting. That split is what makes the math testable in Node, and what lets a tool with a forward mode and a solve-backwards mode run both through one code path so they cannot disagree.

Coerce every form value through one helper that turns blanks, junk, and negatives into `0`. A `NaN` on screen is the most common failure in a tool like this.

## Tests

Every tool with non-trivial math has `scripts/calc.test.js`, run by `node --test` and wired into the `test` script in `package.json`. Server-side tool code goes under `tests/Unit/` for PHPUnit.

Cover at minimum:

- one hand-worked example per supported country or rate set
- every conditional fee or bracket, on both sides of its threshold
- caps and floors, at the point they start to bind
- empty and zero input producing zeros, not `NaN`
- if the tool solves backwards, a round trip: solve for a target, feed the answer back through the forward math, assert you land on it

Write expected values out longhand in a comment above each test. A test that re-runs the implementation proves nothing.

## Content layer

Every tool carries a real article below the calculator: the direct answer to the search query in the first paragraph, each component explained, a comparison table rendered from `data/`, how to use the tool, a worked example with real numbers, how to do better or pay less, common mistakes, when the tool stops being enough, then the FAQ.

Define the FAQ once as a PHP array and render it with `argo_faq_grid($faqs)` from `partials/faq.php`, which also emits the accordion script. The `FAQPage` schema comes from the same array via `argo_faq_schema_node($faqs)`, so the visible answers and the structured data cannot drift. Never hand-write the accordion markup or a toggle script.

Schema on every tool page is `SoftwareApplication` and `FAQPage` in a `@graph`, plus a `BreadcrumbList` from `argo_breadcrumb_schema()` in `partials/schema.php`, taking a label-to-path map: `['Home' => '/', 'Free Tools' => '/tools/', 'Etsy Fee Calculator' => $canonical_url]`. If the page names a third-party product, add a no-affiliation and trademark line at the foot.

## How Argo Books is mentioned

Three mentions maximum, each one after the reader has been given something useful: a `.page-banner` aside under the hero, one inline mention in the body where it genuinely answers the sentence before it, and a closing section on what the tool cannot do that the product can.

Never interrupt the calculator. No exit popups, gated results, or email walls. The tool working fully without any of that is the entire reason people link to it.

Every link carries the tracking query string, with `placement` marking where it sat:

```php
$ref_qs = '?source=<tool>-tool&utm_source=<tool-slug>&utm_medium=tool&utm_campaign=phase1';
// then: <?= $ref_qs ?>&placement=banner  |  &placement=content
```

## Tracking and registration

Page views are tracked with a short tool name plus the `_tool` suffix (`etsycalc_tool`, `craftcalc_tool`, `pogen_tool`), skipped under CLI so a render check does not pollute stats:

```php
if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../statistics.php';
    track_page_view('<short>_tool');
}
```

A new tool is not finished until all four are done:

1. A row in the right group of `$tool_groups` in `tools/index.php`, with its own one-line description and an icon added to `$tool_icons` in the same file
2. An entry in the `$toolPages` block in `sitemap_urls.php`
3. Its test file in the `test` script in `package.json`
4. `php -l` clean, and one render through the PHP CLI with no notices

## Accessibility and layout

- Every input has a real `<label>`; a placeholder is not a label
- Result panels are `aria-live="polite"`
- Inputs at least 44px tall, `inputmode="decimal"` on money fields
- Mode switches use `role="tablist"` and keep `aria-selected` in sync
- Fields toggled with the `hidden` attribute need `[hidden] { display: none !important; }` scoped to the tool, or an author-declared `display: flex` beats the user agent rule and the field stays on screen
- Tables that can overflow sit in a wrapper with `overflow-x: auto`
- Single column below 860px, and the sticky results panel unsticks

## Known deviations

Current as of 2026-07-30. Listed so they get fixed rather than copied.

| Tool | Deviation |
|---|---|
| Profit analyzer | Tracks as `profit_analyzer`, missing the `_tool` suffix. Left alone deliberately: `track_page_view` keys stats rows by that string, so renaming it splits the page into two series and orphans its history. Not worth it for a naming rule. New tools use the suffix. |
| Profit analyzer, receipt scanner | Still roll their own HTML instead of `shared/layout.php`. Both now link back to the hub from their own nav, so the user-facing gap is closed, but they remain the two pages that do not get shell improvements for free. Worth migrating if either gets a redesign; not worth a standalone refactor. |
| Profit analyzer | No unit tests under `tests/Unit/`, unlike the receipt scanner. The FX conversion and dominant-currency logic in `lib/import/currency.php` is the part worth covering first. |

## Shared code, and what it replaced

Reach for these rather than writing the pattern again. Each replaced a block that had been hand-copied across the site.

| Use this | Instead of | Was duplicated in |
|---|---|---|
| `partials/faq.php` (`argo_faq_grid`, `argo_faq_schema_node`) | Hand-written accordion markup, an inline toggle script, and a separate FAQPage block | 26 pages |
| `resources/scripts/faq-accordion.js` | Per-page FAQ open/close script (loaded automatically by the partial) | 26 pages |
| `partials/schema.php` (`argo_breadcrumb_schema`) | Hand-written BreadcrumbList JSON with manual position numbering | 47 pages |
| `shared/currencies.php` | A copy of the currency list | 3 places on the site, 2 in the desktop app |
| `shared/scripts/currency-format.js` | `Intl.NumberFormat` setup with narrowSymbol and a try/catch fallback | 3 tools |
| `partials/craft-calculator.php` + `shared/scripts/craft-engine.js` | A whole batch-to-unit pricing tool | 5 craft calculators |
| `shared/scripts/business-calcs.js` | Mileage, hourly rate, late fee, markup, break-even, and stall math | 6 calculators |
| `shared/data/mileage-rates.php` | Statutory mileage rates, with sources and a verified date | the mileage calculator |
| `shared/styles/calculator.css` | Per-tool copies of the form, results, and article styles | 2 tools |
| `admin/preserve-scroll.js` | The sessionStorage scroll save/restore dance | 6 admin pages |
| `argo_send_html_email()` in `smtp_mailer.php` | `create_smtp_mailer()` plus a hand-built `mail()` fallback | 4 senders |

A note on `partials/faq.php`: it renders the question as a `<button>`, not a `<div>`, so the accordion is reachable by keyboard. `resources/styles/faq.css` carries the reset that makes the button look like the div it replaced.

`argo_send_html_email()` covers the common case. `email_sender.php` and `api/invoice/invoice_email_sender.php` still have their own senders because they do more (templating, message-ID threading, per-send logging). Move them across only when touching them for another reason.

## The craft product calculators

Five tools price a handmade product from a batch: craft (generic), candle, soap, tumbler, and cake. They are the same math and the same surface, differing only in their material rows and their wording, so a new one is a config plus an article rather than a new build.

To add one, create `<craft>-pricing-calculator/index.php` with a `$craft` config (unit noun, material rows, batch yield, time, markup presets by channel), call `craft_calculator_render($craft)`, and use `craft_calculator_head()` and `craft_calculator_scripts()` for the assets. Everything else, the batch division, the currency picker, the channel comparison table, comes for free.

The batch is the point. Pricing off the pack price rather than what a batch consumed is the single most common error in handmade pricing, which is why the yield field is mandatory and the results panel shows both per-unit and per-batch figures.

Two deliberate limits worth preserving:

- The soap calculator is **not** a lye calculator and says so on the page. Saponification maths is safety-critical, well served by the established tools, and getting it wrong causes chemical burns rather than a bad margin.
- These tools are Tier 1, so they carry the full currency list. Nothing about the math is jurisdiction-bound.
