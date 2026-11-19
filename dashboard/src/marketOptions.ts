export const trafficSources = [
  { title: 'Facebook', value: 'facebook' },
  { title: 'Google', value: 'google' },
  { title: 'TikTok', value: 'tiktok' },
]

export const genders = [
  { title: 'Male', value: 'm' },
  { title: 'Female', value: 'f' },
  { title: 'All', value: 'all' },
]

export const paymentModels = [
  { title: 'CPL', value: 'cpl' },
  { title: 'CPA', value: 'cpa' },
]

export const verticals = [
  { title: 'Gambling', value: 'gambling' },
  { title: 'Betting', value: 'betting' },
  { title: 'Crypto', value: 'crypto' },
  { title: 'Forex', value: 'forex' },
  { title: 'Dating', value: 'dating' },
  { title: 'Nutra', value: 'nutra' },
  { title: 'Finance', value: 'finance' },
  { title: 'Other', value: 'other' },
]

const verticalIconMap: Record<string, string> = {
  gambling: 'mdi-dice-5',
  betting: 'mdi-trophy-outline',
  crypto: 'mdi-bitcoin',
  forex: 'mdi-chart-bar',
  dating: 'mdi-heart-outline',
  nutra: 'mdi-leaf',
  finance: 'mdi-database-outline',
  other: 'mdi-dots-horizontal',
}

const verticalColorMap: Record<string, string> = {
  gambling: '#e53935',
  betting: '#43a047',
  crypto: '#fb8c00',
  forex: '#1e88e5',
  dating: '#ec407a',
  nutra: '#43a047',
  finance: '#00897b',
  other: '#5c6078',
}

const trafficIconMap: Record<string, string> = {
  facebook: 'mdi-facebook',
  google: 'mdi-google',
  tiktok: 'mdi-music-note',
}

const trafficColorMap: Record<string, string> = {
  facebook: '#1877f2',
  google: '#ea4335',
  tiktok: '#111111',
}

export function trafficLabel(value: string): string {
  return trafficSources.find((item) => item.value === value)?.title ?? value
}

export function genderLabel(value: string): string {
  if (!value) return '—'
  return genders.find((item) => item.value === value)?.title ?? value
}

export function modelLabel(value: string): string {
  return paymentModels.find((item) => item.value === value)?.title ?? value
}

export function verticalLabel(value: string): string {
  return verticals.find((item) => item.value === value)?.title ?? value
}

export function verticalIcon(value: string): string {
  return verticalIconMap[value] ?? 'mdi-dots-horizontal'
}

export function verticalColor(value: string): string {
  return verticalColorMap[value] ?? '#5b45e8'
}

export function trafficIcon(value: string): string {
  return trafficIconMap[value] ?? 'mdi-bullhorn-outline'
}

export function trafficColor(value: string): string {
  return trafficColorMap[value] ?? '#5b45e8'
}
