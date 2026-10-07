// etsy-fee-calculator/scripts/calc.test.js
// Verifies the Etsy fee math against hand-worked examples. Run with:
//   node --test etsy-fee-calculator/scripts/*.test.js
//
// Rate fixtures are written out literally rather than imported, so a typo in
// data/fees.php cannot quietly make a wrong test pass.

import { test } from 'node:test';
import assert from 'node:assert';
import { computeSale, priceForProfit, projectYear, num } from './calc.js';

const near = (actual, expected, tol = 0.005, msg = '') =>
  assert.ok(Math.abs(actual - expected) <= tol, `${msg} expected ~${expected}, got ${actual}`);

const US = {
  transactionPct: 0.065,
  processingPct: 0.030,
  processingFlat: 0.25,
  regulatoryPct: 0,
  offsiteAdsCap: 100,
  currencyConversionPct: 0.025,
};
const CA = { ...US, regulatoryPct: 0.0115 };
const GB = { ...US, processingPct: 0.040, processingFlat: 0.20, regulatoryPct: 0.0032 };

test('num() coerces blanks, junk, and negatives to zero', () => {
  assert.strictEqual(num(''), 0);
  assert.strictEqual(num(undefined), 0);
  assert.strictEqual(num('abc'), 0);
  assert.strictEqual(num(-5), 0);
  assert.strictEqual(num('12.50'), 12.5);
});

// $28 item + $5 shipping = $33 order. listing 0.20 | transaction 33*.065=2.145 | processing 33*.03+0.25=1.24 fees 3.585 | costs 6+8+4.50=18.50 | profit 33-3.585-18.50=10.915
test('US sale: every fee line and the profit', () => {
  const r = computeSale({
    itemPrice: 28, shippingCharged: 5, listingFee: 0.20,
    materials: 6, labour: 8, shippingCost: 4.50,
  }, US);

  near(r.orderTotal, 33, 0.001, 'order total');
  near(r.fees.listing, 0.20, 0.001, 'listing');
  near(r.fees.transaction, 2.145, 0.001, 'transaction');
  near(r.fees.processing, 1.24, 0.001, 'processing');
  near(r.fees.regulatory, 0, 0.001, 'regulatory');
  near(r.totalFees, 3.585, 0.001, 'total fees');
  near(r.afterEtsy, 29.415, 0.001, 'after Etsy, before costs');
  near(r.costs, 18.50, 0.001, 'seller costs');
  near(r.profit, 10.915, 0.001, 'profit');
  near(r.feePct, 0.10864, 0.0001, 'fees as % of order');
  near(r.margin, 0.33076, 0.0001, 'margin');
});

// Shipping the buyer pays is part of the order, so Etsy charges fees on it.
// Sellers who assume shipping is fee-free are the reason this is asserted.
test('shipping charged to the buyer is inside the fee base', () => {
  const withShipping = computeSale({ itemPrice: 28, shippingCharged: 5, listingFee: 0.20 }, US);
  const itemOnly = computeSale({ itemPrice: 28, shippingCharged: 0, listingFee: 0.20 }, US);
  near(withShipping.totalFees - itemOnly.totalFees, 5 * (0.065 + 0.030), 0.001, 'fee on $5 of shipping');
});

// $40 order, Canada: transaction 2.60 | processing 1.20+0.25 | regulatory 40*.0115=0.46
test('Canada adds the 1.15% regulatory operating fee', () => {
  const r = computeSale({ itemPrice: 40, listingFee: 0.20 }, CA);
  near(r.fees.regulatory, 0.46, 0.001, 'regulatory');
  near(r.totalFees, 4.71, 0.001, 'total fees');
  near(r.profit, 35.29, 0.001, 'profit');
});

// $25 + $3.50 = $28.50. UK processing is 4% + £0.20, regulatory 0.32%.
test('UK uses the higher processing rate and its own regulatory fee', () => {
  const r = computeSale({ itemPrice: 25, shippingCharged: 3.50, listingFee: 0.20 }, GB);
  near(r.fees.processing, 1.34, 0.001, 'processing');
  near(r.fees.regulatory, 0.0912, 0.001, 'regulatory');
  near(r.totalFees, 3.4837, 0.001, 'total fees');
});

test('Offsite Ads charges 15% or 12% of the whole order', () => {
  const at15 = computeSale({ itemPrice: 33, listingFee: 0.20, offsiteAdsRate: 0.15 }, US);
  const at12 = computeSale({ itemPrice: 33, listingFee: 0.20, offsiteAdsRate: 0.12 }, US);
  near(at15.fees.offsiteAds, 4.95, 0.001, '15% tier');
  near(at12.fees.offsiteAds, 3.96, 0.001, '12% tier');
  near(at15.totalFees - at12.totalFees, 0.99, 0.001, 'difference between tiers');
});

test('Offsite Ads is capped per order', () => {
  const r = computeSale({ itemPrice: 1000, listingFee: 0.20, offsiteAdsRate: 0.15 }, US);
  near(r.fees.offsiteAds, 100, 0.001, 'capped, not 150');
});

test('currency conversion only applies when flagged', () => {
  const off = computeSale({ itemPrice: 100, listingFee: 0.20 }, US);
  const on = computeSale({ itemPrice: 100, listingFee: 0.20, currencyConversion: true }, US);
  near(off.fees.currencyConversion, 0, 0.001, 'off');
  near(on.fees.currencyConversion, 2.50, 0.001, 'on');
});

test('an empty form produces zeros, not NaN and not a phantom listing fee', () => {
  const r = computeSale({ listingFee: 0.20 }, US);
  near(r.orderTotal, 0, 0.001, 'order total');
  near(r.totalFees, 0, 0.001, 'total fees');
  near(r.profit, 0, 0.001, 'profit');
  near(r.margin, 0, 0.001, 'margin');
  assert.ok(Object.values(r.fees).every(Number.isFinite), 'no NaN in the fee breakdown');
});

// The reverse mode is only trustworthy if its answer survives a trip back
// through the forward math. These round-trips are the real test of it.
test('reverse mode round-trips to the target profit', () => {
  const shape = {
    shippingCharged: 5, listingFee: 0.20,
    materials: 6, labour: 8, shippingCost: 4.50,
    targetProfit: 12,
  };
  const solved = priceForProfit(shape, US);
  near(solved.itemPrice, 29.1989, 0.001, 'solved item price');

  const back = computeSale({ ...shape, itemPrice: solved.itemPrice }, US);
  near(back.profit, 12, 0.001, 'round-tripped profit');
});

test('reverse mode round-trips with Offsite Ads and a regulatory fee', () => {
  const shape = {
    shippingCharged: 4, listingFee: 0.20, materials: 9,
    offsiteAdsRate: 0.15, targetProfit: 20,
  };
  const back = computeSale({ ...shape, itemPrice: priceForProfit(shape, CA).itemPrice }, CA);
  near(back.profit, 20, 0.001, 'round-tripped profit');
});

// Above the cap the ads fee stops scaling with price, so the solver has to
// re-solve with it moved to the fixed side. Without that, the price comes out high.
test('reverse mode round-trips past the Offsite Ads cap', () => {
  const shape = { listingFee: 0.20, offsiteAdsRate: 0.15, targetProfit: 5000 };
  const solved = priceForProfit(shape, US);
  const back = computeSale({ ...shape, itemPrice: solved.itemPrice }, US);
  near(back.fees.offsiteAds, 100, 0.001, 'still capped at the solved price');
  near(back.profit, 5000, 0.01, 'round-tripped profit');
});

test('reverse mode refuses an unsolvable rate set instead of returning a negative price', () => {
  assert.strictEqual(priceForProfit({ targetProfit: 10 }, { ...US, transactionPct: 1.2 }), null);
});

test('a year is twelve months of the same sale', () => {
  const sale = computeSale({ itemPrice: 28, shippingCharged: 5, listingFee: 0.20, materials: 6 }, US);
  const year = projectYear(sale, 15);
  assert.strictEqual(year.sales, 180);
  near(year.fees, sale.totalFees * 180, 0.01, 'annual fees');
  near(year.profit, sale.profit * 180, 0.01, 'annual profit');
  near(year.revenue, 33 * 180, 0.01, 'annual revenue');
});
