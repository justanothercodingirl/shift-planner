import { useState } from 'react'
import { describePositions } from '../utils/positions.js'
import SelectField from './SelectField.jsx'

// Pick someone and press Assign. onAssign(employeeId) runs the request. The
// list offers every active employee who is not on the shift yet; whether the
// person really fits is decided by the server's rules after pressing Assign.
export default function AssignForm({ employees, busy, onAssign }) {
  const [choice, setChoice] = useState('')

  if (employees.length === 0) {
    return <p className="muted">Everyone active is already on this shift.</p>
  }

  const options = employees.map((employee) => ({
    value: String(employee.id),
    label: `${employee.name} (${describePositions(employee)})`,
  }))
  // Nothing chosen yet, or the chosen person was just assigned: use the first one left.
  const selected = options.some((option) => option.value === choice)
    ? choice
    : options[0].value

  function handleSubmit(event) {
    event.preventDefault()
    onAssign(Number(selected))
  }

  return (
    <form className="assign-form" onSubmit={handleSubmit}>
      <SelectField
        label="Assign someone"
        options={options}
        value={selected}
        onChange={setChoice}
      />
      <button type="submit" disabled={busy}>
        {busy ? 'Assigning…' : 'Assign'}
      </button>
    </form>
  )
}
