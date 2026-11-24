import { api, apiDelete, apiPost } from '@/api'

export interface SettingsFlow {
  flowId: string
  csrfToken: string
  email: string | null
  state?: string
}

export interface IdentitySession {
  id: string
  device: string
  browser: string
  kind: 'laptop' | 'desktop' | 'phone'
  location: string
  ip: string
  userAgent: string
  lastActive: string
  expiresAt?: string
  current: boolean
}

export interface IdentityError extends Error {
  reauth?: boolean
  redirectBrowserTo?: string
}

export async function fetchSettingsFlow(returnTo?: string): Promise<SettingsFlow> {
  const query = returnTo ? `?return_to=${encodeURIComponent(returnTo)}` : ''
  const response = await api(`/identity/settings-flow${query}`)
  const data = await response.json() as SettingsFlow & { error?: string }
  if (!response.ok) {
    throw new Error(data.error ?? `Settings flow failed: ${response.status}`)
  }
  return data
}

export async function updateIdentitySettings(body: {
  flowId: string
  csrfToken: string
  method: 'profile' | 'password'
  email?: string
  password?: string
}): Promise<SettingsFlow> {
  const response = await api('/identity/settings', {
    method: 'POST',
    body: JSON.stringify(body),
  })
  const data = await response.json() as SettingsFlow & {
    error?: string
    reauth?: boolean
    redirectBrowserTo?: string
  }
  if (!response.ok) {
    const err = new Error(data.error ?? `Update failed: ${response.status}`) as IdentityError
    if (data.reauth) {
      err.reauth = true
      err.redirectBrowserTo = data.redirectBrowserTo
    }
    throw err
  }
  return data
}

export async function listIdentitySessions(): Promise<IdentitySession[]> {
  const response = await api('/identity/sessions')
  const data = await response.json() as { items?: IdentitySession[]; error?: string }
  if (!response.ok) {
    throw new Error(data.error ?? `Sessions failed: ${response.status}`)
  }
  return data.items ?? []
}

export async function revokeIdentitySession(id: string): Promise<void> {
  await apiDelete(`/identity/sessions/${encodeURIComponent(id)}`)
}

export async function revokeOtherIdentitySessions(): Promise<number> {
  const data = await apiDelete<{ count: number }>('/identity/sessions/others')
  return data.count
}

export function sessionWhen(value: string): string {
  if (!value) return '—'
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}