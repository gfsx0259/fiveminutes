export function apexOf(hostname: string): string {
  const parts = hostname.split('.')
  return parts.slice(-2).join('.')
}

export function apiOrigin(hostname: string): string {
  return `https://api.${apexOf(hostname)}`
}

export function loginUrl(hostname: string, returnTo: string): string {
  return `https://id.${apexOf(hostname)}/auth/login?return_to=${encodeURIComponent(returnTo)}`
}

/** Kratos may return docker-internal hosts when the API talks to Kratos server-side. */
export function identityReauthUrl(hostname: string, redirectBrowserTo: string): string {
  const publicId = `https://id.${apexOf(hostname)}`

  function isInternalKratosHost(host: string): boolean {
    const h = host.toLowerCase()
    return h.includes('kratos') || h === 'localhost' || h.endsWith('.local')
  }

  function rewriteNested(url: string): string {
    try {
      const nested = new URL(url)
      if (!isInternalKratosHost(nested.hostname)) {
        return url
      }
      return `${publicId}${nested.pathname}${nested.search}${nested.hash}`
    } catch {
      return url
    }
  }

  try {
    const url = new URL(redirectBrowserTo)
    if (!isInternalKratosHost(url.hostname)) {
      return redirectBrowserTo
    }
    const params = new URLSearchParams(url.search)
    const returnTo = params.get('return_to')
    if (returnTo) {
      params.set('return_to', rewriteNested(returnTo))
    }
    const query = params.toString()
    return `${publicId}${url.pathname}${query ? `?${query}` : ''}${url.hash}`
  } catch {
    return loginUrl(hostname, `${window.location.origin}/account`)
  }
}

export function logout(hostname: string): void {
  const returnTo = `${window.location.origin}/`
  window.location.href = `https://id.${apexOf(hostname)}/logout?return_to=${encodeURIComponent(returnTo)}`
}

/** Dashboard host for a Rider role. */
export function cabinetOrigin(hostname: string, role: string): string {
  const apex = apexOf(hostname)
  const prefix = role === 'partner' ? 'partner' : role === 'buyer' ? 'buyer' : 'admin'
  return `https://${prefix}.${apex}`
}
