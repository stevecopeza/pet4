STATUS: IMPLEMENTED
SCOPE: Ticket Lifecycle State Machines
VERSION: v1.1

# Ticket Lifecycle and State Machines (v1)

## Principle

Ticket lifecycle must be **context-governed** by `lifecycle_owner` / `primary_container`, but stored on the single Ticket entity.

## Status domains

A single `status` field with context-specific allowed transitions, enforced by the `TicketStatus` value object (`Domain\Support\ValueObject\TicketStatus`).

### Support lifecycle (lifecycle_owner='support')
Statuses (implemented):
- `new` → `open` → `pending` → `resolved` → `closed`
Rules:
- `opened_at` set when leaving `new`
- `responded_at` set on first agent response (as implemented by SLA)
- `closed_at` set when closed
- Terminal states: `closed`

### Project lifecycle (lifecycle_owner='project')
Statuses (implemented):
- `planned` → `ready` → `in_progress` → `blocked` → `done` → `closed`
Rules:
- baseline tickets may be `baseline_locked`
- roll-up tickets track derived progress only
- Terminal states: `closed`

### Internal lifecycle (lifecycle_owner='internal')
Statuses (implemented):
- `planned` → `in_progress` → `done` → `closed`
- Terminal states: `closed`

### Implementation
- `TicketStatus` VO: `fromString()`, `canTransitionTo()`, `isTerminal()`, `allForContext()`
- `Ticket::transitionStatus(string $newStatus)` — validates via `TicketStatus::canTransitionTo()`, throws `DomainException` on illegal transition, emits `TicketStatusChanged` event
- API: `lifecycleOwner` included in GET ticket response; `GET /pet/v1/tickets/status-options?lifecycle={context}` returns allowed statuses
- Frontend: dynamic status dropdown per lifecycle context, CSS badges for project/internal statuses

## Leaf-only time logging rule

- If ticket has children (parent_ticket_id referenced by others), mark `is_rollup=1`.
- Domain must reject time logging to roll-up tickets.
- Roll-up progress/time/cost are computed (projection) from leaves.

## Assignment semantics (queue model)

Ticket stores:
- `owning_team_id` / `department_id`
- `preferred_assignee_id` (optional)
- `assigned_to_id` (optional)
- `assignment_mode` ENUM('PREFERRED_PERSON','TEAM_QUEUE_PULL','MANAGER_ALLOCATED')

Rules:
- owning team/department is always set (or derivable) for support/project/internal tickets.
- assignment events update WorkItem projection.

## Domain events (required points)

Ticket should emit events for:
- TicketCreated
- TicketUpdated (optional granular events: status changed, priority changed)
- TicketAssigned
- TicketSplit (parent becomes roll-up, children created)
- TicketLinkAdded (optional)
- TicketCommercialContextChanged (rare; should be blocked post-acceptance baseline)

Events must remain additive and backward compatible.

## Guardrails

- Cross-context updates must not violate lifecycle ownership.
- Baseline lock: once a ticket is marked as quote-baseline accepted, sold fields become immutable.
- Changes after acceptance are represented as new tickets (change order / variance).
