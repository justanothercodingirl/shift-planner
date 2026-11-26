import { labelOf, POSITIONS } from '../constants/options.js'
import { isOvernight } from '../utils/shifts.js'

// Clicking the shift opens it for editing; the Assign button opens the assign dialog.
export default function ShiftCard({ shift, onSelect, onAssign }) {
  const names = shift.assignments.map((a) => a.employeeName).join(', ')

  return (
    <div className="shift-card">
      <button
        type="button"
        className="shift-card-edit"
        onClick={() => onSelect(shift)}
      >
        <strong>
          {shift.startTime}–{shift.endTime}
          {isOvernight(shift) && <span className="next-day"> +1 day</span>}
        </strong>
        <span>{labelOf(POSITIONS, shift.requiredPosition)}</span>
      </button>
      <span className="muted">
        {shift.assignments.length} of {shift.headcount} assigned
      </span>
      {names && <span className="assigned-names">{names}</span>}
      <button
        type="button"
        className="assign-button"
        onClick={() => onAssign(shift)}
      >
        Assign
      </button>
    </div>
  )
}
