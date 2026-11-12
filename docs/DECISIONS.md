# Decision log

One line per non-obvious decision. Add a row after every step.

## Decided
| Date | Decision | Why | Rejected |
|---|---|---|---|
| 2026-11-11 | Employees are available by default and only enter exceptions | Less typing; an assignment fails only if it overlaps an `unavailable` window | Entering every available slot |
| 2026-11-11 | maxWeeklyHours is set by the manager; contract type only pre-fills it | The number comes from the person's contract | Deriving hours from contract type |
| 2026-11-11 | `preferred` availability is a hint, not a rule | Manager keeps the final say | Blocking on preferences |
| 2026-11-11 | Rule violations return 422 with ALL violations at once | Manager fixes everything in one go | Stopping at the first violation |
| 2026-11-11 | One class per rule behind `ShiftRule`, run by `ShiftValidator` | Adding a rule = one class + one test | Rules inside a controller or one big service |
| 2026-11-11 | Rest period is configurable, default 11 hours | The default follows German working time law, but businesses differ | Hard-coded value |
| 2026-11-11 | Publishing an understaffed roster warns but is allowed | Manager may knowingly run short | Blocking publish |
| 2026-11-11 | Edits after publishing are allowed and notify affected employees | Real rosters change after publishing | Locking the roster fully |
| 2026-11-11 | Emails are sent by a queued job with retry; mail failure is logged, never shown to the user | Publishing must not fail because of the mail server | Sending mail inside the request |
| 2026-11-11 | Rules run again for the target employee before a swap can be approved | A swap must not create a violation | Trusting the original check |
| 2026-11-12 | Employee has a one-to-one link to User | Login must know which employee is "me" | Merging into one entity |
| 2026-11-12 | Employee has an `active` flag (soft-deactivate) | Keeps past rosters and hours intact; assigning a deactivated employee is 422 | Hard delete |
| 2026-11-12 | Shift has `headcount` (integer, min 1) | "Two baristas" and SHIFT_FULL need it | One shift per person |
| 2026-11-12 | Availability has nullable `weekday` and `date`, exactly one set (DTO validation) | Matches the spec's field description | Two separate entities |
| 2026-11-12 | A manager may also have an Employee record and be assigned to shifts | Supports owner-operators | Managers never work shifts |
| 2026-11-12 | PostgreSQL 16, PHP >= 8.4, Symfony 8.1, Doctrine ORM 3.7, React 19, Vite 8 | Already in the scaffold | MySQL |
| 2026-11-12 | JWT via lexik/jwt-authentication-bundle; React keeps the token in memory | XSS cannot read it from storage; reload means login again | localStorage, httpOnly cookie |
| 2026-11-12 | One error shape: `{ "error": { "code", "message", "violations": [{ "code", "reason" }] } }`, `violations` only for rule failures | RULES.md API rule 3; one error component in React | Two shapes |
| 2026-11-12 | All rules are hard; no WEEKLY_CAP override | Keeps `ShiftRule::check()` and `Violation` as in the spec | Soft rules with severity |
| 2026-11-12 | Published rosters stay editable by the manager; the 409 "published and locked" is dropped | Matches the earlier decision on edits after publishing | Locking after publish |
| 2026-11-12 | Overnight shifts allowed: end earlier than start means it ends the next day; this replaces the 422 for shifts (stays for availability) | Cafes and bars work past midnight | Disallowing overnight shifts |
| 2026-11-12 | A shift counts towards the week of its start date | Simple; matches how Shift stores date + start + end | Splitting at midnight |
| 2026-11-12 | Rest period is checked across roster weeks | Legally correct; repository looks up neighbouring shifts by date, not by roster | Within one roster only |
| 2026-11-12 | Token lives in memory only, so a page refresh logs the user out | Accepted for now; avoids token storage | Refresh token or cookie (revisit later) |
| 2026-11-12 | Step 1 maps only User and Employee; each later step adds its own entities and migration | One feature per step, reviewable, testable, cleaner git history | One migration for the whole model |
| 2026-11-12 | Employees screen (manager) is screen 6 in the spec, built in step 2 | Managing employees is a manager feature with no screen | API only, no screen |
| 2026-11-12 | Step 1 ships API plus Login only on the frontend | Matches the spec's build order | Employees page in step 1 |
| 2026-11-12 | `maxWeeklyHours` is required in the API; React pre-fills it from the contract type | Matches "contract type only suggests a value" | API-side default |
| 2026-11-12 | First manager and demo data come from doctrine/doctrine-fixtures-bundle (dev dependency) | Empty database has no login; demo data is useful for screenshots | Console command |
| 2026-11-12 | React dev server proxies /api to the Symfony server via Vite's proxy | Avoids CORS in development with no extra library | CORS bundle |
| 2026-11-12 | JWT key pair is generated locally and kept out of git | Private key must never be committed | Committing keys |

## Open questions
| # | Question | Notes |
|---|---|---|
| 6 | SwapRequest statuses | Deferred to build step 4. Suggestion: pending, accepted, declined, approved, rejected, cancelled |
| 10 | How is a manager created after the first one (seeded by fixture)? | Out of step 1. Also: how does a manager who also works shifts get an Employee record? |
