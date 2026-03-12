STATUS: IMPLEMENTED
SCOPE: Ticket Lifecycle State Machines
VERSION: v2
SUPERSEDES: v1.1
DATE: 2026-03-06

# Ticket Lifecycle and State Machines (v2)

> Governed by `00_foundations/02_Ticket_Architecture_Decisions_v1.md`.

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
- `baseline_locked` is NOT a status. See "Baseline lock" section below.
- Roll-up tickets track derived progress only.
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

## Baseline lock (orthogonal property)

`is_baseline_locked` is a boolean field on the ticket, NOT a lifecycle status.

A ticket can be both `in_progress` and baseline-locked simultaneously. These are independent concerns:
- **Operational status** (planned → ready → in_progress → blocked → done → closed) governs work lifecycle.
- **Baseline lock** (is_baseline_locked = 1) governs commercial immutability of sold fields.

Baseline lock is set to 1 when the ticket is created from an accepted quote. It means `sold_minutes` and `sold_value_cents` are immutable.

## Leaf-only time logging rule

Time is logged against leaf tickets only. A ticket with no children is a leaf and may receive time directly. Once split into children, it becomes a rollup and no longer accepts direct time.

An unsplit sold ticket is a leaf and accepts time directly.

Implementation:
- If ticket has children (`parent_ticket_id` referenced by others), mark `is_rollup=1`.
- Domain rejects time logging to roll-up tickets via `canAcceptTimeEntries()` returning `!$this->isRollup`.
- Roll-up progress/time/cost are computed (projection) from leaves.

## Assignment semantics (queue model)

Ticket stores (implemented):
- `queue_id` (team/department queue identifier, nullable string)
- `owner_user_id` (individually assigned employee, nullable string)
- `department_id_ext` (department reference, nullable int)

Rules:
- `assignToTeam(queueId)` sets queue and clears individual owner.
- `assignToEmployee(userId)` sets individual owner, preserves queue context.
- `pull(userId)` is a self-assign alias for `assignToEmployee`.
- Assignment changes emit `TicketAssigned` domain events.
- Assignment events update WorkItem projection.

## Domain events (required points)

Ticket should emit events for:
- TicketCreated
- TicketUpdated (optional granular events: status changed, priority changed)
- TicketAssigned
- TicketSplit (parent becomes roll-up, children created)
- TicketLinkAdded (optional)
- TicketCommercialContextChanged (rare; should be blocked post-acceptance baseline)

Events must remain additive and backward compatible.

## Change orders

Change orders create new tickets with their own `sold_minutes`, linked to the original via `change_order_source_ticket_id`. Change order tickets are NOT children of the original — they are independent sold commitments with explicit traceability.

## Guardrails

- Cross-context updates must not violate lifecycle ownership.
- Baseline lock: once `is_baseline_locked = 1`, sold fields (`sold_minutes`, `sold_value_cents`) are immutable.
- Changes after acceptance are represented as new tickets (change order), not mutations.
- No `ticket_mode` field. Use `lifecycle_owner` for context, `ticket_kind` for classification, `is_baseline_locked` for commercial lock state.
