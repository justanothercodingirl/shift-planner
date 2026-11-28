// An end time earlier than the start time means the shift ends the next day.
// 'HH:MM' strings compare correctly as plain text.
export function isOvernight(shift) {
  return shift.endTime < shift.startTime
}

// Groups shifts that are already in time order by their roster week, keeping the order:
// [{ weekStart: '2026-11-30', shifts: [...] }, ...]
export function groupByWeek(shifts) {
  const weeks = []
  for (const shift of shifts) {
    const last = weeks[weeks.length - 1]
    if (last && last.weekStart === shift.weekStart) last.shifts.push(shift)
    else weeks.push({ weekStart: shift.weekStart, shifts: [shift] })
  }
  return weeks
}
