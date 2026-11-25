import { labelOf, WEEKDAYS } from '../constants/options.js'
import { formatDay } from './weeks.js'

// "Every Monday" for a weekly entry, "Wed 5 Feb 2031" for a one-off entry.
export function describeDay(entry) {
  if (entry.weekday) return `Every ${labelOf(WEEKDAYS, entry.weekday)}`
  return `${formatDay(entry.date)} ${entry.date.slice(0, 4)}`
}
