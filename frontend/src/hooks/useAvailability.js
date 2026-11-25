import { useCallback, useEffect, useState } from 'react'
import {
  createAvailability,
  deleteAvailability,
  listAvailabilities,
  updateAvailability,
} from '../api/availabilities.js'
import useAuthedRequest from './useAuthedRequest.js'

// The availability entries of one employee plus create / update / remove.
// Every action reloads the list. employeeId is null when the user has no
// employee profile; nothing is loaded then.
export default function useAvailability(employeeId) {
  const run = useAuthedRequest()
  const [entries, setEntries] = useState([])
  const [loading, setLoading] = useState(employeeId !== null)
  const [error, setError] = useState(null)
  const [version, setVersion] = useState(0)

  const reload = useCallback(() => setVersion((current) => current + 1), [])

  useEffect(() => {
    if (employeeId === null) return undefined
    let ignore = false
    run((token) => listAvailabilities(token, employeeId))
      .then((list) => {
        if (!ignore) setEntries(list)
      })
      .catch((caught) => {
        if (!ignore) setError(caught)
      })
      .finally(() => {
        if (!ignore) setLoading(false)
      })
    return () => {
      ignore = true
    }
  }, [run, employeeId, version])

  // Returns true when the action worked, so a form knows whether it can close.
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
    entries,
    loading,
    error,
    create: (entry) =>
      act((token) => createAvailability(token, employeeId, entry)),
    update: (id, entry) => act((token) => updateAvailability(token, id, entry)),
    remove: (id) => act((token) => deleteAvailability(token, id)),
  }
}
