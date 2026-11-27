import { labelOf, POSITIONS } from '../constants/options.js'

// "Barista, Service": the main position first, then the additional ones.
// The one place that decides how an employee's positions are written.
export function describePositions(employee) {
  return [employee.position, ...(employee.additionalPositions ?? [])]
    .map((position) => labelOf(POSITIONS, position))
    .join(', ')
}
