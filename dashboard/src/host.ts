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

export function logout(hostname: string): void {
  const returnTo = `${window.location.origin}/`
  window.location.href = `https://id.${apexOf(hostname)}/logout?return_to=${encodeURIComponent(returnTo)}`
}

/** Dashboard host for a Rider role (partner → seller). */
export function cabinetOrigin(hostname: string, role: string): string {
  const apex = apexOf(hostname)
  const prefix = role === 'partner' ? 'seller' : role === 'buyer' ? 'buyer' : 'admin'
  return `https://${prefix}.${apex}`
}
