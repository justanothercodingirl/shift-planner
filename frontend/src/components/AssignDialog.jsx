import { useEffect, useRef, useState } from 'react'
import { labelOf, POSITIONS } from '../constants/options.js'
import { formatDay } from '../utils/weeks.js'
import AssignedList from './AssignedList.jsx'
import AssignForm from './AssignForm.jsx'
import ErrorMessage from './ErrorMessage.jsx'

// A modal window to put people on one shift, using the browser's built-in
// <dialog> element. shift is null while closed. Rule violations from the
// server (error.violations) are listed with their reasons by ErrorMessage.
export default function AssignDialog({
  shift,
  employees,
  error,
  onAssign,
  onRemove,
  onClose,
}) {
  const ref = useRef(null)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    const element = ref.current
    if (shift && !element.open) element.showModal()
    if (!shift && element.open) element.close()
  }, [shift])

  async function withBusy(action) {
    setBusy(true)
    try {
      await action()
    } finally {
      setBusy(false)
    }
  }

  const candidates = shift
    ? employees.filter(
        (employee) =>
          employee.active &&
          !shift.assignments.some((a) => a.employeeId === employee.id),
      )
    : []

  return (
    <dialog ref={ref} className="shift-dialog" onClose={onClose}>
      {shift && (
        <div className="assign-dialog">
          <h2>Assign people</h2>
          <p className="muted">
            {formatDay(shift.date)}, {shift.startTime}–{shift.endTime},{' '}
            {labelOf(POSITIONS, shift.requiredPosition)} ·{' '}
            {shift.assignments.length} of {shift.headcount} assigned
          </p>
          <ErrorMessage error={error} />
          <AssignedList
            assignments={shift.assignments}
            busy={busy}
            onRemove={(assignment) => withBusy(() => onRemove(assignment))}
          />
          <AssignForm
            employees={candidates}
            busy={busy}
            onAssign={(employeeId) => withBusy(() => onAssign(employeeId))}
          />
          <div className="form-actions">
            <button type="button" onClick={onClose}>
              Close
            </button>
          </div>
        </div>
      )}
    </dialog>
  )
}
