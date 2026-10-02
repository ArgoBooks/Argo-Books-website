# Google Ads economics

The living numbers for the Argo Books Google Ads campaign(s): the terminology, the click-to-customer funnel model, the revenue levers, and the actual figures from real spend. Keep this file up to date as new data comes in. The setup (how to build a campaign, step by step) lives in [Google-Ads-campaign-setup.md](setup/Google-Ads-campaign-setup.md).

**Last updated:** 2026-07-23 (all-time data through Jul 23, 2026).

## Terminology

- **CPC** = Cost Per Click. What you pay Google per ad click.
- **CPA** = Cost Per Acquisition, the cost per conversion. The tracked Google conversion is a visit to `/downloads/`, not a paying customer, so Google's CPA is cost per downloads-page visit, not cost per customer.
- **LTV** = Lifetime Value. Total revenue expected from one paying customer over their lifetime.

## The funnel model

A paid click has to survive several steps before it becomes revenue:

```
1. Ad click
2. /downloads/ visit (Google counts this as the "conversion")
3. Download button click
4. Installs and runs the app
5. Hits a Free tier limit
6. Upgrades to Premium
```

Only the first two steps are visible in Google Ads. Everything past `/downloads/` is measured in the admin funnel (`admin/marketing-funnel/`) and app telemetry, not in Google.

## Actual numbers

Campaign "Argo Books - Search", all-time through Jul 23, 2026. Bid strategy: Maximize Clicks. Budget: CA\$10/day (started at CA\$15/day).

| Metric | Value |
|---|---|
| Impressions | 1,269 |
| Clicks | 92 |
| CTR | 7.25% |
| Avg CPC | CA$2.46 |
| Total ad cost | CA$226.45 |
| Google conversions (`/downloads/` visits) | 9 |
| Conversion rate | 9.78% |
| Google CPA (per `/downloads/` visit) | CA$25.16 |

### By device (the big finding)

| Device | Clicks | Cost | Avg CPC | Share of spend |
|---|---|---|---|---|
| Mobile phones | 59 | CA$146.13 | CA$2.48 | 65% |
| Computers | 31 | CA$75.16 | CA$2.42 | 33% |
| Tablets | 2 | CA$5.16 | CA$2.58 | 2% |

About 67% of spend went to mobile and tablet, which cannot install a Windows desktop app. Those clicks produced downloads-page visits (Google counted them as conversions) but zero real installs. Excluding mobile and tablet makes the budget roughly 3x more efficient by sending all of it to computers. The useful CPC is the computer figure: about CA$2.42. See the device-exclusion section in [Google-Ads-campaign-setup.md](setup/Google-Ads-campaign-setup.md).

## What we do not know yet

- **True cost per paying customer.** Google's CPA is per downloads-page visit, not per customer. Across all traffic sources the admin funnel shows only a couple of paying customers all-time, and clean attribution to this specific campaign is not yet possible. Do not treat Google's CPA as the real acquisition cost.
- **CLV.** Premium is CA\$15/mo, so lifetime value depends entirely on retention, and churn in the first 1 to 3 months is still unmeasured. Any CLV figure right now is a guess.
