# Code rules

The goal: the type of bug tells you which file to open.

## Backend (Symfony)
1. Controller methods are max ~15 lines: map input to a DTO, call ONE service,
   return a response.
2. No EntityManager or query builder in controllers or services. Queries live
   in repositories only.
3. Every business rule lives in a service or a Rule class, named by what it
   does (`RosterService::publish()`).
4. One roster rule = one class in `src/Rule/` implementing `ShiftRule`, plus
   one unit test. `ShiftValidator` runs all rules and returns ALL violations.
5. Services receive dependencies through the constructor. No `new` for services.
6. Incoming data is mapped to a DTO and validated before it reaches a service.
7. Services throw domain exceptions (`RosterAlreadyPublishedException`). One
   exception listener turns them into JSON errors with the right status code.
8. Permission checks use Symfony voters or `#[IsGranted]`, not `if` statements
   spread through the code.
9. Configurable values (rest period hours) are parameters, never hard-coded.
10. `declare(strict_types=1);` in every file. Code passes PHP-CS-Fixer
    (`@Symfony`) and PHPStan.

## Frontend (React)
1. One component per file, max ~100 lines. Longer means split it.
2. Components get data through props or a hook. No fetch/axios inside components.
3. All HTTP calls live in `src/api/`. All data state lives in custom hooks
   (`useRoster`, `useAvailability`).
4. Pages compose components. They contain no business logic.
5. Every list, form and item is its own component (`ShiftCard`,
   `AvailabilityForm`, `SwapRequestList`).
6. If JSX is repeated twice, extract a component.
7. API errors are handled in one place and shown through one error component.
8. Code passes ESLint and Prettier.

## API
1. Plural nouns and HTTP verbs: `GET /api/rosters`, `POST /api/shifts/{id}/assignments`.
2. JSON keys in camelCase.
3. One error shape for every endpoint (defined in DECISIONS.md once agreed).

## Both
1. A function does one thing. If you need "and" to describe it, split it.
2. Names say what, not how: `findOverlappingShifts()`, not `getData2()`.
3. Every service method and every rule has a test.
4. Commits are small with a prefix: `feat:`, `fix:`, `test:`, `refactor:`, `docs:`.
5. No change is committed until the developer can explain it in one sentence.

## Where to look when something breaks
| Symptom | File to open |
|---|---|
| Wrong data from the database | Repository |
| Wrong business decision | Service or Rule |
| Wrong status code or JSON shape | Controller or exception listener |
| Input accepted that should be rejected | DTO validation |
| User can do something they should not | Voter |
| UI shows stale data | Hook |
| Request has the wrong URL or body | `src/api/` file |
| It looks wrong on screen | Component |