export function dollars(value: number): string {
  return value.toLocaleString('en-US', { style: 'currency', currency: 'USD' })
}

export function wholeDollars(value: number): string {
  return value.toLocaleString('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0, minimumFractionDigits: 0 })
}

export function compactDollars(value: number): string {
  if (value < 1000) {
    return `$${Math.round(value)}`
  }
  return `$${(value / 1000).toFixed(value >= 10000 ? 0 : 1).replace(/\.0$/, '')}k`
}

export function flag(code: string): string {
  if (!/^[A-Z]{2}$/.test(code)) return ''
  return [...code].map((char) => String.fromCodePoint(0x1f1e6 + char.charCodeAt(0) - 65)).join('')
}
