# Email Outreach

Argo Books has a built-in outreach system that finds small businesses and writes them a personal email about trying Argo Books. You can run it in two modes:
- **Auto-send:** The system generates drafts and sends them automatically.
- **Review before send:** The system generates drafts, but stops there. You open each lead in the Leads tab, read the email, then send it (or tweak it and then send it). Nothing goes out until you click.

Everything lives in the admin dashboard under **Outreach**, which has four tabs: **Discovery**, **Leads**, **Follow-ups**, and **Settings**.

## Google Places channel

Google Places finds small brick-and-mortar businesses.

### How it works

1. **Picks a city**, rotating through Saskatchewan first and then expanding outward into the rest of Canada.
2. **Finds small businesses** there by category (plumbers, cafes, salons, and 90-odd other small-business types) and grabs their public contact email from their website.

   The channel is biased toward new and early businesses (the ones least likely to already have accounting software):
   - Businesses with more Google reviews than the review-count ceiling are skipped. The default is 15, since chains and long-established shops accrue lots of reviews. You can change it in Outreach, Settings (Discovery filters), and it also reads `OUTREACH_MAX_REVIEW_COUNT` from `.env`.
   - Before drafting, the system reads the business's own website and skips ones that look clearly established: an old founding year (older than `OUTREACH_ESTABLISHED_MAX_AGE_YEARS`, default 8 years), copyright dates, or "20+ years experience" style claims. A cheap text check handles the obvious cases, then a quick AI pass catches softer signals (decades of awards, long client lists) with no literal year. Sparse, brand-new, or "now open" sites pass through. This is a deliberate skew, not a perfect classifier.
3. **Writes each one a short email** with Gemini (currently `gemini-2.5-flash`). The email references the kind of business they run and the everyday headaches that category tends to have.
4. **Sends the first emails**, up to the daily cap (`OUTREACH_DAILY_SEND_LIMIT`), with a tracked link so clicks can be attributed back to the lead.
5. **Schedules follow-ups**: when a first email goes out, the system queues a follow-up sequence (default: 3 more emails at +3, +7, and +14 days). The schedule is configurable in Settings.
6. **Halts follow-ups** for any lead who replied, unsubscribed, or hard-bounced since the last run.
7. **Drafts each follow-up** with Gemini about a day before it's due to send. Each one personalizes against the lead's business, the original first email, and a per-touch "intent" (e.g. "gentle bump", "different angle", "final note before closing"). The intent comes from the default set for that touch in Settings.
8. **Sends follow-ups** that are approved, up to the daily follow-up cap (`OUTREACH_DAILY_FOLLOWUP_LIMIT`, default 75 across all touch positions). In Auto-send mode, drafts auto-approve and go straight out. In Review-before-send mode, they queue in the Follow-ups tab for you to approve.

## Shopify channel

Finds small Canadian Shopify sellers in their first 3–24 months. Stores that are past the "just launched" stage but likely haven't yet found solid accounting software

### How it works

1. SerpAPI runs a `site:myshopify.com` dork query, returning `.myshopify.com` storefronts.
2. The evaluator fetches each storefront's `/products.json`, checks product count (5–∞) and age of the oldest product (3–24 months), and looks for a Canadian address signal (postal code or province) on the storefront.
3. It then scrapes the store's contact page for a direct email address. Role-mailbox addresses (`support@`, `partnerships@`, etc.) are rejected; only personal or general-contact addresses are accepted.
4. Stores that pass all checks are imported as leads (`status='imported'`). Stores that fail are recorded with a `reject_reason` so they aren't re-evaluated on future runs.

## The Discovery tab

Manual discovery for both channels, useful for spot-checking what the cron would find before letting it run on its own, or for sourcing leads outside the cron schedule.

- **Google Places**: the city / province / category / size / limit form. Searches Google Places live and previews matching businesses with their scraped contact emails. Pick which ones to import.
- **Shopify**: pick a SerpAPI dork from the rotation (or write a custom one) and run it. Each result gets evaluated by the same filter as the cron (Canadian signal, 3–24 month age, 5+ products, non-gatekept email, not agency-operated) and the table shows fit / rejected / already-imported with the reject reason. Import any fit row, or "Import All Fits" in bulk.

Both panels show the daily quota state so you don't blow through SerpAPI's free tier mid-day. The Shopify panel respects the same `SERPAPI_DAILY_QUERY_LIMIT` and `OUTREACH_DAILY_SHOPIFY_DISCOVERY_LIMIT` env vars the cron uses.

## The Leads tab

This is the list of businesses the system has found or that you've added manually.

From here you can:

- **Add a lead manually** or import a CSV spreadsheet.
- **Open a lead** to see the AI-generated email, edit the draft before it's sent, or mark the conversation status (interested / not interested / onboarded / replied).
- **Bulk-generate drafts** or **bulk-send emails** for a group of leads you've selected.
- **See the full activity history** for any lead: every draft, every send, every click.

Every outreach email's `argorobots.com` link is rewritten to include a `?source=outreach-{leadId}` parameter. Hits land in `referral_visits` and show up against the lead as "Clicked" automatically.

## The Follow-ups tab

This is the review queue for follow-up emails. It only matters in Review-before-send mode. In Auto-send mode, follow-ups are sent right away.

The tab has five sub-views:

- **Pending review**: drafts waiting for you to approve. The pill carries a count badge so you can tell at a glance whether there's work to do.
- **Approved & queued**: drafts you've approved that are waiting for their scheduled send time.
- **Upcoming**: touches that are scheduled but haven't been drafted yet (drafting happens about a day before each send).
- **Sent**: what's gone out in the last 30 days.
- **Halted / failed**: sequences that stopped (lead replied, unsubscribed, bounced, you manually halted, or the AI couldn't produce a draft).

For each pending row you can:

- **Approve & queue**: sends after the scheduled time.
- **Regenerate draft**: re-draft if the wording doesn't feel right.
- **Skip this touch**: drop just this one touch; the next touch in the sequence still goes out on its original schedule.
- **Halt sequence**: stop ALL remaining follow-ups for this lead.

Bulk-select via checkboxes to approve, skip, or halt sequences for multiple rows at once.

You can also see the per-lead sequence (every touch + status + scheduled date) by opening any lead in the Leads tab.

## The Settings tab

The Settings tab has two runtime controls plus the sequence configuration:

- **Outreach system**: master enable/disable for the whole pipeline.
- **Send mode**: Auto-send vs Review-before-send (affects both first emails AND follow-ups).
- **Discovery filters**: the Google Places review-count ceiling. Lower it to bias discovery toward newer and smaller businesses; raise it to cast a wider net. Leave it blank to fall back to `OUTREACH_MAX_REVIEW_COUNT` in `.env` (or the built-in default of 15). The panel shows the value currently in effect and where it comes from.
- **Follow-up sequence**: an editable table of touches. Each row is one touch: how many days after the previous touch it sends (1-90), and a default "intent" string that drives Gemini's wording. Add/remove rows for between 0 and 6 follow-up touches. Setting 0 touches disables follow-ups entirely.

The Settings tab also shows a tail of the day's pipeline log for a quick health check.

## What you should do

**The no-touch setup:**

1. Go to **Outreach → Settings**, turn **Auto-send** on.
2. That's it. Leave it alone. Check back once a week or so to check on the emails to ensure they still look right. First emails and follow-ups both auto-approve and go straight out.

**The cautious setup:**

1. Go to **Outreach → Settings**, turn **Auto-send** off.
2. Open the drafts in the **Leads** tab every day, review or edit them, then send the email (or use bulk send). New first emails will queue here.
3. Open the **Follow-ups** tab to review drafted follow-ups for leads who already received their first email. Approve / regenerate / skip / halt per row, or use bulk actions.

## What actually gets sent

Each lead receives a sequence of emails. By default the first email plus 3 follow-ups (4 total), spaced +3, +7, +14 days after the first email. The count and gaps are configurable in Settings.

- **First email**: short (2–3 short paragraphs, under 100 words), AI-personalized to the lead's category and city. Includes a tracked argorobots.com link and a soft one-line unsubscribe.
- **Follow-ups**: also AI-personalized, threaded as `Re:` replies to the original so they land in the recipient's existing inbox conversation rather than as fresh emails. Each touch has its own intent (gentle bump / different angle / final note before closing), so the sequence doesn't read as the same email three times.

The sequence automatically halts when the lead replies, unsubscribes, hard-bounces, or you manually halt it. Halted sequences sit in the Follow-ups tab's Halted/failed sub-view for the record.
