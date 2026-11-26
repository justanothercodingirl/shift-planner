import { useCallback, useState } from 'react'
import { assignEmployee, unassign } from '../api/assignments.js'
import useAuthedRequest from './useAuthedRequest.js'

// The assign dialog: which shift is open and the assign / remove actions.
// The shift is remembered by id and looked up in the freshly loaded roster, so
// the dialog shows the new list after every reload. Each action reloads the
// week and returns true when it worked; on failure it keeps the server's
// error, including the rule violations, for the dialog to show.
export default function useAssignment(shifts, reload) {
  const run = useAuthedRequest()
  const [shiftId, setShiftId] = useState(null)
  const [error, setError] = useState(null)

  const act = useCallback(
    async (call) => {
      setError(null)
      try {
        await run(call)
        reload()
        return true
      } catch (caught) {
        setError(caught)
        return false
      }
    },
    [run, reload],
  )

  return {
    shift: shifts.find((shift) => shift.id === shiftId) ?? null,
    error,
    open: (shift) => {
      setError(null)
      setShiftId(shift.id)
    },
    close: () => setShiftId(null),
    assign: (employeeId) =>
      act((token) => assignEmployee(token, shiftId, employeeId)),
    remove: (assignment) => act((token) => unassign(token, assignment.id)),
  }
}
