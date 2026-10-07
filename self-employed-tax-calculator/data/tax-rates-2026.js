// self-employed-tax-calculator/data/tax-rates-2026.js
//
// Single source of truth for the 2026 tax-year rates used by the self-employed
// tax calculator. To update for a future year, copy this file, edit the
// numbers, bump TAX_META, and point calc.js at the new file.
//
// TAX YEAR: 2026
// LAST VERIFIED: 2026-06-07
//
// SOURCES (all figures are 2026 tax year):
//  - US federal brackets + standard deduction: Tax Foundation 2026
//    https://taxfoundation.org/data/all/federal/2026-tax-brackets/
//  - US Social Security wage base ($184,500) + SE tax (15.3%, 92.35% factor):
//    SSA 2026 COLA fact sheet; IRS self-employment tax guidance.
//  - Canada federal brackets + basic personal amount: taxtips.ca/taxrates/canada.htm
//  - CPP / QPP 2026 (rates, YMPE $74,600, YAMPE $85,000, $3,500 exemption):
//    taxtips.ca/cpp-qpp-and-ei/cpp-qpp-contribution-rates.htm
//  - Each province/territory: taxtips.ca/taxrates/<prov>.htm
//
// IMPORTANT — this is a deliberately SIMPLE ("set aside roughly this much")
// estimate. It assumes a single / basic filer with no dependants and only the
// standard deduction (US) or basic personal amount (Canada). It errs slightly
// HIGH (the safe direction for a set-aside tool) by omitting:
//   US: the QBI (199A) deduction, state income tax, the extra 0.9% Medicare
//       surtax over $200k, and non-single filing statuses.
//   CA: the CPP/QPP deduction & credit, EI/QPIP, the Ontario low-income
//       reduction, and dependant/spousal credits. Uses the maximum federal
//       basic personal amount (exact below $181,440; minor at higher incomes).
// It is NOT tax advice. See the disclaimer on the page.

export const TAX_META = {
  taxYear: 2026,
  lastVerified: '2026-06-07',
};

// Brackets are marginal: each entry taxes income between the previous entry's
// `upTo` and this entry's `upTo` at `rate`. The final entry uses Infinity.
export const US = {
  standardDeduction: 16100, // single filer, 2026
  brackets: [
    { upTo: 12400, rate: 0.10 },
    { upTo: 50400, rate: 0.12 },
    { upTo: 105700, rate: 0.22 },
    { upTo: 201775, rate: 0.24 },
    { upTo: 256225, rate: 0.32 },
    { upTo: 640600, rate: 0.35 },
    { upTo: Infinity, rate: 0.37 },
  ],
  se: {
    netFactor: 0.9235,      // net earnings from self-employment = profit * 92.35%
    socialSecurityRate: 0.124,
    medicareRate: 0.029,
    socialSecurityWageBase: 184500, // 2026 SS taxable maximum
    minNetEarnings: 400,    // below this, no SE tax is owed
  },
};

// Canada Pension Plan (rest of Canada) and Quebec Pension Plan (QC).
// Self-employed pay both the employee and employer portions.
export const CANADA_PENSION = {
  basicExemption: 3500,
  ympe: 74600,  // Year's Maximum Pensionable Earnings (first ceiling)
  yampe: 85000, // Year's Additional Maximum Pensionable Earnings (second ceiling)
  cpp: { baseSelfRate: 0.119, secondSelfRate: 0.08 }, // CPP + CPP2
  qpp: { baseSelfRate: 0.126, secondSelfRate: 0.08 }, // QPP + QPP2 (Quebec)
};

export const CANADA_FEDERAL = {
  brackets: [
    { upTo: 58523, rate: 0.14 },
    { upTo: 117045, rate: 0.205 },
    { upTo: 181440, rate: 0.26 },
    { upTo: 258482, rate: 0.29 },
    { upTo: Infinity, rate: 0.33 },
  ],
  lowestRate: 0.14, // rate at which the basic personal amount credit is given
  // Basic personal amount phases from max down to min between these incomes.
  bpaMax: 16452,
  bpaMin: 14829,
  bpaPhaseStart: 181440,
  bpaPhaseEnd: 258482,
  quebecAbatement: 0.165, // QC residents' federal tax is reduced by 16.5%
};

// Provinces / territories. `bpa` is the provincial basic personal amount, credited at `lowestRate` (the first bracket's rate).
export const PROVINCES = {
  AB: {
    name: 'Alberta',
    lowestRate: 0.08,
    bpa: 22769,
    brackets: [
      { upTo: 61200, rate: 0.08 },
      { upTo: 154259, rate: 0.10 },
      { upTo: 185111, rate: 0.12 },
      { upTo: 246813, rate: 0.13 },
      { upTo: 370220, rate: 0.14 },
      { upTo: Infinity, rate: 0.15 },
    ],
  },
  BC: {
    name: 'British Columbia',
    lowestRate: 0.0506,
    bpa: 13216,
    brackets: [
      { upTo: 50363, rate: 0.0506 },
      { upTo: 100728, rate: 0.077 },
      { upTo: 115648, rate: 0.105 },
      { upTo: 140430, rate: 0.1229 },
      { upTo: 190405, rate: 0.147 },
      { upTo: 265545, rate: 0.168 },
      { upTo: Infinity, rate: 0.205 },
    ],
  },
  MB: {
    name: 'Manitoba',
    lowestRate: 0.108,
    bpa: 15780,
    brackets: [
      { upTo: 47000, rate: 0.108 },
      { upTo: 100000, rate: 0.1275 },
      { upTo: Infinity, rate: 0.174 },
    ],
  },
  NB: {
    name: 'New Brunswick',
    lowestRate: 0.094,
    bpa: 13664,
    brackets: [
      { upTo: 52333, rate: 0.094 },
      { upTo: 104666, rate: 0.14 },
      { upTo: 193861, rate: 0.16 },
      { upTo: Infinity, rate: 0.195 },
    ],
  },
  NL: {
    name: 'Newfoundland and Labrador',
    lowestRate: 0.087,
    bpa: 13094,
    brackets: [
      { upTo: 44678, rate: 0.087 },
      { upTo: 89354, rate: 0.145 },
      { upTo: 159528, rate: 0.158 },
      { upTo: 223340, rate: 0.178 },
      { upTo: 285319, rate: 0.198 },
      { upTo: 570638, rate: 0.208 },
      { upTo: 1141275, rate: 0.213 },
      { upTo: Infinity, rate: 0.218 },
    ],
  },
  NS: {
    name: 'Nova Scotia',
    lowestRate: 0.0879,
    bpa: 11932,
    brackets: [
      { upTo: 30995, rate: 0.0879 },
      { upTo: 61991, rate: 0.1495 },
      { upTo: 97417, rate: 0.1667 },
      { upTo: 157124, rate: 0.175 },
      { upTo: Infinity, rate: 0.21 },
    ],
  },
  NT: {
    name: 'Northwest Territories',
    lowestRate: 0.059,
    bpa: 18198,
    brackets: [
      { upTo: 53003, rate: 0.059 },
      { upTo: 106009, rate: 0.086 },
      { upTo: 172346, rate: 0.122 },
      { upTo: Infinity, rate: 0.1405 },
    ],
  },
  NU: {
    name: 'Nunavut',
    lowestRate: 0.04,
    bpa: 19659,
    brackets: [
      { upTo: 55801, rate: 0.04 },
      { upTo: 111602, rate: 0.07 },
      { upTo: 181439, rate: 0.09 },
      { upTo: Infinity, rate: 0.115 },
    ],
  },
  ON: {
    name: 'Ontario',
    lowestRate: 0.0505,
    bpa: 12989,
    brackets: [
      { upTo: 53891, rate: 0.0505 },
      { upTo: 107785, rate: 0.0915 },
      { upTo: 150000, rate: 0.1116 },
      { upTo: 220000, rate: 0.1216 },
      { upTo: Infinity, rate: 0.1316 },
    ],
    // Ontario surtax, applied to basic Ontario tax (after the BPA credit):
    // 20% of tax over t1, plus an additional 36% of tax over t2.
    surtax: { t1: 5818, r1: 0.20, t2: 7446, r2: 0.36 },
  },
  PE: {
    name: 'Prince Edward Island',
    lowestRate: 0.095,
    bpa: 15000,
    brackets: [
      { upTo: 33928, rate: 0.095 },
      { upTo: 65820, rate: 0.1347 },
      { upTo: 106890, rate: 0.166 },
      { upTo: 142250, rate: 0.1762 },
      { upTo: 200000, rate: 0.19 },
      { upTo: Infinity, rate: 0.20 },
    ],
  },
  QC: {
    name: 'Quebec',
    lowestRate: 0.14,
    bpa: 18952,
    usesQpp: true, // Quebec uses QPP, and federal tax gets the 16.5% abatement
    brackets: [
      { upTo: 54345, rate: 0.14 },
      { upTo: 108680, rate: 0.19 },
      { upTo: 132245, rate: 0.24 },
      { upTo: Infinity, rate: 0.2575 },
    ],
  },
  SK: {
    name: 'Saskatchewan',
    lowestRate: 0.105,
    bpa: 20381,
    brackets: [
      { upTo: 54532, rate: 0.105 },
      { upTo: 155805, rate: 0.125 },
      { upTo: Infinity, rate: 0.145 },
    ],
  },
  YT: {
    name: 'Yukon',
    lowestRate: 0.064,
    bpa: 16452, // Yukon mirrors the federal basic personal amount
    // Statutory rates 6.4 / 9 / 10.9 / 12.8 / 15. A table showing 12.93% gives the effective rate after the BPA clawback.
    brackets: [
      { upTo: 58523, rate: 0.064 },
      { upTo: 117045, rate: 0.09 },
      { upTo: 181440, rate: 0.109 },
      { upTo: 500000, rate: 0.128 },
      { upTo: Infinity, rate: 0.15 },
    ],
  },
};

// Display order for the province dropdown (alphabetical by full name).
export const PROVINCE_ORDER = [
  'AB', 'BC', 'MB', 'NB', 'NL', 'NS', 'NT', 'NU', 'ON', 'PE', 'QC', 'SK', 'YT',
];
