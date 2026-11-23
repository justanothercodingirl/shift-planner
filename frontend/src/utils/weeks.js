// Week and date helpers. Dates are plain 'YYYY-MM-DD' strings and all the maths
// runs in UTC, so a time zone can never move a date to the neighbouring day.

const DAY_MS = 24 * 60 * 60 * 1000

function parse(iso) {
  return new Date(`${iso}T00:00:00Z`)
}

function format(date) {
  return date.toISOString().slice(0, 10)
}

export function addDays(iso, days) {
  return format(new Date(parse(iso).getTime() + days * DAY_MS))
}

// The Monday of the week that contains the given date.
export function mondayOf(iso) {
  const daysSinceMonday = (parse(iso).getUTCDay() + 6) % 7
  return addDays(iso, -daysSinceMonday)
}

// Today's date in the browser's own time zone, as 'YYYY-MM-DD'.
export function todayIso() {
  const now = new Date()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${now.getFullYear()}-${month}-${day}`
}

// The seven dates of the week, Monday first.
export function weekDays(mondayIso) {
  return Array.from({ length: 7 }, (_, index) => addDays(mondayIso, index))
}

function label(iso, options) {
  return parse(iso).toLocaleDateString('en-GB', { ...options, timeZone: 'UTC' })
}

// "Mon 23 Nov"
export function formatDay(iso) {
  return label(iso, { weekday: 'short', day: 'numeric', month: 'short' })
}

// "23 Nov – 29 Nov 2026"
export function formatWeek(mondayIso) {
  const sunday = addDays(mondayIso, 6)
  const from = label(mondayIso, { day: 'numeric', month: 'short' })
  const to = label(sunday, { day: 'numeric', month: 'short', year: 'numeric' })
  return `${from} – ${to}`
}
