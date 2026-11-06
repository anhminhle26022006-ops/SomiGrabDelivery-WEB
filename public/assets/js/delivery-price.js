(() => {
  'use strict';
  const scaled = value => {
    const text = String(value);
    if (!/^\d+(?:\.\d{1,3})?$/.test(text)) throw new Error('Invalid decimal');
    const [whole, fraction = ''] = text.split('.');
    return BigInt(whole) * 1000n + BigInt(fraction.padEnd(3, '0'));
  };
  function calculate(c, distance, weight, currency, time = 10000, weather = 10000) {
    const meters = scaled(distance), grams = scaled(weight);
    if (meters >= scaled(c.max_distance_km)) throw new Error('OUTSIDE_SERVICE_AREA');
    if (grams <= 0n || grams > scaled(c.max_weight_kg)) throw new Error('WEIGHT_NOT_SUPPORTED');
    const extraMeters = meters > scaled(c.included_distance_km) ? meters - scaled(c.included_distance_km) : 0n;
    const extraGrams = grams > scaled(c.included_weight_kg) ? grams - scaled(c.included_weight_kg) : 0n;
    const distanceMills = extraMeters * BigInt(c.per_km_minor[currency]);
    const weightMills = extraGrams * BigInt(c.per_kg_minor[currency]);
    const subtotalMills = BigInt(c.base_fee_minor[currency]) * 1000n + distanceMills + weightMills;
    const numerator = subtotalMills * BigInt(time) * BigInt(weather), denominator = 100000000000n;
    return {total: Number((numerator + denominator - 1n) / denominator), base: c.base_fee_minor[currency],
      distance: Number(distanceMills) / 1000, weight: Number(weightMills) / 1000,
      subtotal: Number(subtotalMills) / 1000};
  }
  window.SomiDeliveryPrice = {calculate};
})();
