export interface ChartSeries {
  label: string
  color: string
  values: number[]
}

export interface TooltipRow {
  label: string
  value: string
  color?: string
}

export function niceMax(value: number): number {
  if (!(value > 0)) return 1
  const scale = 10 ** Math.floor(Math.log10(value))
  for (const step of [1, 2, 4, 6, 8, 10]) {
    if (step * scale >= value) return step * scale
  }
  return 10 * scale
}
