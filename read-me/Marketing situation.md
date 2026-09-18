# Marketing situation

Context snapshot for anyone (including Claude) picking up marketing work. Numbers are as of **2026-09-03** unless a line says otherwise. Update this file when the picture changes.

Related: [Google Ads economics.md](Google%20Ads%20economics.md), [Email outreach.md](Email%20outreach.md).

## Where things stand

- Solo founder, bootstrapping. Argo Books has been in development for about 2 years.
- Windows, macOS, and Linux. The macOS build shipped 2026-09-09, signed and notarized, for Apple Silicon and Intel; see [macOS](#macos).
- **3 paying customers: 2 subscriptions and 1 one-time.** The two subscribers signed up around May, both auto-renewed and are still active, although they don't use the app.
  - Customer 1 came from a YouTube video (the receipt scanning one).
  - Customer 2 came from Google search.
  - Customer 3 bought the lifetime deal through Stack Social in late August, so there is no recurring revenue from them.
- Pricing: \$15/month or \$150/year. Customer 1 is paying \$10/month with no payment processing fee added, because I increased the price after he signed up.
- Revenue: **\$77.22 CAD all time**, \$25.74 in the last 30 days. 2 active licenses, 0% churn so far.
- Running costs: **~\$170 CAD/month.** Claude Code \$140, Azure code signing \$10, Apple Developer Program \$10, website hosting and domain \$10. Against \$25.74 of revenue that is about \$145/month out of pocket, so a \$1,000 spend is roughly seven months of runway.
- **The "LTV" tile on `/admin/marketing-funnel/` is not lifetime value.** It computes `total_revenue / total_paying` (see `admin/marketing-funnel/index.php`), which is revenue booked to date per customer. It reads low early and drifts up as customers renew. Do not quote it as LTV.
- **LTV is not known.** Both customers are only a few months in and neither has churned, so there is no retention data to calculate it from.
- App telemetry, excluding my own device: **40 people have installed and run the app** (2026-09-15). Most open it, spend a few minutes without really doing anything, and leave. From the 28 Aug snapshot, when the count was 35: 21 active in the last 30 days, 8 on a Premium identity, and only 9 ever came back on a second day.
- **Both paying customers have not opened the app in over a month**, despite still being billed. A feedback email went to both and neither replied. 0% churn is not a retention signal yet, because the numbers are too small and not enough time has passed.

## Traffic numbers are not trustworthy yet

Read this before drawing any conclusion from the visitor counts below.

Internal analytics reports about 4.9k visitors all time (5.1k by 15 Sept), 82% of them **direct** (4,206), 14% referral (698), 4% organic search (194), 1% organic social (47). Google Search Console over roughly the same window reports **46 clicks** from 12.8K impressions, 0.4% CTR, average position 63.7.

Those two pictures do not reconcile, and the internal one is the suspect one:

- 4,206 direct visitors means "no referrer sent". This is a suspicion, not a measurement. Nobody has verified how much of it is bots, so do not state it as settled. For a site with essentially no brand awareness, almost nobody is typing the URL in. Bots, scrapers, uptime checks, and referrer-stripped traffic all land in this bucket. The site's bot filter (`is_likely_bot()` in `statistics.php`) is basic and probably not catching everything.
- Average position in Search Console is very low. Organic search is indexed but barely being served to anyone.

What **is** trustworthy is app telemetry, because it requires someone to actually install and run a desktop app: 40 unique users as of 2026-09-15. Work backwards from that number, not from 5.1k.

## Funnel (all traffic, all time)

From `/admin/marketing-funnel/`, 2026-09-15.

| Step | Count | From previous step |
|---|---|---|
| Landing | 5,100 | |
| Downloads page | 434 | 8.6% |
| Download click | 72 | 16.6% |
| App first run | 40 | 55.6% |
| Premium signup | 2 | 5.0% |
| Premium paid | 2 | 100% |

The landing figure may be inflated by bots, so the 91% drop from landing to downloads page is not useful. Treat it as unresolved rather than explained. The steps that are real: 72 download clicks produced 40 first runs, and 2 of those 40 became paying customers. Conversion after install is fine. The problem is that only 40 humans have ever installed it.

Top entry pages: `/` (2.34), `/downloads/` (325), `/pricing/` (259), `/features/invoicing/` (224), `/compare/argo-books-vs-quickbooks/` (145).

### Per-source funnel, and why YouTube traffic is different

From the users-by-source export, 3 Sept 2026. The all-traffic column is that day's snapshot, so it is smaller than the current funnel above; the per-source rates are what matter here, not the totals. Summing the nine `youtube-*` video CTA sources (excluding the channel bio link):

| Step | YouTube video links | All traffic |
|---|---|---|
| Landings | 49 | 4,900 |
| Download clicks | 16 (**32.7%**) | 408 to 59 (14.5%) |
| Installs | 9 (56.3%) | 34 (57.6%) |
| Paying | 1 (11.1% of installs) | 2 (5.9% of installs) |

Video CTA links point at `/downloads/?source=...`, so a YouTube visitor's first page is the downloads page. That means the landing-to-downloads-page step does not exist for this traffic, and the bot question above does not apply to it.

The number that matters: **YouTube visitors click download at 32.7%, more than double the 14.5% of everyone else.** This is the most reliable conversion figure. Once someone has clicked download, source stops mattering (56.3% vs 57.6% install rate).

Link CTR for reference: the receipt scanner video has 223 views and produced 26 landings, an **11.7%** click-through on a description link. Useful as a benchmark when a sponsored placement quotes its own link CTR, because a sponsor slot interrupts a browsing viewer or has different intent, while these 26 came from people who searched for the topic.

## The reachability gap

The gap is contact, not data.

- **Desktop telemetry** reports on every install: sessions, page views, feature usage, errors, startup timings, company scale. Read it at `/admin/app-stats/`.
- **Website analytics** cover the whole funnel by source, at `/admin/marketing-funnel/` and `/admin/website-stats/`.
- **An in-app survey** asks "Where did you hear about Argo Books?" when there is no referral data (`ArgoBooks/Controls/SourceSurveyOverlay.axaml`). Its options are served by the website from `/api/survey-options.php`, so they can be changed without shipping an app update, and answers post back to the site.

What is missing:

- **No email address for free users.** Only paying customers hand one over. There is no account requirement at install, deliberately, because "no account, runs on your computer" is part of the positioning, and reduces friction.
- **Community accounts are not linked to telemetry.** 8 people signed up on the website, and there is no way to tell which of them ever ran the app.

The cheap ways to narrow it, none of which require an account at install: ask more than the source question in the existing survey (what they came to do, what stopped them), or offer an optional email field in the app for people who want to be told about updates.

## What has been tried

### YouTube channel
9 videos, **496 total views** (channel checked 3 Sept 2026). Best performer is "Best Free AI Receipt Scanner" (223 views, 3 months old), which produced one of the two paying customers. Also posting comments on other accounting software videos mentioning Argo Books.

Current view counts, newest first:

| Video | Views | Age |
|---|---|---|
| Best Free Invoicing Software for Small Business (2026) | 5 | 15 hours |
| QuickBooks Receipt Scanner vs a Free Alternative (2026) | 8 | 2 days |
| How To Redeem Your Argo Books License Key | 7 | 12 days |
| Scan Receipts Into Excel Automatically (Free) | 40 | 3 weeks |
| QuickBooks Desktop Is Discontinued: What To Do Now | 51 | 1 month |
| Best Free QuickBooks Alternative in 2026 | 63 | 1 month |
| Free invoicing with online payments | 28 | 2 months |
| Best Free AI Receipt Scanner | 223 | 3 months |
| Argo Books Demo | 73 | 4 months |

Subscriber count: 3.

### Stack Social
A Stack Social went live late August, and has resulted in one paying customer so far. Argo Books Premium is being sold as a lifetime deal of $83.99 CAD. My share of the revenue is 45-50%, depending on how they acquired each customer. Stack Social is generally either a hit or a miss, with most companies making almost no sales, while some do very well, with hundreds, or thousands of sales. While ~$40 revenue on each sale is very little considering I also have business expenses, based on my research:
- Around 80% of people who buy lifetime software deals never use the software, or use it very little. 
- Lifetime users tend to churn at similar rates as subscription users.

**The first payout was \$18, not the ~\$40 expected** (noted 2026-09-15). Against the \$83.99 CAD listing and a stated 45-50% share, \$18 is about 21%. Unexplained so far. Things that could account for it, worth investigating: the sale price was discounted below the listed price (flash sale or bundle), or the sale came through a partner storefront rather than Stack Social's own, which is what "45-50% depending on how they acquired each customer" implies.
Plus, this is a great opportunity to get customer reviews, which would be extremely valuable because I currently have no social proof. I could add this social proof to my website's landing page and include it in my outreach emails.

### Google Ads
About CA\$300 spent, 0 attributable customers. Details in [Google Ads economics.md](Google%20Ads%20economics.md). Roughly two thirds of spend went to mobile and tablet clicks that cannot install a Windows app, which was a mistake. Even with device exclusions, realistic cost per customer looks like \$300+ against an unproven LTV. Not viable.

### Cold email outreach
~1,100 emails sent starting around January 2026. A couple of replies, zero customers. Stopped.

### Editorial outreach
22 emails to blog and article writers who cover accounting/bookkeeping software, started July 20226. Zero responses. Main friction is finding unique targets, and the auto-discovery feature in the admin outreach page does not work well.

### YouTuber outreach
82 emails sent starting about a month ago. 2 responses, both rejections (one "schedule is full", one asked which regions Argo Books supports then went quiet). A third response showed interest then said that the email had been forwarded to someone else for consideration. A fourth response gave a quote of $1000 USD as a flat-fee instead of an affiliate (see below).

**The channel behind the \$1000 USD quote** (checked 2026-09-15):

- **Sponsored software reviews**, which is close to an ideal audience: people who watch software reviews are software buyers, and they are used to clicking a link to try the product.
- Hundreds of videos, uploading almost daily. 20k to 40k views each, about 30k on average. Well produced, 6 to 7 minutes long, roughly 1,000 likes against 0 to 30 dislikes and about 10 comments per video.
- Every video is sponsored, and a few sponsors repeat, usually larger companies. Repeat sponsors are a strong signal.
- Every video carries a tracked link in the description and in a pinned comment.
- **His published rates:** average link CTR: 1.5-2.3%* which is the share of viewers who click the description link through to the sponsor's site.
- **Price: \$1000 USD, and he has not moved on it.** I countered on the grounds of being a small company unsure the economics work, and the only thing offered back was \$950 for a 5-minute video instead of the usual 6 to 7 minutes. That is a different product at basically the same rate, not a discount. At 30k views \$1000 is about a \$33 CPM against a \$15-30 norm, and daily uploads with a full sponsor slate mean he has the demand to hold it.
- **What \$1000 USD buys**, using his link CTR against Argo's own YouTube funnel rates: 450 to 690 link clicks (\$1.45 to \$2.22 per click), 147 to 226 download clicks at 32.7%, and **82 to 126 installs** at 56%.
- **Break-even.** \$1000 USD is about \$1,370 CAD, roughly 9 subscriptions at \$150 CAD/year, which needs 7 to 11% of those installs to pay. The only two figures available, 6% of all installs and 11% of YouTube installs, sit either side of that. Year-one payback is a coin flip, and the real outcome turns on renewal, which is unknown.
- **Decision: consider funding it from Avalonia Port Challenge prize money (winners announced 2026-11-06).**

### Reddit
0-5 comments a day, roughly 100 total. Posts get auto removed immediately even when they follow the rules and do not mention Argo Books, with no explanation given. Account is 2 months old with 21 karma, which is the likely cause. About 90% of comments get 1 view, and some get 10-100.

### LinkedIn
16 connections, 53 profile views, 943 post impressions (879 of those from the latest post), 4 posts. Messaged startup/business/accounting influencers about the affiliate program. Nothing has come of it, at least that we can measure.

### Directory listings: done, no measurable traffic
Already listed on G2, Capterra, Product Hunt, and roughly 30 cheap Product Hunt copycats. None of it brought traffic. G2 and the other main listings were updated in September 2026 to include macOS.

That result is expected, and it clarifies what a listing is actually for. The value was never the directory's own visitors, it's that the directory's pages rank in Google.

- G2 and Capterra do rank, but they rank on review volume, so they stay dead until there are reviews. Hard with 2 customers.
- The ~30 copycats fail the test entirely. No further effort there.

### SEO: indexed, but not ranking yet
Programmatic SEO pages, clean site structure, all pages indexed in Google Search Console, auto-submission to Bing and others. It did produce one of the two paying customers (I assume, given it was unattributed). But 36 clicks in 3 months at average position 64.5 means the pages exist and are indexed without ranking anywhere useful.

### Growth loops
Documents users send to other businesses carry a tracked link to Argo Books. The people receiving them are often small businesses too, so some install and send documents of their own.

- **Invoices** (built 2026-09-16): on the portal after paying, in the portal footer, at the bottom of the invoice email, and in the invoice generator's PDF, Word and Excel downloads.
- **Accountant pack** (built 2026-09-17): the year-end pack emailed from the app, and the readme inside it. Accountants see many small businesses, so one install can recommend it to others.
- **Purchase orders** (built 2026-09-17): the footer of the PO email and the PO PDF. Suppliers keep books of their own.

Sources are `loop-*`, grouped as "Growth loops (documents users send)" on `/admin/referral-links/`. Check installs per source after 30 days before investing more, or deciding whether Premium removes the branding.

## Honest read

The two things that produced customers are YouTube and organic search. Both are slow, compounding, and free. Everything push-based (cold email, editorial outreach, YouTuber outreach, paid ads) has produced zero customers.

The real constraint is not conversion, it's reach. 40 people have installed and run the app, and 2 of them have paid for a subscription.

The second problem is retention. Both paying customers have gone a month without opening the app. Most of the free users use the app for a few minutes, do almost nothing, sometimes come back a few days later, do nothing, then leave. Not sure why. This could be a problem with the telemetry (unlikely, but possible), normal user behavior, or a real problem.

## Next steps

### 1. YouTube

**Ride the QuickBooks Desktop discontinuation while it lasts.** There is a population being forced off desktop software onto a \$360+/year subscription, searching right now for what to do. Argo Books is a free desktop alternative, which is close to a perfect fit, and this window closes.

**Title every video as the search query, never as the product.** The channel's own numbers already prove this: "Best Free AI Receipt Scanner" got 221 views while "Argo Books Demo" got 73 despite being a month older. The QuickBooks-titled videos are the fastest starters. Topic selection is doing nearly all the work at this size.

**How to pick topics** (in order of usefulness):

1. **Google Search Console, Performance → Queries.** 8.95K impressions means Google is already showing the site for real searches. Sort by impressions and look specifically at high-impression, zero-click queries. That is validated demand that is currently being lost, and it comes from the actual audience rather than a guess. Best source available, and it is free and already owned.
2. **YouTube search autocomplete.** Type "quickbooks", "accounting software", "bookkeeping", "invoice", "receipt" into YouTube search and read the suggestions. Those are literal queries ordered roughly by volume. Twenty minutes produces a long title list.
3. **Check what already ranks for each candidate query.** If old videos with high view counts hold the top spots, demand is sustained. If the top results are thin or outdated, that is an opening.
4. **Complaint mining.** Comments under QuickBooks videos, and threads in r/smallbusiness and r/bookkeeping, are full of repeated grievances (price increases, data export, forced migration). Each recurring complaint is a video title.

Validate every title against YouTube autocomplete before committing. Do not invent queries.

### 2. Microsoft Store listing

About a day of work. The Store accepts unpackaged Win32 apps, so the existing installer can be listed without repackaging as MSIX. Mostly forms: description, screenshots, age rating, privacy policy, then certification review. Individual developer account is a one-time fee, around \$19 USD (confirm current pricing).

Expect very little traffic. Store search volume for accounting software is thin and the Store skews toward games and big-name apps. The reasons to do it anyway are that it is permanent for one day of work, and that a Store listing is a trust signal for a small-business owner deciding whether to run an unknown `.exe` on the machine holding their financial records.

### 3. Linux app stores: Flathub and Snapcraft

A few hours each, and free. Linux users go to these stores to find apps, and they are the audience most likely to want software that runs locally with no account, which is exactly Argo Books' positioning.

- **Flathub** is the main one. Submission is a pull request to Flathub's GitHub with a Flatpak manifest, followed by a human review. Flathub accepts proprietary apps, so the license is not a blocker. The manifest wraps the existing Linux build and has to declare the file access the app needs, because Flatpak apps run sandboxed.
- **Snapcraft** (the Snap Store, built into Ubuntu) is the second. Register the name, write a `snapcraft.yaml` around the Linux build, and publish with the `snapcraft` command. Review is mostly automated.

Expect small numbers: Linux is 3 of the 46 devices in the 2026-09-16 activity export. The reasons to do it anyway are the same as the Microsoft Store: permanent for little work, and a store listing reads as more trustworthy than a download from a site nobody has heard of.

### 4. Mac: Homebrew cask and the Mac App Store

- **Homebrew cask** is the cheap one: a pull request to the `homebrew-cask` repository pointing at the signed, notarized download. Free, and Mac users who install from the terminal look there first. Homebrew reviews new casks for notability, so a young app with few users may be turned down until it has more of a following.
- **The Mac App Store** is a real project, not a listing. The developer account is already paid for. The work is App Sandbox, which limits where the app can read and write files and would need testing against how company files are opened and saved, plus Apple's review. Apple also generally requires Premium to be sold through in-app purchase in a store app, which takes a 15 to 30% cut and needs its own implementation. Check Apple's current rules before committing, since they have been changing.

Expect Homebrew to be small. The Mac App Store is the store in this list most likely to reach non-technical Mac users, which is why it is worth scoping properly rather than dismissing, even though it is the most work.
