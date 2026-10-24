# API costs

Every outside service Argo Books pays for, and what uses it.

## Google Gemini

The main running cost. All AI calls are made by the website with `GEMINI_API_KEY`; the desktop and phone apps send them through `/api/ai/completions.php`. Calls with an image or PDF use `GEMINI_MODEL_EXTRACTION` (Gemini 3.5 Flash) and text-only calls use `GEMINI_MODEL` (Gemini 3.1 Flash-Lite). Prices per million tokens are in `api/ai/_models.php`.

### Receipt scanner (desktop and phone)

Reads a photo or PDF of a receipt into an expense.

- **Read the receipt:** sends the image and gets back the supplier, date, totals, taxes and every line item. Counts against the monthly scan limit.
- **Check for missed items:** sends the image again with the items found so far, only when the first read looks unsure or doesn't add up. Not counted against the limit.
- **Suggest supplier and category:** text only, matches the receipt to an existing supplier and expense category. Desktop only, not counted against the limit.

### Spreadsheet import

Turns a spreadsheet of any layout into the company's records. One import can make many calls but counts as one import against the monthly limit.

- **Find the tables:** one call per messy sheet, to find where the headers and data rows are. Clean sheets skip it.
- **Map the columns:** one call per batch of sheets, to work out what each sheet holds and what each column means.
- **Process rows:** one call per 100 rows, only for sheets too irregular to import by column mapping.
- **Categorize products:** one call per import for products left without a category.
- **Rescue:** when nothing could be imported, one call per sheet to decide whether it can be read, one per mixed profit and loss sheet to outline it, then row processing.

### Bank statement import

Reads bank statements and suggests what each line is.

- **Read a PDF or photo:** sends the statement to `/api/bank/extract.php` and gets back its transactions. Counts as one bank import.
- **Map unknown columns:** for a CSV or Excel file whose headers aren't recognized, the same column mapping call as the spreadsheet import.
- **Categorize lines:** one call per 40 lines, suggesting a product, category and supplier or customer for each.

### Free receipt scanner (website)

Reads a receipt for a website visitor at `/free-receipt-scanner/`, one call per scan, capped per visitor, per IP and site-wide per day.

### Profit Analyzer (website)

Reads a visitor's spreadsheet at `/profit-analyzer/`, with the same find tables, map columns, process rows and categorize calls as the spreadsheet import. Limited to 5 a day per IP.

### Outreach

Writes cold emails to small businesses, from the daily `cron/outreach_pipeline.php` and the admin outreach page.

- **Size and newness checks:** up to two calls per found lead, to skip businesses that are too big or too new.
- **Summarize the business:** one call per lead that has no summary yet.
- **Draft the email:** one call per first email, capped by `OUTREACH_DAILY_SEND_LIMIT`.
- **Draft follow-ups:** one call per follow-up email.
- **Read a lead's website:** one call to fill in a lead's name, category and city from its site.

### Marketing agent research

Searches the web for the marketing agent through `/api/agent/?action=research`, using Google Search grounding, which is billed per request on top of tokens. Limited to 40 a day.

## Anthropic (Claude)

Runs the scheduled marketing agent in Anthropic's cloud, billed on the Anthropic account rather than by the website. Nothing in the website code calls Claude.

## Azure Trusted Signing

Signs the Windows build for each release, $9.99 a month on the Basic plan.

## Payment processors

Stripe, Square and PayPal charge per transaction, not per API call: Premium subscriptions and renewals are charged to Argo Books, and invoice payments through the portal are charged to the business's own account.

## Free plans

These cost nothing while usage stays within each service's free plan.

### Resend

Sends every email the website sends.

- **Invoices:** from the desktop app, 25 a month on Argo Books' free plan.
- **Purchase orders, accountant packs and quotes:** from the desktop app, rate limited per hour.
- **Payment portal:** receipts, reminders and refund codes.
- **Website:** contact form, tool results, Profit Analyzer results, account and license key emails.
- **Crons:** outreach, marketing broadcasts, invoice reminders and rate reminders.

### Open Exchange Rates

Fills in exchange rates the free sources don't have, within 1,000 requests a month, capped at 900 by `OER_MONTHLY_BUDGET`. Each date is fetched once and kept. See [Exchange rates](Exchange-rates.md).

### ipinfo

Looks up the country of a visitor's IP for website statistics, referrals and app telemetry. Each IP is looked up once and kept. The desktop app also calls ipinfo.io, ipapi.co and ip-api.com directly, without keys.

### Azure Translator

Translates the app's text into each language when the translation tool runs, within 2 million characters a month.
