import { useCallback, useEffect, useState } from 'react'
import { listSwapRequests } from '../api/swaps.js'
import useAuthedRequest from './useAuthedRequest.js'

// The swap requests the signed-in user may see (employees: the ones they made
// or received; managers: all), newest first. reload() fetches them again.
export default function useSwapRequests() {
  const run = useAuthedRequest()
  const [requests, setRequests] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [version, setVersion] = useState(0)

  const reload = useCallback(() => setVersion((current) => current + 1), [])

  useEffect(() => {
    let ignore = false
    run((token) => listSwapRequests(token))
      .then((list) => {
        if (!ignore) setRequests(list)
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
  }, [run, version])

  return { requests, loading, error, reload }
}
