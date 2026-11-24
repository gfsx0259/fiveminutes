export const APP_NAME = 'Rider'

/** Sidebar / browser tab label for a route path. */
export function routePageTitle(path: string, role?: string): string | null {
  const byPath: Record<string, string> = {
    '/': 'Home',
    '/dashboard': 'Dashboard',
    '/reports': 'Reports',
    '/stock': 'Buy leads',
    '/orders': role === 'partner' ? 'My orders' : 'Orders',
    '/leads': role === 'partner' ? 'My leads' : 'Leads',
    '/performance': 'Performance',
    '/presets': 'Presets',
    '/offers': 'Offers',
    '/caps': 'Caps',
    '/users': 'Users',
    '/account': 'User settings',
  }

  return byPath[path] ?? null
}

export function documentTitle(path: string, role?: string): string {
  const page = routePageTitle(path, role)
  return page ? `${APP_NAME} | ${page}` : APP_NAME
}