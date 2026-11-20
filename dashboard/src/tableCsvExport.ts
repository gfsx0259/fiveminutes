import { downloadCsv } from '@/csv'

export type CsvColumn = { title: string; key: string }

const SKIP_KEYS = new Set(['actions', 'orderActions'])

export function cellToString(value: unknown): string {
  if (value === null || value === undefined) return ''
  if (typeof value === 'boolean') return value ? 'Yes' : 'No'
  if (typeof value === 'number' && Number.isFinite(value)) return String(value)
  return String(value)
}

/** Export visible table columns (skips action columns and blank headers). */
export function exportTableCsv<T extends object>(
  filename: string,
  columns: CsvColumn[],
  items: T[],
  valueOf?: (key: string, item: T) => string,
): void {
  const cols = columns.filter((c) => c.title.trim() !== '' && !SKIP_KEYS.has(c.key))
  if (cols.length === 0 || items.length === 0) return
  const header = cols.map((c) => c.title)
  const body = items.map((item) =>
    cols.map((c) => (valueOf ? valueOf(c.key, item) : cellToString((item as Record<string, unknown>)[c.key]))),
  )
  downloadCsv(filename, [header, ...body])
}