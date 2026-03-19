
STATUS: SUPERSEDED
SUPERSEDED BY: PET_Escalation_Completion_Corrected_v2.md
SUPERSESSION DATE: 2026-03-17
NOTES: Retained for historical traceability only. Do not use this v1 spec for new implementation decisions.
# PET Escalation Engine Completion — Specification v1

Location: docs/ToBeMoved/PET_Escalation_Engine_Completion_v1.md

## Purpose
Complete the Escalation subsystem so that risk detection in tickets/projects produces deterministic escalations, visible timelines, and actionable management workflows.

Escalations convert operational signals (SLA breach, priority risk, inactivity, project variance) into explicit governance events.

---

# 1. Structural Specification

## Entity
Escalation

### Fields
- id
- source_entity_type (ticket | project | task | sla)
- source_entity_id
- escalation_type (sla_breach | inactivity | priority_risk | project_variance)
- severity (warning | critical)
- status (open | acknowledged | resolved)
- opened_at
- acknowledged_at
- resolved_at
- owner_user_id
- metadata_json

### Invariants
- Escalations are immutable once resolved
- Only one OPEN escalation per (source_entity_type, source_entity_id, escalation_type)
- Escalation lifecycle must be additive

### State transitions
open → acknowledged → resolved

Invalid transitions:
- open → resolved (must acknowledge first)
- acknowledged → open
- resolved → anything

### Events
- escalation_opened
- escalation_acknowledged
- escalation_resolved

### Persistence
Tables:
- pet_escalations
- pet_escalation_transitions

### API

Create escalation
POST /pet/v1/escalations

Acknowledge escalation
POST /pet/v1/escalations/{id}/acknowledge

Resolve escalation
POST /pet/v1/escalations/{id}/resolve

List escalations
GET /pet/v1/escalations

---

# 2. Lifecycle Integration Contract

## Parent entity lifecycle alignment

### Tickets
Escalation created when:
- SLA breach
- no response within defined threshold
- repeated reopen

### Projects
Escalation created when:
- budget variance > threshold
- schedule variance > threshold

### Render rules
Escalations render when:
- status != resolved

### Creation rules
Escalation creation only triggered by:
- rule engine evaluation
- explicit admin creation

### Mutation rules
Escalations mutate only through lifecycle endpoints.

---

# 3. Prohibited Behaviours

- Must not auto-resolve escalations
- Must not create duplicate open escalations for same source/type
- Must not allow mutation of escalation metadata after resolution
- Must not bypass domain transition checks

---

# 4. Stress-Test Scenarios

Ticket SLA breach
→ escalation opens

Manager acknowledges
→ escalation acknowledged

Work completed
→ escalation resolved

Project overrun
→ escalation opened

Multiple risk checks
→ no duplicate escalation created

---

# 5. Demo Seed Requirements

Seed data should include:

Ticket escalation example
Project escalation example

Seed must create:
- 1 open escalation
- 1 acknowledged escalation
- 1 resolved escalation
