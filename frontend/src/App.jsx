import { Navigate, Route, Routes } from 'react-router-dom'
import './App.css'
import AppLayout from './components/AppLayout.jsx'
import HomeRedirect from './components/HomeRedirect.jsx'
import ManagerRoute from './components/ManagerRoute.jsx'
import ProtectedRoute from './components/ProtectedRoute.jsx'
import AvailabilityPage from './pages/AvailabilityPage.jsx'
import EmployeesPage from './pages/EmployeesPage.jsx'
import LoginPage from './pages/LoginPage.jsx'
import MyShiftsPage from './pages/MyShiftsPage.jsx'
import RosterPage from './pages/RosterPage.jsx'

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route element={<ProtectedRoute />}>
        <Route element={<AppLayout />}>
          <Route path="/" element={<HomeRedirect />} />
          <Route path="/my-shifts" element={<MyShiftsPage />} />
          <Route path="/availability" element={<AvailabilityPage />} />
          <Route element={<ManagerRoute />}>
            <Route path="/roster" element={<RosterPage />} />
            <Route path="/employees" element={<EmployeesPage />} />
          </Route>
        </Route>
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
