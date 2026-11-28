import { useCallback, useState } from 'react'
import { requestSwap, swapAction } from '../api/swaps.js'
import useAuthedRequest from './useAuthedRequest.js'

// Ask for a swap, or accept / decline / cancel / approve / reject one. Each action
// reloads the list when it works and returns true; on failure it keeps the
// server's error (including rule violations) for the screen and returns false.
export default function useSwapActions(reload) {
  const run = useAuthedRequest()
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  const act = useCallback(
    async (call) => {
      setError(null)
      setBusy(true)
      try {
        await run(call)
        reload()
        return true
      } catch (caught) {
        setError(caught)
        return false
      } finally {
        setBusy(false)
      }
    },
    [run, reload],
  )

  return {
    error,
    busy,
    clearError: () => setError(null),
    request: (assignmentId, targetEmployeeId) =>
      act((token) => requestSwap(token, assignmentId, targetEmployeeId)),
    answer: (id, action) => act((token) => swapAction(token, id, action)),
  }
}
