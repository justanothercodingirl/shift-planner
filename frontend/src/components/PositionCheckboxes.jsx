import { POSITIONS } from '../constants/options.js'

// "Can also work as": one checkbox per position except the main one (an
// employee's main position is chosen in the dropdown above). selected is the
// list of ticked position values; onToggle(value) flips one of them.
export default function PositionCheckboxes({
  mainPosition,
  selected,
  onToggle,
}) {
  return (
    <fieldset className="checkbox-group">
      <legend>Can also work as</legend>
      {POSITIONS.filter((position) => position.value !== mainPosition).map(
        (position) => (
          <label key={position.value}>
            <input
              type="checkbox"
              checked={selected.includes(position.value)}
              onChange={() => onToggle(position.value)}
            />
            {position.label}
          </label>
        ),
      )}
    </fieldset>
  )
}
