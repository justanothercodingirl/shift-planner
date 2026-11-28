import { useEffect, useState } from 'react'
import { listSwapCandidates } from '../api/swaps.js'
import useAuthedRequest from './useAuthedRequest.js'

// The colleagues who could take over one of my shifts, loaded each time the
// swap dialog is opened for it (openKey changes at every opening, so a list
// from an earlier opening is never shown). assignmentId is null while closed.
export default function useSwapCandidates(assignmentId, openKey) {
  const run = useAuthedRequest()
  // The answer remembers which opening it belongs to, so "loading" is derived.
  const [loaded, setLoaded] = useState({ openKey: null })

  useEffect(() => {
    if (assignmentId === null) return undefined
    let ignore = false
    run((token) => listSwapCandidates(token, assignmentId))
      .then((candidates) => {
        if (!ignore) setLoaded({ openKey, candidates, error: null })
      })
      .catch((error) => {
        if (!ignore) setLoaded({ openKey, candidates: [], error })
      })
    return () => {
      ignore = true
    }
  }, [run, assignmentId, openKey])

  const ready = assignmentId !== null && loaded.openKey === openKey
  return {
    candidates: ready ? loaded.candidates : [],
    error: ready ? loaded.error : null,
    loading: assignmentId !== null && !ready,
  }
}
