STATUS: AUTHORITATIVE — FOR REVIEW AND ALIGNMENT
SCOPE: Lead → Quote → Sales → Delivery → Ticket Lifecycle
VERSION: v1.0
AUTHOR: Oz (AI Agent) / Steve Cope
DATE: 2026-03-06

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
comprehensively** but **only partially implemented**. The two most significant gaps are:

1. Quote acceptance still creates **legacy Tasks**, not Tickets. The ticket backbone schema
   is in place but the application layer has not been updated to use it.
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

**Application / integration layer (NOT done):**
- `Application\Delivery\Listener\CreateProjectFromQuoteListener` still creates
  `Domain\Delivery\Entity\Task` objects — NOT Tickets.
- `Domain\Delivery\Entity\Task` is a thin, anemic entity: name, estimatedHours,
  completed, roleId only. No status machine, no lifecycle, no SLA, no assignment.
- `wp_pet_tasks` still exists as a live table (was dropped and recreated). The `ticket_id`
  bridge column exists but is **never populated by application code**.
- Ticket creation on quote acceptance is **not implemented**. The listener must be
  updated to create tickets (one per sold labour item, with locked `sold_minutes`).
- WBS ticket splitting (breakdown post-sale) is **not implemented**.
- Note: Draft ticket creation during quoting is NOT required per architecture decisions.
  No baseline/execution clone model. Tickets are created at acceptance only.

### Current reality in one sentence
The database is ready to be ticket-first, but every project created from a quote still
produces old-style Tasks with no ticket linkage. The two worlds are connected by bridge
columns that are never written to.

### Contradictions in the docs (partially resolved)
- `docs/15_implementation_blueprint/Ticket_Backbone_Planning_State_v1.md` says "Development has NOT yet begun"
  — this is **stale**. The schema migrations have been done. That doc needs updating.
- `docs/05_data_model/02_Ticket_Data_Model_and_Migrations_v1.md` says STATUS: IMPLEMENTED
  for M1–M5 — this is accurate for the schema but **misleading** because the application
  layer has not been updated.
- **Resolved:** The draft ticket / baseline+execution clone contradiction has been resolved
  by `00_foundations/02_Ticket_Architecture_Decisions_v1.md`. All affected docs have been
  updated to v2.

### Estimated completion: ~35%

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

**NOT done:**
- The listener creates `Domain\Delivery\Entity\Task[]` — not Tickets. The quote acceptance
  path has **zero ticket creation**. Must be updated to create one ticket per sold labour
  item with locked `sold_minutes` and `is_baseline_locked = 1`.
- `CatalogComponent` items (products, software, subscriptions) do not generate any
  delivery artefacts on acceptance. Products vanish after the quote is accepted from
  a delivery perspective.
- The `SimpleUnit` component type (used in the block-based quoting UX) similarly produces
  no delivery artefacts.
- WBS hierarchy (parent/child tickets) is supported in the schema but no creation path
  exists to populate it.
- Note: Draft ticket creation during quoting is NOT required per architecture decisions.
  No baseline/execution clone needed.

### Current reality in one sentence
Quote acceptance creates a Project record and a flat list of Tasks with no lifecycle, but
the ticket-based delivery spine does not exist at all — the documented flow is entirely
aspirational at the application layer.

### Estimated completion: ~25%

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

**Answer: They SHOULD be, and the schema says they ARE, but in practice they are not.**

The `wp_pet_tickets` table is capable of serving both roles (the backbone columns are there).
The Ticket domain entity handles both `lifecycle_owner='support'` and
`lifecycle_owner='project'` with separate state machines.

However:
- The application path that creates project work (quote acceptance) **still creates Tasks**,
  not Tickets.
- Support tickets are created via `CreateTicketCommand` → `wp_pet_tickets` with
  `lifecycle_owner='support'`.
- Project tasks are created via `CreateProjectHandler` → `wp_pet_tasks` with
  `lifecycle_owner` effectively nonexistent.
- These two populations are entirely disconnected at the application layer.

There are two separate `$wpdb->query` implementations serving them, two separate
controller paths, and zero cross-wiring. The bridge column (`tasks.ticket_id`) exists but
is empty.

**This is the root issue.** Everything else in the delivery lifecycle depends on resolving
this split.

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

## 7. Priority Order for Alignment

Based on impact and dependency chain:

1. **Close the Task/Ticket split at the application layer.**
   The listener that handles `QuoteAccepted` must create Tickets (lifecycle_owner='project')
   instead of Tasks, with `sold_minutes` locked and `is_baseline_locked = 1`. This is the
   highest-leverage single change. Everything downstream (time logging, WBS, delivery
   tracking, capacity) depends on it.

2. ~~**Add draft ticket creation during quoting.**~~ **REMOVED per architecture decisions.**
   No tickets during quoting. Quote builder manages its own task records.

3. **Wire the Lead entity correctly.**
   Remove the hard `customerId` requirement from Lead creation. A lead can reference a
   customer optionally; it should not require one.

4. **Decide on Opportunity before building pipeline stages.**
   The choice of whether to add an Opportunity entity between Lead and Quote is an important
   product decision. It does not need to be resolved immediately, but it needs an explicit
   decision before further CRM investment.

5. **Procurement linkage** — deferred, as agreed.

---

## 8. Documentation Hygiene Required

The following documents are stale or contradictory and should be updated before development:

| Document | Issue |
|---|---|
| `docs/15_implementation_blueprint/Ticket_Backbone_Planning_State_v1.md` | Says "Development has NOT yet begun" — migrations have been done |
| `docs/05_data_model/02_Ticket_Data_Model_and_Migrations_v1.md` | M1–M5 marked ✅ but app layer not done; misleading |
| `docs/06_features/crm_leads_to_opportunities.md` | Says leads don't require Customer; code contradicts this |

---

## Appendix: Completion Summary

| Area | Schema | Domain Entity | Application Layer | UI/API |
|---|---|---|---|---|
| Ticket unification | ✅ Done | ✅ Done | ❌ Not done | ⚠️ Partial |
| Lead → Quote | ✅ Done | ✅ Done | ✅ Done | ✅ Done |
| Sales pipeline (Opportunity) | ❌ | ❌ | ❌ | ❌ |
| Quote draft tickets | N/A — removed per architecture decisions | | | |
| Quote acceptance → tickets | ❌ | ✅ Ready | ❌ | ❌ |
| Delivery ticket lifecycle | ✅ Schema | ✅ Done | ❌ Not wired | ❌ |
| Purchasing intent | ⚠️ Partial | ❌ | ❌ | ❌ |

---

*This document should be reviewed and approved by Steve Cope before any implementation work begins
on the affected areas. Once approved, it supersedes the stale planning documents listed in
Section 8.*
