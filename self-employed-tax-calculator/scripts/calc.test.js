// self-employed-tax-calculator/scripts/calc.test.js
// Verifies the tax math against hand-worked 2026 examples. Run with:
//   node --test self-employed-tax-calculator/scripts/*.test.js

import { test } from 'node:test';
import assert from 'node:assert';
import { computeUS, computeCanada, bracketTax } from './calc.js';

const near = (actual, expected, tol = 1, msg = '') =>
  assert.ok(Math.abs(actual - expected) <= tol, `${msg} expected ~${expected}, got ${actual}`);

test('bracketTax sums marginal slices', () => {
  const brackets = [{ upTo: 100, rate: 0.10 }, { upTo: 200, rate: 0.20 }, { upTo: Infinity, rate: 0.30 }];
  assert.strictEqual(bracketTax(0, brackets), 0);
  near(bracketTax(50, brackets), 5);          // 50 * 10%
  near(bracketTax(150, brackets), 10 + 10);   // 100*10% + 50*20%
  near(bracketTax(250, brackets), 10 + 20 + 15); // + 50*30%
});

// US: $60k profit. SE tax 60000*0.9235*0.153 = 8477.73, half 4238.865. Taxable 39661.135. Income tax 12400*0.10 + 27261.135*0.12 = 4511.34. Total 12989.07.
test('US $60k self-employed', () => {
  const r = computeUS(60000);
  near(r.contribution, 8477.73, 0.5, 'SE tax');
  near(r.incomeTax, 4511.34, 0.5, 'income tax');
  near(r.total, 12989.07, 1, 'total');
  near(r.quarterly, 3247.27, 0.5, 'quarterly');
  near(r.effectiveRate, 0.2165, 0.001, 'effective rate');
});

// US high income exercises the Social Security wage-base cap ($184,500). $250k: seBase=230875; SS=184500*0.124=22878; Medicare=230875*0.029=6695.375; SE=29573.375.
test('US $250k caps Social Security portion', () => {
  const r = computeUS(250000);
  near(r.contribution, 29573.38, 1, 'SE tax with SS cap');
  near(r.total, 76145.64, 2, 'total');
});

test('US zero and tiny profit owe nothing', () => {
  assert.strictEqual(computeUS(0).total, 0);
  assert.strictEqual(computeUS(300).contribution, 0); // below $400 net-earnings floor
});

// Canada ON: $60k profit. CPP = (60000-3500)*0.119 = 6723.50. Federal = (58523*0.14 + 1477*0.205) - 16452*0.14 = 8496.005 - 2303.28 = 6192.73.
test('Canada Ontario $60k self-employed', () => {
  const r = computeCanada(60000, 'ON');
  near(r.contribution, 6723.50, 0.5, 'CPP');
  near(r.federalTax, 6192.73, 0.5, 'federal');
  near(r.provincialTax, 2624.52, 0.5, 'Ontario');
  near(r.total, 15540.75, 1, 'total');
});

// Quebec exercises QPP (12.6%) + the 16.5% federal abatement. QPP = 56500*0.126 = 7119. Federal = 6192.725 * 0.835 = 5170.93.
test('Canada Quebec $60k uses QPP and abatement', () => {
  const r = computeCanada(60000, 'QC');
  near(r.contribution, 7119.00, 0.5, 'QPP');
  near(r.total, 18319.40, 1.5, 'total');
});

// CPP2 kicks in above the first ceiling (YMPE $74,600).
// $90k Ontario: base CPP = (74600-3500)*0.119 = 8460.90; CPP2 = (85000-74600)*0.08 = 832.
test('Canada $90k adds the CPP2 second contribution', () => {
  const r = computeCanada(90000, 'ON');
  near(r.contribution, 8460.90 + 832, 1, 'CPP base + CPP2');
});

test('every province computes a positive total at $80k', () => {
  const codes = ['AB', 'BC', 'MB', 'NB', 'NL', 'NS', 'NT', 'NU', 'ON', 'PE', 'QC', 'SK', 'YT'];
  for (const code of codes) {
    const r = computeCanada(80000, code);
    assert.ok(r.total > 0 && r.total < 80000, `${code} total out of range: ${r.total}`);
    assert.ok(r.effectiveRate > 0.1 && r.effectiveRate < 0.45, `${code} effective rate odd: ${r.effectiveRate}`);
  }
});
