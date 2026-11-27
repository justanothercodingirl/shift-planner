import { useCallback, useState } from 'react'
import { publishRoster } from '../api/rosters.js'
import useAuthedRequest from './useAuthedRequest.js'

// Publishing one roster. After a successful publish the week is reloaded and
// the warnings from the server (understaffed shifts) are kept for a notice.
// The outcome remembers its roster, so a notice or error from one week never
// shows up on another week.
export default function usePublishRoster(rosterId, reload) {
  const run = useAuthedRequest()
  const [outcome, setOutcome] = useState(null)
  const [busy, setBusy] = useState(false)

  const publish = useCallback(async () => {
    setOutcome(null)
    setBusy(true)
    try {
      const roster = await run((token) => publishRoster(token, rosterId))
      setOutcome({ rosterId, warnings: roster.warnings ?? [], error: null })
      reload()
    } catch (error) {
      setOutcome({ rosterId, warnings: [], error })
    } finally {
      setBusy(false)
    }
  }, [run, rosterId, reload])

  const current = outcome?.rosterId === rosterId ? outcome : null

  return {
    publish,
    busy,
    warnings: current?.warnings ?? [],
    error: current?.error ?? null,
    dismissWarnings: () => setOutcome(null),
  }
}
