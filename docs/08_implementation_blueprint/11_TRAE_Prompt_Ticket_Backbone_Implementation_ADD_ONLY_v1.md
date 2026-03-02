STATUS: AUTHORITATIVE — IMPLEMENTATION REQUIRED
SCOPE: Ticket Backbone Correction
VERSION: v1

# TRAE Prompt — Ticket Backbone Implementation (ADD-only, scope-locked)

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
- 01_Ticket_Backbone_Principles_and_Invariants_v1
- 02_Ticket_Data_Model_and_Migrations_v1
- 04_Quote_to_Ticket_to_Project_Flow_v1
- 05_Time_Entry_Ticket_Enforcement_v1
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

5) Add ticket_id (nullable) to wp_pet_quote_tasks (or the labour quote task table in use) for draft linkage.

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
STEP 4 — QUOTE DRAFT TICKET CREATION (LABOUR ONLY)
------------------------------------------
Implement draft ticket creation for labour quote tasks:

When a labour quote task is created (or updated) in quote builder:
- Ensure a wp_pet_tickets row exists with quote_id set and status 'draft_quote' (or mapped).
- Persist ticket_id onto the quote task row.
- Ensure idempotency (do not create duplicates).

NOTE:
Do NOT redesign the quote builder UI. Implement at the application/service layer where quote tasks are created.

------------------------------------------
STEP 5 — QUOTE ACCEPTANCE → PROJECT TICKETS
------------------------------------------
On QuoteAccepted:

- For each labour quote task with ticket_id:
  - Create baseline lock semantics:
    - Either mark existing ticket as baseline_locked OR create a baseline copy.
  - Create project execution tickets (clones) as needed and link them to the project.

Maintain backward compatibility:
- Existing CreateProjectFromQuoteListener currently creates delivery tasks.
- You MAY keep creating delivery tasks, but they must be linked:
  - tasks.ticket_id must be set to the corresponding project execution ticket.

Hard requirement:
- After acceptance, baseline sold values are immutable.

------------------------------------------
STEP 6 — WORK ITEM PROJECTION ALIGNMENT
------------------------------------------
Ensure WorkItemProjector covers:
- Project execution tickets (not only support tickets)
so department queues and assignment work consistently.

Do NOT remove support behavior.

------------------------------------------
STEP 7 — TESTS
------------------------------------------
Add integration tests for:
- Backfill idempotency.
- Task -> ticket mapping created once.
- Time submit enforcement.
- Quote labour draft ticket creation.
- Quote acceptance creates linked project tickets and tasks.ticket_id mapping.

Output:
- A single report in PR description listing:
  - migrations added
  - services/commands added
  - tests added
  - how to run backfill command
  - how to verify in Admin UI (if applicable)

END.
```
