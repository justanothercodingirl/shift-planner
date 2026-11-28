import { labelOf, POSITIONS } from '../constants/options.js'
import { isOvernight } from '../utils/shifts.js'
import { formatDay } from '../utils/weeks.js'

// One of the employee's own shifts: day, time (overnight marked) and position.
export default function MyShiftRow({ shift }) {
  return (
    <tr>
      <td>{formatDay(shift.date)}</td>
      <td>
        {shift.startTime}–{shift.endTime}
        {isOvernight(shift) && <span className="next-day"> +1 day</span>}
      </td>
      <td>{labelOf(POSITIONS, shift.requiredPosition)}</td>
    </tr>
  )
}
