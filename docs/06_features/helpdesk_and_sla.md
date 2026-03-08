# PET – Helpdesk and SLA

## Purpose of this Document
This document defines how PET handles **support work**, ensuring that assistance is contextual, measurable, and SLA‑enforceable.

Support is treated as structured work, not informal interruption.

---

## Core Principles

- All support work resolves to a Ticket
- Time is mandatory and attributable
- SLA compliance is factual, not negotiated

---

## Ticket Model

Tickets represent:
- Requests for help
- Incidents
- Support tasks during or after projects

Tickets are not optional abstractions.

---

## Context Requirements

A Ticket must be linked to at least one of:
- Customer
- Site
- Project

Optional links:
- Contact
- Task

Lack of context blocks ticket creation.

---

## SLA Association

Tickets may:
- Inherit SLA from Customer or Contract
- Explicitly opt out of SLA

SLA applicability is explicit and visible.

---

## Time Logging

All support effort:
- Must be logged against the Ticket
- May be billable or non‑billable

Support time outside tickets is invalid.

---

## SLA Measurement

Measured events include:
- Response time
- Resolution time
- Breach occurrence

Breaches are immutable events.

---

## Lifecycle Enforcement

Tickets follow the defined state machine.

Illegal actions:
- Logging time on closed tickets
- Re‑opening closed tickets

---

## What This Prevents

- Invisible support load
- SLA disputes
- Unmeasured interruptions

---

## See also: Ticket Backbone

The Ticket Backbone specification unifies support tickets with project and internal work and defines SLA and entitlement alignment at the Ticket level.

Related documents:

- `00_foundations/01_Ticket_Backbone_Principles_and_Invariants_v1.md`
- `03_domain_model/03_Ticket_Lifecycle_and_State_Machines_v1.md`
- `12_sla_engine/07_SLA_Agreement_Entitlement_Drawdown_v1.md`
- `36_work_orchestration/08_Work_Orchestration_Queues_and_Assignment_v1.md`

---

**Authority**: Normative

This document defines PET’s helpdesk and SLA behaviour.
