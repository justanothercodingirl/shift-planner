import { groupByWeek } from '../utils/shifts.js'
import { formatWeek } from '../utils/weeks.js'
import MyShiftRow from './MyShiftRow.jsx'

// The employee's upcoming shifts, one table per roster week.
export default function MyShiftList({ shifts }) {
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
          </tr>
        </thead>
        <tbody>
          {week.shifts.map((shift) => (
            <MyShiftRow key={shift.id} shift={shift} />
          ))}
        </tbody>
      </table>
    </section>
  ))
}
