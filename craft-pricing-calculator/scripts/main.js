// craft-pricing-calculator/scripts/main.js
// Wires the pricing form to the pure calc and renders live. Vanilla ES module.
//
// Tier 1 tool (see read-me/Tool-page-standards.md): the math is the same in any
// currency, so the picker carries the full supported list. Locales come from
// window.ARGO_CURRENCY_LOCALES, emitted by index.php from shared/currencies.php.

import { computeCraftPrice } from './calc.js';
import { currencyFormatter, applyCurrencyAffixes } from '../../shared/scripts/currency-format.js';

const $ = (sel) => document.querySelector(sel);
const el = {
  currency: $('[data-cc="currency"]'),
  material: $('[data-cc="material"]'),
  labor: $('[data-cc="labor"]'),
  markup: $('[data-cc="markup"]'),
  price: $('[data-cc="price"]'),
  cost: $('[data-cc="cost"]'),
  profit: $('[data-cc="profit"]'),
  margin: $('[data-cc="margin"]'),
};

let money = (n) => `$${(Number.isFinite(n) ? n : 0).toFixed(2)}`;
const pct = (rate) => `${Math.round(rate * 100)}%`;

// Rebuild the formatter for the chosen currency and push its symbol into the
// money input affixes. The :not() skips the percent suffix on the markup field,
// which is not a currency affix.
function setCurrency(code) {
  const fmt = currencyFormatter(code);
  money = fmt.money;
  applyCurrencyAffixes(fmt.symbol, '.calc-money', '.calc-money-affix:not(.calc-money-affix-right)');
}

function render() {
  const r = computeCraftPrice({
    materialCost: el.material.value,
    laborCost: el.labor.value,
    markupPercent: el.markup.value,
  });
  el.price.textContent = money(r.sellingPrice);
  el.cost.textContent = money(r.totalCost);
  el.profit.textContent = money(r.profit);
  el.margin.textContent = pct(r.margin);
}

function wirePresets() {
  const buttons = document.querySelectorAll('[data-cc-preset]');
  buttons.forEach((btn) => {
    btn.addEventListener('click', () => {
      el.markup.value = btn.getAttribute('data-cc-preset');
      buttons.forEach((b) => b.classList.toggle('is-active', b === btn));
      render();
    });
  });
  // Typing a custom markup clears the active preset highlight.
  if (el.markup) {
    el.markup.addEventListener('input', () => {
      buttons.forEach((b) => {
        if (b.getAttribute('data-cc-preset') !== el.markup.value) b.classList.remove('is-active');
      });
    });
  }
}

function init() {
  if (!el.material) return;

  if (el.currency) {
    setCurrency(el.currency.value);
    el.currency.addEventListener('change', () => {
      setCurrency(el.currency.value);
      render();
    });
  }

  const form = el.material.closest('form') || document;
  form.addEventListener('input', render);
  wirePresets();
  render();
}

init();
