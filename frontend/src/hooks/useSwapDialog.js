import { useState } from 'react'

// Which of my shifts the "Request swap" dialog is open for. The shift is kept
// by its assignment id and looked up in the loaded list, so it stays current.
// openKey changes every time the dialog is opened, so data tied to one opening
// (the list of colleagues) is never reused for the next.
export default function useSwapDialog(shifts) {
  const [state, setState] = useState({ assignmentId: null, openKey: 0 })

  return {
    shift: shifts.find((shift) => shift.id === state.assignmentId) ?? null,
    openKey: state.openKey,
    open: (shift) =>
      setState((current) => ({
        assignmentId: shift.id,
        openKey: current.openKey + 1,
      })),
    close: () => setState((current) => ({ ...current, assignmentId: null })),
  }
}
