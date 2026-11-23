/** Count non-empty filter fields for mobile filter panel badge. */
export function countFilledFilters(filters: object): number {
  return Object.values(filters).filter((value) => {
    if (Array.isArray(value)) {
      return value.length > 0
    }
    if (value === undefined || value === null) {
      return false
    }
    if (typeof value === 'string') {
      return value.trim() !== ''
    }
    return true
  }).length
}