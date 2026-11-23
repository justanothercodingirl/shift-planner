import { useState } from 'react'
import { CONTRACT_TYPES } from '../constants/options.js'

function suggestedHours(contractType) {
  return CONTRACT_TYPES.find((type) => type.value === contractType)
    ?.suggestedHours
}

const NEW_EMPLOYEE = {
  email: '',
  password: '',
  name: '',
  position: 'barista',
  contractType: 'part_time',
  maxWeeklyHours: String(suggestedHours('part_time')),
}

// Field values for the add / edit form. Pass the employee to edit, or null to add one.
// Choosing a contract type pre-fills the weekly hours until the manager edits them.
export default function useEmployeeForm(employee) {
  const editing = employee !== null
  const [values, setValues] = useState(
    editing
      ? {
          name: employee.name,
          position: employee.position,
          contractType: employee.contractType,
          maxWeeklyHours: String(employee.maxWeeklyHours),
        }
      : NEW_EMPLOYEE,
  )
  const [hoursEdited, setHoursEdited] = useState(editing)

  const setField = (field) => (value) =>
    setValues((current) => ({ ...current, [field]: value }))

  function setContractType(contractType) {
    setValues((current) => ({
      ...current,
      contractType,
      ...(hoursEdited
        ? {}
        : { maxWeeklyHours: String(suggestedHours(contractType)) }),
    }))
  }

  function setHours(value) {
    setHoursEdited(true)
    setField('maxWeeklyHours')(value)
  }

  // The JSON the API expects: email and password only when adding.
  function payload() {
    const common = {
      name: values.name.trim(),
      position: values.position,
      contractType: values.contractType,
      maxWeeklyHours: Number(values.maxWeeklyHours),
    }
    if (editing) return common
    return { email: values.email.trim(), password: values.password, ...common }
  }

  return { editing, values, setField, setContractType, setHours, payload }
}
