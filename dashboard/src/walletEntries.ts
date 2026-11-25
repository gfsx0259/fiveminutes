import type { Bucket } from '@/purchases'
import type { CabinetLead } from '@/stores/leads'
import type { OrderRow } from '@/stores/market'
import type { WalletTransaction } from '@/stores/wallet'

export type EntryType = 'deposit' | 'charge' | 'payout' | 'withdrawal'

export interface EntryTypeView {
  value: EntryType
  label: string
  plural: string
  icon: string
  incoming: boolean
  color: string
  note: string
}

export const entryTypes: EntryTypeView[] = [
  { value: 'deposit', label: 'Deposit', plural: 'Deposits', icon: 'mdi-arrow-bottom-left', incoming: true, color: '#2f9e5b', note: 'Funds added to the wallet' },
  { value: 'payout', label: 'Lead payout', plural: 'Lead payouts', icon: 'mdi-cash-plus', incoming: true, color: '#4f8fe8', note: 'Paid to you for delivered and approved leads' },
  { value: 'charge', label: 'Lead charge', plural: 'Lead charges', icon: 'mdi-cart-arrow-down', incoming: false, color: '#5b45e8', note: 'CPL leads are charged on delivery, CPA leads when they are sold to you' },
  { value: 'withdrawal', label: 'Withdrawal', plural: 'Withdrawals', icon: 'mdi-arrow-top-right', incoming: false, color: '#e0607e', note: 'Funds taken out of the wallet' },
]

export function entryTypeView(type: EntryType): EntryTypeView {
  return entryTypes.find((item) => item.value === type) ?? entryTypes[0]!
}

export function entryTypesFor(role: string | undefined): EntryTypeView[] {
  const shown: EntryType[] = role === 'buyer' ? ['payout', 'deposit', 'withdrawal'] : ['deposit', 'charge', 'withdrawal']
  return shown.map(entryTypeView)
}

export function entryTypeOf(transaction: WalletTransaction): EntryType {
  if (transaction.kind === 'partner_lead_charge') return 'charge'
  if (transaction.kind === 'buyer_lead_credit') return 'payout'
  return transaction.direction === 'credit' ? 'deposit' : 'withdrawal'
}

export interface WalletEntry {
  id: string
  at: number
  type: EntryType
  amount: number
  signed: number
  balanceAfter: number
  leadUuid: string
  orderId: string
  lead: CabinetLead | null
  country: string
  model: string
  stock: boolean
}

export function walletEntriesOf(transactions: WalletTransaction[], leads: CabinetLead[], orders: OrderRow[]): WalletEntry[] {
  const leadByUuid = new Map(leads.map((lead) => [lead.uuid, lead]))
  const orderById = new Map(orders.map((order) => [order.id, order]))
  return transactions
    .map((transaction) => {
      const type = entryTypeOf(transaction)
      const amount = transaction.amountCents / 100
      const lead = transaction.leadUuid ? leadByUuid.get(transaction.leadUuid) ?? null : null
      const orderId = transaction.orderId ?? lead?.orderId ?? ''
      const order = orderId ? orderById.get(orderId) : undefined
      return {
        id: transaction.id,
        at: Date.parse(transaction.createdAt),
        type,
        amount,
        signed: entryTypeView(type).incoming ? amount : -amount,
        balanceAfter: (transaction.balanceAfterCents ?? 0) / 100,
        leadUuid: transaction.leadUuid ?? '',
        orderId,
        lead,
        country: lead?.country || order?.geo || '',
        model: order?.paymentModel ?? '',
        stock: order?.stock ?? false,
      }
    })
    .sort((left, right) => right.at - left.at)
}

export function balanceAt(entries: WalletEntry[], at: number): number {
  return entries.find((entry) => entry.at < at)?.balanceAfter ?? 0
}

export interface FlowLine {
  type: EntryTypeView
  amount: number
  count: number
}

export interface WalletFlow {
  closing: number
  lines: FlowLine[]
}

export function walletFlowOf(entries: WalletEntry[], from: number, to: number, types: EntryTypeView[]): WalletFlow {
  const inPeriod = entries.filter((entry) => entry.at >= from && entry.at < to)
  return {
    closing: balanceAt(entries, to),
    lines: types.map((type) => {
      const list = inPeriod.filter((entry) => entry.type === type.value)
      return { type, amount: list.reduce((sum, entry) => sum + entry.amount, 0), count: list.length }
    }),
  }
}

export interface FlowBucket {
  bucket: Bucket
  totals: Record<EntryType, number>
  balance: number
}

export function flowBucketsOf(entries: WalletEntry[], buckets: Bucket[]): FlowBucket[] {
  return buckets.map((bucket) => {
    const totals: Record<EntryType, number> = { deposit: 0, charge: 0, payout: 0, withdrawal: 0 }
    for (const entry of entries) {
      if (entry.at >= bucket.from && entry.at < bucket.to) totals[entry.type] += entry.amount
    }
    return { bucket, totals, balance: balanceAt(entries, bucket.to) }
  })
}
