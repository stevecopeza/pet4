# PET – Screen-Level Contracts

## Purpose of this Document
Defines what actions are permitted on each PET screen.

A screen-level contract specifies:
- allowed actions
- forbidden actions
- read-only vs mutating boundaries

---

## Global Rules
- if an action is not listed as allowed, it is forbidden
- UI does not bypass domain/application rule checks
- read surfaces must not trigger hidden write behavior
- feature-gated screens must degrade safely when flags are disabled

---

## Overview Screen (`pet-dashboard`)
Allowed:
- view role-shaped operational summary
- navigate to linked operational surfaces

Forbidden:
- direct create/edit/delete actions from overview widgets

---

## Dashboards Screen (`pet-dashboards`)
Allowed:
- view dashboard panels and KPI summaries
- drill into underlying records in their owning surfaces
- in Project Manager persona, open Delivery project detail by clicking a project card (`?page=pet-delivery#project=<id>`)

Forbidden:
- direct mutation from dashboard panels

---

## My Work Screen (`pet-my-work`)
Allowed:
- view grouped work composition (`Needs Attention`, `In Progress`, `Stable / Monitoring`)
- open linked ticket/project destinations

Forbidden:
- inline mutation of ticket/project/task domain state
- direct assignment/queue mutation from this surface

---

## My Profile Screen (`pet-my-profile`)
Allowed:
- edit own identity fields (name, email)
- edit own team context
- change primary role by ending active assignment and creating a new assignment
- update own availability metadata (`availability_state`, `availability_pattern`, `next_available_note`, `location_note`)
- add own skill ratings and certifications
- navigate into linked ticket/project responsibilities

Forbidden:
- editing another employee profile from this surface
- bypassing assignment command path for role transitions
- introducing independent profile-only identity stores

---

## Performance Screen (`pet-performance`)
Allowed:
- load latest benchmark payload
- trigger benchmark run
- inspect run status, probe metrics, workload contract metrics, recommendations, and probe errors

Forbidden:
- direct edit/delete of benchmark results
- hidden mutation of domain records unrelated to benchmark execution

---

## Staff Screen (`pet-people`)
Allowed:
- list/filter/sort employees
- open employee editor
- archive employees via explicit action

Forbidden:
- hard deletion of employee history records from this screen
- implicit readiness persistence fields

### Staff Setup Journey (Feature-Gated: `pet_staff_setup_journey_enabled`)
Allowed:
- guided step navigation (Identity, Org Placement, Role Assignment, Capabilities, Management Context)
- in-journey role assignment edits
- readiness and next-step derivation at runtime
- explicit readiness/name sort selection

Forbidden:
- persisted setup-status storage column
- hard-blocked gating that prevents progression/editing
- showing non-setup operational panels while journey mode is open

---

## Customers Screen (Customer Setup Journey)
Allowed:
- view customer setup progress from runtime-derived branch/contact indicators
- follow guided next actions into Branches/Contacts

Forbidden:
- persisted setup-status fields that duplicate derived status
- hard-blocking progression gates

---

## Branches Screen (Customer Context)
Allowed:
- create/edit/archive branches
- preserve customer context when launched from customer setup flow

Forbidden:
- showing unrelated customer branches in customer-scoped context

---

## Contacts Screen (Customer/Branch Context)
Allowed:
- create/edit/archive contacts
- preserve customer/branch continuity from prior setup step

Forbidden:
- presenting branch options from unrelated customers

---

## Opportunities Screen
Allowed:
- maintain opportunity details
- progress qualified opportunities toward quote creation

Forbidden:
- bypassing qualification lifecycle

---

## Quotes Screen
Allowed:
- create/edit draft quotes
- send quote

Forbidden:
- mutating locked/immutable quote states

---

## Delivery Screen
Allowed:
- view and manage delivery records within domain constraints
- drill into project/task context
- open a specific project directly in detail workspace via URL hash (`#project=<id>`)
- return from detail workspace to project list using explicit back navigation

Forbidden:
- mutation paths that bypass sold/baseline governance constraints

---

## Time Screen
Allowed:
- create draft entries
- submit entries
- create compensating entries

Forbidden:
- editing submitted/locked entries directly

---

## Support Screen
Allowed:
- create and operate tickets through command-based flows
- perform assignment/routing actions exposed by support command endpoints

Forbidden:
- logging time against closed tickets
- bypassing assignment legality invariants

---

## Settings Screen
Allowed:
- schema/configuration updates by authorized roles

Forbidden:
- operational record mutation hidden behind settings routes

---

**Authority**: Normative
