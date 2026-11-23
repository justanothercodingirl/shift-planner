import { useCallback, useEffect, useState } from 'react'
import {
  createEmployee,
  deactivateEmployee,
  listEmployees,
  updateEmployee,
} from '../api/employees.js'
import useAuthedRequest from './useAuthedRequest.js'

// The employee list plus create / update / deactivate. Every action reloads the list.
export default function useEmployees() {
  const run = useAuthedRequest()
  const [employees, setEmployees] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [version, setVersion] = useState(0)

  const reload = useCallback(() => setVersion((current) => current + 1), [])

  useEffect(() => {
    let ignore = false
    run((token) => listEmployees(token))
      .then((list) => {
        if (!ignore) setEmployees(list)
      })
      .catch((caught) => {
        if (!ignore) setError(caught)
      })
      .finally(() => {
        if (!ignore) setLoading(false)
      })
    return () => {
      ignore = true
    }
  }, [run, version])

  // Returns true when the action worked, so a form knows whether it can close.
  const act = useCallback(
    async (call) => {
      setError(null)
      try {
        await run(call)
        reload()
        return true
      } catch (caught) {
        setError(caught)
        return false
      }
    },
    [run, reload],
  )

  return {
    employees,
    loading,
    error,
    create: (employee) => act((token) => createEmployee(token, employee)),
    update: (id, employee) =>
      act((token) => updateEmployee(token, id, employee)),
    deactivate: (id) => act((token) => deactivateEmployee(token, id)),
  }
}
