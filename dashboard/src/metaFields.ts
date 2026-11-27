export interface MetaRow {
  key: string
  value: string
}

export function metaFromRows(rows: MetaRow[]): Record<string, string> {
  const meta: Record<string, string> = {}
  for (const row of rows) {
    const key = row.key.trim()
    if (key === '') {
      continue
    }
    meta[key] = row.value.trim()
  }
  return meta
}

export function formatLeadMeta(meta: unknown): string {
  if (!meta || typeof meta !== 'object' || Array.isArray(meta)) {
    return '—'
  }
  const parts = Object.entries(meta as Record<string, unknown>)
    .filter((entry): entry is [string, string | number | boolean] => {
      const value = entry[1]
      return value !== null && value !== undefined && typeof value !== 'object'
    })
    .map(([key, value]) => `${key}: ${String(value)}`)
  return parts.length === 0 ? '—' : parts.join(' · ')
}
