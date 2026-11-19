export function formatHm(h: number, m: number): string {
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
}

export function parseHm(value: string): { h: number; m: number } | null {
  const match = /^(\d{1,2}):(\d{2})$/.exec(value.trim())
  if (!match) {
    return null
  }
  const h = Number(match[1])
  const m = Number(match[2])
  if (!Number.isFinite(h) || !Number.isFinite(m) || h < 0 || h > 23 || m < 0 || m > 59) {
    return null
  }
  return { h, m }
}

/** Next wall-clock hour at :00 (e.g. 15:35 → 16:00; 15:00:00 → 15:00). */
export function nextFullHourLocal(now = new Date()): string {
  let h = now.getHours()
  const m = now.getMinutes()
  const s = now.getSeconds()
  if (m > 0 || s > 0) {
    h += 1
  }
  if (h > 23) {
    return '23:59'
  }
  return formatHm(h, 0)
}

export function defaultOpensForServiceDate(serviceDateIso: string, todayIso: string): string {
  if (serviceDateIso === todayIso) {
    return nextFullHourLocal()
  }
  return '00:00'
}