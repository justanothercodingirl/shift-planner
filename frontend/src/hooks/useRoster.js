import { useCallback, useEffect, useState } from 'react'
import { createRoster, getRoster, listRosters } from '../api/rosters.js'
import useAuthedRequest from './useAuthedRequest.js'

// Loads the roster of one week (identified by its Monday) together with its shifts.
// status: 'loading' | 'ready' | 'missing' (no roster yet) | 'error'
export default function useRoster(weekStart) {
  const run = useAuthedRequest()
  // The loaded data remembers which week it belongs to, so "loading" can be
  // derived: it is simply "the data is for another week".
  const [loaded, setLoaded] = useState({ weekStart: null })
  const [actionError, setActionError] = useState(null)
  const [creating, setCreating] = useState(false)
  const [version, setVersion] = useState(0)

  const reload = useCallback(() => setVersion((current) => current + 1), [])

  useEffect(() => {
    let ignore = false
    run(async (token) => {
      const rosters = await listRosters(token)
      const found = rosters.find((roster) => roster.weekStart === weekStart)
      return found ? getRoster(token, found.id) : null
    })
      .then((roster) => {
        if (!ignore) setLoaded({ weekStart, roster, error: null })
      })
      .catch((error) => {
        if (!ignore) setLoaded({ weekStart, roster: null, error })
      })
    return () => {
      ignore = true
    }
  }, [run, weekStart, version])

  const createForThisWeek = useCallback(async () => {
    setActionError(null)
    setCreating(true)
    try {
      await run((token) => createRoster(token, weekStart))
      reload()
    } catch (error) {
      setActionError(error)
    } finally {
      setCreating(false)
    }
  }, [run, weekStart, reload])

  let status = 'loading'
  if (loaded.weekStart === weekStart) {
    if (loaded.error) status = 'error'
    else status = loaded.roster ? 'ready' : 'missing'
  }

  return {
    status,
    roster: loaded.weekStart === weekStart ? loaded.roster : null,
    error:
      actionError ?? (loaded.weekStart === weekStart ? loaded.error : null),
    creating,
    createRoster: createForThisWeek,
    reload,
  }
}
