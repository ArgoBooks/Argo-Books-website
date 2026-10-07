// shared/scripts/business-calcs.test.js
// Hand-worked examples for the standalone business calculators. Run with:
//   node --test shared/scripts/*.test.js
//
// Rate fixtures are written out literally rather than imported from the PHP
// data file, so a typo there cannot make a wrong test pass.

import { test } from 'node:test';
import assert from 'node:assert';
import {
  num, signed, mileageTiered, mileageSplit, hourlyRate,
  lateFee, pricing, breakEven, craftFair,
} from './business-calcs.js';

const near = (a, e, tol = 0.005, msg = '') =>
  assert.ok(Math.abs(a - e) <= tol, `${msg} expected ~${e}, got ${a}`);

const CA_TIERS = [{ upTo: 5000, rate: 0.73 }, { upTo: null, rate: 0.67 }];
const GB_TIERS = [{ upTo: 10000, rate: 0.55 }, { upTo: null, rate: 0.25 }];
const AU_TIERS = [{ upTo: 5000, rate: 0.88 }, { upTo: null, rate: 0 }];

test('num and signed coerce form values', () => {
  assert.strictEqual(num(''), 0);
  assert.strictEqual(num(-5), 0);
  assert.strictEqual(signed(-5), -5);
  assert.strictEqual(signed('abc'), 0);
});

/* ------------------------------------------------------------- mileage */

// 4,000 km, all inside the first Canadian band: 4000 * 0.73 = 2920.
test('tiered mileage below the first threshold uses one rate', () => {
  const r = mileageTiered(4000, CA_TIERS);
  near(r.deduction, 2920, 0.01);
  assert.strictEqual(r.bands.length, 1);
  near(r.effectiveRate, 0.73, 0.0001);
});

// 12,000 km Canada: 5000 * 0.73 = 3650, then 7000 * 0.67 = 4690. Total 8340.
test('tiered mileage splits at the threshold', () => {
  const r = mileageTiered(12000, CA_TIERS);
  near(r.deduction, 8340, 0.01, 'total');
  assert.strictEqual(r.bands.length, 2);
  near(r.bands[0].distance, 5000, 0.01);
  near(r.bands[1].distance, 7000, 0.01);
  near(r.effectiveRate, 0.695, 0.0001, 'blended rate is below the headline rate');
});

// 14,000 UK miles: 10000 * 0.55 = 5500, then 4000 * 0.25 = 1000. Total 6500.
test('UK tiers drop sharply after 10,000 miles', () => {
  const r = mileageTiered(14000, GB_TIERS);
  near(r.deduction, 6500, 0.01);
  near(r.bands[1].rate, 0.25, 0.0001);
});

// Australia caps the method at 5,000 km rather than paying a lower rate above it.
test('a cap excludes distance rather than rating it lower', () => {
  const r = mileageTiered(8000, AU_TIERS, 5000);
  near(r.claimable, 5000, 0.01, 'claimable');
  near(r.excluded, 3000, 0.01, 'excluded');
  near(r.deduction, 4400, 0.01, '5000 * 0.88');
  assert.strictEqual(r.bands.length, 1, 'no zero-rate band is emitted');
});

test('exactly at the threshold stays in the first band', () => {
  const r = mileageTiered(5000, CA_TIERS);
  assert.strictEqual(r.bands.length, 1);
  near(r.deduction, 3650, 0.01);
});

// The US 2026 mid-year change: 4,000 miles at 72.5c then 6,000 at 76c.
// 2900 + 4560 = 7460.
test('a split-rate year charges each period its own rate', () => {
  const r = mileageSplit([
    { distance: 4000, rate: 0.725 },
    { distance: 6000, rate: 0.76 },
  ]);
  near(r.deduction, 7460, 0.01, 'total');
  near(r.distance, 10000, 0.01);
  near(r.effectiveRate, 0.746, 0.0001, 'blended');
});

test('empty mileage input gives zero, not NaN', () => {
  for (const r of [mileageTiered('', CA_TIERS), mileageSplit([{ distance: '', rate: 0.725 }])]) {
    assert.ok(Number.isFinite(r.deduction));
    near(r.deduction, 0, 0.001);
    assert.strictEqual(r.bands.length, 0);
  }
});

/* --------------------------------------------------------- hourly rate */

// Target $60k after 25% tax, $12k expenses, 40h/week, 6 weeks off, 60% billable.
test('hourly rate grosses up for tax and unbillable time', () => {
  const r = hourlyRate({
    targetIncome: 60000, businessExpenses: 12000, hoursPerWeek: 40,
    weeksOff: 6, billablePercent: 60, taxPercent: 25,
  });
  assert.strictEqual(r.workingWeeks, 46);
  near(r.hoursWorked, 1840, 0.01);
  near(r.billableHours, 1104, 0.01);
  near(r.preTaxIncome, 80000, 0.01);
  near(r.revenueNeeded, 92000, 0.01);
  near(r.rate, 83.3333, 0.001);
  near(r.dayRate, 666.6667, 0.01);
});

// The naive figure people start from: salary / hours worked, ignoring everything.
test('the naive rate is reported for comparison and is much lower', () => {
  const r = hourlyRate({
    targetIncome: 60000, businessExpenses: 12000, hoursPerWeek: 40,
    weeksOff: 6, billablePercent: 60, taxPercent: 25,
  });
  near(r.naiveRate, 32.6087, 0.001, '60000 / 1840');
  assert.ok(r.rate > r.naiveRate * 2, 'the real rate is more than double the naive one');
});

// Charging the naive rate: bill 1104 hours at 32.6087 = 36000 revenue, less 12000 expenses = 24000 pre-tax, less 25% tax = 18000 take-home.
test('the naive rate is shown against the take-home it would really produce', () => {
  const r = hourlyRate({
    targetIncome: 60000, businessExpenses: 12000, hoursPerWeek: 40,
    weeksOff: 6, billablePercent: 60, taxPercent: 25,
  });
  near(r.naiveTakeHome, 18000, 1, 'take-home at the naive rate');
  assert.ok(r.naiveTakeHome < 60000, 'well short of the target');
});

test('naive take-home floors at zero rather than going negative', () => {
  const r = hourlyRate({
    targetIncome: 5000, businessExpenses: 40000, hoursPerWeek: 40,
    weeksOff: 0, billablePercent: 50, taxPercent: 20,
  });
  assert.ok(r.naiveTakeHome >= 0, 'never negative');
});

test('zero billable hours cannot divide by zero', () => {
  const r = hourlyRate({ targetIncome: 50000, hoursPerWeek: 40, billablePercent: 0 });
  assert.strictEqual(r.rate, 0);
  assert.ok(Number.isFinite(r.rate));
});

/* ------------------------------------------------------------- late fee */

// $5,000, 60 days late, 8% simple: 5000 * 0.08 * 60/365 = 65.75. Plus $40 flat.
test('simple interest prorates the annual rate by days', () => {
  const r = lateFee({ amount: 5000, daysOverdue: 60, annualRate: 8, flatFee: 40 });
  near(r.interest, 65.7534, 0.01);
  near(r.totalFees, 105.7534, 0.01);
  near(r.totalDue, 5105.7534, 0.01);
  near(r.dailyInterest, 1.0959, 0.001);
});

// Monthly compounding over 90 days: two whole months plus 30 spare days.
test('monthly compounding exceeds simple interest over the same period', () => {
  const s = lateFee({ amount: 5000, daysOverdue: 90, annualRate: 12, compound: 'simple' });
  const c = lateFee({ amount: 5000, daysOverdue: 90, annualRate: 12, compound: 'monthly' });
  assert.ok(c.interest > s.interest, `compound ${c.interest} should exceed simple ${s.interest}`);
  near(c.interest, 151.51, 0.5, 'three months at 1% compounding');
});

test('a paid-on-time invoice carries no interest', () => {
  const r = lateFee({ amount: 5000, daysOverdue: 0, annualRate: 8 });
  near(r.interest, 0, 0.001);
  near(r.totalDue, 5000, 0.001);
});

/* -------------------------------------------------------- markup/margin */

test('cost plus markup produces the price, and the margin follows', () => {
  const r = pricing({ known: 'cost+markup', cost: 40, markupPercent: 150 });
  near(r.price, 100, 0.001);
  near(r.profit, 60, 0.001);
  near(r.marginPercent, 60, 0.001);
});

test('cost plus target margin produces the price, and the markup follows', () => {
  const r = pricing({ known: 'cost+margin', cost: 40, marginPercent: 60 });
  near(r.price, 100, 0.001);
  near(r.markupPercent, 150, 0.001);
});

test('cost and price derive both percentages', () => {
  const r = pricing({ known: 'cost+price', cost: 40, price: 100 });
  near(r.markupPercent, 150, 0.001);
  near(r.marginPercent, 60, 0.001);
});

// The confusion this calculator exists for: doubling cost is 100% markup, 50% margin.
test('doubling cost is a 100% markup and a 50% margin', () => {
  const r = pricing({ known: 'cost+markup', cost: 25, markupPercent: 100 });
  near(r.price, 50, 0.001);
  near(r.marginPercent, 50, 0.001);
});

test('markup and margin round-trip through price', () => {
  for (const markup of [20, 75, 150, 300]) {
    const a = pricing({ known: 'cost+markup', cost: 40, markupPercent: markup });
    const b = pricing({ known: 'cost+margin', cost: 40, marginPercent: a.marginPercent });
    near(b.price, a.price, 0.01, `markup ${markup}`);
  }
});

/* ------------------------------------------------------------ break-even */

// Fixed 6000, price 25, variable 10: contribution 15, units 400, revenue 10000.
test('break-even divides fixed costs by contribution per unit', () => {
  const r = breakEven({ fixedCosts: 6000, pricePerUnit: 25, variableCostPerUnit: 10 });
  near(r.contribution, 15, 0.001);
  near(r.units, 400, 0.001);
  assert.strictEqual(r.unitsRounded, 400);
  near(r.revenue, 10000, 0.001);
  near(r.contributionMargin, 0.6, 0.0001);
  assert.strictEqual(r.viable, true);
});

test('a part unit rounds up, because you cannot sell half a thing', () => {
  const r = breakEven({ fixedCosts: 1000, pricePerUnit: 30, variableCostPerUnit: 13 });
  near(r.units, 58.8235, 0.001);
  assert.strictEqual(r.unitsRounded, 59);
});

test('selling at or below variable cost never breaks even', () => {
  for (const price of [10, 8]) {
    const r = breakEven({ fixedCosts: 5000, pricePerUnit: price, variableCostPerUnit: 10 });
    assert.strictEqual(r.viable, false);
    assert.strictEqual(r.units, null);
    assert.strictEqual(r.revenue, null);
  }
});

test('profitAt reports the loss below break-even and profit above it', () => {
  const r = breakEven({ fixedCosts: 6000, pricePerUnit: 25, variableCostPerUnit: 10 });
  near(r.profitAt(400), 0, 0.001, 'at break-even');
  near(r.profitAt(500), 1500, 0.001, 'above');
  near(r.profitAt(300), -1500, 0.001, 'below');
});

/* ------------------------------------------------------------ craft fair */

// Booth 120 + travel 45 + other 20 = 185 cash. 9 hours at 20 = 180 time. Average sale 28 with 35% materials: contribution 18.20 a sale.
test('market stall reports both break-even points', () => {
  const r = craftFair({
    boothFee: 120, travel: 45, otherCosts: 20, hours: 9, hourlyRate: 20,
    averageSale: 28, materialPercent: 35, salesMade: 0,
  });
  near(r.cashCosts, 185, 0.01);
  near(r.timeCost, 180, 0.01);
  near(r.totalCost, 365, 0.01);
  near(r.contributionPerSale, 18.2, 0.01);
  assert.strictEqual(r.salesToCoverCash, 11);
  assert.strictEqual(r.salesToCoverAll, 21);
});

// 24 sales at 28 = 672 revenue, materials 235.20, cash profit 672-235.20-185 = 251.80.
// True profit after paying yourself: 251.80 - 180 = 71.80. Hourly 251.80/9 = 27.98.
test('market stall separates cash profit from profit after paying yourself', () => {
  const r = craftFair({
    boothFee: 120, travel: 45, otherCosts: 20, hours: 9, hourlyRate: 20,
    averageSale: 28, materialPercent: 35, salesMade: 24,
  });
  near(r.revenue, 672, 0.01);
  near(r.materialsUsed, 235.2, 0.01);
  near(r.profitCash, 251.8, 0.01);
  near(r.profitTrue, 71.8, 0.01);
  near(r.effectiveHourly, 27.9778, 0.01);
});

test('a stall selling nothing loses exactly its cash costs', () => {
  const r = craftFair({ boothFee: 120, travel: 45, hours: 8, hourlyRate: 20, averageSale: 30, materialPercent: 40, salesMade: 0 });
  near(r.profitCash, -165, 0.01);
  near(r.profitTrue, -325, 0.01);
});

test('an average sale of zero cannot break even', () => {
  const r = craftFair({ boothFee: 100, averageSale: 0, materialPercent: 40 });
  assert.strictEqual(r.viable, false);
  assert.strictEqual(r.salesToCoverCash, null);
});
