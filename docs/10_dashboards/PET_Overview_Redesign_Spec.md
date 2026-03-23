# PET Overview Redesign --- Implementation Specification

## Purpose

Transform the PET Overview page from a passive dashboard into an
operational control surface aligned to Plan → Execute → Track.

------------------------------------------------------------------------

## Core Principles

-   Priority over completeness
-   Action over reporting
-   Flow over silos
-   Events over snapshots

------------------------------------------------------------------------

## Layout Structure

``` mermaid
flowchart TB
    A[Critical Attention] --> B[Plan Execute Track]
    B --> C[People & Capacity]
    C --> D[Customer Readiness]
    D --> E[Event Feed & Advisory]
```

------------------------------------------------------------------------

## 1. Critical Attention Panel

### Must Include:

-   SLA breaches
-   Escalations
-   At-risk projects
-   Unassigned tickets

### Rules:

-   Sorted by urgency
-   No empty state ambiguity

------------------------------------------------------------------------

## 2. Operational Flow (PET Core)

``` mermaid
flowchart LR
    A[Plan] --> B[Execute] --> C[Track]
```

### Plan

-   Pending quotes
-   Aging quotes
-   Blocked quotes

### Execute

-   Active projects (health)
-   Active tickets (SLA state)

### Track

-   Timesheet compliance
-   Variance (sold vs actual)
-   Commercial adjustments

------------------------------------------------------------------------

## 3. People & Capacity

-   Capacity vs demand
-   Overloaded staff
-   Underutilized staff
-   Skill bottlenecks

------------------------------------------------------------------------

## 4. Customer Readiness

-   Incomplete / Partial / Ready
-   Impact on delivery

------------------------------------------------------------------------

## 5. Event Feed

``` mermaid
sequenceDiagram
    participant System
    participant Feed
    System->>Feed: Ticket Breach Event
    System->>Feed: Escalation Triggered
    System->>Feed: Project Risk Change
```

------------------------------------------------------------------------

## 6. Advisory Snapshot

-   Top signals only
-   Derived from events
-   Must explain WHY

------------------------------------------------------------------------

## UI Rules

-   No business logic in UI
-   Everything clickable
-   No duplicate metrics
-   No dead panels

------------------------------------------------------------------------

## Visual Impact

YES --- Major

Transforms page into operational control center.
