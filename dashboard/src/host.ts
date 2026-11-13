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
