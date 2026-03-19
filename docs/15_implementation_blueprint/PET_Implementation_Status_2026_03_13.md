# PET Implementation Status — 13 March 2026

**Status:** AUTHORITATIVE
**Scope:** Records the implementation state of all work completed in the 13 March 2026 session against the governing specification documents.

This document maps each specification to the implemented code, identifies deviations, and records outstanding work.

---

# 1. Escalation Engine Completion

**Spec:** `PET_Escalation_Completion_Corrected_v2.md`

## Implemented

### Entity (Escalation.php)
- `summary` field added — auto-derived from `reason` via `defaultSummary()` (truncated to 140 chars), or explicitly provided
- `resolutionNote` field added — set on `resolve()`
- `openedAt()` accessor added (alias for `createdAt`)
- Guard: resolved escalation cannot be acknowledged (`DomainException`)
- Dedupe key cleared on resolve ✓
- Severity model preserved: LOW / MEDIUM / HIGH / CRITICAL ✓
- Status model preserved: OPEN / ACKED / RESOLVED ✓

### Command layer
- `TriggerEscalationCommand` accepts optional `summary` parameter
- `TriggerEscalationHandler` passes `summary` through to entity constructor
- `SlaEscalationBridgeListener` passes `'SLA Breach'` as summary

### Persistence (SqlEscalationRepository.php)
- `summary`, `resolution_note` persisted and hydrated
- `mapRowToEntity()` handles nullable `summary` and `resolution_note` with graceful fallback for existing rows

### Migration (CreateEscalationTables.php)
- `metadata_json longtext NULL` — fixes MySQL rejection of `DEFAULT '{}'` on longtext

### REST API (EscalationController.php)
- `serialize()` now includes `summary`, `opened_at`, `resolution_note`
- Detail endpoint normalises transitions via `normalizeTransition()` — typed array output instead of raw DB objects

### UI (Escalations.tsx)
- List table: summary column, opened_at instead of created_at
- Detail panel: grid layout with summary, reason, resolution note, timeline table
- Click-through from escalation ID to detail
- Actions refresh detail panel after acknowledge/resolve

### Tests (EscalationPersistenceTest.php)
- Summary roundtrip assertion
- Resolution note persistence assertion
- Resolved-cannot-be-acknowledged guard test
- SLA bridge noop when feature flag off
- Test schema includes `summary` and `resolution_note` columns

## Spec compliance
All §2 canonical fields implemented. All §2.6 invariants preserved. All §2.7 state transitions enforced. API surface matches §2.10.

## Outstanding
- No admin filter by severity (§2.4 severity model is persisted but not filterable in UI)
- Timeline timestamps are raw strings, not formatted

---

# 2. Work Orchestration, Assignment Routing, and Queue Visibility

**Spec:** `PET_Work_Orchestration_Assignment_Routing_Queue_Visibility_v2.md`

## Implemented

### WorkItem entity
- `assignedTeamId`, `assignmentMode`, `queueKey`, `routingReason` fields added
- `ASSIGNMENT_MODE_TEAM_QUEUE`, `ASSIGNMENT_MODE_USER_ASSIGNED`, `ASSIGNMENT_MODE_UNROUTED` constants
- `normalizeAssignmentState()` enforces mutual exclusivity (§2.3A: no dual active assignment)
- `buildQueueKey()` derives canonical keys: `support:team:{id}`, `delivery:user:{id}`, etc.
- `isUnroutedAllowedForSourceType()` restricts unrouted to ticket/escalation/admin
- `updateAssignment()` method for explicit routing transitions

### DepartmentQueue entity
- `assignedTeamId` field added
- `exitQueue()` method for closing stale queue entries
- `enter()` factory accepts optional `assignedTeamId`

### WorkItemProjector
- `onTicketCreated`: uses `updateAssignment()` with queue/owner from ticket
- `onTicketAssigned`: creates work item if missing, manages queue enter/exit, handles all assignment modes
- `onProjectTaskCreated`: fully implemented — role-team lookup, department resolution, queue entry creation
- `RoleTeamRepository` injected for delivery routing

### AssignWorkItemHandler
- Blocks ticket-sourced reassignment (`'Ticket-sourced work must be assigned via Ticket commands.'`)
- `ActivityLogRepository` dependency removed — activity logging moved to source-domain event path

### SlaClockCalculator
- `generationRunId` threaded through all signal creation
- `clearForWorkItem()` receives run ID for additive signal history

### Persistence
- `SqlWorkItemRepository`: persists/hydrates all 4 new fields; constructor accepts untyped `$wpdb`
- `SqlDepartmentQueueRepository`: persists `assigned_team_id`; `findByWorkItemId` scoped to active (unpicked) entries

### REST API
- `WorkController`: `/my-items` gated behind both `isWorkProjectionEnabled()` and `isQueueVisibilityEnabled()`
- `WorkItemController`: ticket-sourced assignment blocked at API level; `serializeWorkItem()` includes all new fields; advisory signal lookup gated behind feature flag

### UI (WorkItems.tsx)
- Complete rewrite: queue-based navigation with `QueueDescriptor`, `QueueSummaryRow`, `QueueItem` types
- Queue selector with counts, default to user's own support queue
- Columns: reference, title, priority, status, assignment mode, routing reason

### Tests (WorkItemTest.php)
- Assignment mode transitions verified
- Unrouted restriction for `project_task` source type

## Spec compliance
All §2.1 canonical fields implemented. §2.2 assignment modes implemented. §2.3 invariants enforced. §2.4 visibility scopes implemented via `WorkQueueVisibilityService`. §2.6 API surfaces implemented.

## Outstanding
- No item-level actions in queue UI (pick up, reassign, return to queue) — backend endpoints exist
- No SLA clock column in queue view — data available but not rendered
- No drill-through from queue item to source entity
- `assignment_mode` and `routing_reason` shown as raw values, not human-readable labels

---

# 3. Advisory Layer Outputs and Report Generation

**Spec:** `PET_Advisory_Layer_Outputs_And_Report_Generation_v1.md`

## Implemented

### AdvisorySignal entity
- Enriched with: `status`, `resolvedAt`, `generationRunId`, `title`, `summary`, `metadata`, `sourceEntityType`, `sourceEntityId`, `customerId`, `siteId`
- All §2.3 canonical signal fields covered

### AdvisorySignalRepository
- `clearForWorkItem()` now does `UPDATE status='INACTIVE'` instead of `DELETE` — additive history (§2.4B)
- `findActiveByWorkItemId()` filters on `status = 'ACTIVE'`
- `save()` uses `insert` instead of `replace` — no destructive overwrites

### AdvisoryGenerator
- `generationRunId` created per run and threaded through all signal creation
- `clearForWorkItem()` called with run ID before regenerating — prior signals preserved as INACTIVE

### SlaClockCalculator
- Same `generationRunId` pattern applied to all signal types (SLA risk, deadline risk, idle, capacity)

### AdvisoryGenerationJob
- Feature flag gate: `isAdvisoryEnabled()` check before running cron

### Persistence (SqlAdvisorySignalRepository)
- All new fields persisted and hydrated
- `metadata_json` serialised/deserialised correctly
- Graceful fallback for rows missing new columns

### REST API
- `AdvisorySignalController` registered in ApiRegistry and ContainerFactory
- `AdvisoryReportController` registered — serves versioned reports

### UI
- `Advisory.tsx` component exists, routed from `App.tsx` via `pet-advisory` page
- `AdminPageRegistry` adds Advisory menu item

## Spec compliance
§2.1 advisory model principle preserved. §2.2 entities implemented. §2.3 fields covered. §2.4 invariants enforced (derived-only, versioned, no mutation, explicit generation, read-side safety).

## Outstanding
- `Advisory.tsx` component needs full implementation — signal list, report list, report detail, generation trigger, severity filtering
- No advisory report version navigation UI
- No scope-filtered signal browsing

---

# 4. Dashboard Composition and Manager Summary Surfaces

**Spec:** `PET_Dashboard_Composition_And_Manager_Summary_Surfaces_v1.md`

## Implemented

### DashboardCompositionService
- `getMeSummary()`: assembles scoped payload with personas, panels, active scope
- Panel types: `queue_summary`, `support_workload`, `escalation_summary`, `advisory_summary`, `resilience_summary`, `recent_activity`, `team_queue`, `my_queue`, `advisory_signals`
- Persona routing: manager, support, pm (stub), timesheets (stub), sales (stub/admin-only)
- Feature flag gating per panel (escalation, advisory, resilience)
- All §2.3 canonical panel fields implemented in `panel()` helper

### DashboardAccessPolicy
- `listVisibleTeamScopes()`: TEAM / MANAGERIAL / ADMIN scopes derived from team membership and manager relationships
- `resolveTeamScope()`: validates user access to requested team
- `listAllowedPersonas()`: persona list derived from visibility scope rank
- Manager hierarchy: `managedTeamIds()` with recursive descendant team resolution

### DashboardsController
- `GET /dashboards/me/summary` with optional `?team_id=`
- Feature flag gate: `isDashboardsEnabled()` at route registration
- 403 returned when requested team scope is not visible to user

### StandaloneDashboardPage
- Feature flag gate on registration and render
- Access check uses `DashboardAccessPolicy.listVisibleTeamScopes()` instead of raw `manage_options`

### UI (Dashboards.tsx)
- `ServerPanel`, `ServerScope`, `ServerSummary` types
- `loadServerSummary()` bootstrap with response validation
- Scope selector dropdown in header
- Visibility badge showing active scope
- Persona tab gating by server-side `allowed_personas`
- ManagerView: 3 server-composed panels (escalation KPIs, advisory KPIs, resilience signals + generate action)
- SupportView: team queue + my queue KPIs, advisory signals panel
- `useMemo` / derived values above early returns (React hooks order)

### Styles (dashboard-styles.css)
- New classes: `pd-grid`, `pd-card`, `pd-card-header`, `pd-breakdown`, `pd-list`, severity border variants

## Spec compliance
All §2.3 canonical fields present. §2.4 invariants preserved (read-only, derived, scope-gated, no render side effects). §5.1 A–E implemented. §7 demo seed contract met (with today's seed fixes).

## Outstanding
- PM, Timesheets, Sales persona views are stubs (return placeholder panels)
- Scope selector is raw `<select>`, not styled to match tab buttons
- No panel drill-through to source entities

---

# 5. People Resilience Outputs

**Spec:** `PET_People_Resilience_Outputs_v1.md`

## Implemented

### Domain model
- `ResilienceAnalysisRun` entity with scope, version, summary, status
- `ResilienceSignal` entity with signal_type, severity, title, summary, employee_id, analysis_run_id
- Signal types implemented: utilisation overload, single-point-of-failure, workload concentration

### Persistence
- `ResilienceAnalysisRunRepository` and `ResilienceSignalRepository` interfaces
- SQL implementations wired in ContainerFactory

### Generation
- `GenerateResilienceAnalysisHandler` — explicit command path (§2.4D)
- Additive run history — new run does not destroy prior runs (§2.4B)
- Versioned analysis per team

### REST API
- `ResilienceController` registered in ApiRegistry

### Dashboard integration
- `resiliencePanel()` in `DashboardCompositionService` renders signals, severity, source summary, and "Generate" action
- Feature flag gated: `isResilienceIndicatorsEnabled()`

### Demo seed (fixed today)
- Now generates for On-Call, Support, and Delivery teams
- Work items distributed across team members with intentional concentration
- Two analysis runs per team for additive version history

## Spec compliance
§2.2 canonical concepts implemented. §2.3 fields covered. §2.4 invariants enforced. §2.8 API surface present.

## Outstanding
- No dedicated resilience admin page — only visible via dashboard panel
- No signal detail view with employee context
- No analysis run history browser

---

# 6. Support / Helpdesk Operational UX Completion

**Spec:** `PET_Support_Helpdesk_Operational_Completion_v1.md`

## Implemented

### Assignment model (Ticket.php)
- `assignToEmployee()` now clears `queueId` — enforces team XOR user (§3.1)
- `hasOperationalOwner()` method: returns true iff exactly one of queueId/ownerUserId is set

### Ticket lifecycle safety
- `canAcceptTimeEntries()` rewritten:
  - Rollup tickets: always false
  - Support tickets: false if closed, requires operational owner
  - Non-support: requires `in_progress` status
- `LogTimeHandler` error message updated to match

### CreateTicketHandler
- Enforces `exactly one operational owner (team XOR user)` — throws `DomainException`
- `DepartmentResolver` dependency removed — routing is now event-driven

### Command handlers
- `AssignTicketToTeamHandler`, `AssignTicketToUserHandler`, `PullTicketHandler` — dedicated handlers wired in ContainerFactory

### TicketController
- `assign-to-team`, `assign-to-employee`, `return-to-queue`, `reassign`, `pull` endpoints — all delegate to command handlers
- No inline work-item projection sync in controller (correct architectural boundary)

### Feature flag
- `isSupportOperationalImprovementsEnabled()` in FeatureFlagService
- `Support.tsx` gates to `<SupportOperational />` when flag is active

### ShortcodeRegistrar
- Refactored from raw `findByDepartmentUnassigned` to `WorkQueueVisibilityService` / `WorkQueueQueryService`

### Tests (TicketTimeLoggingTest.php)
- `testSupportAssignedToTeamAcceptsTime`
- `testSupportAssignedToUserAcceptsTime`
- `testSupportClosedRejectsTime`
- `testSupportUnassignedRejectsTime`

## Spec compliance
§3 assignment model enforced. §4 queue visibility server-side. §5 lifecycle safety implemented. §9 feature flag gating correct. §10 tests cover assignment legality and time-entry rules.

## Outstanding — highest priority
- `SupportOperational.tsx` needs full implementation:
  - Queue-first workflow (pull, return-to-queue, reassign actions)
  - Operational owner visibility (team-queued vs user-assigned)
  - SLA state per ticket (colour-coded urgency)
  - Manager oversight panels (§7: ticket distribution, SLA risk, backlog aging, workload per technician)
  - UX parity with the rest of PET (glass-panel styling, KPI strips, attention cards)
- `SupportOperationalController` endpoints need UI consumers

---

# 7. Feature Flag Discipline

## Implemented

### FeatureFlagService
- `isDashboardsEnabled()` and `isSupportOperationalImprovementsEnabled()` added
- Existing: `isEscalationEngineEnabled()`, `isAdvisoryEnabled()`, `isResilienceIndicatorsEnabled()`, `isQueueVisibilityEnabled()`, `isWorkProjectionEnabled()`

### AdminPageRegistry
- Exposes 4 flags to frontend JS: `resilience_indicators_enabled`, `dashboards_enabled`, `helpdesk_enabled`, `support_operational_improvements_enabled`

### Flag usage
- Route registration gated: DashboardsController, WorkController, StandaloneDashboardPage
- UI rendering gated: Support.tsx, Employees.tsx (utilisation), Dashboards.tsx (persona tabs)
- Panel composition gated: escalation, advisory, resilience panels in DashboardCompositionService
- Cron gated: AdvisoryGenerationJob

### Demo seed
- `seedFeatureFlags()` registers all flags
- `enableDemoFeatureFlags()` enables all 14 flags for `demo_full` profile
- `pet_dashboards_enabled` included in enable list

## No outstanding work

---

# 8. Demo Seed Improvements

## Implemented

### seedWorkOrchestration (fixed today)
- `containerToDept` now looks up numeric team IDs from `pet_teams` table
- Falls back to string names if teams not yet seeded

### seedResilience (fixed today)
- Generates work items and resilience analysis for On-Call, Support, and Delivery teams
- Distributed across team members with intentional lead concentration
- Two analysis runs per team for additive version history

### Existing seed coverage
- Escalation seeding: 3 escalations (open, acknowledged, resolved) via command handlers
- Advisory seeding: signals generated for Noah/Ava, customer advisory report for Acme
- Support: assigned/unassigned tickets, SLA timers, multi-customer coverage
- Work orchestration: work items for all open tickets with SLA scenarios

## No outstanding work — seed now produces complete dashboard-ready data on purge+reseed

---

# 9. Test Coverage

## Implemented

### New tests
- `EscalationPersistenceTest`: summary roundtrip, resolution note, resolved-cannot-acknowledge, SLA bridge noop, dedupe recovery
- `TicketTimeLoggingTest`: support assignment rules (team accepts, user accepts, closed rejects, unassigned rejects)
- `WorkItemTest`: assignment mode transitions, unrouted restriction

### Infrastructure
- `WpdbStub`: `get_row` respects `$output` and `$offset`; `get_results` handles ARRAY_A, ARRAY_N, OBJECT_K
- `tests/bootstrap.php`: `wpdb` class stub, WP constant stubs (ARRAY_A, ARRAY_N, OBJECT, OBJECT_K)

### Verification
- 324 tests, 685 assertions, all passing
- PHP lint: all 47 changed files clean
- TypeScript build: 927 modules, clean

## Outstanding
- No integration test for dashboard read-side safety (spec §6.1)
- No test for scope visibility restriction (spec §6.2)
- No test for queue visibility enforcement

---

# 10. Miscellaneous Changes

### BillingExport (BillingExport.php)
- `confirm()` idempotency guard — no-op if already confirmed
- `BillingController`: confirm endpoint added

### Delivery (AddTaskCommand/Handler)
- `roleId` parameter added for delivery-work routing via role-team mappings

### ProjectBlockEditor.tsx
- Phase move-up/move-down/delete buttons added

### Employees.tsx
- Utilisation fetch gated behind `resilience_indicators_enabled` flag

### SqlExternalMappingRepository
- `exists()` helper with safe table-existence check

### Repository constructors
- `SqlWorkItemRepository`, `SqlDepartmentQueueRepository`, `SqlAdvisorySignalRepository`, `SqlExternalMappingRepository`: constructor type hints relaxed from `\wpdb` to `$wpdb` (allows test stubs)

---

# 11. Summary of Outstanding Interface Work

Priority order:

1. **SupportOperational.tsx** — full implementation required. Backend complete, UI is a stub. Highest user-facing impact.
2. **Advisory.tsx** — needs signal list, report list, report detail, generation trigger.
3. **WorkItems.tsx polish** — missing item actions, SLA column, drill-through, human-readable labels.
4. **Resilience dedicated view** — currently only in dashboard panel. Optional standalone page.
5. **Escalations.tsx polish** — severity filter, formatted timestamps.
6. **Dashboard cosmetics** — scope selector styling, stub persona views.
