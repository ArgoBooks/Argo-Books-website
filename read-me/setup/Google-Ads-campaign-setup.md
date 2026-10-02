# Google Ads campaign setup

A start-to-finish guide for setting up a Google Ads Search campaign for Argo Books. This is what was done for the first "QuickBooks Alternative" campaign in May 2026, written so future campaigns (targeting other competitors, other keyword themes) can follow the same recipe.

**This doc is the setup recipe: how to build the campaign. The unit economics, the funnel math, and the actual cost numbers live in [Google-Ads-economics.md](../Google-Ads-economics.md), which is kept up to date as real data comes in. Dollar amounts in this recipe are setup defaults, not performance figures.**

**Terminology note:**
- **CPC** = Cost Per Click.
- **CPA** = Cost Per Acquisition, the cost per conversion. The tracked Google conversion is a `/downloads/` visit, not a paying customer.
- **CLV / LTV** = Customer Lifetime Value.

## What this campaign does

Targets people in Canada searching for terms like "quickbooks alternative" and "free accounting software" on Google Search. When they click the ad, they land on the Argo Books vs QuickBooks comparison page. Their visit is tracked via `?source=google-ads-qb-alt` so attribution shows up in the admin referral dashboard. If they then visit `/downloads/`, that counts as a conversion in Google Ads.

Budget: a small daily amount (start around CA$10 to CA$15/day; see [Google-Ads-economics.md](../Google-Ads-economics.md) for the current figure). All settings can be changed after launch except budget type (daily vs. total).

## Before you start

1. **A verified Google Ads account.** For an unincorporated sole proprietor, verify as "Individual", not "Organization." The Organization path asks for a Dun & Bradstreet match and a Certificate of Incorporation, neither of which apply when there is no registered corporation. For the "Individual", a driver's license is enough.

2. **A payment method.** VISA, credit card, or debit card all work. Google charges after spend hits a threshold, not upfront.

3. **The Google tag (gtag.js) installed on the site.** Already done. See [main.js](../resources/scripts/main.js) at the top of the file. Tag ID is `AW-17210317271`. Loads on every page automatically because every PHP page includes `main.js` from its `<head>`.

4. **Referral source tracking installed on every landing page.** The campaign sends traffic to pages that need to capture `?source=google-ads-qb-alt`. The pages need to have `require_once __DIR__ . '/../track_referral.php';` (or `/../../` from deeper folders) near the top.

5. **A referral link row in the admin.** Go to `admin/referral-links/` and create one with:
   - Source code: `google-ads-qb-alt`
   - Display name: `Google Ads QuickBooks Alternative`
   - Target URL: `https://argorobots.com/compare/argo-books-vs-quickbooks/`

## Step-by-step campaign creation

Go to Google Ads, click "New campaign."

### Step 1: Campaign objective

Pick **"Create a campaign without guidance"** (the last option, bottom right). The other options (Sales, Leads, Website traffic) push you toward Google's smart bidding which needs conversion history you don't have yet on a fresh campaign.

### Step 2: Campaign type

Pick **Search**. Never "Performance Max" because it's a black-box campaign type that spreads budget across Search, Display, YouTube, and Gmail with no transparency. Search-only is what we want.

### Step 3: Conversion goals

Just click Continue. The default "Sign-ups" goal is fine. Don't add or modify anything here.

### Step 4: Results to get

Check **Website visits** only. Leave **Phone calls** unchecked.

When asked for the URL, enter:
```
https://argorobots.com/compare/argo-books-vs-quickbooks/
```

### Step 5: Campaign name

Use a meaningful name so reports are readable later. Convention: `Search - [theme] - [country]`. For example:
```
Search - QuickBooks Alternative - CA
```

### Step 6: Bidding

- **What do you want to focus on?** → Change from "Conversions" to **Clicks**. Conversions-based smart bidding needs ~30+ conversions of data to optimize, which you don't have on day one.
- Check **"Set a maximum cost per click bid limit"** → enter a cap in CAD. This caps the worst-case spend per click. See [Google-Ads-economics.md](../Google-Ads-economics.md) for the current max CPC to use and what these keywords actually cost.
- Leave **"Adjust your bidding to help acquire new customers"** unchecked.

### Step 7: Campaign settings

Three changes required:

- **Networks: UNCHECK BOTH**:
  - Google Search Partners Network (lower quality, parked domains)
  - Google Display Network (text ads become image ads on random sites which wastes money for small budgets)

  This is the most important fix on the page. Search-only is the discipline that makes a small budget productive.

- **Locations**: switch from "All countries" to **Canada**.

  Then expand **Location options** below the picker and change **Include** from "Presence or interest" to **"Presence: People in or regularly in your included locations"**.

  This matters more than it looks. Google defaults to "Presence or interest", which serves the ad to anyone who has merely shown interest in Canada, wherever they actually are. The first campaign ran on that default and paid for searches like `tally erp 9 download` (Indian accounting software, CA$18.85 across eight queries), `free ct600 filing software` (UK corporation tax) and `free uk bookkeeping software`. Two of its nine recorded conversions were UK users who could never become customers.

  Expand to the US later once Canadian data shows what's working.

- **Languages**: leave at English.

- **EU political ads**: pick **"No"**.

- **Audience segments**: leave empty. The default **Observation** setting at the bottom is correct.

#### Step 7b: More settings (expand this section)

- **Ad rotation**: "Optimize: Prefer best performing ads" is fine for a hands-off campaign. Switch to "Rotate evenly" only if you plan to manually compare per-ad performance.

- **Campaign URL options → Tracking template**: paste:
  ```
  {lpurl}?source=google-ads-qb-alt
  ```

  `{lpurl}` is Google's placeholder for "whatever landing page URL this ad points to." Setting it here means every ad and every sitelink automatically gets `?source=google-ads-qb-alt` appended without per-ad editing.

- Leave **Final URL suffix** blank.
- Leave **Custom parameters** blank.

### Step 8: AI Max for Search campaigns

**Leave the "Optimize your campaign with AI Max" toggle OFF.** This is the biggest trap in the whole flow. AI Max would:

- Override your exact and phrase match keywords with broad-match-style expansion
- AI-rewrite your ad headlines based on the landing page
- Redirect ad clicks to "more relevant" pages other than the one you chose

You want full control over keywords, copy, and landing page. AI Max stays off.

Under **Asset optimization**, leave both `Text customization` and `Final URL expansion` unchecked.

Under **Brands**, leave at defaults (0 limiting, 0 excluding).

### Step 9: Keyword and asset generation

Click **Skip** (bottom right). Do not click Generate.

The auto-filled description is generated from your landing page and looks decent, but clicking Generate creates broad-match keyword suggestions and AI-generated headlines that pull you off the manual setup you're about to do.

### Step 10: Keywords and ads

**Keywords**: delete any auto-suggested keywords, paste this exact list:

```
[quickbooks alternative]
[quickbooks alternative canada]
[free accounting software]
[free accounting software canada]
[free invoicing software]
[free invoice software]
[free bookkeeping software]
[free receipt scanner]
[receipt scanner app]
[small business accounting software canada]
[free small business accounting software]
[alternative to quickbooks]
"quickbooks alternative"
"free accounting software"
"free invoicing app"
"free bookkeeping software"
"receipt scanner software"
"small business accounting canada"
"alternative to quickbooks"
"free accounting software small business"
[offline accounting software]
[desktop accounting software]
"offline accounting software"
"accounting software offline"
"desktop bookkeeping software"
```

The `offline` and `desktop` terms were added after the first campaign's search terms report. `offline accounting software free download` was the cheapest converting click in the whole campaign at CA$1.78, and the theme was not being targeted deliberately at all. Those two words are what actually separate Argo Books from Wave, Xero and QuickBooks; every other keyword above competes in a category they already dominate.

Match-type rules:
- `[brackets]` = exact match: ad shows only for that exact query (or very close variants). Highest intent.
- `"quotes"` = phrase match: the phrase must appear, other words can be around it.
- No symbols = broad match: Google decides what's "related." Avoid entirely on small budgets.

If Google shows a banner saying "Apply +2.2% Add more keywords," dismiss it. Google wants you to bid wider; you want focused targeting.

**Headlines**: delete all auto-suggested headlines, replace with these 15:

```
Free Accounting Software
The QuickBooks Alternative
Made in Canada
No Subscription Required
Free Forever Plan
Scan Receipts Instantly
Send Invoices in Seconds
Built for Small Business
Download for Free
No Credit Card Required
Argo Books - Free Download
Free Bookkeeping Software
Try Argo Books Today
Tired of QuickBooks Fees?
Simple Accounting Software
```

Each headline is 30 characters max. Mix the brand, the unfair advantages (free, no subscription, Canadian), and the features (receipts, invoices, bookkeeping).

Ignore the "Ad strength: Poor" indicator that appears. Ad Strength rewards using more variations and more AI-generated content, not actual conversion performance. It is a vanity metric.

**Descriptions**: 4 of them, 90 characters max each:

```
Free accounting software for Canadian small businesses. No subscription. Download today.
Scan receipts, send invoices, track expenses. Made in Canada by an indie developer.
Tired of QuickBooks fees? Argo Books is free, simple, and built for small business owners.
Free forever plan with no credit card required. The simple QuickBooks alternative.
```

**Display path**: cosmetic only, shows as `argorobots.com/Path1/Path2` in the ad. Doesn't affect routing:
- Path 1: `Free`
- Path 2: `Accounting`

**Sitelinks**: add 4. Don't add `?source=` to the URLs; the campaign-level tracking template handles that automatically.

| Sitelink text | Description 1 | Description 2 | Final URL |
|---|---|---|---|
| Download for Free | No subscription required | Windows, macOS, Linux | `https://argorobots.com/downloads/` |
| Compare to QuickBooks | Side-by-side comparison | See how much you'll save | `https://argorobots.com/compare/argo-books-vs-quickbooks/` |
| Pricing | Free plan + $10/mo Premium | No hidden fees | `https://argorobots.com/pricing/` |
| Features | Invoicing, receipts, reports | Plus AI-powered tools | `https://argorobots.com/features/` |

**Callouts**: short non-clickable benefit statements. 25 char max:

```
Free Forever Plan
No Credit Card Required
Made in Canada
Receipt Scanning Included
Works Offline
No Subscription
```

**Images**: upload at least one. Multiple is better because Google rotates them. Use PNG. Aspect ratios: 1.91:1 (1200×628 recommended) and 1:1 (1200×1200 recommended). The image may be rejected if text overlay exceeds ~20% of canvas area. Three images are located at `C:\Users\evand\Desktop\Argo logos\Third\ads`.

**Business name**: Google auto-fills this from your verification. For an Individual verification it shows your personal name. Leave it.

**Business logo**: upload the Argo Books logo, located at `"C:\Users\evand\Desktop\Argo logos\Third\Argo Books icon white background.png"`.

### Step 11: Budget

Switch from Google's recommended budget to **Set custom budget**. Enter your daily amount (see [Google-Ads-economics.md](../Google-Ads-economics.md) for the current figure). Keep type as **Average daily budget**. Currency stays CAD.

Don't go under CA$10/day. Google needs minimum volume to optimize. Don't scale up before the funnel is converting; that just spends more at the same unfavorable ratio.

The budget projections, the click-to-customer funnel model, and the revenue levers (Free tier limits, retention) all live in [Google-Ads-economics.md](../Google-Ads-economics.md).

### Step 12: Review and publish

Skim the summary. The most important things to confirm:

- Network: Search only (no Display, no Search Partners)
- Location: Canada, with **Location options set to "Presence"**, not the default "Presence or interest"
- Bid strategy: Maximize Clicks with $3 max CPC
- Tracking template: `{lpurl}?source=google-ads-qb-alt`
- AI Max: off
- Keywords are in `[brackets]` and `"quotes"`, not broad match
- All 4 sitelinks point to pages that include `track_referral.php`

Click **Publish campaign**.

## Exclude mobile and tablet (critical for a desktop app)

Argo Books is desktop only, so people on phones and tablets cannot install it. Every mobile or tablet click is wasted spend. This is not a small effect: in the first campaign, mobile and tablet took about 65% of the total spend (mobile alone was CA\$146 of a CA\$226 total) and produced zero real installs.

Exclude both device types:

1. Open the campaign.
2. Left menu: **Insights and reports**, then **When and where ads showed**, then the **Devices** tab.
3. In the **Bid adj.** column, hover over the dash on the **Mobile phones** row, click the pencil edit button, choose **Decrease by 100%**, and save.
4. Repeat for the **Tablets** row.

A -100% bid adjustment means "never show on this device".

### Mac traffic (no longer a problem)

This used to be a section about wasted spend. Google Search cannot target or exclude by operating system at all: it targets device type only (computer, mobile, tablet), and OS targeting exists only in Display, Video, and App campaigns. So Mac desktops saw the ads, clicked them, and landed on a page with nothing to download.

Since the macOS build shipped on 2026-09-09, that click converts like any other. Nothing needs excluding, and there is nothing left to mitigate.

Two things follow from that:

- **Do not put "for Windows" in ad copy.** Earlier advice here said to, so Mac users would self-select out before costing a click. Doing that now turns away qualified traffic. The headlines and descriptions above never carried it, so there is nothing to undo, but do not add it back.
- **Mac is worth its own keywords.** "accounting software for mac" is a real search with real intent, and `/accounting-software-for-mac/` already exists to land it on. It was not worth bidding on while there was nothing to sell those visitors.

## Conversion tracking setup

This part is separate from the campaign creation flow. Google will prompt you to set up a conversion event after the campaign is published, or you can set it up later via "Tools → Conversions".

The setup:

1. **Conversion action name**: `Sign-up` (or `Download Installer` which is the same meaning for Argo Books since downloading the desktop app is the meaningful conversion).
2. **Goal type**: "Other → Sign-up".
3. **Tracking method**: Page load.
4. **Trigger URL**: when the user lands on `https://argorobots.com/downloads/`.

Google generates a conversion event snippet that looks like:
```html
<script>
  gtag('event', 'conversion', {'send_to': 'AW-17210317271/niGZCJv2vbkbENezwo5A'});
</script>
```

This snippet is already installed on `downloads/index.php` right after `main.js` loads (so the global `gtag` function is defined when the event fires). See [downloads/index.php](../downloads/index.php) around line 145.

Note: the conversion ID after the slash (`niGZCJv2vbkbENezwo5A` in the example) is unique per conversion action. If you create new conversion actions in the future, Google will generate new snippets with new IDs. Paste them into the same spot in `downloads/index.php` or wherever the conversion should fire.

## What ends up installed in the code

Across all of this, these things end up in the repo:

| Where | What |
|---|---|
| `resources/scripts/main.js` (top) | The Google gtag (AW-17210317271). Fires from `<head>` on every page automatically. |
| `downloads/index.php` (head, after main.js) | The Google Ads conversion event snippet. Fires when someone lands on the downloads page. |
| Each landing page used in the campaign | `require_once __DIR__ . '/../track_referral.php';` (or `/../../` depending on depth). Reads `?source=` from the URL and writes it to the `referral_visits` table. |
| `admin/referral-links/` | A row with `source_code = google-ads-qb-alt` so visits get a proper display name in the admin dashboard. |

If you set up a second campaign with a different source code (for example `google-ads-receipts` for a receipt-scanning-themed campaign), repeat the admin referral-links row and update the campaign's tracking template to use the new source code. No code changes needed.

## After launch

The first 24 to 48 hours are quiet. Google reviews ads (usually approves within a few hours) and the bid strategy is "learning." Don't tweak anything yet.

After 3-5 days:
- Check "Tools → Reporting → Predefined reports → Basic → Search keywords". See which keywords are getting impressions and clicks. Pause any keyword with high spend and zero clicks.
- Check Search terms report. This shows the actual queries that triggered your ads (vs. the keywords you targeted). Add irrelevant queries as negative keywords. This is the single highest-ROI ongoing maintenance task.

After 1 to 2 weeks:
- Check `https://argorobots.com/admin/marketing-funnel` for visit and conversion counts.
- Compare Google Ads' reported conversion count to the admin dashboard count. They should roughly agree.
- Watch the trend, not just absolute CPA. Every tracked conversion is a `/downloads/` visit, not a paying customer, so Google's CPA is cost per downloads-page visit, not cost per customer. The metric that actually pays the bills is Premium sign-ups, which you measure in your own admin, not in Google. See [Google-Ads-economics.md](../Google-Ads-economics.md) for the current CPA, what counts as normal, and the real cost per customer as it becomes measurable.

After 30+ days:
- Consider switching bid strategy from Manual CPC / Maximize Clicks to Maximize Conversions once you have 15+ conversions of data. Smart bidding works once it has signal.
- Consider expanding to a second ad group (or second campaign) targeting different keyword themes such as `free invoicing software`, `receipt scanner app`, industry-specific (`accounting software for landscapers`), etc.

## Negative keywords list to add

Build a shared negative keyword list and apply it to the campaign. These prevent the ad from showing on irrelevant searches:

```
crack
torrent
pirate
jobs
salary
career
course
tutorial
training
certification
students
university
tally
tallyprime
erp9
vyapar
busywin
gnucash
quicken
excel
android
apk
ct600
"simply accounting"
"open source"
"quickbooks login"
"quickbooks support"
"intuit login"
"intuit support"
```

Plus anything you see in the Search terms report that's irrelevant to your product.

## Common traps to avoid

- **Performance Max campaigns.** Black box, eats budget.
- **Broad match keywords.** Even one broad match keyword can blow through daily budget on unrelated searches.
- **AI Max for Search.** Same problem in a different wrapper.
- **Smart Bidding before having conversion data.** Maximize Conversions and Target CPA need 15-30 conversions of history to work. Starting from zero, they overspend trying to learn.
- **Display Network on a Search campaign.** Text ads become image ads on random websites. Tank conversion rate.
- **Ad Strength score.** A vanity metric. Real predictors of performance: keyword-to-search match, landing page relevance, ad copy specificity. Ignore Ad Strength entirely.
- **Google's "Recommendations" panel.** Every recommendation that promises "+1%" or "+2%" by adding more inventory or more assets is usually a way for Google to extract more spend. Real impact comes from negative keywords and tighter targeting, not from adding more.
- **Letting Google auto-generate copy.** It produces generic ad text that doesn't differentiate.
- **Forgetting to disable Search Partners and Display.** Always uncheck both when creating a new Search campaign.

## Related files

- [main.js](../resources/scripts/main.js): gtag installation
- [track_referral.php](../track_referral.php): referral source resolver
- [statistics.php](../statistics.php): `track_referral_visit()` function
- [admin/referral-links/index.php](../admin/referral-links/index.php): admin UI for referral source management
- [downloads/index.php](../downloads/index.php): conversion event snippet
- [compare/argo-books-vs-quickbooks/index.php](../compare/argo-books-vs-quickbooks/index.php): primary landing page
