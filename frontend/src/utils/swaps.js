import { labelOf, POSITIONS } from '../constants/options.js'
import { formatDay } from './weeks.js'

// "Mon 30 Nov, 08:00–14:00, Barista" for the shift of a swap request or of one of my shifts.
export function describeShift(shift) {
  return `${formatDay(shift.date)}, ${shift.startTime}–${shift.endTime}, ${labelOf(POSITIONS, shift.requiredPosition)}`
}

// The request that is still waiting for someone (pending or accepted) which
// the given employee made for one of their shifts, or undefined.
export function openSwapFor(requests, assignmentId, employeeId) {
  return requests.find(
    (request) =>
      request.assignmentId === assignmentId &&
      request.requester.id === employeeId &&
      (request.status === 'pending' || request.status === 'accepted'),
  )
}

export const ACTION_LABELS = {
  accept: 'Accept',
  decline: 'Decline',
  cancel: 'Withdraw',
  approve: 'Approve',
  reject: 'Reject',
}

// What the viewer may do with a request, in the order the buttons appear.
// viewer is { employeeId, isManager }.
export function actionsFor(request, viewer) {
  const actions = []
  const waitingForColleague = request.status === 'pending'
  const open = waitingForColleague || request.status === 'accepted'

  if (request.target.id === viewer.employeeId && waitingForColleague) {
    actions.push('accept', 'decline')
  }
  if (request.requester.id === viewer.employeeId && open) {
    actions.push('cancel')
  }
  if (viewer.isManager && request.status === 'accepted') {
    actions.push('approve', 'reject')
  }
  return actions
}

// What a shift's open swap says in the My shifts table.
export function describeOpenSwap(request) {
  return request.status === 'accepted'
    ? `${request.target.name} agreed, waiting for the manager`
    : `Waiting for ${request.target.name}`
}
