import { apiOrigin } from '@/host'

export async function api(path: string, init: RequestInit = {}): Promise<Response> {
  const headers = new Headers(init.headers)
  headers.set('Accept', 'application/json')
  if (init.body !== undefined && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json')
  }

  return fetch(`${apiOrigin(window.location.hostname)}${path}`, {
    ...init,
    credentials: 'include',
    headers,
  })
}

export async function apiPost<T>(path: string, body: unknown): Promise<T> {
  const response = await api(path, { method: 'POST', body: JSON.stringify(body) })
  const data = await response.json() as T & { error?: string }
  if (!response.ok) {
    throw new Error(typeof data.error === 'string' ? data.error : `Request failed: ${response.status}`)
  }

  return data
}

export async function apiDelete<T>(path: string): Promise<T> {
  const response = await api(path, { method: 'DELETE' })
  const data = await response.json() as T & { error?: string }
  if (!response.ok) {
    throw new Error(typeof data.error === 'string' ? data.error : `Request failed: ${response.status}`)
  }

  return data
}
