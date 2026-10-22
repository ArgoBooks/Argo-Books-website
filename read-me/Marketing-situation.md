# Marketing situation

Context snapshot for anyone (including Claude) picking up marketing work. Update this file when the picture changes.

Last updated: 2026-10-21.

Related: [Google-Ads-economics.md](Google-Ads-economics.md), [Email-outreach.md](Email-outreach.md).

## Where things stand

- Solo founder, bootstrapping. Argo Books has been in development for about 2 years.
- Windows, macOS, and Linux. The macOS build shipped 2026-09-09, signed and notarized, for Apple Silicon and Intel; see [macOS](#macos).
- **11 paying customers: 2 subscriptions and 9 one-time.** The two subscribers signed up around May and both auto-renewed for months, although neither used the app. One has now stopped (see MRR below), leaving 1 active subscription.
  - Customer 1 came from a YouTube video (the receipt scanning one).
  - Customer 2 came from Google search.
  - Customers 3 to 11 bought the lifetime deal through Stack Social, one each on 2026-08-27, 2026-09-27, 2026-09-28, 2026-09-29, 2026-10-01, 2026-10-03, 2026-10-12, 2026-10-19 and 2026-10-21. None of them produce recurring revenue.
- Pricing: \$15/month or \$150/year. Customer 1 is paying \$10/month with no payment processing fee added, because I increased the price after he signed up.
- Revenue: **\$112.96 CAD from subscriptions** all time, all of it inside the last year, plus **\$185.12 USD from Stack Social** across eight of its nine lifetime sales, and the 2026-10-12 sale on top, whose amount is still to be read off their dashboard. Kept in their own currencies because that is how each is paid. Stack Social keys are reseller keys and never touch the site's billing, so they are absent from the admin revenue figures entirely.
- **MRR fell to \$15.74 in October**, after sitting flat at \$25.74 from June to September. Active licenses went from 2 to 1: the first subscriber churn. The \$10 drop matches Customer 1's price, so it is most likely them; confirm on `/admin/license/`.
- Running costs: **~\$170 CAD/month.** Claude Code \$140, Azure code signing \$10, Apple Developer Program \$10, website hosting and domain \$10. Against \$15.74 of recurring revenue that is about \$154/month out of pocket, so a \$1,000 spend is roughly six and a half months of runway.
- **The "LTV" tile on `/admin/marketing-funnel/` is not lifetime value.** It computes `total_revenue / total_paying` (see `admin/marketing-funnel/index.php`), which is revenue booked to date per customer. It reads low early and drifts up as customers renew. Do not quote it as LTV.
- **LTV is not known.** One subscriber has churned after about five months and the other is still paying, which is far too little data to calculate it from.
- App telemetry, excluding my own device: **133 devices have installed and run the app**, up from 40 on 2026-09-15. 81 of them first ran in October, 48 of those on a Mac (see [the October jump](#the-october-jump)). By platform: 77 Windows, 53 macOS, 3 Linux. Most are in the United States, then Canada.
- **Use is thin.** Across the 133, the median total time active in the app is 1.5 minutes. 65 created a company of their own, 48 came back on a second day, 6 on five or more days, and only **10 have ever recorded an expense, a sale or an invoice**. 98 opened it in the last 30 days and 38 in the last 7.
- **Neither subscriber has opened the app in over a month.** A feedback email went to both and neither replied. One has since stopped paying, which is what paying for an unused app eventually leads to; the other is likely to follow unless they start using it.

## Traffic numbers are not trustworthy yet

Read this before drawing any conclusion from the visitor counts below.

Internal analytics reports about 4.9k visitors all time (5.1k by 15 Sept), 82% of them **direct** (4,206), 14% referral (698), 4% organic search (194), 1% organic social (47). Google Search Console over roughly the same window reports **46 clicks** from 12.8K impressions, 0.4% CTR, average position 63.7.

Those two pictures do not reconcile, and the internal one is the suspect one:

- 4,206 direct visitors means "no referrer sent". This is a suspicion, not a measurement. Nobody has verified how much of it is bots, so do not state it as settled. For a site with essentially no brand awareness, almost nobody is typing the URL in. Bots, scrapers, uptime checks, and referrer-stripped traffic all land in this bucket. The site's bot filter (`is_likely_bot()` in `statistics.php`) is basic and probably not catching everything.
- Average position in Search Console is very low. Organic search is indexed but barely being served to anyone.

What **is** trustworthy is app telemetry, because it requires someone to actually install and run a desktop app: 133 devices. Work backwards from that number, not from the landing count.

## Funnel (all traffic, all time)

From `/admin/marketing-funnel/`.

| Step | All time | From previous step | Last 30 days | From previous step |
|---|---|---|---|---|
| Landing | 6,000 | | 862 | |
| Downloads page | 599 | 10.0% | 154 | 17.9% |
| Download click | 172 | 28.7% | 93 | 60.4% |
| App first run | 145 | 84.3% | 102 | 110% |
| Premium signup | 2 | 1.4% | 0 | 0% |
| Premium paid | 2 | 100% | 0 | |

The landing figure may be inflated by bots, so the drop from landing to downloads page is not useful. Treat it as unresolved rather than explained.

- **First runs now outnumber download clicks** in the last 30 days, because Microsoft Store and Homebrew installs never pass through the website. From here on, download clicks undercount installs.
- **Nobody who installed in the last 30 days has paid**, apart from the Stack Social buyers, who never touch the site's billing. Both Premium signups date from around May.
- The funnel's 145 first runs and the 133 devices above probably differ because the 133 leave out my own devices.

Top entry pages (2026-09-15): `/` (2.34), `/downloads/` (325), `/pricing/` (259), `/features/invoicing/` (224), `/compare/argo-books-vs-quickbooks/` (145).

### Installs by tagged source

From the users-by-source export. Only visits that arrive on a tagged link are counted here, so these 44 installs are a third of the 133. The rest came with no tag: search, the Microsoft Store, Homebrew, or someone typing the address.

| Source | Landings | Download clicks | Installs | Paying |
|---|---|---|---|---|
| YouTube (video links and channel) | 125 | 48 | 23 | 1 |
| AI assistants | 108 | 15 | 15 | 0 |
| Paid ads (Google) | 80 | 5 | 2 | 0 |
| Social media | 147 | 2 | 2 | 0 |
| Website guides | 417 | 2 | 1 | 0 |
| Affiliates | 1 | 1 | 1 | 0 |
| Invoice generator | 449 | 0 | 0 | 0 |
| Directories | 36 | 0 | 0 | 0 |

- **AI assistants are now the second biggest tagged source**: DuckDuckGo's assistant sent 11 installs and ChatGPT 4, with no work put in. Being described accurately on the site is what feeds this.
- **YouTube is still the best**, and still the only tagged source with a paying customer. The Desktop accounting video produced 8 installs, the most of any single link.
- **The invoice generator and the guides bring traffic that does not install.** 449 and 417 landings for 0 and 1 installs. They are good for search, not for downloads.

### The October jump

New devices by month: 10 in June, 6 in July, 15 in August, 21 in September, **81 in October** (to the 21st). Two listings went live just before it:

- **Homebrew**, added on 2026-10-02. New Macs went from about one a week to 10, 7 and 4 on 3, 4 and 5 October, and have stayed at a few a week since. 48 of October's 81 are Macs.
- **The Microsoft Store**, live around 2026-10-01. New Windows devices rose from about one every two days to a few a day over the same week.

Neither listing tells us its installs, so this is timing, not proof. But nothing else changed that week, and the Mac jump is too sharp to be chance. It suggests store listings reach people that the website does not, which raises the priority of Flathub, Snapcraft and the Mac App Store.

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
- **An in-app survey** asks two questions once someone has recorded their first expense: "Where did you hear about Argo Books?", only when there is no referral data, and from 2.0.20 "What do you mainly want to use it for?", asked of everyone (`ArgoBooks/Controls/SourceSurveyOverlay.axaml`). The choices for both are served by the website from `/api/survey-options.php`, so they can be changed without shipping an app update, and answers post back to the site.
- **A closing question** (2.0.20) reaches the people the survey never does. The first time someone closes the app without having recorded anything, it asks what they were hoping to do and whether anything got in the way. It is asked once and can be skipped (`ArgoBooks/Controls/ExitSurveyOverlay.axaml`).
- **A review request** (2.0.20) appears on the dashboard once someone has had the app for two weeks and recorded ten things, and opens `/review/`. It is shown once and can be dismissed.

Both sets of answers are on `/admin/marketing-funnel/`, under the source survey, and on each person's card at `/admin/app-stats/?tab=user-activity`.

What is missing:

- **No email address for free users.** Only paying customers hand one over. There is no account requirement at install, deliberately, because "no account, runs on your computer" is part of the positioning, and reduces friction.
- **Community accounts are not linked to telemetry.** 8 people signed up on the website, and there is no way to tell which of them ever ran the app.

The app already offers an optional email sign-up for update news, on the dashboard once someone has recorded something. It needs no account. Check how many have used it before building anything more for this gap.

## What has been tried

### YouTube channel
13 videos, **2,150 total views**, 34.1 hours watched and 13 subscribers (YouTube Analytics, lifetime). 10,410 thumbnail impressions at a 7.4% click-through rate. That is four times the views of 3 Sept, and the daily views have climbed steadily since mid-September, led by one video. Also posting comments on other accounting software videos mentioning Argo Books.

- **"Top 5 Free Desktop Accounting Software (2026)" is now the best performer**: 646 views in under six weeks, 9.1% click-through, 4 subscribers and 8 installs through its link, the most of any single link. Its daily views are still rising.
- **"Best Free AI Receipt Scanner"** is second with 543 views. It produced one of the two paying subscribers.
- **Titles taken from real search queries work.** The "Best Free ..." videos for desktop accounting and inventory have the most views of the September batch. Payroll for Canada and rental management are newer and still small, and payroll serves a smaller, Canada-only audience.

View counts from YouTube Studio, newest first:

| Video | Published | Views |
|---|---|---|
| Payroll Software for Small Business in Canada (2026) | 2026-09-27 | 24 |
| Best Free Rental Management Software for Small Business (2026) | 2026-09-25 | 49 |
| Best Free Inventory Management Software for Small Business (2026) | 2026-09-15 | 172 |
| Top 5 Free Desktop Accounting Software (2026) | 2026-09-12 | 646 |
| Best Free Invoicing Software for Small Business (2026) | 2026-09-03 | 62 |
| QuickBooks Receipt Scanner vs a Free Alternative (2026) | 2026-08-31 | 29 |
| How To Redeem Your Argo Books License Key | 2026-08-22 | 23 |
| Scan Receipts Into Excel Automatically (Free) | 2026-08-06 | 117 |
| QuickBooks Desktop Is Discontinued: What To Do Now | 2026-07-30 | 65 |
| Best Free QuickBooks Alternative in 2026 | 2026-07-17 | 123 |
| Free invoicing with online payments | 2026-06-03 | 33 |
| Best Free AI Receipt Scanner | 2026-06-02 | 543 |
| Argo Books Demo | 2026-04-08 | 130 |

### Stack Social
A Stack Social deal went live late August. **Nine sales so far, one each on 2026-08-27, 2026-09-27, 2026-09-28, 2026-09-29, 2026-10-01, 2026-10-03, 2026-10-12, 2026-10-19 and 2026-10-21.** That is roughly one every three or four days since late September. Argo Books Premium is being sold as a lifetime deal of $83.99 CAD. My share of the revenue is 45-50%, depending on how they acquired each customer. Stack Social is generally either a hit or a miss, with most companies making almost no sales, while some do very well, with hundreds, or thousands of sales. While ~$40 revenue on each sale is very little considering I also have business expenses, based on my research:
- Around 80% of people who buy lifetime software deals never use the software, or use it very little. 
- Lifetime users tend to churn at similar rates as subscription users.

**Everything is in US dollars, and the rate is being paid correctly** (confirmed 2026-09-29 from their earnings CSV). The deal sells for \$59.99 USD; \$83.99 CAD is just what a Canadian buyer is shown. Earnings so far are \$185.12 USD across the eight sales with a known amount, no refunds and no chargebacks:

| Sale date | Sold for | Where | Rate | Card fee | Earnings |
|---|---|---|---|---|---|
| 2026-08-27 | \$51.00 | Network | 40.1% | \$2.04 | \$18.41 |
| 2026-09-27 | \$59.99 | Stack O&O | 50% | \$2.40 | \$27.60 |
| 2026-09-28 | \$59.99 | Network | 40.1% | \$2.40 | \$21.66 |
| 2026-09-29 | \$59.99 | Stack O&O | 50% | \$2.40 | \$27.60 |
| 2026-10-01 | \$44.99 | Network | 40.1% | \$1.80 | \$16.24 |
| 2026-10-03 | \$59.99 | Stack O&O | 50% | \$2.40 | \$27.60 |
| 2026-10-12 | | | | | not read yet |
| 2026-10-19 | \$59.99 | Stack O&O | 50% | \$2.40 | \$27.60 |
| 2026-10-21 | \$51.00 | Network | 40.1% | \$2.04 | \$18.41 |

The maths is `sale price x rate, minus a 4% card fee`, and it checks out on every row with an amount. The 2026-10-19 and 2026-10-21 rows are also read off the earnings chart, but each earnings figure fits only one realistic price and rate, the same as the 2026-09-27 and 2026-08-27 sales. The 2026-10-01 and 2026-10-03 rows are derived from the earnings chart rather than read off their dashboard, and only their dates and earnings are certain. The 2026-10-03 row is the safer of the two: \$27.60 only works at the Stack O&O rate on a \$59.99 sale, because Network would need a sale price of \$76.45, which is not a price anybody sets. The 2026-10-01 row is the uncertain one. Both rates fit the arithmetic: Network gives \$44.99 and Stack O&O gives \$35.30. Network is the guess only because \$44.99 is a price somebody would actually set. Worth confirming against their earnings CSV. The rate depends on where the sale came from, which is what "45-50% depending on how they acquired each customer" meant:

- **Stack O&O**, their own site, pays **50%**, so \$27.60 a sale.
- **Network**, someone else's storefront, pays **40.1%**, so \$21.66 a sale.

The two weakest sales were both Network sales and both discounted, to \$51.00 and \$44.99. So there is no shortfall and nothing to query with them. Expect \$27.60 when they sell it themselves and \$21.66 when a partner does, and around \$24 on average. Note that \$40 a sale was never possible at this price: half of \$59.99 is \$30 before fees.

**Redemption so far: 1 of 5** (2026-09-29). Four of the five sold in the last five days, so that number means nothing yet. Check it at `/admin/license/?tab=reseller-batches`. The one person who redeemed gave an email address but never confirmed it, so there are no usable contacts from this deal yet.

**Stack Social does not give you the buyers' email addresses.** The only way to reach these customers is by asking them in the app when they enter their key.

Stack Social is a great opportunity to get customer reviews, which would be extremely valuable because I currently have no social proof.

### Google Ads
About CA\$300 spent, 0 attributable customers. Details in [Google-Ads-economics.md](Google-Ads-economics.md). Roughly two thirds of spend went to mobile and tablet clicks that cannot install a Windows app, which was a mistake. Even with device exclusions, realistic cost per customer looks like \$300+ against an unproven LTV. Not viable.

### Cold email outreach
~1,100 emails sent starting around January 2026. A couple of replies, zero customers. Stopped.

### Editorial outreach
22 emails to blog and article writers who cover accounting/bookkeeping software, started July 2026. Zero responses. Main friction is finding unique targets. The auto-discovery feature in the admin outreach page did not work well and was removed on 2026-10-05, so targets are now added by hand.

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

### Microsoft Store listing

Submitted on 2026-09-30 against version 2.0.18 and [live](https://apps.microsoft.com/detail/xpdmdvrxj0xs0m), in all 240 markets with an English-only listing. Review has taken under a day in practice, against a stated three business days. The listing points at one specific installer, so each release has to repoint it (`docs/Publishing.md` in the desktop repository); it was moved to 2.0.21 on 2026-10-18.

Expect very little traffic. Competitor listings pulled on 2026-09-30 carry 7, 14 and 6 reviews, and two of the five have none at all, which is the clearest available measure of how little volume this category sees on the Store. The reasons to do it are: it's permanent, and a Store listing is a trust signal for a small-business owner deciding whether to run an unknown `.exe` on the machine holding their financial records.

### Reddit
As of September 2026: 0-5 comments a day, roughly 100 total. Posts get auto removed immediately even when they follow the rules and do not mention Argo Books, with no explanation given. Account is 2 months old with 21 karma, which is the likely cause. About 90% of comments get 1 view, and some get 10-100.

### LinkedIn
As of September 2026: 16 connections, 53 profile views, 943 post impressions (879 of those from the latest post), 4 posts. Messaged startup/business/accounting influencers about the affiliate program. Nothing has come of it, at least that we can measure.

The WinForms to Avalonia article post (published 2026-09-15, Avalonia UI tagged in the first sentence) reached 1901 impressions, 34 reactions, 1 comment and 4 reposts in three days, twice the amount as the other 2 posts. One repost was from Jumar Macato, a software engineer at Avalonia UI.

### Warm introduction to a credit union
A neighbour, James, works at a credit union, knows Chris, the CEO of Mainstreet Credit Union, and introduced us in a LinkedIn group chat in September 2026. That led to an email, then his executive assistant booking 30 minutes.

**Mainstreet Credit Union**, Strathroy Ontario: 10 branches across Southwestern Ontario, over \$1 billion under administration, a Canadian-owned co-operative with a separate Business and Investment Advice Centre. Chris Inniss, President and CEO.

**The meeting, 2026-09-28.** Booked for 30 minutes, ran about 6 (12:38 to 12:44). Video failed in Teams so it was screen share and audio only. He said very little, asked twice for an email with more information, and closed on the second ask. He ended with "send me an email and I'll see if any of my business members are interested".

**What was learned about their book.** Asked whether the business members are mainly agricultural: no, all sorts. He does loans and investments from \$15,000 to \$15 million.

**The ask.** He asked directly whether I was looking for a referral, and the answer was "yes, open to ideas, trying to get it in front of people". That is defensible: a narrow ask can be declined narrowly, and leaving it open let him offer the thing he was actually willing to do, which he did. The lesson is smaller than it first looked. A specific ask does not have to be a big one, and "ideally an introduction to whoever works with your smaller business members" is just as easy to agree to while saving him the work of inventing the option. Someone with six minutes who is handed that work defaults to the smallest move available, which is asking for an email.

**Follow-up.** One email, written so the middle paragraph can be forwarded to a member untouched, with a single ask for an introduction to someone on their business banking side. Link tagged `?source=mainstreet`, which is the only real measure of whether it went anywhere: he is not going to reply to say he forwarded it.

**Honest read.** A meeting the other side closes twice in six minutes is usually a polite no, although he may have just been busy. The named commitment to look at his members is worth the email, but expect nothing. The value in this one is the lesson about the ask, and the fact that a warm introduction reached a CEO at all, which no amount of cold email has done.

### Homebrew
The macOS build has been in [Homebrew's own cask repository](https://github.com/Homebrew/homebrew-cask/blob/main/Casks/a/argo-books.rb) since 2026-10-02, so `brew install --cask argo-books` works. Each release needs a pull request from a Mac (`docs/Publishing.md`). It is the likeliest cause of the jump in Mac installs that followed (see [the October jump](#the-october-jump)).

### Inbound offers: all declined
Unsolicited offers have started arriving. None has been worth taking, and later ones of the same kind can be ignored.

- **Paid "Top 5" rankings.** Tech Times offered to rank Argo Books No. 1 in a "Top 5 Best Payroll Software in 2026" article (October 2026). It is a paid placement written to look like a review, and the readers are mostly American, while Argo Books' payroll is Canada only.
- **Link-building requests.** An agency for Relay, a small-business banking app, asked for a link to Relay in the cash-flow forecasting guide. It offers nothing back and sends readers to a product that overlaps with Argo Books.

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

## People in touch

The free users and prospects who write in, worth knowing by name because the product is answering what they ask.

### Kevin Kling (free user)
Runs a short-term rental in Maine, US, managed by a rental company, and uses the macOS build. He upgraded macOS just to run it. He first wrote in September with four problems, three of which were fixed in 2.0.19 and the fourth found later, and has written several times since, asking about rentals, reports and depreciation. Friendly, technical (he writes software himself) and the most engaged user so far.

- His questions shaped 2.0.22: fixed assets and depreciation, alongside the Sales by Customer and Expenses by Supplier reports that were built the day before he asked.
- **The best candidate for a first review.** The in-app review prompt only records locally whether it was shown, so there is no way to know if he has seen it. Ask him personally once 2.0.22 is out and he has tried what he asked for, and point him at `/review/`.

### Joe Violanti (prospect)
Wrote through the contact form on 2026-10-16 while weighing a purchase: how to create asset, liability and equity accounts without a chart of accounts, and whether Argo Books is bookkeeping or only income and expense tracking. That is the objection an accountant-minded buyer will raise. What answers it now:

- An opening bank balance in Edit Company, which the Balance Sheet's cash starts from (shipped in 2.0.21).
- Fixed assets with depreciation, shown on the Balance Sheet at cost less depreciation (2.0.22).
- Not yet: loans and other liabilities, or owner money put in and taken out.

## What 2.0.22 adds that matters for marketing

Not released yet. Worth a video and a What's New entry each once it ships.

- **Fixed assets and depreciation**, with an Asset Register report. For a Canadian company, each asset type comes with its CRA Capital Cost Allowance class and rate filled in, kept current from the website like the payroll tables. Canadian payroll and CCA together are something few small tools offer.
- **New report templates:** Sales by Customer, Expenses by Supplier, Payroll Summary, Rentals and Customer Statement.
- **Assets in spreadsheet export and import**, the list an accountant asks for at year end.

## Honest read

The two things that produced customers are YouTube and organic search. Both are slow, compounding, and free. Everything push-based (cold email, editorial outreach, YouTuber outreach, paid ads) has produced zero customers.

Reach was the constraint, and October loosened it: 133 devices have now run the app, against 40 in mid-September, mostly thanks to the store listings rather than anything on the website.

That moves the problem to activation. Only 10 of the 133 have ever recorded an expense, a sale or an invoice, and the median person spends a minute and a half in the app in total. More installs will not turn into customers until more people get as far as recording something.

The other problem is retention. Neither subscriber used the app, and in October one of them stopped paying, the first churn. Most of the free users use the app for a few minutes, do almost nothing, sometimes come back a few days later, do nothing, then leave. Not sure why. This could be a problem with the telemetry (unlikely, but possible), normal user behavior, or a real problem.

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

### 2. Linux app stores: Flathub and Snapcraft

A few hours each, and free. Linux users go to these stores to find apps, and they are the audience most likely to want software that runs locally with no account, which is exactly Argo Books' positioning.

- **Flathub** is the main one. Submission is a pull request to Flathub's GitHub with a Flatpak manifest, followed by a human review. Flathub accepts proprietary apps, so the license is not a blocker. The manifest wraps the existing Linux build and has to declare the file access the app needs, because Flatpak apps run sandboxed.
- **Snapcraft** (the Snap Store, built into Ubuntu) is the second. Register the name, write a `snapcraft.yaml` around the Linux build, and publish with the `snapcraft` command. Review is mostly automated.

Expect small numbers: Linux is 3 of the 46 devices in the 2026-09-16 activity export. The reasons to do it anyway are the same as the Microsoft Store: permanent for little work, and a store listing reads as more trustworthy than a download from a site nobody has heard of.

### 3. Mac App Store

The Homebrew cask is done (see What has been tried). The Mac App Store is a real project, not a listing. The developer account is already paid for. The work is App Sandbox, which limits where the app can read and write files and would need testing against how company files are opened and saved, plus Apple's review. Apple also generally requires Premium to be sold through in-app purchase in a store app, which takes a 15 to 30% cut and needs its own implementation. Check Apple's current rules before committing, since they have been changing.

It is the store in this list most likely to reach non-technical Mac users, which is why it is worth scoping properly rather than dismissing, even though it is the most work.
