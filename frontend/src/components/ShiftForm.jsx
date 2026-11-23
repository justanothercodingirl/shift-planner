import { useState } from 'react'
import { POSITIONS } from '../constants/options.js'
import useShiftForm from '../hooks/useShiftForm.js'
import { addDays } from '../utils/weeks.js'
import ErrorMessage from './ErrorMessage.jsx'
import SelectField from './SelectField.jsx'
import TextField from './TextField.jsx'

// Adds a shift (shift = null) or edits one. onSubmit(payload) and onDelete()
// run the request; the date is limited to the roster's week.
export default function ShiftForm({
  shift,
  date,
  weekStart,
  error,
  onSubmit,
  onDelete,
  onCancel,
}) {
  const form = useShiftForm(shift, date)
  const [busy, setBusy] = useState(false)

  async function withBusy(action) {
    setBusy(true)
    try {
      await action()
    } finally {
      setBusy(false)
    }
  }

  function handleSubmit(event) {
    event.preventDefault()
    withBusy(() => onSubmit(form.payload()))
  }

  function handleDelete() {
    if (window.confirm('Delete this shift?')) withBusy(onDelete)
  }

  return (
    <form className="shift-form" onSubmit={handleSubmit}>
      <h2>{shift ? 'Edit shift' : 'Add shift'}</h2>
      <ErrorMessage error={error} />
      <TextField
        label="Date"
        type="date"
        value={form.values.date}
        onChange={form.setField('date')}
        min={weekStart}
        max={addDays(weekStart, 6)}
      />
      <div className="form-row">
        <TextField
          label="Start"
          type="time"
          value={form.values.startTime}
          onChange={form.setField('startTime')}
        />
        <TextField
          label="End"
          type="time"
          value={form.values.endTime}
          onChange={form.setField('endTime')}
        />
      </div>
      <p className="muted">
        An end time earlier than the start means the shift ends the next day.
      </p>
      <SelectField
        label="Position"
        options={POSITIONS}
        value={form.values.requiredPosition}
        onChange={form.setField('requiredPosition')}
      />
      <TextField
        label="People needed"
        type="number"
        min={1}
        value={form.values.headcount}
        onChange={form.setField('headcount')}
      />
      <div className="form-actions">
        <button type="submit" disabled={busy}>
          {busy ? 'Saving…' : 'Save'}
        </button>
        <button type="button" onClick={onCancel} disabled={busy}>
          Cancel
        </button>
        {shift && (
          <button type="button" onClick={handleDelete} disabled={busy}>
            Delete
          </button>
        )}
      </div>
    </form>
  )
}
