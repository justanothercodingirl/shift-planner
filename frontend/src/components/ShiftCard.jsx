import { labelOf, POSITIONS } from '../constants/options.js'
import { isOvernight } from '../utils/shifts.js'

export default function ShiftCard({ shift }) {
  const people =
    shift.headcount === 1 ? '1 person' : `${shift.headcount} people`

  return (
    <div className="shift-card">
      <strong>
        {shift.startTime}–{shift.endTime}
        {isOvernight(shift) && <span className="next-day"> +1 day</span>}
      </strong>
      <span>{labelOf(POSITIONS, shift.requiredPosition)}</span>
      <span className="muted">needs {people}</span>
    </div>
  )
}
