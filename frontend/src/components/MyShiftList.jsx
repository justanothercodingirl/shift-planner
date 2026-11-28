import { groupByWeek } from '../utils/shifts.js'
import { openSwapFor } from '../utils/swaps.js'
import { formatWeek } from '../utils/weeks.js'
import MyShiftRow from './MyShiftRow.jsx'

// The employee's upcoming shifts, one table per roster week. swapRequests are
// the employee's swap requests, so a shift with an open one shows its status.
export default function MyShiftList({
  shifts,
  swapRequests,
  employeeId,
  onRequestSwap,
}) {
  if (shifts.length === 0) {
    return (
      <p className="muted">
        You have no upcoming shifts. Shifts appear here once the manager has
        published a roster.
      </p>
    )
  }

  return groupByWeek(shifts).map((week) => (
    <section key={week.weekStart}>
      <h2>{formatWeek(week.weekStart)}</h2>
      <table className="data-table">
        <thead>
          <tr>
            <th>Day</th>
            <th>Time</th>
            <th>Position</th>
            <th />
          </tr>
        </thead>
        <tbody>
          {week.shifts.map((shift) => (
            <MyShiftRow
              key={shift.id}
              shift={shift}
              openSwap={openSwapFor(swapRequests, shift.id, employeeId)}
              onRequestSwap={onRequestSwap}
            />
          ))}
        </tbody>
      </table>
    </section>
  ))
}
