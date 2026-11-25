import { useState } from 'react'

const NEW_ENTRY = {
  repeats: 'weekly',
  weekday: 'monday',
  date: '',
  startTime: '08:00',
  endTime: '16:00',
  type: 'unavailable',
}

// Field values for the availability form. Pass the entry to edit, or null to add one.
export default function useAvailabilityForm(entry) {
  const [values, setValues] = useState(
    entry
      ? {
          repeats: entry.weekday ? 'weekly' : 'date',
          weekday: entry.weekday ?? NEW_ENTRY.weekday,
          date: entry.date ?? '',
          startTime: entry.startTime,
          endTime: entry.endTime,
          type: entry.type,
        }
      : NEW_ENTRY,
  )

  const setField = (field) => (value) =>
    setValues((current) => ({ ...current, [field]: value }))

  // The JSON the API expects: exactly one of weekday and date.
  function payload() {
    const { repeats, weekday, date, startTime, endTime, type } = values
    const when = repeats === 'weekly' ? { weekday } : { date }
    return { ...when, startTime, endTime, type }
  }

  return { values, setField, payload }
}
