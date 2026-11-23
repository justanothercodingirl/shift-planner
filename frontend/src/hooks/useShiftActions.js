import { useCallback, useState } from 'react'
import { createShift, deleteShift, updateShift } from '../api/shifts.js'
import useAuthedRequest from './useAuthedRequest.js'

// Create, update and delete shifts of one roster. Each action reloads the week
// when it works and returns true; on failure it keeps the server's error so the
// dialog can show it, and returns false.
export default function useShiftActions(rosterId, reload) {
  const run = useAuthedRequest()
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
    error,
    clearError: () => setError(null),
    // shift is the shift being edited, or null when adding a new one
    save: (shift, payload) =>
      shift
        ? act((token) => updateShift(token, shift.id, payload))
        : act((token) => createShift(token, rosterId, payload)),
    remove: (shift) => act((token) => deleteShift(token, shift.id)),
  }
}
