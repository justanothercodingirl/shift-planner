import ErrorMessage from '../components/ErrorMessage.jsx'
import MyShiftList from '../components/MyShiftList.jsx'
import SwapRequestDialog from '../components/SwapRequestDialog.jsx'
import useAuth from '../hooks/useAuth.js'
import useColleagues from '../hooks/useColleagues.js'
import useMyShifts from '../hooks/useMyShifts.js'
import useSwapActions from '../hooks/useSwapActions.js'
import useSwapDialog from '../hooks/useSwapDialog.js'
import useSwapRequests from '../hooks/useSwapRequests.js'

// The employee's own upcoming shifts from published rosters, each with a way to
// ask a colleague to take it over.
export default function MyShiftsPage() {
  const { user } = useAuth()
  const { shifts, loading, error } = useMyShifts()
  const swaps = useSwapRequests()
  const { colleagues, loading: loadingColleagues } = useColleagues()
  const actions = useSwapActions(swaps.reload)
  const dialog = useSwapDialog(shifts)

  function handleRequestSwap(shift) {
    actions.clearError()
    dialog.open(shift)
  }

  async function handleSend(colleagueId) {
    if (await actions.request(dialog.shift.id, colleagueId)) dialog.close()
  }

  return (
    <main className="page page-wide">
      <h1>My shifts</h1>
      <ErrorMessage error={error ?? swaps.error} />
      {loading ? (
        <p>Loading…</p>
      ) : (
        <MyShiftList
          shifts={shifts}
          swapRequests={swaps.requests}
          employeeId={user?.employeeId}
          onRequestSwap={handleRequestSwap}
        />
      )}
      <SwapRequestDialog
        shift={dialog.shift}
        colleagues={colleagues}
        loadingColleagues={loadingColleagues}
        error={actions.error}
        busy={actions.busy}
        onSend={handleSend}
        onClose={dialog.close}
      />
    </main>
  )
}
