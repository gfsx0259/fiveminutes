import { apiOrigin } from '@/host'

export async function api(path: string, init: RequestInit = {}): Promise<Response> {
  const headers = new Headers(init.headers)
  headers.set('Accept', 'application/json')
  if (init.body !== undefined && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json')
  }

  const response = await fetch(`${apiOrigin(window.location.hostname)}${path}`, {
    ...init,
    credentials: 'include',
    headers,
  })
  if (response.status === 403) {
    window.dispatchEvent(new Event(ACCESS_CHANGED_EVENT))
  }

  return response
}

export const ACCESS_CHANGED_EVENT = 'rider:access-changed'

async function apiSend<T>(method: string, path: string, body: unknown): Promise<T> {
  const response = await api(path, { method, body: JSON.stringify(body) })
  const data = await response.json() as T & { error?: string }
  if (!response.ok) {
    throw new Error(typeof data.error === 'string' ? data.error : `Request failed: ${response.status}`)
  }

  return data
}

export async function apiPost<T>(path: string, body: unknown): Promise<T> {
  return apiSend<T>('POST', path, body)
}

export async function apiPut<T>(path: string, body: unknown): Promise<T> {
  return apiSend<T>('PUT', path, body)
}

export async function apiDelete<T>(path: string): Promise<T> {
  const response = await api(path, { method: 'DELETE' })
  const data = await response.json() as T & { error?: string }
  if (!response.ok) {
    throw new Error(typeof data.error === 'string' ? data.error : `Request failed: ${response.status}`)
  }

  return data
}
