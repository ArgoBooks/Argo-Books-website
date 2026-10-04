// shared/scripts/craft-engine.test.js
// Hand-worked examples for the batch-to-unit craft pricing math. Run with:
//   node --test shared/scripts/*.test.js

import { test } from 'node:test';
import assert from 'node:assert';
import { computeCraft, priceAtMarkup, marginForMarkup, num } from './craft-engine.js';

const near = (actual, expected, tol = 0.005, msg = '') =>
  assert.ok(Math.abs(actual - expected) <= tol, `${msg} expected ~${expected}, got ${actual}`);

test('num() clamps blanks, junk, and negatives to zero', () => {
  assert.strictEqual(num(''), 0);
  assert.strictEqual(num('abc'), 0);
  assert.strictEqual(num(-4), 0);
  assert.strictEqual(num('7.5'), 7.5);
});

// A candle batch: $24 wax + $3 wicks + $18 jars + $9 fragrance = $54 for 12.
// materials 54/12 = 4.50 | 180min batch / 12 = 15min each, at $20/hr = 5.00
// overhead 1.25
// unit cost 10.75 | at 150% markup price = 26.875 | profit 16.125 | margin 60%
test('candle batch divides materials across the yield', () => {
  const r = computeCraft({
    materials: [24, 3, 18, 9],
    batchYield: 12,
    hourlyRate: 20,
    minutesPerBatch: 180,
    overheadPerUnit: 1.25,
    markupPercent: 150,
  });
  near(r.materialsBatch, 54, 0.001, 'batch materials');
  near(r.materialsPerUnit, 4.50, 0.001, 'materials per unit');
  near(r.minutesPerUnit, 15, 0.001, 'batch time divided down');
  near(r.labourPerUnit, 5.00, 0.001, 'labour per unit');
  near(r.unitCost, 10.75, 0.001, 'unit cost');
  near(r.price, 26.875, 0.001, 'price');
  near(r.profit, 16.125, 0.001, 'profit');
  near(r.margin, 0.6, 0.0001, 'margin');
});

test('batch totals are the unit figures times the yield', () => {
  const r = computeCraft({ materials: [54], batchYield: 12, hourlyRate: 20, minutesPerBatch: 180, overheadPerUnit: 1.25, markupPercent: 150 });
  near(r.batchCost, 10.75 * 12, 0.01, 'batch cost');
  near(r.batchRevenue, 26.875 * 12, 0.01, 'batch revenue');
  near(r.batchProfit, 16.125 * 12, 0.01, 'batch profit');
});

// A one-off cake: yield 1, so materials pass straight through.
test('a yield of one passes materials through untouched', () => {
  const r = computeCraft({ materials: [12, 6, 4], batchYield: 1, hourlyRate: 25, minutesPerBatch: 120, markupPercent: 100 });
  near(r.materialsPerUnit, 22, 0.001, 'materials');
  near(r.labourPerUnit, 50, 0.001, 'two hours at $25');
  near(r.unitCost, 72, 0.001, 'unit cost');
  near(r.price, 144, 0.001, 'doubled');
});

// A blank or zero yield must not produce Infinity on screen.
test('a zero or blank yield is treated as one batch of one', () => {
  for (const y of [0, '', undefined, -3]) {
    const r = computeCraft({ materials: [10], batchYield: y, markupPercent: 0 });
    assert.strictEqual(r.batchYield, 1, `yield ${JSON.stringify(y)}`);
    assert.ok(Number.isFinite(r.unitCost), 'unit cost finite');
    near(r.unitCost, 10, 0.001);
  }
});

test('an empty form produces zeros, not NaN', () => {
  const r = computeCraft({});
  for (const [k, v] of Object.entries(r)) {
    assert.ok(Number.isFinite(v), `${k} is finite`);
  }
  near(r.price, 0, 0.001);
  near(r.margin, 0, 0.001);
});

// The whole reason the form asks for batch time: the same 60 minutes spread
// over more units costs less per unit, which is the lever makers actually pull.
test('batch time divides across the yield', () => {
  const ten = computeCraft({ materials: [0], batchYield: 10, hourlyRate: 30, minutesPerBatch: 60, markupPercent: 0 });
  near(ten.minutesPerUnit, 6, 0.001, 'six minutes each');
  near(ten.labourPerUnit, 3, 0.001, 'six minutes at $30/hr');

  const twenty = computeCraft({ materials: [0], batchYield: 20, hourlyRate: 30, minutesPerBatch: 60, markupPercent: 0 });
  near(twenty.minutesPerUnit, 3, 0.001, 'same hour, twice the units');
  near(twenty.labourPerUnit, 1.5, 0.001, 'labour per unit halves');
});

test('priceAtMarkup matches the main calculation', () => {
  near(priceAtMarkup(10.75, 150), 26.875, 0.001);
  near(priceAtMarkup(10.75, 0), 10.75, 0.001);
});

// Markup and margin are the pair sellers most often confuse: doubling your cost
// is a 100% markup but only a 50% margin.
test('a markup converts to its margin', () => {
  near(marginForMarkup(100), 0.5, 0.0001, '100% markup is a 50% margin');
  near(marginForMarkup(150), 0.6, 0.0001, '150% markup is a 60% margin');
});
