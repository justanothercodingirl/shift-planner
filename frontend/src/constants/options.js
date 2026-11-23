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

export function labelOf(options, value) {
  return options.find((option) => option.value === value)?.label ?? value
}
