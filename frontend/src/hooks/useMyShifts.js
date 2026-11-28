import { useEffect, useState } from 'react'
import { listMyAssignments } from '../api/me.js'
import useAuthedRequest from './useAuthedRequest.js'

// The signed-in employee's own upcoming shifts, in time order.
export default function useMyShifts() {
  const run = useAuthedRequest()
  const [shifts, setShifts] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  useEffect(() => {
    let ignore = false
    run((token) => listMyAssignments(token))
      .then((list) => {
        if (!ignore) setShifts(list)
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
  }, [run])

  return { shifts, loading, error }
}
