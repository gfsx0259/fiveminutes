export const timeZoneIds = [
  'UTC',
  'Europe/London',
  'Europe/Lisbon',
  'Europe/Madrid',
  'Europe/Paris',
  'Europe/Berlin',
  'Europe/Rome',
  'Europe/Warsaw',
  'Europe/Kyiv',
  'Europe/Moscow',
  'Europe/Istanbul',
  'America/New_York',
  'America/Chicago',
  'America/Denver',
  'America/Los_Angeles',
  'America/Mexico_City',
  'America/Sao_Paulo',
  'Asia/Dubai',
  'Asia/Kolkata',
  'Asia/Bangkok',
  'Asia/Singapore',
  'Asia/Shanghai',
  'Asia/Tokyo',
  'Australia/Sydney',
  'Pacific/Auckland',
  'Africa/Johannesburg',
] as const

export function browserTimeZone(): string {
  try {
    const zone = Intl.DateTimeFormat().resolvedOptions().timeZone
    return zone && zone.length > 0 ? zone : 'UTC'
  } catch {
    return 'UTC'
  }
}

export function timeZoneLabel(id: string, now = new Date()): string {
  try {
    const offset = new Intl.DateTimeFormat('en-GB', {
      timeZone: id,
      timeZoneName: 'shortOffset',
    })
      .formatToParts(now)
      .find((part) => part.type === 'timeZoneName')?.value
    const name = id.replaceAll('_', ' ')
    return offset ? `${name} · ${offset}` : name
  } catch {
    return id
  }
}

export function timeZoneOptions(selected: string): { title: string; value: string }[] {
  const ids = selected && !timeZoneIds.includes(selected as (typeof timeZoneIds)[number])
    ? [selected, ...timeZoneIds]
    : [...timeZoneIds]
  return ids.map((id) => ({ title: timeZoneLabel(id), value: id }))
}
