import { ACTION_LABELS, actionsFor, describeShift } from '../utils/swaps.js'
import SwapStatusBadge from './SwapStatusBadge.jsx'

// One swap request: the shift, who asks whom, the status, and the buttons the
// viewer may use (see actionsFor). onAction(action, request) runs the request.
export default function SwapRequestRow({ request, viewer, busy, onAction }) {
  function handle(action) {
    if (action === 'approve') {
      const message = `Hand ${describeShift(request.shift)} over to ${request.target.name}?`
      if (!window.confirm(message)) return
    }
    onAction(action, request)
  }

  return (
    <tr>
      <td>{describeShift(request.shift)}</td>
      <td>
        {request.requester.name} → {request.target.name}
      </td>
      <td>
        <SwapStatusBadge status={request.status} />
      </td>
      <td className="actions">
        {actionsFor(request, viewer).map((action) => (
          <button
            key={action}
            type="button"
            disabled={busy}
            onClick={() => handle(action)}
          >
            {ACTION_LABELS[action]}
          </button>
        ))}
      </td>
    </tr>
  )
}
