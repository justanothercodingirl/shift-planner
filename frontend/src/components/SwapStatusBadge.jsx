import { SWAP_STATUSES } from '../constants/options.js'
import Badge from './Badge.jsx'

// The status of a swap request in words and a fitting colour.
export default function SwapStatusBadge({ status }) {
  const known = SWAP_STATUSES.find((option) => option.value === status)

  return <Badge tone={known?.tone}>{known?.label ?? status}</Badge>
}
