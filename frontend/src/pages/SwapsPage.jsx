import ErrorMessage from '../components/ErrorMessage.jsx'
import SwapRequestList from '../components/SwapRequestList.jsx'
import useAuth from '../hooks/useAuth.js'
import useSwapActions from '../hooks/useSwapActions.js'
import useSwapRequests from '../hooks/useSwapRequests.js'

// Swap requests. Employees see the ones asked of them (to accept or decline)
// and the ones they made (to withdraw); the manager sees the ones waiting for
// approval and everything else. Rule violations from the server show on top.
export default function SwapsPage() {
  const { user } = useAuth()
  const swaps = useSwapRequests()
  const actions = useSwapActions(swaps.reload)
  const isManager = user?.role === 'manager'
  const viewer = { employeeId: user?.employeeId, isManager }
  const shared = {
    viewer,
    busy: actions.busy,
    onAction: (action, request) => actions.answer(request.id, action),
  }

  const waiting = swaps.requests.filter((r) => r.status === 'accepted')
  const others = swaps.requests.filter((r) => r.status !== 'accepted')
  const forMe = swaps.requests.filter((r) => r.target.id === viewer.employeeId)
  const mine = swaps.requests.filter(
    (r) => r.requester.id === viewer.employeeId,
  )

  return (
    <main className="page page-wide">
      <h1>Swaps</h1>
      <ErrorMessage error={actions.error ?? swaps.error} />
      {swaps.loading ? (
        <p>Loading…</p>
      ) : isManager ? (
        <>
          <SwapRequestList
            title="Waiting for your approval"
            requests={waiting}
            emptyText="Nothing is waiting for your approval."
            {...shared}
          />
          <SwapRequestList
            title="All other requests"
            requests={others}
            emptyText="No other requests yet."
            {...shared}
          />
        </>
      ) : (
        <>
          <SwapRequestList
            title="Requests for me"
            requests={forMe}
            emptyText="Nobody has asked you to take over a shift."
            {...shared}
          />
          <SwapRequestList
            title="My requests"
            requests={mine}
            emptyText="You have not asked anyone yet. Use “Request swap” on My shifts."
            {...shared}
          />
        </>
      )}
    </main>
  )
}
