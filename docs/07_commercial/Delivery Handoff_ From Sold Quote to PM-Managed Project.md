# Delivery Handoff: From Sold Quote to PM-Managed Project
STATUS: PROPOSAL — FOR TEAM DISCUSSION
DATE: 2026-03-06
## Purpose
This document describes what needs to happen between a quote being accepted ("sold") and delivery work beginning. It is intended for team review before implementation starts.
## Where We Are Today
### What's built and working
1. **Quote → Accept button** — When a quote is in `sent` state, a salesperson clicks "Accept Quote" in the UI (`QuoteDetails.tsx`). This triggers `AcceptQuoteHandler`.
2. **AcceptQuoteHandler** (built today) — On acceptance, within a single transaction:
    * Quote state transitions to `accepted` (immutable from this point)
    * `QuoteAccepted` event fires
    * `CreateProjectFromQuoteListener` creates a `Project` entity with `source_quote_id`, `soldHours`, `soldValue`
    * `createTicketsFromQuote()` creates one Ticket per sold labour item with locked `sold_minutes`, `sold_value_cents`, `is_baseline_locked = 1`, `lifecycle_owner = 'project'`, `status = 'planned'`
    * Payment schedule milestones fire due events as applicable
3. **Ticket entity** — Fully supports both support and project contexts. Backbone fields are all in place: `sold_minutes`, `estimated_minutes`, `is_rollup`, `parent_ticket_id`, `root_ticket_id`, `change_order_source_ticket_id`, project lifecycle state machine (`planned → ready → in_progress → blocked → done → closed`).
### What's NOT built (the gap)
1. **ProjectDetails.tsx is legacy** — Still renders old `Task` entities from `wp_pet_tasks`. Does not show the Tickets created on acceptance. The "Add Task" form creates old Tasks, not tickets.
2. **No PM assignment** — `Project` entity has no `assignedPmId` field. No UI for assigning a PM.
3. **No delivery review step** — No workflow where a PM reviews the sold scope (tickets) and confirms or refines before work begins.
4. **No WBS splitting** — Schema supports it (`parent_ticket_id`, `root_ticket_id`, `is_rollup`), API endpoint and handler do not exist yet.
5. **No ticket status transitions in project context** — The domain supports `planned → ready → in_progress → done → closed`, but the project UI has no way to trigger these transitions.
## Proposed Flow: What Delivery Looks Like
Based on existing documentation (references below), the handoff should work as follows:
### Step 1: Sale completes (ALREADY BUILT)
Salesperson clicks "Accept Quote". System creates Project + Tickets. Quote becomes immutable.
### Step 2: PM Assignment
A project needs an owner. When a project is created from a quote, it should be assignable to a PM.
**What this requires:**
* Add `pm_employee_id` (nullable BIGINT) to `wp_pet_projects` schema
* Add `pmEmployeeId` to the `Project` domain entity
* UI in the Projects list or ProjectDetails to assign a PM (dropdown of active employees)
* Optional: notification/event when a PM is assigned (`ProjectPmAssigned`)
**Design question for team:** Should PM assignment happen automatically (e.g. based on customer, team, or quote owner)? Or is it always a manual step? The simplest starting point is manual assignment.
### Step 3: PM Reviews Sold Scope
The PM opens the project and sees the tickets that were created from the accepted quote. Each ticket shows:
* Subject (from quote task title)
* Sold hours (`sold_minutes / 60`) — read-only, locked
* Sold value (`sold_value_cents / 100`) — read-only, locked
* Estimated hours (`estimated_minutes / 60`) — editable by PM
* Status (initially `planned`)
* Required role (from quote snapshot)
* Assignment (initially unassigned)
**What this requires:**
* Replace the old Task-based view in `ProjectDetails.tsx` with a ticket-based view
* Fetch tickets via `GET /pet/v1/tickets?lifecycle_owner=project&project_id={id}` (this filtering already works per `Sold_Ticket_Structural_Spec_v1.md` §1.6)
* Render each ticket with sold baseline prominently displayed alongside editable operational fields
* Remove the old "Add Task" form (or repurpose it — see Step 4)
**Key principle from docs:** "Planning may evolve, sold totals may not" (`docs_04_features_project_delivery.md`). The PM can adjust `estimated_minutes` and add detail, but `sold_minutes` and `sold_value_cents` are always visible and immutable.
### Step 4: PM Refines / Adds Detail (WBS Splitting)
If a sold ticket is too coarse (e.g. "Frontend Development — 40 hours"), the PM can split it into more granular child tickets.
Per `02_Ticket_Architecture_Decisions_v1.md` Decision 9 and `04_Quote_to_Ticket_to_Project_Flow_v1.md` §WBS Expansion:
* Parent ticket becomes `is_rollup = 1` (no longer accepts time entries)
* Child tickets get `parent_ticket_id` = parent, `root_ticket_id` = the original sold root
* Children's `estimated_minutes` are allocated from the parent's budget
* Variance = parent's `sold_minutes` minus `SUM(estimated_minutes)` on all leaf descendants
* Splitting depth is unlimited
**What this requires:**
* New API endpoint: `POST /pet/v1/tickets/{id}/split` with `SplitTicketCommand`/`SplitTicketHandler`
* UI: a "Split" action on any leaf project ticket, allowing the PM to define child tickets with titles, estimated hours, and role assignments
* Variance display: show `sold_minutes - SUM(leaf estimated_minutes)` on the parent ticket row
### Step 5: PM Transitions Tickets to "Ready"
Once the PM has reviewed and optionally refined the scope, they move tickets from `planned` → `ready`, signalling that work can begin.
**What this requires:**
* Status transition buttons/controls on each ticket in the project view
* The domain already validates transitions via `Ticket::transitionStatus()` — the UI just needs to call `PUT /pet/v1/tickets/{id}` with the new status
### Step 6: Work Begins (Time Logging)
Team members log time against leaf tickets. This is largely built (timesheets, `ticket_id` on time entries). The constraint that only leaf tickets accept time (`canAcceptTimeEntries()`) is already enforced in the domain.
## What Changes Per Existing Entity
### Project entity (`Project.php`)
* Add: `pm_employee_id` (nullable)
* Consider: Remove `tasks` array (old Task[] relationship) once ticket view is the primary interface — or keep for backward compat during transition
### ProjectDetails.tsx
* Replace: Task table → Ticket table (fetched from tickets API, filtered by `project_id`)
* Add: PM assignment control
* Add: Sold vs estimated variance display per ticket
* Add: Ticket status transition controls
* Add: WBS split action
* Remove: Old "Add Task" form
### New API endpoints needed
* `POST /pet/v1/tickets/{id}/split` — WBS splitting
* `PUT /pet/v1/projects/{id}` — needs to support `pmEmployeeId` update
### New domain classes needed
* `SplitTicketCommand` / `SplitTicketHandler`
* Migration for `pm_employee_id` on projects
## Suggested Implementation Order
1. **Wire ProjectDetails to show Tickets** — Immediate bridge. Replace Task table with ticket-based rendering. This makes the current state visible without adding new features.
2. **Add PM assignment** — Field on Project + dropdown in UI. Simple and standalone.
3. **Add ticket status transitions in project view** — Buttons to move tickets through the project lifecycle (`planned → ready → in_progress → done → closed`).
4. **Build WBS splitting** — `POST /tickets/{id}/split` endpoint + UI. This is the PM's primary refinement tool.
5. **Variance dashboard** — Show sold vs estimated vs actual at project and ticket level.
## Open Questions for Team
1. **PM assignment: manual or automatic?** Starting manual is simplest, but should we plan for routing rules (e.g. by customer account manager, team, or round-robin)?
2. **Old Task entity: deprecate now or keep?** `ProjectDetails.tsx` currently renders Tasks. Once we wire tickets, do we immediately drop the old Task path, or run both for a transition period?
3. **Ticket assignment: per-ticket or per-role?** Sold tickets carry `required_role_id`. Should the PM assign individuals to specific tickets, or assign a person to a role and let tickets auto-populate?
4. **"Ready for delivery" gate:** Should there be a project-level state transition (e.g. `planned → active`) that the PM triggers after reviewing all tickets? Or is it sufficient that individual tickets move to `ready`?
5. **Notification/handoff:** When a quote is accepted and a project is created, how does the PM know? Email? Dashboard item? Just check the project list?
## Reference Documents
* `docs/00_foundations/02_Ticket_Architecture_Decisions_v1.md` — Binding architecture decisions (10 decisions)
* `docs/00_foundations/01_Ticket_Backbone_Principles_and_Invariants_v1.md` (v2) — Core invariants
* `docs/03_commercial/04_Quote_to_Ticket_to_Project_Flow_v1.md` (v2) — Full acceptance→ticket→project flow including WBS and change orders
* `docs/03_commercial/05_Sold_Ticket_Structural_Spec_v1.md` — Sold ticket fields, invariants, lifecycle, prohibited behaviours, stress tests
* `docs/04_features/docs_04_features_project_delivery.md` — Delivery principles ("planning may evolve, sold totals may not")
* `docs/04_features/docs_04_features_commercial_transition_rules.md` — Acceptance sequence and WBS mapping
* `docs/Planning/PET_Lifecycle_Gap_Analysis_v1_0.md` — Current state assessment
