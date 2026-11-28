// Choices shown in forms and tables. The values are exactly the API's enum values.

export const POSITIONS = [
  { value: 'barista', label: 'Barista' },
  { value: 'service', label: 'Service' },
  { value: 'kitchen', label: 'Kitchen' },
  { value: 'bar', label: 'Bar' },
  { value: 'shift_lead', label: 'Shift lead' },
]

// suggestedHours are the defaults from SPEC.md; the manager can change them.
export const CONTRACT_TYPES = [
  { value: 'full_time', label: 'Full time', suggestedHours: 40 },
  { value: 'part_time', label: 'Part time', suggestedHours: 20 },
  { value: 'working_student', label: 'Working student', suggestedHours: 20 },
  { value: 'mini_job', label: 'Mini job', suggestedHours: 10 },
]

export const WEEKDAYS = [
  { value: 'monday', label: 'Monday' },
  { value: 'tuesday', label: 'Tuesday' },
  { value: 'wednesday', label: 'Wednesday' },
  { value: 'thursday', label: 'Thursday' },
  { value: 'friday', label: 'Friday' },
  { value: 'saturday', label: 'Saturday' },
  { value: 'sunday', label: 'Sunday' },
]

export const AVAILABILITY_TYPES = [
  { value: 'unavailable', label: 'Cannot work' },
  { value: 'preferred', label: 'Would prefer' },
]

// Whether an availability entry repeats every week or applies to one date.
export const REPEATS = [
  { value: 'weekly', label: 'Every week' },
  { value: 'date', label: 'One date only' },
]

// The statuses of a swap request, with the badge colour that fits each.
export const SWAP_STATUSES = [
  { value: 'pending', label: 'Waiting for colleague', tone: 'neutral' },
  { value: 'accepted', label: 'Waiting for manager', tone: 'neutral' },
  { value: 'declined', label: 'Declined', tone: 'muted' },
  { value: 'approved', label: 'Approved', tone: 'good' },
  { value: 'rejected', label: 'Rejected', tone: 'muted' },
  { value: 'cancelled', label: 'Cancelled', tone: 'muted' },
]

export function labelOf(options, value) {
  return options.find((option) => option.value === value)?.label ?? value
}
