export function dollars(value: number): string {
  return value.toLocaleString('en-US', { style: 'currency', currency: 'USD' })
}

export function flag(code: string): string {
  if (!/^[A-Z]{2}$/.test(code)) return ''
  return [...code].map((char) => String.fromCodePoint(0x1f1e6 + char.charCodeAt(0) - 65)).join('')
}
