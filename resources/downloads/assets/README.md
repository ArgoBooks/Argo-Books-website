# CRA asset rates

CRA's Capital Cost Allowance classes and first-year rules, served as plain static JSON.

The desktop app fetches `{host}/resources/downloads/assets/cca.json` once per run, the first time an asset or expense form opens for a Canadian company. It fills in each asset's class and rate from it and depreciates the asset the way CRA does. Uploading a new file here is what makes a budget's change reach customers without an app release.

## Before uploading

The app ignores a file that fails its check, and nothing tells the customer, so check it first. The file must:

- have a `revision` higher than the copy built into the app and the last file uploaded here, or the app keeps what it has
- give every class a rate above 0 and at most 100, with no two rates for one class covering the same date
- have no two first-year rules for every class covering the same date, and a factor above 0 and at most 3 on each
- map every asset type in `typeDefaults` to a class in the file

`CcaRateValidator` in the desktop repository is the same check the app runs, and `CcaRateTests` shows what each failure looks like.

## Preparing an update

The full process, including where the figures come from, is in `docs/AssetRateUpdates.md` in the desktop repository. Commit the file there as well as uploading it here: the upload reaches existing installs, the commit means a fresh install has it offline.

The reminder email (`cron/cca_rate_reminder.php`) goes quiet once this file's `reviewedOn` date falls in the current month.
