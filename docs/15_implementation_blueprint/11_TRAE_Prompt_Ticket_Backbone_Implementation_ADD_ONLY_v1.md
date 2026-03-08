STATUS: AUTHORITATIVE — IMPLEMENTATION REQUIRED
SCOPE: Ticket Backbone Correction
VERSION: v2
SUPERSEDES: v1
DATE: 2026-03-06

# TRAE Prompt — Ticket Backbone Implementation (ADD-only, scope-locked) (v2)

> Governed by `00_foundations/02_Ticket_Architecture_Decisions_v1.md`.

Copy/paste this entire prompt to TRAE.

```text
clear

YOU ARE EXECUTING A STRICT IMPLEMENTATION TASK FOR PET.

NON-NEGOTIABLES:
- ADDITIVE CHANGES ONLY unless explicitly permitted below.
- NO refactors for cleanliness.
- NO deletions of tables/fields/handlers.
- Backward compatibility is mandatory; users may skip versions.
- Forward-only, fail-fast migrations; no down migrations.
- Domain layer: no WP/DB access.
- UI layer: no business logic.
- Accepted quotes and submitted/locked time are immutable; corrections are additive.

OBJECTIVE:
Enforce the invariant: "All person work activity must be tied to a Ticket."

CURRENT DRIFT (FACT):
- Support Tickets exist (wp_pet_tickets).
- Delivery Tasks exist (wp_pet_tasks).
- Time Entries reference task_id only (wp_pet_time_entries has no ticket_id).
- Quotes create delivery tasks on acceptance; no ticket linkage.

SCOPE:
Implement the Ticket Backbone bridging plan defined in docs:
- 00_foundations/02_Ticket_Architecture_Decisions_v1 (BINDING DECISIONS)
- 01_Ticket_Backbone_Principles_and_Invariants_v1 (v2)
- 02_Ticket_Data_Model_and_Migrations_v1 (v2)
- 04_Quote_to_Ticket_to_Project_Flow_v1 (v2)
- 05_Time_Entry_Ticket_Enforcement_v1 (v2)
- 09_Backward_Compatibility_and_Transition_Plan_v1

DO NOT modify documentation.

------------------------------------------
STEP 1 — MIGRATIONS (ADD ONLY)
------------------------------------------
Create forward-only migrations to:

1) Extend wp_pet_tickets with additive columns required for:
- primary_container
- lifecycle_owner
- project_id
- quote_id
- phase_id
- parent_ticket_id
- is_rollup
- billing_context_type
- agreement_id
- required_role_id
- department_id
- sold_minutes (or sold_minutes equivalent)
- estimated_minutes (or equivalent)
Add indexes where needed.

2) Create wp_pet_ticket_links table.

3) Add ticket_id (nullable) to wp_pet_time_entries with index.

4) Add ticket_id (nullable) to wp_pet_tasks with index/unique as appropriate.

5) Add ticket_id (nullable) to wp_pet_quote_tasks (set at acceptance, NOT during quoting).
6) Add change_order_source_ticket_id (nullable) to wp_pet_tickets.
7) Add is_baseline_locked (TINYINT(1) NOT NULL DEFAULT 0) to wp_pet_tickets.

Migrations must be idempotent:
- Use column existence checks.
- Do not assume a clean environment.

------------------------------------------
STEP 2 — BACKFILL (SAFE, IDEMPOTENT)
------------------------------------------
Implement an application-level backfill service and a WP-CLI command to run it manually.

Backfill rules:
A) For each existing wp_pet_tasks row where ticket_id is NULL:
- Create a corresponding wp_pet_tickets row (lifecycle_owner='project', primary_container='project').
- Set tasks.ticket_id to created ticket id.
- Do NOT alter task fields.
- Store mapping evidence in task.malleable_data if needed.

B) For each existing wp_pet_time_entries row where ticket_id is NULL:
- If its task_id maps to a task with ticket_id, set time_entries.ticket_id.
- Otherwise leave NULL and annotate time_entries.malleable_data with a reconciliation marker.

Never change:
- start_time, end_time, duration_minutes, submitted_at, status history.

------------------------------------------
STEP 3 — TIME SUBMISSION ENFORCEMENT (DOMAIN + HANDLER)
------------------------------------------
Modify SubmitTimeEntryHandler and/or TimeEntry domain logic to enforce:

- If ticket_id is NULL on submit:
  - Attempt resolve via task_id -> task.ticket_id.
  - If still NULL: hard error.

- Reject submission if ticket is roll-up (is_rollup=1) or has children.

Add integration tests proving:
- Cannot submit without ticket_id unless resolvable.
- Cannot submit against roll-up tickets.
- Existing time entries can still be listed/read without ticket_id.

------------------------------------------
STEP 4 — QUOTE ACCEPTANCE → TICKET CREATION
------------------------------------------
NO DRAFT TICKETS DURING QUOTING. Tickets are created at acceptance only.

On QuoteAccepted:

- For each labour QuoteTask on the accepted quote:
  - Create ONE ticket in wp_pet_tickets with:
    - sold_minutes = snapshotted duration (immutable from this point)
    - sold_value_cents = snapshotted sell value (immutable)
    - is_baseline_locked = 1
    - lifecycle_owner = 'project'
    - primary_container = 'project'
    - status = 'planned'
    - root_ticket_id = self
    - project_id = project being created
  - Store ticket_id on the QuoteTask record.

What does NOT happen:
- No "baseline ticket" created as separate record.
- No "execution ticket clone" created.
- No ticket_mode set.

Maintain backward compatibility:
- Existing CreateProjectFromQuoteListener currently creates delivery tasks.
- You MAY keep creating delivery tasks, but they must be linked:
  - tasks.ticket_id must be set to the corresponding ticket.

Hard requirements:
- After acceptance, sold_minutes and sold_value_cents are immutable.
- is_baseline_locked = 1 on all tickets created from accepted quotes.

------------------------------------------
STEP 6 — WORK ITEM PROJECTION ALIGNMENT
------------------------------------------
Ensure WorkItemProjector covers:
- Project tickets created at acceptance (not only support tickets)
so department queues and assignment work consistently.

Do NOT remove support behavior.

------------------------------------------
STEP 7 — TESTS
------------------------------------------
Add integration tests for:
- Backfill idempotency.
- Task -> ticket mapping created once.
- Time submit enforcement.
- Quote acceptance creates one ticket per sold labour item with locked sold_minutes.
- No tickets created during quoting (verify no ticket_id on quote tasks before acceptance).
- tasks.ticket_id mapping set correctly.

Output:
- A single report in PR description listing:
  - migrations added
  - services/commands added
  - tests added
  - how to run backfill command
  - how to verify in Admin UI (if applicable)

END.
```
