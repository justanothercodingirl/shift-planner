import { labelOf, POSITIONS } from '../constants/options.js'
import { isOvernight } from '../utils/shifts.js'
import { describeOpenSwap } from '../utils/swaps.js'
import { formatDay } from '../utils/weeks.js'
import Badge from './Badge.jsx'

// One of the employee's own shifts: day, time (overnight marked), position, and
// either the "Request swap" button or what the open swap request is waiting for.
export default function MyShiftRow({ shift, openSwap, onRequestSwap }) {
  return (
    <tr>
      <td>{formatDay(shift.date)}</td>
      <td>
        {shift.startTime}–{shift.endTime}
        {isOvernight(shift) && <span className="next-day"> +1 day</span>}
      </td>
      <td>{labelOf(POSITIONS, shift.requiredPosition)}</td>
      <td className="actions">
        {openSwap ? (
          <Badge>{describeOpenSwap(openSwap)}</Badge>
        ) : (
          <button type="button" onClick={() => onRequestSwap(shift)}>
            Request swap
          </button>
        )}
      </td>
    </tr>
  )
}
