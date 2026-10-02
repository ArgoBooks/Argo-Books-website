// shared/scripts/currency-format.js
//
// Currency formatting for the calculator tools. One copy of the Intl setup that
// the craft pricing calculator and the Etsy fee calculator were both carrying.
//
// Locales come from window.ARGO_CURRENCY_LOCALES, which the page emits from
// shared/currencies.php. See read-me/Tool-page-standards.md.

/** Locale for a currency code, falling back to en-US. */
export function localeFor(code) {
  const locales = window.ARGO_CURRENCY_LOCALES || {};
  return locales[code] || 'en-US';
}

/**
 * Build formatters for one currency.
 *
 * narrowSymbol keeps Canadian and Australian dollars as "$" rather than "CA$"
 * and "A$", which is what a seller in those countries expects to see against
 * their own prices. A few older engines throw on it, hence the fallback.
 *
 * @param {string} code   ISO currency code
 * @param {string} [locale]  override; defaults to localeFor(code)
 * @returns {{money: (n:number)=>string, moneyRound: (n:number)=>string, symbol: string}}
 *   money      full precision, cents included
 *   moneyRound whole units, for annual or aggregate figures
 *   symbol     the currency's narrow symbol, for input affixes
 */
export function currencyFormatter(code, locale) {
  const loc = locale || localeFor(code);
  const opts = { style: 'currency', currency: code, currencyDisplay: 'narrowSymbol' };

  let fmt;
  try {
    fmt = new Intl.NumberFormat(loc, opts);
  } catch {
    fmt = new Intl.NumberFormat(loc, { style: 'currency', currency: code });
  }
  const rounded = new Intl.NumberFormat(loc, { ...opts, maximumFractionDigits: 0 });

  const part = fmt.formatToParts(1).find((p) => p.type === 'currency');

  return {
    money: (n) => fmt.format(Number.isFinite(n) ? n : 0),
    moneyRound: (n) => rounded.format(Number.isFinite(n) ? n : 0),
    symbol: part ? part.value : '$',
  };
}

/**
 * The narrow symbol for a currency, e.g. "$", "£", "CHF". Falls back to "$" if
 * the engine rejects the code, which is what the invoice generator wants for an
 * affix that must always render something.
 */
export function symbolFor(code, locale) {
  try {
    return currencyFormatter(code || 'USD', locale).symbol;
  } catch {
    return '$';
  }
}

/**
 * Write a currency symbol into every money-input affix inside `root`, and set
 * each input's left padding from the affix's rendered width. Symbols run from
 * one glyph ($) to three (CHF, NT$), so a fixed CSS padding clips or floats.
 *
 * @param {string} symbol
 * @param {string} wrapSelector   the element wrapping one affix and one input
 * @param {string} affixSelector  the affix inside that wrapper
 */
export function applyCurrencyAffixes(symbol, wrapSelector, affixSelector) {
  document.querySelectorAll(wrapSelector).forEach((wrap) => {
    const affix = wrap.querySelector(affixSelector);
    const input = wrap.querySelector('input');
    if (!affix || !input) return;
    affix.textContent = symbol;
    input.style.paddingLeft = `${affix.offsetWidth + 18}px`;
  });
}
