# Email Outreach

Argo Books has a built-in outreach system that writes each business on your lead list a personal email about trying Argo Books, sends it, and follows up. It does not find the businesses. You add them yourself, one at a time or from a CSV spreadsheet. You can run it in two modes:
- **Auto-send:** The system generates drafts and sends them automatically.
- **Review before send:** The system generates drafts, but stops there. You open each lead in the Leads tab, read the email, then send it (or tweak it and then send it). Nothing goes out until you click.

Everything lives in the admin dashboard under **Outreach**. The **Email** channel has three tabs: **Leads**, **Follow-ups**, and **Settings**.

The page also has two more channels, **Editorial Partners** and **Creator Partners**. Each is a list of the leads already in it, with the same drafting and sending. There is no way to add to those two lists any more: they were filled by a search feature that has been removed.

## What the daily pipeline does

A cron runs once a day and works through the leads in the list.

1. **Skips businesses that look established.** Before drafting, the system reads the business's own website and skips ones with an old founding year (older than `OUTREACH_ESTABLISHED_MAX_AGE_YEARS`, default 8 years), copyright dates, or "20+ years experience" style claims. A cheap text check handles the obvious cases, then a quick AI pass catches softer signals (decades of awards, long client lists) with no literal year. Sparse, brand-new, or "now open" sites pass through. This is a deliberate skew toward new businesses, which are the least likely to already have accounting software. It is not a perfect classifier.
2. **Writes each one a short email** with Gemini (currently `gemini-2.5-flash`). The email references the kind of business they run and the everyday headaches that category tends to have.
3. **Sends the first emails**, up to the daily cap (`OUTREACH_DAILY_SEND_LIMIT`), with a tracked link so clicks can be attributed back to the lead.
4. **Schedules follow-ups**: when a first email goes out, the system queues a follow-up sequence (default: 3 more emails at +3, +7, and +14 days). The schedule is configurable in Settings.
5. **Halts follow-ups** for any lead who replied, unsubscribed, or hard-bounced since the last run.
6. **Drafts each follow-up** with Gemini about a day before it's due to send. Each one personalizes against the lead's business, the original first email, and a per-touch "intent" (e.g. "gentle bump", "different angle", "final note before closing"). The intent comes from the default set for that touch in Settings.
7. **Sends follow-ups** that are approved, up to the daily follow-up cap (`OUTREACH_DAILY_FOLLOWUP_LIMIT`, default 75 across all touch positions). In Auto-send mode, drafts auto-approve and go straight out. In Review-before-send mode, they queue in the Follow-ups tab for you to approve.

## The Leads tab

This is the list of businesses you've added, along with any the removed search feature found earlier.

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
