# Shift Planner

A web app where a café or restaurant manager builds a weekly roster and employees
see their shifts, give availability and swap shifts. The API refuses assignments
that break the rules.

## Stack
- Backend: PHP, Symfony, Doctrine ORM, Symfony Messenger, PHPUnit
- Frontend: React, Vite, ESLint + Prettier
- Auth: JWT via lexik/jwt-authentication-bundle, two roles (manager, employee))
- Exact versions, database and JWT library: see "Open questions" in docs/DECISIONS.md

## Commands

- Backend run:   `cd backend && symfony serve`
- Backend tests: `cd backend && php bin/phpunit`
- Backend style: `cd backend && vendor/bin/php-cs-fixer fix && vendor/bin/phpstan analyse`
- Frontend run:  `cd frontend && npm run dev`
- Frontend lint: `cd frontend && npm run lint`

## Structure
```
backend/
  src/Controller/      thin: request in, response out
  src/Service/         RosterService, ShiftValidator, SwapService
  src/Rule/            one class per rule, all implement ShiftRule
  src/Entity/          Doctrine entities
  src/Repository/      all database queries
  src/Dto/             validated input objects
  src/MessageHandler/  notification jobs
  tests/               unit test per rule, API tests for the main flow
frontend/
  src/pages/           one file per screen
  src/components/      small display pieces
  src/hooks/           state and loading/error logic
  src/api/             all HTTP calls
docs/                  SPEC.md, RULES.md, DECISIONS.md
```

## Must read before any work
@docs/SPEC.md
@docs/RULES.md
@docs/DECISIONS.md

## Working agreement
- Plan first. Wait for the developer's approval before writing code.
- One feature per step, with its tests. Follow the build order in docs/SPEC.md.
- Never decide an open question alone. Ask, then log the answer in docs/DECISIONS.md.
- Explain every non-obvious choice in one or two sentences.
- After each step: list the files changed and what each one is responsible for.
- For a bug: reproduce it with a failing test first, then fix it.
- Do not add libraries, features or files that the spec does not ask for.