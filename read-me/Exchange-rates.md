# Exchange rates

The desktop app converts every transaction to USD at the rate for the transaction's own date (see `docs/Calculations.md`, Rule 4, in the app repo). It gets those rates from this server, through `api/exchange-rates.php` (one date) and `api/exchange-rates-batch.php` (up to 500 dates). The profit analyzer reads the same rates.

## Storage

Every date, today included, is fetched once and stored in the `exchange_rates` table for good, one row per date with every currency's rate against USD. A stored date is never fetched or changed again, so every transaction on a date converts at the same rate, whichever request priced it first. Each row records where its rates came from in the `source` column.

A day is only stored when it has a rate for every currency the app supports (`shared/currencies.php`), apart from currencies that did not exist yet on that day (GHS before July 2007, BYN before July 2016). Otherwise the next source is asked. The Bulgarian lev is filled in from the euro at its fixed rate of 1.95583 when a source does not publish it, as Frankfurter stopped doing when Bulgaria adopted the euro.

## Sources

All of this lives in `api/_exchange_rate_sources.php`. Each source is asked only for the dates the one before it could not price.

1. **Frankfurter** (`api.frankfurter.dev/v2`): official reference rates from about 100 central banks, free, no key, history back to 1999 or earlier for every supported currency. On weekends and holidays a currency's rate is the latest one its central bank published, up to 7 days old. For today, Frankfurter only counts once most supported currencies have a rate published today, so the server never stores an earlier day's rates as today's. Until then, and all weekend, today comes from the next sources.
2. **Open Exchange Rates**, on the free plan: 1,000 requests a month, all currencies, history back to 1999. It is used while the server's own allowance lasts.
3. **fawazahmed0 currency API**, free, no key, served from jsDelivr with a Cloudflare mirror. It has every supported currency, but only from 2 March 2024, and a day's file appears some hours into that UTC day.

A date that no source can price comes back unavailable, and the app keeps the transaction pending until it can.

## The Open Exchange Rates allowance

The server allows itself `OER_MONTHLY_BUDGET` Open Exchange Rates requests (default 900) in any 31 days, counted from the rows it stored from that source. 31 days covers any billing month, whatever day the plan resets on, and the headroom below 1,000 leaves room for failed requests.

With Frankfurter first, Open Exchange Rates is mostly asked only for today's rate on weekends and early in the UTC day, about 30 requests a month. To change the allowance, set `OER_MONTHLY_BUDGET` in `.env`.
