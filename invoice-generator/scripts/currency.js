// invoice-generator/scripts/currency.js
// Currency formatting helpers used across the live tool, PDF, and DOCX generators.

// Currencies in our picker that all share the "$" symbol. When one of these
// is selected, formatMoney appends the ISO code (e.g. "$10.00 CAD") so the
// reader can tell US, Canadian, and Australian dollars apart.
const DOLLAR_CODES = new Set(['USD', 'CAD', 'AUD']);

export function isDollarCurrency(currencyCode) {
  return DOLLAR_CODES.has(currencyCode);
}

export function formatMoney(amount, currencyCode, locale) {
  if (!Number.isFinite(amount)) amount = 0;
  try {
    const formatted = new Intl.NumberFormat(locale || 'en-US', {
      style: 'currency',
      currency: currencyCode || 'USD',
      currencyDisplay: 'narrowSymbol',
    }).format(amount);
    return isDollarCurrency(currencyCode) ? `${formatted} ${currencyCode}` : formatted;
  } catch (_e) {
    return `${currencyCode || ''} ${amount.toFixed(2)}`;
  }
}
