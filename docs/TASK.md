# Tasks

One task = one commit. Work in order.

## Phase 1: Foundation
- [x] 1. feat: add User entity and migration
- [x] 2. feat: add fixtures for first manager and demo data
- [x] 3. feat: add JWT login endpoint
- [x] 4. feat: add exception listener and shared error shape
- [x] 5. feat: add Employee entity with position and contract enums
- [x] 6. feat: add employee CRUD API (manager only)
- [x] 7. feat: add login page and auth context in React

## Phase 2: Roster and shifts
- [x] 8. feat: add Roster and Shift entities
- [x] 9. feat: add roster API (create week, list, get)
- [x] 10. feat: add shift API (create, update, delete)
- [x] 11. feat: add employee list and form in React
- [x] 12. feat: add roster week view in React
- [x] 13. feat: add shift create dialog in React

## Phase 3: Assignment and rule engine
- [x] 14. feat: add Assignment entity and assign endpoint
- [x] 15. feat: add ShiftRule interface and ShiftValidator service
- [x] 16. feat: add overlap rule with tests
- [x] 17. feat: add position match rule with tests
- [x] 18. feat: add rest period rule with tests
- [x] 19. feat: add weekly cap rule with tests
- [x] 20. feat: add Availability entity, API and rule with tests
- [x] 21. feat: add availability editor in React
- [x] 22. feat: show violations in the assign dialog
- [ ] 22a. feat: add additional positions to employees with tests
- [ ] 22b. feat: add additional positions to the employee form in React

## Phase 4: Publish and notify
- [ ] 23. feat: add roster publish workflow (draft to published)
- [ ] 24. feat: send publish notification via Messenger

## Phase 5: Swaps
- [ ] 25. feat: add SwapRequest entity and request/accept/approve API
- [ ] 26. feat: add swap screens in React
- [ ] 27. feat: add my shifts screen in React

## Phase 6: Finish
- [ ] 28. feat: add hours overview and CSV export
- [ ] 29. chore: add Docker setup for PHP, Postgres and Node
- [ ] 30. chore: add GitHub Actions pipeline and README with screenshots