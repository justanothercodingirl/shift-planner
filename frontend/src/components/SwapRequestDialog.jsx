import { useEffect, useRef, useState } from 'react'
import { describePositions } from '../utils/positions.js'
import { describeShift } from '../utils/swaps.js'
import ErrorMessage from './ErrorMessage.jsx'
import SelectField from './SelectField.jsx'

// A modal window to ask a colleague to take over one of my shifts, using the
// browser's built-in <dialog>. shift is null while closed. The server's answer
// to onSend(colleagueId) (resolving to true when it worked) closes the dialog.
export default function SwapRequestDialog({
  shift,
  colleagues,
  loadingColleagues,
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

  const options = colleagues.map((colleague) => ({
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
          <ErrorMessage error={error} />
          {loadingColleagues ? (
            <p className="muted">Loading colleagues…</p>
          ) : options.length === 0 ? (
            <p className="muted">There is nobody to ask.</p>
          ) : (
            <SelectField
              label="Colleague"
              options={options}
              value={selected}
              onChange={setChoice}
            />
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
