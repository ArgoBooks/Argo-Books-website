// etsy-fee-calculator/scripts/calc.js
//
// Pure Etsy fee math. No DOM, no globals: every function takes the rates it
// needs as an argument, so the same code runs in the browser (fed from
// window.ETSY_FEES, which PHP renders from data/fees.php) and under node --test
// with hand-written fixtures.
//
// Fee model (rates verified 2026-07-30, see data/fees.php):
//   order total  = item price + shipping charged to the buyer
//   listing fee  = flat, per sale
//   transaction  = 6.5% of the order total, shipping included
//   processing   = a percentage of the order total plus a flat amount, by country
//   regulatory   = a percentage of the order total, in some countries only
//   offsite ads  = 15% or 12% of the order total, capped per order
//   conversion   = 2.5% when the listing currency differs from the payout currency

/** Coerce a form value to a usable non-negative number. Blank fields read 0, never NaN. */
export function num(value) {
  const n = typeof value === 'number' ? value : parseFloat(value);
  return Number.isFinite(n) ? Math.max(0, n) : 0;
}

/**
 * The share of the order total that scales with price. Everything here is a
 * percentage of the order, so it can be summed into one rate and, crucially,
 * inverted when solving for a price.
 */
function variableRate(input, rates) {
  return (
    rates.transactionPct +
    rates.processingPct +
    rates.regulatoryPct +
    num(input.offsiteAdsRate) +
    (input.currencyConversion ? rates.currencyConversionPct : 0)
  );
}

/** Fees that do not move with the price: the listing fee and the flat part of processing. */
function fixedFees(input, rates) {
  return num(input.listingFee) + rates.processingFlat;
}

/** Everything the seller spends on the order, outside of Etsy's cut. */
function sellerCosts(input) {
  return num(input.materials) + num(input.labour) + num(input.shippingCost) + num(input.otherCosts);
}

/**
 * Forward mode: given a price, work out every fee and what is left.
 *
 * @param {object} input  itemPrice, shippingCharged, shippingCost, materials,
 *                        labour, otherCosts, listingFee, offsiteAdsRate,
 *                        currencyConversion
 * @param {object} rates  transactionPct, processingPct, processingFlat,
 *                        regulatoryPct, offsiteAdsCap, currencyConversionPct
 */
export function computeSale(input, rates) {
  const itemPrice = num(input.itemPrice);
  const shippingCharged = num(input.shippingCharged);
  const orderTotal = itemPrice + shippingCharged;

  // No sale, no fees. Guards the flat charges, which would otherwise show up
  // against an empty form.
  const hasSale = orderTotal > 0;

  const adsRate = num(input.offsiteAdsRate);
  const fees = {
    listing: hasSale ? num(input.listingFee) : 0,
    transaction: orderTotal * rates.transactionPct,
    processing: hasSale ? orderTotal * rates.processingPct + rates.processingFlat : 0,
    regulatory: orderTotal * rates.regulatoryPct,
    offsiteAds: Math.min(orderTotal * adsRate, rates.offsiteAdsCap),
    currencyConversion: input.currencyConversion ? orderTotal * rates.currencyConversionPct : 0,
  };

  const totalFees = fees.listing + fees.transaction + fees.processing +
    fees.regulatory + fees.offsiteAds + fees.currencyConversion;

  const costs = sellerCosts(input);
  const profit = orderTotal - totalFees - costs;

  return {
    itemPrice,
    shippingCharged,
    orderTotal,
    fees,
    totalFees,
    costs,
    profit,
    // After Etsy, before the seller's own costs. This is the number that shows
    // up in the Etsy payment account, which is why it is worth reporting.
    afterEtsy: orderTotal - totalFees,
    feePct: orderTotal > 0 ? totalFees / orderTotal : 0,
    margin: orderTotal > 0 ? profit / orderTotal : 0,
  };
}

/**
 * Reverse mode: given a target profit, solve for the item price that delivers it.
 *
 * profit = orderTotal - (orderTotal * variableRate + fixedFees) - costs
 * so     orderTotal = (profit + fixedFees + costs) / (1 - variableRate)
 *
 * Returns null when the equation has no solution, which needs a combined rate at
 * or above 100%. Real rates top out near 22%, but a bad rates object should fail
 * visibly rather than produce a negative price.
 */
export function priceForProfit(input, rates) {
  const target = num(input.targetProfit);
  const costs = sellerCosts(input);
  const fixed = fixedFees(input, rates);
  const adsRate = num(input.offsiteAdsRate);

  let rate = variableRate(input, rates);
  if (rate >= 1) return null;

  let orderTotal = (target + fixed + costs) / (1 - rate);

  // Past the per-order Offsite Ads cap the fee stops scaling with price, so it moves from the variable side of the equation to the fixed side and the price has to be solved again.
  if (adsRate > 0 && orderTotal * adsRate > rates.offsiteAdsCap) {
    rate -= adsRate;
    orderTotal = (target + fixed + rates.offsiteAdsCap + costs) / (1 - rate);
  }

  const itemPrice = orderTotal - num(input.shippingCharged);
  return { orderTotal, itemPrice };
}

/**
 * Scale one sale out to a year. This is what turns "$4.84 a sale" into
 * "$871 a year", which is the number most sellers have never actually worked out.
 */
export function projectYear(sale, salesPerMonth) {
  const sales = num(salesPerMonth) * 12;
  return {
    sales,
    revenue: sale.orderTotal * sales,
    fees: sale.totalFees * sales,
    costs: sale.costs * sales,
    profit: sale.profit * sales,
  };
}
