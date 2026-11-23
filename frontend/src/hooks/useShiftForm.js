import { useState } from 'react'

const NEW_SHIFT = {
  startTime: '09:00',
  endTime: '17:00',
  requiredPosition: 'barista',
  headcount: '1',
}

// Field values for the shift form. Pass the shift to edit, or null with the
// date to start from when adding one.
export default function useShiftForm(shift, date) {
  const [values, setValues] = useState(
    shift
      ? {
          date: shift.date,
          startTime: shift.startTime,
          endTime: shift.endTime,
          requiredPosition: shift.requiredPosition,
          headcount: String(shift.headcount),
        }
      : { ...NEW_SHIFT, date },
  )

  const setField = (field) => (value) =>
    setValues((current) => ({ ...current, [field]: value }))

  // The JSON the API expects.
  function payload() {
    return { ...values, headcount: Number(values.headcount) }
  }

  return { values, setField, payload }
}
