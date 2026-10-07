// shared/scripts/business-calcs.js
//
// Pure math for the standalone business calculators: mileage, hourly rate,
// late fees, markup and margin, break-even, and market stall profit. No DOM and
// no globals, so business-calcs.test.js drives every function directly and each
// tool's main.js only formats and paints.
//
// Rates that a tax authority sets are never hardcoded here. They arrive as an
// argument from shared/data/mileage-rates.php via the page.

/** Coerce a form value to a non-negative number. Blanks and junk read 0. */
export function num(value) {
  const n = typeof value === 'number' ? value : parseFloat(value);
  return Number.isFinite(n) ? Math.max(0, n) : 0;
}

/** Same, but allows negatives, for figures that can legitimately go below zero. */
export function signed(value) {
  const n = typeof value === 'number' ? value : parseFloat(value);
  return Number.isFinite(n) ? n : 0;
}

/* ---------------------------------------------------------------- mileage */

/**
 * Deduction for a tiered rate (Canada, UK): the first N units earn the higher
 * rate, everything above it earns the lower one. A tier with upTo === null runs
 * to infinity.
 *
 * @param {number} distance
 * @param {Array<{upTo: ?number, rate: number}>} tiers
 * @param {?number} cap  optional hard ceiling on claimable distance (Australia)
 */
export function mileageTiered(distance, tiers, cap = null) {
  const total = num(distance);
  const claimable = cap ? Math.min(total, cap) : total;

  let remaining = claimable;
  let consumed = 0;
  let deduction = 0;
  const bands = [];

  for (const tier of tiers) {
    if (remaining <= 0) break;
    const limit = tier.upTo === null || tier.upTo === undefined
      ? Infinity
      : Math.max(0, tier.upTo - consumed);
    const inBand = Math.min(remaining, limit);
    if (inBand <= 0) continue;

    const amount = inBand * tier.rate;
    bands.push({ distance: inBand, rate: tier.rate, amount });
    deduction += amount;
    remaining -= inBand;
    consumed += inBand;
  }

  return {
    distance: total,
    claimable,
    excluded: total - claimable,
    bands,
    deduction,
    effectiveRate: claimable > 0 ? deduction / claimable : 0,
  };
}

/**
 * Deduction where the rate depends on when the trip happened rather than how
 * far you have driven (the US mid-year change). Each entry pairs a period's
 * distance with that period's rate.
 *
 * @param {Array<{distance: number|string, rate: number}>} periods
 */
export function mileageSplit(periods) {
  const bands = [];
  let deduction = 0;
  let distance = 0;

  for (const p of periods) {
    const d = num(p.distance);
    if (d <= 0) continue;
    const amount = d * p.rate;
    bands.push({ distance: d, rate: p.rate, amount });
    deduction += amount;
    distance += d;
  }

  return {
    distance,
    claimable: distance,
    excluded: 0,
    bands,
    deduction,
    effectiveRate: distance > 0 ? deduction / distance : 0,
  };
}

/* ------------------------------------------------------------ hourly rate */

/**
 * The rate you must charge to take home a target income.
 *
 * The trap this exists to expose: people divide their target salary by 2,080
 * hours and get a number that ignores unbillable time, business costs, and tax.
 * Billable hours are always a fraction of hours worked.
 */
export function hourlyRate(input) {
  const targetIncome = num(input.targetIncome);
  const expenses = num(input.businessExpenses);
  const weeksOff = Math.min(num(input.weeksOff), 51);
  const workingWeeks = Math.max(1, 52 - weeksOff);
  const hoursPerWeek = num(input.hoursPerWeek);
  const billablePct = Math.min(100, num(input.billablePercent)) / 100;
  const taxPct = Math.min(99, num(input.taxPercent)) / 100;

  const hoursWorked = hoursPerWeek * workingWeeks;
  const billableHours = hoursWorked * billablePct;

  // Target divided by hours worked: the sum almost everyone starts with.
  const naiveRate = hoursWorked > 0 ? targetIncome / hoursWorked : 0;

  // Gross up for tax: to keep `targetIncome` after tax you must earn more.
  const preTaxIncome = taxPct < 1 ? targetIncome / (1 - taxPct) : 0;
  const revenueNeeded = preTaxIncome + expenses;

  return {
    workingWeeks,
    hoursWorked,
    billableHours,
    unbillableHours: hoursWorked - billableHours,
    preTaxIncome,
    revenueNeeded,
    rate: billableHours > 0 ? revenueNeeded / billableHours : 0,
    dayRate: billableHours > 0 ? (revenueNeeded / billableHours) * 8 : 0,

    // The naive figure people start from and what it really leaves them, since take-home reads better than a shortfall.
    naiveRate,
    naiveTakeHome: Math.max(0, (naiveRate * billableHours - expenses) * (1 - taxPct)),
  };
}

/* ---------------------------------------------------------------- late fee */

/**
 * Interest and fees on an overdue invoice.
 *
 * @param {object} input
 *   amount        the unpaid invoice total
 *   daysOverdue   days past the due date
 *   annualRate    annual interest percentage
 *   flatFee       one-off administrative charge
 *   compound      'simple' | 'monthly'
 */
export function lateFee(input) {
  const amount = num(input.amount);
  const days = Math.round(num(input.daysOverdue));
  const annual = num(input.annualRate) / 100;
  const flat = num(input.flatFee);

  let interest;
  if (input.compound === 'monthly') {
    // Monthly compounding on whole months elapsed, then simple interest on the
    // remaining part-month, which is how most accounting packages bill it.
    const months = Math.floor(days / 30);
    const spare = days - months * 30;
    const monthlyRate = annual / 12;
    const compounded = amount * Math.pow(1 + monthlyRate, months);
    interest = (compounded - amount) + compounded * monthlyRate * (spare / 30);
  } else {
    interest = amount * annual * (days / 365);
  }

  const totalFees = interest + flat;
  return {
    amount,
    days,
    interest,
    flatFee: flat,
    totalFees,
    totalDue: amount + totalFees,
    dailyInterest: annual > 0 ? amount * annual / 365 : 0,
    effectivePercent: amount > 0 ? totalFees / amount : 0,
  };
}

/* --------------------------------------------------------- markup / margin */

/**
 * Fill in the rest of a price from any one of cost, price, markup, or margin.
 * `known` says which pair the caller supplied.
 */
export function pricing(input) {
  const cost = num(input.cost);
  let price = num(input.price);
  let markup = num(input.markupPercent);
  let margin = num(input.marginPercent);

  switch (input.known) {
    case 'cost+markup':
      price = cost * (1 + markup / 100);
      break;
    case 'cost+margin': {
      const m = Math.min(99.999, margin) / 100;
      price = cost / (1 - m);
      break;
    }
    case 'cost+price':
    default:
      break;
  }

  const profit = price - cost;
  markup = cost > 0 ? (profit / cost) * 100 : 0;
  margin = price > 0 ? (profit / price) * 100 : 0;

  return { cost, price, profit, markupPercent: markup, marginPercent: margin };
}

/* --------------------------------------------------------------- breakeven */

/**
 * Units that must sell before fixed costs are covered.
 * Contribution per unit is price minus variable cost; at or below zero no
 * volume ever breaks even, which the page needs to say plainly.
 */
export function breakEven(input) {
  const fixed = num(input.fixedCosts);
  const price = num(input.pricePerUnit);
  const variable = num(input.variableCostPerUnit);

  const contribution = price - variable;
  const viable = contribution > 0;

  const units = viable ? fixed / contribution : null;
  return {
    fixedCosts: fixed,
    price,
    variable,
    contribution,
    contributionMargin: price > 0 ? contribution / price : 0,
    viable,
    units,
    unitsRounded: viable ? Math.ceil(units) : null,
    revenue: viable ? Math.ceil(units) * price : null,
    // Profit once a given volume is sold, for the "what if" line.
    profitAt: (qty) => num(qty) * contribution - fixed,
  };
}

/* -------------------------------------------------------------- craft fair */

/**
 * Whether a market stall paid for itself.
 *
 * Stall costs are almost entirely fixed and paid before you sell anything, so
 * the useful output is how many sales it takes to get back to zero.
 */
export function craftFair(input) {
  const boothFee = num(input.boothFee);
  const travel = num(input.travel);
  const otherCosts = num(input.otherCosts);
  const hours = num(input.hours);
  const hourlyRateValue = num(input.hourlyRate);

  const cashCosts = boothFee + travel + otherCosts;
  const timeCost = hours * hourlyRateValue;
  const totalCost = cashCosts + timeCost;

  const avgSale = num(input.averageSale);
  const materialPct = Math.min(100, num(input.materialPercent)) / 100;
  const contributionPerSale = avgSale * (1 - materialPct);

  const sales = num(input.salesMade);
  const revenue = sales * avgSale;
  const materialsUsed = revenue * materialPct;
  const profitCash = revenue - materialsUsed - cashCosts;
  const profitTrue = profitCash - timeCost;

  const viable = contributionPerSale > 0;
  return {
    cashCosts,
    timeCost,
    totalCost,
    contributionPerSale,
    viable,
    // Sales needed to cover the cash you laid out, and to also pay for your day.
    salesToCoverCash: viable ? Math.ceil(cashCosts / contributionPerSale) : null,
    salesToCoverAll: viable ? Math.ceil(totalCost / contributionPerSale) : null,
    revenue,
    materialsUsed,
    profitCash,
    profitTrue,
    effectiveHourly: hours > 0 ? profitCash / hours : 0,
  };
}
