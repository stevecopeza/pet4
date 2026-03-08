# PET Demo Readiness Autofill Rules v1.1

Version: 1.1\
Date: 2026-02-14\
Status: Binding (Demo-only Scaffolding)

## Purpose

Provide deterministic demo-only scaffolding to satisfy readiness gates
**without changing domain rules**.

## General Rules

-   Autofill is allowed only inside demo seed services.
-   Autofill must be deterministic (no randomness, no time-dependent
    values unless explicitly fixed).
-   Autofill must be minimal: add only what's required to pass readiness
    gates.
-   Autofill must be logged as part of seed step results.

## Quote Autofill

### Rule: Minimal Line Item

If quote has no line items: - Add 1 line item: - name:
`DEMO Line Item - Base` - qty: 1 - unit_price: fixed constant (e.g.,
1000.00 in currency units) - tax: 0 unless tax is required

### Rule: Payment Schedule (Critical)

If quote intended for acceptance and payment schedule missing: - Create
a schedule with 1 installment: - name: `DEMO Payment - 100%` - amount:
equals quote total (same units as domain total, e.g., cents) - due_date:
deterministic (e.g., seed date or a fixed date string) - Validation:
sum(schedule.amount) == quote.total exactly

### Rule: Readiness Flags / Approvals (if required by domain)

If domain requires approvals: - Apply the minimal approved state using
existing domain command(s). - If no command exists, do not invent
one---mark capability missing and degrade.

## Project Autofill

Projects must derive from accepted quote. Autofill may: - Ensure there
is at least 1 milestone if milestone completion is part of demo: -
`DEMO Milestone - M1` - Ensure minimal task structure if milestone
completion requires it.

## Ticket/SLA Autofill

If ticket requires SLA policy assignment: - Assign `DEMO SLA - Standard`
policy to ticket using official assignment pathway. - Initialize SLA
clock state deterministically.

## Time Autofill

If time entry required fields missing: - Fill with: - `DEMO Work - W1` -
duration: fixed (e.g., 60 minutes) - rate: fixed - references: project
P1 and/or ticket T1

## Autofill Auditability

Seed response must record for each step: - which autofill rules were
applied - which entity keys were affected (Q1, P1, etc.)
