import { useState } from 'react'
import { CONTRACT_TYPES, POSITIONS } from '../constants/options.js'
import useEmployeeForm from '../hooks/useEmployeeForm.js'
import SelectField from './SelectField.jsx'
import TextField from './TextField.jsx'

// Adds an employee (employee = null) or edits one. onSubmit(payload) resolves
// to true when the server accepted it.
export default function EmployeeForm({ employee, onSubmit, onCancel }) {
  const form = useEmployeeForm(employee)
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
    <form className="panel employee-form" onSubmit={handleSubmit}>
      <h2>{form.editing ? `Edit ${employee.name}` : 'Add employee'}</h2>
      {form.editing ? (
        <p className="muted">{employee.email}</p>
      ) : (
        <>
          <TextField
            label="Email"
            type="email"
            value={form.values.email}
            onChange={form.setField('email')}
            maxLength={180}
          />
          <TextField
            label="Initial password (8 or more characters)"
            type="password"
            value={form.values.password}
            onChange={form.setField('password')}
            minLength={8}
            maxLength={72}
            autoComplete="new-password"
          />
        </>
      )}
      <TextField
        label="Name"
        value={form.values.name}
        onChange={form.setField('name')}
        maxLength={100}
      />
      <SelectField
        label="Position"
        options={POSITIONS}
        value={form.values.position}
        onChange={form.setField('position')}
      />
      <SelectField
        label="Contract type"
        options={CONTRACT_TYPES}
        value={form.values.contractType}
        onChange={form.setContractType}
      />
      <TextField
        label="Max weekly hours"
        type="number"
        min={1}
        value={form.values.maxWeeklyHours}
        onChange={form.setHours}
      />
      <div className="form-actions">
        <button type="submit" disabled={saving}>
          {saving ? 'Saving…' : 'Save'}
        </button>
        <button type="button" onClick={onCancel}>
          Cancel
        </button>
      </div>
    </form>
  )
}
