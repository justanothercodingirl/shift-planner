import { useState } from 'react'
import { AVAILABILITY_TYPES, REPEATS, WEEKDAYS } from '../constants/options.js'
import useAvailabilityForm from '../hooks/useAvailabilityForm.js'
import SelectField from './SelectField.jsx'
import TextField from './TextField.jsx'

// Adds an availability entry (entry = null) or edits one. onSubmit(payload)
// resolves to true when the server accepted it.
export default function AvailabilityForm({ entry, onSubmit, onCancel }) {
  const form = useAvailabilityForm(entry)
  const [saving, setSaving] = useState(false)

  async function handleSubmit(event) {
    event.preventDefault()
    setSaving(true)
    try {
      await onSubmit(form.payload())
    } finally {
      setSaving(false)
    }
  }

  return (
    <form className="panel" onSubmit={handleSubmit}>
      <h2>{entry ? 'Edit availability' : 'Add availability'}</h2>
      <SelectField
        label="Repeats"
        options={REPEATS}
        value={form.values.repeats}
        onChange={form.setField('repeats')}
      />
      {form.values.repeats === 'weekly' ? (
        <SelectField
          label="Weekday"
          options={WEEKDAYS}
          value={form.values.weekday}
          onChange={form.setField('weekday')}
        />
      ) : (
        <TextField
          label="Date"
          type="date"
          value={form.values.date}
          onChange={form.setField('date')}
        />
      )}
      <div className="form-row">
        <TextField
          label="From"
          type="time"
          value={form.values.startTime}
          onChange={form.setField('startTime')}
        />
        <TextField
          label="To"
          type="time"
          value={form.values.endTime}
          onChange={form.setField('endTime')}
        />
      </div>
      <p className="muted">
        A whole day is 00:00 to 23:59. The end must be later than the start.
      </p>
      <SelectField
        label="This means you"
        options={AVAILABILITY_TYPES}
        value={form.values.type}
        onChange={form.setField('type')}
      />
      <div className="form-actions">
        <button type="submit" disabled={saving}>
          {saving ? 'Saving…' : 'Save'}
        </button>
        <button type="button" onClick={onCancel} disabled={saving}>
          Cancel
        </button>
      </div>
    </form>
  )
}
