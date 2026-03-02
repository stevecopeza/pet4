STATUS: IMPLEMENTED
SCOPE: Test Infrastructure, Conventions, and Coverage
VERSION: v1

# Test Infrastructure and Coverage (v1)

## Framework

- **PHPUnit 9.6** via Composer (`phpunit/phpunit ^9.6`)
- Config: `phpunit.xml` at plugin root
- Bootstrap: `tests/bootstrap.php` (Composer autoload + WP function stubs)

## Test Suites

### Unit (`tests/Unit/`)
Pure domain tests — no database, no WordPress, no HTTP. Instantiate entities and value objects directly.

### Integration (`tests/Integration/`)
Tests that touch infrastructure (database, WordPress APIs). Not yet populated — reserved for repository round-trips and migration verification.

## Directory Structure

```
tests/
├── bootstrap.php
├── Unit/
│   └── Domain/
│       ├── Commercial/
│       │   ├── Entity/
│       │   │   └── ContractTest.php
│       │   └── ValueObject/
│       │       ├── ContractStatusTest.php
│       │       └── QuoteStateTest.php
│       ├── Delivery/
│       │   └── ValueObject/
│       │       └── ProjectStateTest.php
│       ├── Support/
│       │   ├── Service/
│       │   │   └── SlaStateResolverTest.php
│       │   └── ValueObject/
│       │       └── TicketStatusTest.php
│       ├── Time/
│       │   └── Entity/
│       │       └── TimeEntryTest.php
│       └── Work/
│           └── Entity/
│               └── WorkItemTest.php
└── Integration/
    (empty — future)
```

## Conventions

- Namespace: `Pet\Tests\Unit\Domain\{BoundedContext}\{Type}\{Class}Test`
- One test class per production class
- Factory helpers via private `make*()` methods with sensible defaults
- `@dataProvider` for parameterized validation (e.g. valid/invalid statuses)
- `expectException()` + `expectExceptionMessage()` for negative cases
- No mocks in domain unit tests — entities are constructed directly

## Current Coverage (163 tests, 249 assertions)

### Value Objects
- **QuoteState** — all valid/invalid transitions, `isTerminal()`, `fromString()`
- **ContractStatus** — same pattern
- **ProjectState** — same pattern
- **TicketStatus** — all 3 lifecycle contexts (support/project/internal), transitions, terminal states, `allForContext()`

### Entities
- **TimeEntry** — submit/updateDraft/lock guards, createCorrection/createReversal, setId, archive, isCorrection
- **WorkItem** — create factory, source type validation (4 valid + invalid), status validation (3 valid + invalid), all mutators
- **Contract** — complete/terminate from active, all invalid transitions throw DomainException

### Domain Services
- **SlaStateResolver** — `determineState()`: active (no SLA, >1hr), warning (<1hr), breached (resolution + response overdue), paused (4 statuses), responded-already bypass; `resolveTransitionEvents()`: warning/breach/escalation/already-escalated/paused/active

## Planned (Future Sessions)

### Domain unit tests
- Quote entity — component add/remove on terminal state, `validateReadiness`, margin calcs
- Project entity — `completeTask()`, `addTask()`, `archive()`
- Ticket entity — `assignSla()`, `markAsResponded()` idempotency, `update()` side effects
- PriorityScoringService — each component, edge cases

### Application unit tests
- QuoteAcceptedListener — contract/baseline/SLA snapshot; idempotency
- WorkItemProjector — idempotency, department resolution
- FeatureFlagService — missing setting returns false

### Integration tests
- SqlQuoteRepository save/find round-trip
- MigrationRunner — all migrations execute without SQL errors
- PersistingEventBus — SourcedEvent persistence

## Running Tests

```bash
# All unit tests
cd wp-content/plugins/pet && vendor/bin/phpunit --testsuite Unit

# Specific test class
vendor/bin/phpunit tests/Unit/Domain/Time/Entity/TimeEntryTest.php

# With coverage (requires Xdebug)
vendor/bin/phpunit --testsuite Unit --coverage-text
```
