// shared/scripts/craft-engine.js
//
// Batch-to-unit pricing math shared by the craft product calculators (candles,
// soap, tumblers, cakes). No DOM, no globals, so the tests drive it directly.
//
// The model that matters for handmade sellers is the batch. You buy wax by the
// pound and a bag of wicks, then pour twelve candles. Pricing off the pack
// price is the single most common mistake, so everything here is entered per
// batch and divided down.
//
//   materials per unit = sum(batch material costs) / batch yield
//   minutes per unit   = minutes for the whole batch / batch yield
//   labour per unit    = hourly rate * (minutes per unit / 60)
//   unit cost          = materials + labour + overhead
//   price              = unit cost * (1 + markup%)
//   margin             = (price - unit cost) / price

/** Coerce a form value to a non-negative number. Blanks and junk read 0. */
export function num(value) {
  const n = typeof value === 'number' ? value : parseFloat(value);
  return Number.isFinite(n) ? Math.max(0, n) : 0;
}

/**
 * @param {object} input
 *   materials       array of per-batch material costs (numbers or form strings)
 *   batchYield      units one batch makes; anything below 1 is treated as 1
 *   hourlyRate      what the maker wants to earn per hour
 *   minutesPerBatch hands-on time for the whole batch, divided down for you
 *   overheadPerUnit packaging, stall fees, listing fees, amortised per unit
 *   markupPercent   percentage added on top of unit cost
 */
export function computeCraft(input) {
  const list = Array.isArray(input.materials) ? input.materials : [];
  const materialsBatch = list.reduce((sum, v) => sum + num(v), 0);

  // A batch always makes at least one thing. Guarding here rather than at the
  // input keeps a half-typed "0" from producing Infinity on screen.
  const batchYield = Math.max(1, num(input.batchYield) || 1);

  const materialsPerUnit = materialsBatch / batchYield;

  // Makers think in batches ("a batch takes me about three hours"), so the
  // form asks for batch time and the division happens here rather than in
  // the user's head.
  const minutesPerBatch = num(input.minutesPerBatch);
  const minutesPerUnit = minutesPerBatch / batchYield;
  const labourPerUnit = num(input.hourlyRate) * (minutesPerUnit / 60);
  const overheadPerUnit = num(input.overheadPerUnit);

  const unitCost = materialsPerUnit + labourPerUnit + overheadPerUnit;
  const markup = num(input.markupPercent);
  const price = unitCost * (1 + markup / 100);
  const profit = price - unitCost;

  return {
    materialsBatch,
    batchYield,
    materialsPerUnit,
    minutesPerBatch,
    minutesPerUnit,
    labourPerUnit,
    overheadPerUnit,
    unitCost,
    price,
    profit,
    margin: price > 0 ? profit / price : 0,
    batchCost: unitCost * batchYield,
    batchRevenue: price * batchYield,
    batchProfit: profit * batchYield,
  };
}

/** Price for one unit at a given markup, for the by-channel comparison table. */
export function priceAtMarkup(unitCost, markupPercent) {
  return num(unitCost) * (1 + num(markupPercent) / 100);
}

/** The margin a given markup produces. */
export function marginForMarkup(markupPercent) {
  const k = num(markupPercent) / 100;
  return k / (1 + k);
}
