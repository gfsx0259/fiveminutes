export interface CalendarDay {
  iso: string
  number: number
  outside: boolean
}

export const weekdays = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su']

export function monthGrid(year: number, month: number): CalendarDay[] {
  const first = new Date(Date.UTC(year, month, 1))
  const offset = (first.getUTCDay() + 6) % 7
  const start = new Date(first)
  start.setUTCDate(1 - offset)
  return Array.from({ length: 42 }, (_, index) => {
    const date = new Date(start)
    date.setUTCDate(start.getUTCDate() + index)
    return {
      iso: date.toISOString().slice(0, 10),
      number: date.getUTCDate(),
      outside: date.getUTCMonth() !== month,
    }
  })
}

export function monthTitle(year: number, month: number): string {
  return new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric', timeZone: 'UTC' })
    .format(new Date(Date.UTC(year, month, 1)))
}

export function shiftMonth(year: number, month: number, delta: number): { year: number, month: number } {
  const next = new Date(Date.UTC(year, month + delta, 1))
  return { year: next.getUTCFullYear(), month: next.getUTCMonth() }
}
