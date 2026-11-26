import { apiPost } from '@/api'
import { showErrorToast } from '@/composables/useToast'

export type ExportKind = 'leads' | 'orders' | 'reports' | 'transactions'

export async function allowExport(kind: ExportKind, rows: number): Promise<boolean> {
  try {
    await apiPost('/exports', { kind, rows })
    return true
  } catch (error) {
    showErrorToast(error instanceof Error ? error.message : 'Export is not allowed')
    return false
  }
}
