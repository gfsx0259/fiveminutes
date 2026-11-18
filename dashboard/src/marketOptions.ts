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
