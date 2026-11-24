export const TABLE_CELL_COPY_SKIP_KEYS = new Set([
  'actions',
  'orderActions',
  'data-table-select',
  'data-table-expand',
  'supplied',
  'stockFreshness',
  'dailyCap',
  'status',
  'outcome',
  'liveInject',
  'partnerDelivery',
  'clientDeposit',
  'approvalRate',
  'share',
])

const INTERACTIVE_SELECTOR = [
  '.copyable',
  '.ui-table-cell-actions',
  '.ui-cell-no-copy',
  '.v-btn',
  'button',
  '.v-menu',
  'a[href]',
  'input',
  'textarea',
  'select',
  '.breakdown-table__meter',
  '.stock-freshness-cell',
  '.stock-live-today-cell',
].join(', ')

export type TableCellCopyResolver = (
  key: string,
  item: Record<string, unknown>,
) => string | null | undefined

export function isTableCellCopyTarget(target: EventTarget | null): boolean {
  if (!(target instanceof Element)) {
    return false
  }
  return !target.closest(INTERACTIVE_SELECTOR)
}

export function copyTextFromExportCell(text: string): string | null {
  const trimmed = text.trim()
  if (!trimmed || trimmed === '—') {
    return null
  }
  return trimmed
}

export function tableCellCopyFromExport<T extends object>(
  exportCell: (key: string, item: T) => string,
): TableCellCopyResolver {
  return (key, item) => copyTextFromExportCell(exportCell(key, item as T))
}

export function resolveTableCellCopyText(
  key: string,
  item: Record<string, unknown>,
  resolve?: TableCellCopyResolver,
): string | null {
  const custom = resolve?.(key, item)
  if (custom !== undefined) {
    if (custom === null || custom === '') {
      return null
    }
    return custom
  }
  const raw = item[key]
  if (raw == null || raw === '') {
    return null
  }
  if (typeof raw === 'object') {
    return null
  }
  const text = String(raw).trim()
  if (!text || text === '—') {
    return null
  }
  return text
}