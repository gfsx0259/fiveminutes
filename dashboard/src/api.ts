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
