# Shift Planner: specification

## 1. Purpose
The manager prepares a weekly roster. Employees see their shifts, give
availability and swap shifts. The app refuses assignments that break the rules.

## 2. Roles
| Role | Can do |
|---|---|
| manager | Create shifts, assign people, approve swaps, publish the roster, manage employees |
| employee | Set own availability, view own shifts, request swaps, see own hours |

## 3. Core features
1. Login with two roles (JWT).
2. Employee profiles: position, contract type, maximum weekly hours.
3. Availability: employees mark when they cannot or would prefer to work.
4. Weekly roster: the manager creates shifts and assigns employees in a calendar grid.
5. Rule check: every assignment is validated; violations are shown with a clear reason.
6. Publish: the roster moves from draft to published and employees are notified.
7. Shift swap: an employee asks, a colleague accepts, the manager approves.
8. Hours overview: planned hours per employee per month, with CSV export.

Out of scope: payroll, time tracking of actually worked hours, multiple locations,
mobile app.

## 4. Data model
| Entity | Fields |
|---|---|
| User | email, password, role (manager, employee) |
| Employee | user*, name, position, contractType, maxWeeklyHours, active* |
| Availability | employee, weekday or date, from, to, type |
| Roster | weekStart, status (draft, published) |
| Shift | roster, date, start, end, requiredPosition, headcount* |
| Assignment | shift, employee |
| SwapRequest | assignment, targetEmployee, status |

Fields marked * were not in the original idea but are needed by the flows below.
They are logged as proposals in DECISIONS.md.

Enums:
- Position: barista, service, kitchen, bar, shift_lead
- ContractType: full_time, part_time, working_student, mini_job
- AvailabilityType: unavailable, preferred
- RosterStatus: draft, published
- SwapStatus: to be defined in the swap step (see DECISIONS.md, open questions)

### Maximum weekly hours
Set by the manager when creating the employee, because it comes from that
person's contract. The contract type only pre-fills a suggestion that the
manager can change.

| Contract type | Suggested default |
|---|---|
| full_time | 40 |
| part_time | 20 |
| working_student | 20 |
| mini_job | 10 |

### Availability
- Employees are available by default and only enter exceptions.
- from, to: times of day (08:00 to 14:00). They describe a window, not a shift.
- type: `unavailable` blocks an assignment. `preferred` is only a hint for the manager.
- weekday: repeats every week. date: applies once. Exactly one of the two is set.

Example rows for Anna:

| Weekday / date | From | To | Type |
|---|---|---|---|
| Monday | 08:00 | 16:00 | unavailable |
| Saturday | 08:00 | 14:00 | preferred |
| 2026-11-24 | 00:00 | 23:59 | unavailable |

## 5. Main flow
1. Employees enter availability for next week.
2. The manager opens a draft roster and adds shifts (for example Saturday 08:00
   to 14:00, two baristas).
3. The manager assigns Anna to a shift, and the API runs all rules.
4. If a rule fails, the API returns 422 with the reasons.
5. The manager publishes, and a queued job emails everyone.
6. Anna requests a swap with Ben. Ben accepts. The rules run again for Ben
   before the manager can approve.

## 6. Rules
| Rule | Meaning |
|---|---|
| No overlap | An employee cannot be in two shifts at once. |
| Rest period | Minimum hours between two shifts. Default 11, configurable. |
| Weekly cap | Planned hours must not exceed maxWeeklyHours. |
| Availability | The shift must not overlap an `unavailable` window. |
| Position match | The employee's position must match the shift's required position. |

### Rule violations
All return 422. Return all violations at once, not only the first.

| Code | Example reason |
|---|---|
| OVERLAP | Anna already works 10:00 to 16:00 that day |
| REST_PERIOD | Only 9 hours rest since the previous shift |
| WEEKLY_CAP | This would bring Anna to 24 of 20 hours |
| UNAVAILABLE | Anna is unavailable Monday 08:00 to 16:00 |
| POSITION_MISMATCH | Shift needs a barista, Anna is kitchen |
| SHIFT_FULL | Both barista slots are already filled |
| ALREADY_ASSIGNED | Anna is already on this shift |

### Rule engine
Each rule is one small class behind one interface:

```php
interface ShiftRule
{
    public function check(Employee $employee, Shift $shift): ?Violation;
}
```

`ShiftValidator` collects all rules and runs them. The controller only calls the
service and returns the result. Adding a rule means adding one class and one
test, with no changes elsewhere.

## 7. Other errors
| Step | What can go wrong | Status |
|---|---|---|
| Availability | End time before start time | 422 |
| Availability | Overlaps one of the employee's own entries | 422 |
| Availability | Editing a colleague's availability | 403 |
| Roster and shifts | Roster for that week already exists | 409 |
| Roster and shifts | Shift date outside the roster week | 422 |
| Roster and shifts | End before start | 422 |
| Roster and shifts | Headcount below 1 | 422 |
| Assign | Employee or shift not found | 404 |
| Assign | Employee deactivated | 422 |
| Assign | Roster already published and locked | 409 (conflicts with "edits after publishing", see DECISIONS.md) |
| Publish | Roster already published | 409 |
| Publish | Roster has no shifts | 422 |
| Publish | Shifts still understaffed | Warning, publish allowed |
| Email job | Mail server fails | No user error: retry in the queue and log it |
| Swap | Shift already started or in the past | 422 |
| Swap | Swapping with yourself | 422 |
| Swap | Target employee declined | 409 |
| Swap | A pending request already exists | 409 |
| Swap | Target employee fails a rule | 422 |
| Swap | Assignment was changed by someone else meanwhile | 409 |

## 8. React screens
1. Login
2. Roster week view (main screen: grid of days and shifts)
3. Availability editor
4. My shifts and swap requests
5. Hours overview
6. Employees (manager: list, create, edit, deactivate; built in step 2)

## 9. Build order
1. Entities, auth, employee CRUD.
2. Roster and shift API plus the week view and the Employees screen.
3. Rule engine with tests (spend the most time here).
4. Swap workflow and notifications.
5. Hours export, Docker, CI, README with screenshots.