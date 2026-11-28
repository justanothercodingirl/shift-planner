import { useEffect, useState } from 'react'
import { listColleagues } from '../api/colleagues.js'
import useAuthedRequest from './useAuthedRequest.js'

// The other active employees, to choose someone to take over a shift.
// loading is true until the list has arrived (or failed).
export default function useColleagues() {
  const run = useAuthedRequest()
  const [colleagues, setColleagues] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let ignore = false
    run((token) => listColleagues(token))
      .then((list) => {
        if (!ignore) setColleagues(list)
      })
      .catch(() => {
        // The dialog simply offers nobody; the swap request itself reports real problems.
      })
      .finally(() => {
        if (!ignore) setLoading(false)
      })
    return () => {
      ignore = true
    }
  }, [run])

  return { colleagues, loading }
}
