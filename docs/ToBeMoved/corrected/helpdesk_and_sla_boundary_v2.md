# Helpdesk and SLA Boundary v2

STATUS: AUTHORITATIVE FOR FEATURE BOUNDARY
VERSION: v2
OWNING LAYER: Features
SUPERSEDES: docs/06_features/helpdesk_and_sla.md
RELATED:
- docs/03_domain_model/03_Ticket_Lifecycle_and_State_Machines_v2.md
- docs/07_commercial/04_Quote_to_Ticket_to_Project_Flow_v2.md
- docs/12_sla_engine/01_domain_model.md
- docs/12_sla_engine/08_tiered_sla_spec.md

## Purpose

This document clarifies the operational boundary between:
- support/helpdesk work
- SLA-governed work
- project-delivery work

Its job is to stop lifecycle collapse and object-model confusion.

---

## 1. Core boundary principle

Support/helpdesk and project delivery may both use Ticket as the operational backbone, but they are not the same operational context.

Therefore:
- shared object model does not imply shared lifecycle
- shared UI components do not imply shared rules
- SLA timing does not define project execution lifecycle
- project planning does not replace support queue management

---

## 2. Helpdesk scope

Helpdesk governs:
- incidents
- requests
- support queue work
- assignment and response handling
- SLA-driven operational commitments where applicable

Helpdesk tickets use support lifecycle semantics.

They may:
- have assignees
- have teams
- be governed by calendars/SLA rules
- trigger escalation/risk logic
- link to knowledge or advisory context

They must not automatically become project-delivery tickets.

---

## 3. SLA scope

SLA governs time-based obligations and entitlements around eligible support work.

SLA may determine:
- response targets
- resolution targets
- office-hours or out-of-hours timing logic
- breach and warning states
- entitlement drawdown where the model supports it

SLA does not:
- redefine project delivery lifecycle
- automatically create project WBS
- silently convert support obligations into sold delivery scope

---

## 4. Project-delivery scope

Project delivery governs:
- sold or explicitly approved delivery work
- PM-managed planning
- WBS refinement
- execution against delivery tickets
- sold/estimated/actual variance

Project delivery may be related to helpdesk and SLA work, but it is not governed by SLA queue semantics.

---

## 5. Cross-boundary interactions

### Allowed interactions

Allowed examples:
- support ticket escalates and results in a linked project remediation ticket
- support findings inform a future sold quote
- project work resolves a recurring support cause
- SLA breach contributes to escalation/risk visibility
- advisory outputs reference support and delivery data together

### Not allowed

Not allowed examples:
- support ticket silently reclassified as project child to make planning easier
- SLA breach automatically mutates a project ticket status
- project ticket inherits support queue timestamps as its execution lifecycle
- helpdesk render path auto-creates project work without explicit command

---

## 6. Assignment boundary

Helpdesk assignment and project assignment may both use team/person patterns, but their semantics differ.

Helpdesk assignment focuses on:
- queue ownership
- response accountability
- support resolution responsibility

Project assignment focuses on:
- delivery planning
- execution responsibility
- PM-managed coordination

One must not be treated as the other by assumption.

---

## 7. Commercial boundary

Support entitlements and project delivery should not be flattened into a single commercial/execution mechanism.

Rules:
- SLA-backed support does not automatically create sold project baseline
- project delivery baseline originates from accepted commercial scope or another explicit project-creation path
- post-support remediation requiring project work should create linked but distinct delivery records

---

## 8. Time and visibility boundary

Actual support work and project work may both produce time records, but management views must preserve context.

Required visibility:
- support actuals in support context
- project actuals in project context
- cross-context rollups only as derived views, not by mutating source records

---

## 9. Tiered / office-hours boundary

Where an SLA includes different behaviour inside and outside office hours, the SLA logic remains attached to the support obligation.

This does not justify:
- swapping lifecycle_owner mid-ticket
- changing project status because the calendar boundary changed
- applying helpdesk timer transitions to project work by convenience

The correct model is:
- same support-context ticket
- different SLA timing behaviour according to calendar/rule evaluation
- explicit visibility of timer impact

---

## 10. Prohibited behaviours

The following are prohibited:

- auto-creating project work on helpdesk render
- auto-changing lifecycle_owner due to SLA event
- treating support tickets as project WBS nodes
- using project statuses as the authoritative support queue lifecycle
- using SLA timing as a project planning engine
- hiding cross-context linkage by flattening records into one mutable object

---

## 11. Authority statement

For the feature boundary between helpdesk, SLA, and project delivery, this document is authoritative.
Dedicated SLA-engine docs may define SLA logic in more depth, but they must not violate the context boundaries defined here.
