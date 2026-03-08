# PET – Stress‑Test Scenarios

## Purpose of this Document
This document defines **deliberate stress‑test scenarios** used to validate PET’s design under pressure.

These are not QA test cases. They are **system‑level thought experiments** intended to expose:
- Architectural cracks
- Invariant violations
- Operational ambiguity
- Long‑term degradation risks

If PET survives these scenarios on paper, it will survive in production.

---

## How to Use This Document

For each scenario:
- Walk through expected behaviour
- Identify which documents govern the outcome
- Confirm no rule conflicts exist

If a scenario cannot be resolved cleanly, the design—not the scenario—is wrong.

---

## Scenario 1: Quote Accepted, Then Pricing Error Discovered

**Situation**
- Quote is accepted online
- Sales notices a pricing error afterwards

**Expected Behaviour**
- Accepted quote is immutable
- A *new* corrective quote (delta or replacement) is created
- Original quote remains visible forever

**Documents Exercised**
- Quoting & Pricing
- Domain Immutability
- UI Behavioural Standards

**Failure Smell**
- Editing accepted quotes
- Silent value correction

---

## Scenario 2: Project Runs Out of Sold Hours Mid‑Delivery

**Situation**
- Project reaches sold hour limit
- Tasks remain incomplete

**Expected Behaviour**
- Variance event is raised
- PM can continue planning but variance is visible
- Commercial action required (change order or write‑off)

**Documents Exercised**
- Project Delivery
- Time Tracking
- KPI Definitions

**Failure Smell**
- Hours continuing without signal
- PM manually inflating sold hours

---

## Scenario 3: Engineer Submits Incorrect Time, After Invoicing

**Situation**
- Time is submitted and invoiced
- Engineer realises error later

**Expected Behaviour**
- Original time entry remains immutable
- Compensating time entry is created
- Invoicing reconciliation is triggered

**Documents Exercised**
- Timesheets UX
- Data Immutability
- QuickBooks Integration

**Failure Smell**
- Editing submitted time
- Hiding historical error

---

## Scenario 4: SLA Breach Disputed by Customer

**Situation**
- Customer disputes an SLA breach

**Expected Behaviour**
- SLA events remain immutable
- Timeline reconstructed from events
- Commercial concession handled separately

**Documents Exercised**
- Helpdesk & SLA
- Domain Events
- Dashboards

**Failure Smell**
- Deleting or editing SLA events
- Manual KPI correction

---

## Scenario 5: External Integration Partially Fails (QuickBooks)

**Situation**
- Invoice intent sent
- External system times out

**Expected Behaviour**
- PET records failure event
- No local state mutation
- Reconciliation task created

**Documents Exercised**
- Integration Principles
- QuickBooks Integration
- Migration & Failure Handling

**Failure Smell**
- Retrying silently
- Assuming success

---

## Scenario 6: Schema Change After Years of Data

**Situation**
- Lead schema changes after 3 years
- Old leads viewed

**Expected Behaviour**
- Old schema version respected
- Deprecated fields visible but locked
- KPIs remain interpretable

**Documents Exercised**
- Malleable Schemas
- Data Model
- UI Behavioural Standards

**Failure Smell**
- Reinterpreting old data
- Field meaning drift

---

## Scenario 7: Manager Changes Role Structure Mid‑Year

**Situation**
- Org structure changes
- Historical KPIs reviewed

**Expected Behaviour**
- Historical KPIs remain based on old structure
- New structure applies prospectively

**Documents Exercised**
- Org Structure Versioning
- KPI Definitions
- Permissions Model

**Failure Smell**
- Retroactive KPI changes
- Visibility inconsistency

---

## Scenario 8: Five‑Year Data Volume Growth

**Situation**
- Millions of events and time entries

**Expected Behaviour**
- Read models scale independently
- Events retained, projections optimised
- No schema rewrites required

**Documents Exercised**
- Domain Events
- Data Model
- Dashboards

**Failure Smell**
- Deleting history for performance
- Ad‑hoc summary tables without lineage

---

## Exit Criteria

PET passes stress testing when:
- Every scenario resolves without rule conflicts
- No scenario requires silent data mutation
- All outcomes are explainable via documents

---

**Authority**: Normative

This document defines PET’s stress‑test scenarios.

