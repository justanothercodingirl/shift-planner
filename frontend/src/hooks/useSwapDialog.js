import { useState } from 'react'

// Which of my shifts the "Request swap" dialog is open for. The shift is kept
// by its assignment id and looked up in the loaded list, so it stays current.
export default function useSwapDialog(shifts) {
  const [assignmentId, setAssignmentId] = useState(null)

  return {
    shift: shifts.find((shift) => shift.id === assignmentId) ?? null,
    open: (shift) => setAssignmentId(shift.id),
    close: () => setAssignmentId(null),
  }
}
