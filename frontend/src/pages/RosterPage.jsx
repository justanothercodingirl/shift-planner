import { useState } from 'react'
import Badge from '../components/Badge.jsx'
import ErrorMessage from '../components/ErrorMessage.jsx'
import MissingRoster from '../components/MissingRoster.jsx'
import ShiftDialog from '../components/ShiftDialog.jsx'
import WeekGrid from '../components/WeekGrid.jsx'
import WeekNavigator from '../components/WeekNavigator.jsx'
import useRoster from '../hooks/useRoster.js'
import useShiftActions from '../hooks/useShiftActions.js'
import useShiftDialog from '../hooks/useShiftDialog.js'
import { addDays, mondayOf, todayIso } from '../utils/weeks.js'

export default function RosterPage() {
  const [weekStart, setWeekStart] = useState(() => mondayOf(todayIso()))
  const { status, roster, error, creating, createRoster, reload } =
    useRoster(weekStart)
  const shiftDialog = useShiftDialog()
  const actions = useShiftActions(roster?.id, reload)

  function handleAdd(date) {
    actions.clearError()
    shiftDialog.openAdd(date)
  }

  function handleSelect(shift) {
    actions.clearError()
    shiftDialog.openEdit(shift)
  }

  async function handleSave(payload) {
    if (await actions.save(shiftDialog.dialog.shift, payload)) {
      shiftDialog.close()
    }
  }

  async function handleDelete() {
    if (await actions.remove(shiftDialog.dialog.shift)) shiftDialog.close()
  }

  return (
    <main className="page page-roster">
      <div className="page-header">
        <h1>Roster</h1>
        {roster && (
          <Badge tone={roster.status === 'published' ? 'good' : 'neutral'}>
            {roster.status === 'published' ? 'Published' : 'Draft'}
          </Badge>
        )}
      </div>
      <WeekNavigator
        weekStart={weekStart}
        onPrevious={() => setWeekStart(addDays(weekStart, -7))}
        onNext={() => setWeekStart(addDays(weekStart, 7))}
        onToday={() => setWeekStart(mondayOf(todayIso()))}
      />
      <ErrorMessage error={error} />
      {status === 'loading' && <p>Loading…</p>}
      {status === 'missing' && (
        <MissingRoster
          weekStart={weekStart}
          onCreate={createRoster}
          creating={creating}
        />
      )}
      {status === 'ready' && (
        <WeekGrid
          weekStart={weekStart}
          shifts={roster.shifts}
          onAdd={handleAdd}
          onSelect={handleSelect}
        />
      )}
      <ShiftDialog
        dialog={shiftDialog.dialog}
        weekStart={weekStart}
        error={actions.error}
        onSave={handleSave}
        onDelete={handleDelete}
        onClose={shiftDialog.close}
      />
    </main>
  )
}
