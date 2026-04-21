STATUS: AUTHORITATIVE — UPDATED 2026-04-20
SCOPE: Lead → Quote → Sales → Delivery → Ticket Lifecycle
VERSION: v1.1
AUTHOR: Oz (AI Agent) / Steve Cope
DATE: 2026-03-06
UPDATED: 2026-04-20 — Ticket backbone application layer is now IMPLEMENTED. All Task/Ticket claims corrected.

# PET Lifecycle Gap Analysis v1.0

## Purpose

This document is a candid assessment of how closely the current PET codebase and documentation
aligns with the intended end-to-end lifecycle:

> **Lead → Quote → Sales Process (Won/Lost) → Purchasing (products) → Project/Delivery (services) → Tickets**

It is intended as a shared reference for the development team and stakeholders before further
development begins on any of these connected areas.

---

## Executive Summary

PET has strong foundations in several of these areas independently, but the connective tissue
between them is incomplete. The core vision — that a **single Ticket entity** acts as the
universal work unit spanning quoting, sales, delivery, and support — is **documented
comprehensively** and **now substantially implemented at the application layer**.

**UPDATE 2026-04-20:** Gap 1 below has been resolved. Quote acceptance now creates Tickets.
The two remaining significant gaps are:

1. ~~Quote acceptance still creates **legacy Tasks**, not Tickets.~~ **RESOLVED** — `AcceptQuoteHandler`
   now calls `CreateProjectTicketHandler` which creates `Domain\Support\Entity\Ticket` records
   with all backbone fields populated. `AddTaskHandler` is disabled (throws `DomainException`).
2. The **CRM sales pipeline** exists only as the Quote state machine. There is no Opportunity
   entity, no structured qualification stage, and Leads currently require a Customer (contrary
   to documented intent).

Below is a breakdown by area.

---

## 1. The Ticket Unification Question

### What the vision says (updated per architecture decisions v1)
> Support tickets and project delivery tickets are the **same entity**. "type/context" is
> metadata, not a separate table. All person work activity must be tied to a Ticket.
> **No tickets are created during quoting.** On acceptance, one ticket per sold labour item
> is created with immutable `sold_minutes`. There is no baseline/execution clone model.
> Change orders create new tickets linked via `change_order_source_ticket_id`.

*(Source: `docs/00_foundations/02_Ticket_Architecture_Decisions_v1.md`,
`docs/00_foundations/01_Ticket_Backbone_Principles_and_Invariants_v1.md` (v2),
`docs/07_commercial/04_Quote_to_Ticket_to_Project_Flow_v1.md` (v2))*

### What is implemented

**Schema layer (done):**
- `wp_pet_tickets` has the full backbone extension: `primary_container`, `lifecycle_owner`,
  `project_id`, `quote_id`, `phase_id`, `parent_ticket_id`, `root_ticket_id`, `ticket_kind`,
  `sold_minutes`, `estimated_minutes`, `is_rollup`, `billing_context_type`, etc.
- `wp_pet_ticket_links` cross-context table exists.
- `wp_pet_time_entries` now has `ticket_id` (legacy `task_id` has been migrated away).
- `wp_pet_tasks` has a `ticket_id` bridge column.
- Work items source_type no longer includes `project_task`; it is now `ticket`-only.

**Domain entity layer (done):**
- `Domain\Support\Entity\Ticket` carries all backbone fields with correct getters,
  `canAcceptTimeEntries()`, and `transitionStatus()` with lifecycle-governed validation.
- Three lifecycle contexts work: `support` (new→open→resolved→closed),
  `project` (planned→ready→in_progress→done→closed), `internal`.

**Application / integration layer (NOW DONE — updated 2026-04-20):**
- `AcceptQuoteHandler` calls `createTicketsFromQuote()` → `CreateProjectTicketHandler` →
  creates `Domain\Support\Entity\Ticket` records with all backbone fields:
  `soldMinutes`, `estimatedMinutes`, `projectId`, `quoteId`, `isRollup`, `isBaselineLocked=true`,
  `lifecycleOwner='project'`, `primaryContainer='project'`, `billingContextType='project'`.
- Rollup tickets are created when a component has multiple tasks; child tickets reference the rollup via `parentTicketId`.
- `CreateProjectFromQuoteListener` creates the **Project record only** — it has an explicit
  comment: "do NOT create legacy Task entities here."
- `AddTaskHandler::handle()` throws `DomainException('Legacy project task creation is disabled
  in tickets-only delivery execution. Use project tickets.')` — Task creation is disabled.
- `Domain\Delivery\Entity\Task` still exists as a file but is **dead code** — nothing calls
  `AddTaskHandler` except tests. It will be removed in Phase 8.
- `wp_pet_tasks` table still exists (referenced in `DemoPurgeService`). Bridge column
  `ticket_id` was intended for backfill (Phase 2) but the table is no longer written to
  by any active handler.
- `LogTimeHandler` enforces `canAcceptTimeEntries()` against Tickets (Phase 3 done).
- `WorkItemProjector::onTicketCreated()` handles project ticket creation — no `onProjectTaskCreated` handler remains.

**Remaining application layer work:**
- WBS ticket splitting (breakdown post-acceptance): parent/child hierarchy is supported in schema but no creation path exists
- Phase 6: SLA agreement/entitlement integration for delivery tickets
- Admin UI (Phase 7): `Project` in `types.ts` still has `tasks: Task[]` — admin project view needs updating to show Tickets
- Phase 8 (optional): Remove `Domain\Delivery\Entity\Task`, `AddTaskHandler`, `AddTaskCommand`, and eventually drop `wp_pet_tasks`

### Current reality in one sentence
Quote acceptance creates Tickets correctly. The delivery backbone is operational. What remains
is WBS splitting, SLA for project tickets, admin UI cutover, and legacy code cleanup.

### Contradictions in the docs (resolved as of 2026-04-20)
- `docs/15_implementation_blueprint/Ticket_Backbone_Planning_State_v1.md` — updated separately.
- `docs/05_data_model/02_Ticket_Data_Model_and_Migrations_v1.md` — schema accurate; application layer now also done.
- The draft ticket / baseline+execution clone contradiction has been resolved by
  `00_foundations/02_Ticket_Architecture_Decisions_v1.md`.

### Estimated completion: ~75%

---

## 2. Lead → Quote (CRM Pipeline)

### What the vision says
> CRM-style pipeline: Lead → Qualification → Opportunity → Quote → Won/Lost.
> Leads are permissive (no Customer required). Qualification is a separate entity with
> schema-driven required fields. Opportunities have Gold/Silver/Bronze classification.
> Skipping stages is forbidden.

*(Source: `docs/06_features/crm_leads_to_opportunities.md`)*

### What is implemented

**Done:**
- `Domain\Commercial\Entity\Lead` exists with states: `new` → `qualified` → `converted` /
  `disqualified`.
- `POST /pet/v1/leads/{id}/convert` — direct Lead → Quote conversion is working.
- `Quote.leadId` — quotes carry a reference back to their source lead (nullable; a quote
  can be created without a lead for existing clients — correct).
- Sales Dashboard (`PET_Sales_Dashboard_v1.md`) is implemented: pipeline value, quotes sent,
  win rate, active leads, avg deal size, aging follow-up items.

**NOT done:**
- **Opportunity entity** — does not exist. The CRM doc explicitly marks it as future roadmap.
- **Qualification record** — does not exist as a separate entity or schema.
- **Gold/Silver/Bronze classification** — not implemented.
- **Structured pipeline stages** — the "sales process" is entirely the Quote state machine
  (draft → sent → accepted/rejected). There are no intermediate pipeline stages between
  Lead and Quote.
- **Lead without Customer** — the `Lead` entity has a required `$customerId` in its
  constructor and the `CreateLeadCommand` requires `customerId`. The documentation says
  leads should not require a Customer. This is a contradiction between code and docs.

### Current reality in one sentence
We have a functional but shallow CRM: capture a lead, convert it to a quote, track the
quote through to won/lost. The richer sales pipeline (opportunity stages, qualification
workflow, pre-sales effort tracking) is documented but not built.

### Estimated completion: ~45%

---

## 3. Quote → Project → Delivery

### What the vision says (updated per architecture decisions v1)
> On acceptance, one ticket per sold labour item is created with immutable `sold_minutes`.
> No draft tickets during quoting. No baseline/execution clone. The sold ticket can be
> split into WBS children (unlimited depth). Time is logged against leaf tickets only.
> Change orders create new tickets linked via `change_order_source_ticket_id`.

*(Source: `docs/07_commercial/04_Quote_to_Ticket_to_Project_Flow_v1.md` (v2))*

### What is implemented

**Done:**
- `QuoteAccepted` event → `CreateProjectFromQuoteListener` → `Project` is created with
  `source_quote_id` set correctly.
- `ImplementationComponent` milestones/tasks are iterated and converted to project tasks
  with role and hours.
- Project has `soldHours` and `soldValue` from the quote (immutable constraint respected).

**Done (updated 2026-04-20):**
- `AcceptQuoteHandler` creates the Project (via `CreateProjectFromQuoteListener` on `QuoteAccepted` event)
  and Tickets (via `createTicketsFromQuote()` → `CreateProjectTicketHandler`).
- `ImplementationComponent` milestones/tasks are iterated; each task row becomes a Ticket with locked
  `soldMinutes`. Multi-task components get a rollup Ticket with children.
- `OnceOffServiceComponent` units are similarly provisioned as Tickets (rollup + children pattern).

**NOT done:**
- `CatalogComponent` items (products, software, subscriptions) do not generate any
  delivery artefacts on acceptance. Products vanish after the quote is accepted from
  a delivery perspective.
- The `SimpleUnit` component type (used in the block-based quoting UX) similarly produces
  no delivery artefacts.
- WBS hierarchy (parent/child tickets) is supported in the schema but no post-acceptance
  *splitting* path exists — the rollup/child structure provisioned at acceptance is fixed.
- Note: Draft ticket creation during quoting is NOT required per architecture decisions.
  No baseline/execution clone needed.

### Current reality in one sentence
Quote acceptance creates a Project record plus a Ticket tree (rollup + child tickets) with
locked `soldMinutes`. The delivery backbone is functional. WBS post-sale splitting and catalog
product delivery artefacts are the remaining gaps.

### Estimated completion: ~75%

---

## 4. Purchasing / Products

*(Parked per agreement — noted for completeness)*

There is a `docs/05_data_model/procurement_intent_schema.md` and
`docs/21_supplier_governance/` covering procurement intent. The schema tables exist
(`CreateBillingExportTables`, etc.). However, the link between accepted product quote lines
and any procurement workflow is not implemented. CatalogProducts exist in the quote but
generate no downstream purchasing actions on acceptance.

### Estimated completion: ~10%

---

## 5. Support Tickets vs Delivery Tickets — Final Verdict

The single most important question: **are they the same thing?**

**Answer: YES — as of 2026-04-20, they are unified at the application layer.**

The `wp_pet_tickets` table serves both roles. The Ticket domain entity handles both
`lifecycle_owner='support'` and `lifecycle_owner='project'` with separate state machines.

**Current reality:**
- Support tickets are created via `CreateTicketCommand` → `wp_pet_tickets` with `lifecycle_owner='support'`.
- Project (delivery) tickets are created via `AcceptQuoteHandler` → `CreateProjectTicketHandler` → `wp_pet_tickets`
  with `lifecycle_owner='project'`.
- `LogTimeHandler` enforces `canAcceptTimeEntries()` against the Ticket entity for both contexts.
- `WorkItemProjector::onTicketCreated()` handles project ticket creation — no separate `onProjectTaskCreated` path remains.

**Remaining disconnection (minor):**
- `wp_pet_tasks` table still exists (never written to by active code) — decommission is Phase 8.
- Admin project UI (`types.ts` `Project.tasks: Task[]`) still references the legacy Task shape — needs updating to show Tickets.
- WBS post-sale splitting is not yet implemented.

~~This is the root issue.~~ **This issue is resolved.**

---

## 6. What Works Well (to be preserved)

- The **Quote builder** is sophisticated: versioned, block/section/component model,
  payment schedule validation, immutability on acceptance. This is solid and should not
  be disrupted.
- The **Support helpdesk** path (ticket creation, SLA automation, escalation, assignment)
  is well-implemented and tested.
- The **Ticket domain entity** is already complete enough to serve both contexts — it
  does not need to be rebuilt, only wired differently.
- The **Event bus** is working. The `CreateProjectFromQuoteListener` pattern is correct —
  it just needs to emit Tickets instead of Tasks.
- The **Lead → Quote conversion** flow works correctly for the current scope.
- The **Sales dashboard** provides real commercial visibility.

---

## 7. Priority Order for Alignment (updated 2026-04-20)

Based on impact and dependency chain:

1. ~~**Close the Task/Ticket split at the application layer.**~~ **DONE as of 2026-04-20.**
   `AcceptQuoteHandler` creates Tickets via `CreateProjectTicketHandler`. `AddTaskHandler` is disabled.

2. ~~**Add draft ticket creation during quoting.**~~ **REMOVED per architecture decisions.**
   No tickets during quoting. Quote builder manages its own task records.

3. **Wire the Lead entity correctly.**
   Remove the hard `customerId` requirement from Lead creation. A lead can reference a
   customer optionally; it should not require one.

4. **Implement WBS ticket splitting.**
   Post-acceptance splitting of sold tickets into child tickets is not yet implemented.
   Schema supports it; no creation path exists.

5. **Decide on Opportunity before building pipeline stages.**
   The choice of whether to add an Opportunity entity between Lead and Quote is an important
   product decision. It does not need to be resolved immediately, but it needs an explicit
   decision before further CRM investment.

6. **Admin UI cutover (Phase 7):** Update `types.ts` `Project.tasks: Task[]` to reference
   Tickets. Update admin project view to show delivery tickets, not the legacy task list.

7. **Legacy code cleanup (Phase 8):** Remove `Domain\Delivery\Entity\Task`, `AddTaskHandler`,
   `AddTaskCommand`. Eventually drop `wp_pet_tasks` table via a forward migration.

8. **Procurement linkage** — deferred, as agreed.

---

## 8. Documentation Hygiene Required

The following documents are stale or contradictory and should be updated before development:

| Document | Issue |
|---|---|
| `docs/15_implementation_blueprint/Ticket_Backbone_Planning_State_v1.md` | Says "Development has NOT yet begun" — migrations have been done |
| `docs/05_data_model/02_Ticket_Data_Model_and_Migrations_v1.md` | M1–M5 marked ✅ but app layer not done; misleading |
| `docs/06_features/crm_leads_to_opportunities.md` | Says leads don't require Customer; code contradicts this |

---

## Appendix: Completion Summary (updated 2026-04-20)

| Area | Schema | Domain Entity | Application Layer | UI/API |
|---|---|---|---|---|
| Ticket unification | ✅ Done | ✅ Done | ✅ Done (2026-04-20) | ⚠️ Partial (admin UI still shows legacy Task type) |
| Lead → Quote | ✅ Done | ✅ Done | ✅ Done | ✅ Done |
| Sales pipeline (Opportunity) | ❌ | ❌ | ❌ | ❌ |
| Quote draft tickets | N/A — removed per architecture decisions | | | |
| Quote acceptance → tickets | ✅ Done | ✅ Done | ✅ Done | ⚠️ Portal: done; Admin: WBS view pending |
| Delivery ticket lifecycle | ✅ Done | ✅ Done | ✅ Wired | ⚠️ Time logging works; WBS splitting not yet |
| WBS post-sale splitting | ✅ Schema | ✅ Ready | ❌ Not implemented | ❌ |
| Purchasing intent | ⚠️ Partial | ❌ | ❌ | ❌ |
| Legacy Task cleanup | — | ❌ Dead code present | ❌ Table still exists | — |

---

*This document was reviewed and approved by Steve Cope. Updated 2026-04-20 to reflect that the ticket backbone application layer is now implemented. The priority order in Section 7 reflects the current state.*
