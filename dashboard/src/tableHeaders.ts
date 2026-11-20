export type RiderTableHeader = {
  title: string
  key: string
  sortable?: boolean
  align?: 'start' | 'center' | 'end'
  minWidth?: string | number
  width?: string | number
  headerProps?: Record<string, unknown>
  cellProps?: Record<string, unknown>
}

const STICKY_END = 'ui-col-sticky-end'
const PINNED_KEYS = new Set(['actions', 'orderActions'])
const PINNED_ACTION_FLOOR = 48
/** Fallback when a page does not set `minByKey.actions` / wide row toolbar. */
const PINNED_ACTION_DEFAULT = 268

function mergeClass(existing: unknown, extra: string): string {
  const parts: string[] = []
  if (typeof existing === 'string') {
    parts.push(existing)
  } else if (Array.isArray(existing)) {
    parts.push(...existing.map(String))
  }
  if (extra) {
    parts.push(extra)
  }
  return parts.filter(Boolean).join(' ')
}

/** Pixel min-widths + sticky actions column (LM-style wide tables). */
export function prepareTableHeaders<T extends RiderTableHeader>(
  headers: T[],
  minByKey: Record<string, number> = {},
  defaultMin = 76,
): T[] {
  return headers.map((column) => {
    const minWidth = column.minWidth ?? minByKey[column.key] ?? defaultMin
    if (!PINNED_KEYS.has(column.key)) {
      return { ...column, minWidth }
    }
    const explicitPin =
      column.minWidth != null || Object.prototype.hasOwnProperty.call(minByKey, column.key)
    const numericMin = typeof minWidth === 'number' ? minWidth : defaultMin
    const pinnedMin = explicitPin
      ? Math.max(numericMin, PINNED_ACTION_FLOOR)
      : (numericMin > 200 ? numericMin : PINNED_ACTION_DEFAULT)
    return {
      ...column,
      minWidth: pinnedMin,
      width: column.width ?? pinnedMin,
      headerProps: {
        ...column.headerProps,
        class: mergeClass(column.headerProps?.class, STICKY_END),
      },
      cellProps: {
        ...column.cellProps,
        class: mergeClass(column.cellProps?.class, STICKY_END),
      },
    }
  })
}