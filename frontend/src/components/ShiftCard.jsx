import { labelOf, POSITIONS } from '../constants/options.js'
import { isOvernight } from '../utils/shifts.js'

// Clicking a shift card opens it for editing.
export default function ShiftCard({ shift, onSelect }) {
  const people =
    shift.headcount === 1 ? '1 person' : `${shift.headcount} people`

  return (
    <button
      type="button"
      className="shift-card"
      onClick={() => onSelect(shift)}
    >
      <strong>
        {shift.startTime}–{shift.endTime}
        {isOvernight(shift) && <span className="next-day"> +1 day</span>}
      </strong>
      <span>{labelOf(POSITIONS, shift.requiredPosition)}</span>
      <span className="muted">needs {people}</span>
    </button>
  )
}
