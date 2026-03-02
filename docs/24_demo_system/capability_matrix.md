# PET Demo Capability Matrix v1.1

Version: 1.1\
Date: 2026-02-14\
Status: Binding (Demo System)

## Purpose

Define the complete "Demo Everything" scope as a matrix of: -
**Capabilities** (what must be shown) - **Artifacts** (what must
exist) - **States** (what must be true) - **Transitions** (what must be
executed successfully)

This matrix drives: - Seed design - Preflight checks - Contract tests -
Demo runbook

## Capability Levels

-   **Must Show**: must have at least one coherent instance and be
    viewable via API/UI.
-   **Must Transition**: must be executed live (or via seed pipeline)
    without error.
-   **Nice to Show**: included if available, does not block PASS.

## Matrix

### Core Parties & Structure

  ------------------------------------------------------------------------------
  Area                Must Show  Must Transition Anchor        Notes
                                                 Artifact(s)   
  ------------ ---------------- ---------------- ------------- -----------------
  Customers                  ✅              --- DEMO          Active customer
                                                 Customer -    with sites &
                                                 Acme          contacts

  Sites                      ✅              --- DEMO Site -   Site belongs to
                                                 Acme HQ       one customer

  Contacts                   ✅              --- DEMO          Contact may link
                                                 Contact -     to multiple
                                                 Jane Doe      companies/sites
                                                               per rules

  Teams                      ✅              --- DEMO Team -   Includes
                                                 Delivery      icon/visual
                                                               identity if
                                                               implemented

  Employees                  ✅              --- DEMO          Employee
                                                 Employee -    membership in
                                                 Alex Smith    multiple teams if
                                                               supported

  Org Chart                  ✅              --- Derived view  Read-only
                                                               projection /
                                                               hierarchy view
  ------------------------------------------------------------------------------

### Commercial (Quotes → Projects)

  --------------------------------------------------------------------------
  Area                Must Show  Must Transition Anchor        Required
                                                 Artifact(s)   Transitions
  ------------ ---------------- ---------------- ------------- -------------
  Quote Draft                ✅              --- DEMO Quote -  Exists,
                                                 Q2 (Draft)    editable
                                                               (draft)

  Quote                      ✅               ✅ DEMO Quote -  Draft → Ready
  Accepted                                       Q1 (Accepted) → Accepted

  Change                     ✅           ✅ (if DEMO Change   Draft →
  Orders                              supported) Order - CO1   Approved

  Project                    ✅               ✅ DEMO          Created from
                                                 Project - P1  accepted
                                                 (Active)      quote

  Milestones                 ✅               ✅ DEMO          Planned →
                                                 Milestone -   Completed
                                                 M1            
  --------------------------------------------------------------------------

### Delivery (Tickets / SLA / Time)

  ---------------------------------------------------------------------------------
  Area                 Must Show  Must Transition Anchor         Required
                                                  Artifact(s)    Transitions
  ------------- ---------------- ---------------- -------------- ------------------
  Ticket                      ✅               ✅ DEMO Ticket -  Open → In Progress
                                                  T1 (SLA)       (if exists)

  SLA Clock                   ✅               ✅ Clock for T1   Initialize →
                                                                 Evaluate → Breach
                                                                 detection

  Time Draft                  ✅              --- DEMO Time - D1 Draft is mutable
                                                  (Draft)        

  Time                        ✅               ✅ DEMO Time - W1 Draft → Submitted
  Submitted                                       (Submitted)    (immutable)

  Commercial                  ✅           ✅ (if DEMO           Created →
  Adjustments                          supported) Adjustment -   Approved/Applied
                                                  A1             
  ---------------------------------------------------------------------------------

### Advisory (Derived)

  -------------------------------------------------------------------------
  Area                Must Show  Must Transition Anchor        Notes
                                                 Artifact(s)   
  ------------ ---------------- ---------------- ------------- ------------
  Dashboards                 ✅              --- KPI views     Read-only;
                                                               no mutation
                                                               from
                                                               dashboards

  Activity                   ✅              --- Feed entries  Derived from
  Feed                                                         domain
                                                               events

  Advisory                   ✅              --- Signals list  Derived &
  Signals                                                      versioned
                                                               outputs
  -------------------------------------------------------------------------

## Preferred Depth Workflows (for Option B fallback)

If C cannot be met, ensure these **two** are reliable: 1. Quote Draft →
Ready → Accepted → Project Created 2. Ticket → SLA Clock Init → Evaluate
→ Breach/Warning signal generated

## Mermaid: High-Level Flow

``` mermaid
flowchart LR
  Customer --> Site
  Customer --> Contact
  Employee --> Team
  Customer --> Quote
  Quote -->|Accepted| Project
  Project --> Ticket
  Ticket --> SLA
  Project --> Time
  Time -->|Submitted| Billing
  SLA --> Advisory
  Project --> Advisory
```
