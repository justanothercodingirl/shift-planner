import { useEffect, useRef, useState } from 'react'
import { describePositions } from '../utils/positions.js'
import { describeShift } from '../utils/swaps.js'
import ErrorMessage from './ErrorMessage.jsx'
import SelectField from './SelectField.jsx'

// A modal window to ask a colleague to take over one of my shifts, using the
// browser's built-in <dialog>. shift is null while closed. It lists only the
// colleagues who could take the shift (candidates, from the server); if asking
// is refused outright (the shift started, a request is open) the server's
// reason is shown instead. onSend(colleagueId) resolves to true when it worked.
export default function SwapRequestDialog({
  shift,
  candidates,
  loadingCandidates,
  candidatesError,
  error,
  busy,
  onSend,
  onClose,
}) {
  const ref = useRef(null)
  const [choice, setChoice] = useState('')

  useEffect(() => {
    const element = ref.current
    if (shift && !element.open) element.showModal()
    if (!shift && element.open) element.close()
  }, [shift])

  const options = candidates.map((colleague) => ({
    value: String(colleague.id),
    label: `${colleague.name} (${describePositions(colleague)})`,
  }))
  // Nothing chosen yet: use the first colleague.
  const selected = options.some((option) => option.value === choice)
    ? choice
    : (options[0]?.value ?? '')

  function handleSubmit(event) {
    event.preventDefault()
    if (selected) onSend(Number(selected))
  }

  return (
    <dialog ref={ref} className="shift-dialog" onClose={onClose}>
      {shift && (
        <form className="assign-dialog" onSubmit={handleSubmit}>
          <h2>Request a swap</h2>
          <p className="muted">
            Ask a colleague to take over {describeShift(shift)}. They accept or
            decline, then the manager approves.
          </p>
          <ErrorMessage error={candidatesError ?? error} />
          {loadingCandidates && <p className="muted">Loading colleagues…</p>}
          {!loadingCandidates && !candidatesError && options.length === 0 && (
            <p className="muted">
              No colleague can take this shift right now: they work another
              position, have a shift that clashes or is too close, would go over
              their weekly hours, or are unavailable.
            </p>
          )}
          {options.length > 0 && (
            <>
              <SelectField
                label="Colleague"
                options={options}
                value={selected}
                onChange={setChoice}
              />
              <p className="muted">
                Only colleagues who can take this shift are listed.
              </p>
            </>
          )}
          <div className="form-actions">
            <button type="submit" disabled={busy || !selected}>
              {busy ? 'Sending…' : 'Send request'}
            </button>
            <button type="button" onClick={onClose}>
              Cancel
            </button>
          </div>
        </form>
      )}
    </dialog>
  )
}
