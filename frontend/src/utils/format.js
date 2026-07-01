/**
 * Format a numeric amount as a currency string.
 * Restaurant prices are whole units (AMD has no minor unit in practice).
 */
export function formatPrice(amount, currency = 'AMD') {
  const value = Number(amount) || 0
  try {
    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency,
      maximumFractionDigits: 0,
    }).format(value)
  } catch {
    return `${value.toLocaleString()} ${currency}`
  }
}
