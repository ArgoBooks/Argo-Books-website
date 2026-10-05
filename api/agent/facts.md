# What is true about Argo Books

This is the list of things that may be said about Argo Books in a post or an email. If a claim is not here and not on the live site, it is not said. Each line gives where it comes from, so it can be checked again when the app changes.

Prices and monthly limits are not in this file, because they are set on the server and change. They arrive at the start of every run as `plans_and_prices`. Use those figures and no others.

When a line here and the live site disagree, do not use either. Say so in your summary, so the owner can fix whichever is wrong.

## What it is

1. Argo Books is accounting and bookkeeping software for small businesses. (Home page title.)
2. It is a desktop app, not a cloud service. (Feature pages: "a desktop application, not a cloud service".)
3. It is made for people with no accounting training. There are no debits and credits to learn. (Expense and revenue page.)
4. It is free to download and use, with a paid plan called Premium for those who need more. (Pricing page.)

## Who makes it

5. Argo Books is built and supported in Saskatoon, Canada. (About page.)
6. It was started in 2024 by Evan, its founder, who built it. (About page.)
7. Support questions are answered by the person who built it. (About page: "When you write in, you hear from the person who built it".)

## Where it runs and how to get it

8. It runs on Windows, macOS and Linux. (Downloads page.)
9. On Windows it needs Windows 10 or later, and it is also in the Microsoft Store. (Downloads page.)
10. On Mac it needs macOS 14 Sonoma or later, and there are versions for both Apple Silicon and Intel Macs. It can also be installed with Homebrew. (Downloads page, What's New.)
11. On Linux it comes as an AppImage, which runs on Ubuntu, Debian, Fedora and others with nothing else to install. (Downloads page.)
12. It is available in 54 languages, and a new install opens in the computer's own language when that translation exists. (Site: "54 languages supported". What's New.)
13. It works in 55 currencies. (What's New.)
14. It labels tax with the term each country uses, such as GST/HST in Canada, VAT in the UK and EU, and Sales Tax in the US. (Report builder page.)

## The free plan

15. No credit card, no trial period and no account are needed to start. (Pricing page, invoicing page.)
16. Products, customers and transactions have no cap. (Pricing page.)
17. Sending invoices, scanning receipts, importing spreadsheets and importing bank statements with AI each have a monthly limit. The limits are in `plans_and_prices`. (`config/plans.json`.)
18. Quotes are unlimited on every plan. (Quotes page.)
19. Inventory, including multiple locations, low-stock alerts and purchase orders, is on the free plan. (Inventory page.)
20. Rental management is on the free plan. (Rental page.)
21. The whole report builder, with every accounting statement, is on the free plan with no usage limit. (Report builder page.)

## Premium

22. Unlimited invoices. (`config/plans.json`.)
23. Online payment links, so a customer can pay an invoice by card. (Pricing page.)
24. Higher monthly limits for receipt scans, spreadsheet imports and bank statement imports. The limits are in `plans_and_prices`. (`config/plans.json`.)
25. Canadian payroll. (`config/plans.json`.)
26. Revenue forecasting. (`config/plans.json`.)
27. Signing in with Windows Hello, Touch ID, or the Linux login. (Pricing page.)
28. The API. (`config/plans.json`.)
29. Priority support. (`config/plans.json`.)
30. It is billed monthly or yearly, in Canadian dollars. The prices are in `plans_and_prices`. (Pricing page.)
31. It can be cancelled at any time from the customer portal, and stays active until the end of the period already paid for. (Pricing page.)

## Your data

32. The company file is kept on the user's own computer. It can be backed up or moved like any other file. (Home page, feature pages.)
33. The everyday bookkeeping works without an internet connection. (Home page.)
34. The company's data is encrypted with AES-256-GCM before it is saved. (Documentation, encryption page.)
35. Importing a bank statement needs no bank login and no connection to the bank. It works from the file the user exports. (Bank statement import page.)
36. Scanning a receipt needs an internet connection, because reading it is done by a call to an AI service. The receipt image and the expense it creates are stored on the user's computer. (Receipt scanning page.)
37. Argo Books can keep automatic dated backups of a company: after every save, once a day or once a week, and before an import. (What's New.)

## What it does

### Invoices and quotes

38. An invoice can be built from saved customers and items, with totals and tax worked out. (Invoicing page.)
39. Invoices have templates, and the template and accent colour can be changed. (Invoicing page.)
40. Each invoice has a status: draft, sent, viewed, paid, overdue. (Invoicing page.)
41. A paid invoice becomes revenue in the books without a second entry. (Invoicing page.)
42. Invoices can repeat on a schedule. (What's New: recurring invoices.)
43. A quote is sent as a link where the customer can accept or decline it online, with nothing to install or sign up for. (Quotes page.)
44. An accepted quote turns into a draft invoice in one click. (Quotes page.)
45. A quote does not count as income until it becomes an invoice. (Quotes page.)

### Money in and out

46. An expense or a sale is recorded with a guided form that checks it before saving. (Expense and revenue page.)
47. Monthly revenue, expenses and net profit update as entries are saved. (Expense and revenue page.)
48. Every change is kept, with history and undo. (Expense and revenue page.)
49. An expense, sale or invoice can be duplicated. (What's New.)

### Receipts

50. A receipt can be read from a phone photo, a screenshot, a scan or a PDF, printed or handwritten. (Receipt scanning page.)
51. It reads the vendor, the date, each line item, the tax and the total, and the user checks it before saving. (Receipt scanning page.)
52. The original image stays attached to the expense. (Receipt scanning page.)

### Bringing data in

53. Bank statements import as CSV, Excel or PDF. Each line is pre-filled and nothing is saved until the user confirms it. (Bank statement import page.)
54. Statement lines can be matched against entries already in the books, so they are not duplicated. (Bank statement import page.)
55. Spreadsheets import from Excel or CSV. It works out which column is which and shows what it will create before saving. (Spreadsheet import page.)
56. Customers, products, expenses, revenue and invoices can all be imported from spreadsheets. (Spreadsheet import page.)
57. There is a screen for bringing books over from QuickBooks Online or Desktop. It says which reports to export, then takes them all at once. (What's New.)

### Stock and rentals

58. Stock levels move as items are sold and restocked. (Inventory page.)
59. Stock can be kept in more than one location and moved between them. (Inventory page, What's New.)
60. A product can be stocked by weight, volume or another unit, including part units. (What's New.)
61. Each product can have a reorder point, and is flagged when stock falls to it. (Inventory page.)
62. A purchase order updates stock when it is marked received. (Inventory page.)
63. Rentals are shown on a calendar of what is reserved and what is free. (Rental page.)
64. A rental can be booked ahead as a reservation, and the same units cannot be promised twice. (What's New.)
65. A rental can carry a security deposit, which is returned in full, in part or not at all, and late or damage charges can be added. (Rental page, What's New.)

### Customers

66. Each customer has one record with contact details, purchase history and what they still owe. (Customer page.)

### Reports

67. The built-in statements are the Income Statement, Balance Sheet, Cash Flow Statement, General Ledger, AR Aging, Tax Summary and Sales by Product. (Report builder page.)
68. Reports can be laid out by the user and saved as templates. (Report builder page.)
69. Reports export as a PDF, or as a PNG or JPEG image. (Report builder page.)
70. The year can be sent to an accountant in one go: the main statements as PDFs, every transaction as a spreadsheet, and the receipts. (What's New.)

### Forecasting, on Premium

71. It projects revenue, expenses and net cash flow from the history already in the books. (Forecasting page.)
72. Each projection comes with a confidence range. (Forecasting page.)
73. It detects seasonal patterns and allows for them. (Forecasting page.)

### Payroll, on Premium, Canada only

74. It works out CPP, EI and income tax for every province and territory. (Payroll page.)
75. Quebec is handled through its own system: QPP, QPIP and Quebec income tax. (Payroll page.)
76. The rates come from the CRA's published payroll formulas, and from Revenu Quebec for Quebec. New rates are fetched when they take effect. (Payroll page.)
77. Staff can be salaried or hourly, paid weekly, biweekly, semi-monthly or monthly, and mixed in one pay run. (Payroll page.)
78. It produces pay stubs, T4 slips and the T4 summary, the file the CRA accepts, and an RL-1 worksheet for Quebec. (Payroll page.)
79. It builds the Record of Employment file for ROE Web. (Payroll page.)
80. It does not file anything or send money to the CRA. It prepares the figures and the files, and the user uploads them and pays. (Payroll page.)
81. Premium is one price, not a charge per employee or per pay run. (Payroll page.)

### Connecting other things

82. A Stripe account can be connected with a read-only key. Sales import with their product, customer, tax and discount filled in, and Stripe's fees are tracked. (Stripe integration page.)
83. The API lets a store, a booking system or an in-house tool send sales, expenses, customers and suppliers into the books. Everything is reviewed before it is imported. It is on Premium. (API integration page, `config/plans.json`.)
84. Invoice payments by card go through the business's own Stripe or Square account. (Site code: these are the two providers the payment portal accepts.)

## Free tools on the website, with nothing to install

85. An invoice generator, an estimate generator and a purchase order generator.
86. A receipt scanner.
87. Invoice templates.
88. A profit analyzer.
89. Calculators: break-even, hourly rate, markup and margin, late fees, mileage deduction, self-employed tax, Etsy fees, and pricing calculators for crafts, candles, soap, cakes, tumblers and craft fairs.

(Each of these is a page on the site, listed in `site_pages`. Link to the page itself, through a tracked link.)

## Who it is for

90. The site has pages written for contractors, landscapers, cleaning companies, auto detailing, repair shops, resellers, rental businesses, local wholesalers, software companies and solo operators. (The `for-...` pages. Link to the one that fits.)

## Getting help

91. There is written documentation on the site. (Documentation pages.)
92. There is a community forum for reporting problems, suggesting features and sharing tips. (Community page.)

## On the site, but do not repeat

These appear on the site. They are either figures that should not be quoted as promises, or wording that is stronger than what is true. Leave them out.

- Any accuracy percentage for receipt scanning or for forecasts.
- That data or a file "never leaves your computer". Reading a receipt, a spreadsheet or a bank statement with AI sends that document to be read. Say that the company file stays on the computer, which is true.
- That invoices can be paid through PayPal. The invoicing page says so, but only Stripe and Square can be connected for invoice payments.
- That spreadsheet imports are unlimited on Premium. Use the limit in `plans_and_prices`.
- That the encryption is "the same standard used by banks".

## Never say

- That Argo Books is the only, the first, the best or the number one anything.
- How many users or customers there are, or anything about reviews or ratings.
- That payroll works outside Canada, or that Argo Books files with the CRA or pays it for you.
- That using it makes anyone compliant with tax law. Do not give tax or legal advice in Argo Books' name.
- That it connects to a bank. It does not: it reads statement files.
- That it has a mobile app, or a web version of the full product. It is a desktop app. The free tools on the website are separate from it.
- That it is in the Mac App Store or on Flathub. Of the app stores, it is in the Microsoft Store only.
- Anything about a named competitor in your own words. The site has comparison pages under `/compare/`. Link to the page and let it speak; do not restate its figures.
- Anything about what is coming next. Only what has shipped.
