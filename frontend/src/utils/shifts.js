// An end time earlier than the start time means the shift ends the next day.
// 'HH:MM' strings compare correctly as plain text.
export function isOvernight(shift) {
  return shift.endTime < shift.startTime
}
