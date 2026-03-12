# PET Demo Purge Dependency Graph v1.0

Version: 1.0\
Date: 2026-02-14\
Status: Binding (Demo Purge Safety)

## Purpose

Provide a dependency-aware purge model that prevents referential
integrity failures and protects user-touched or non-demo data.

## Principles

-   Purge targets **one** `seed_run_id` at a time.
-   Purge only deletes/archives entities recorded in the **Demo Seed
    Ledger**.
-   If any item is `user_touched=1` or cannot be safely removed, default
    to **SKIP** (or **ARCHIVE** where supported).

------------------------------------------------------------------------

## Dependency Graph (High-Level)

This graph represents typical dependencies; actual enforcement depends
on current schema and repository behavior.

``` mermaid
flowchart TD
  subgraph Derived["Derived / Projections (delete first)"]
    Feed[Activity Feed Entries]
    KPIs[KPI/Read Models]
    Signals[Advisory Signals]
  end

  subgraph Ops["Operational Records"]
    Time[Time Entries]
    SLAClock[SLA Clock State]
    Tickets[Tickets]
    Milestones[Milestones]
    Projects[Projects]
    Quotes[Quotes]
  end

  subgraph Parties["Parties / Structure (delete last)"]
    ContactLinks[Contact Links]
    Contacts[Contacts]
    Sites[Sites]
    Customers[Customers]
    Teams[Teams]
    Employees[Employees]
    TeamMemberships[Team Memberships]
  end

  Quotes --> Projects
  Projects --> Milestones
  Projects --> Tickets
  Tickets --> SLAClock
  Projects --> Time

  Feed --> Quotes
  Feed --> Projects
  Feed --> Tickets
  Feed --> Time
  KPIs --> Projects
  Signals --> SLAClock

  ContactLinks --> Contacts
  ContactLinks --> Sites
  Sites --> Customers

  TeamMemberships --> Teams
  TeamMemberships --> Employees
```

### Purge Ordering (Recommended)

1.  **Derived/Projection rows**
    -   activity feed entries
    -   advisory signals
    -   KPI/read models
2.  **Automation state**
    -   SLA clock state
3.  **Leaf operational records**
    -   time entries (delete/archive/skip per immutability policy)
4.  **Tickets**
5.  **Milestones**
6.  **Projects**
7.  **Quotes**
    -   If accepted quotes are immutable and not deletable, apply
        **archive marker** or keep but remove from demo navigation;
        still record as ARCHIVED in ledger.
8.  **Org / Party structure (only if demo-owned and unreferenced)**
    -   team memberships
    -   teams/employees (if created as demo-only)
    -   contacts/sites/customers (only if safe)

------------------------------------------------------------------------

## Purge Decision Table

  Condition                                    Action
  -------------------------------------------- -------------------------
  Not in ledger for seed_run_id                Do nothing
  In ledger, user_touched=1                    SKIP + report
  In ledger, safe to delete                    DELETE + mark PURGED
  In ledger, cannot delete but can archive     ARCHIVE + mark ARCHIVED
  In ledger, cannot delete or archive safely   SKIP + report

------------------------------------------------------------------------

## Mermaid: Purge Sequence (Operational)

``` mermaid
sequenceDiagram
  participant Admin as Admin
  participant API as Purge API
  participant Ledger as Demo Seed Ledger
  participant Repo as Repositories

  Admin->>API: POST /system/purge (seed_run_id)
  API->>Ledger: Load ACTIVE ledger rows for seed_run_id
  API->>Repo: Purge projections (feed, KPIs, signals)
  API->>Repo: Purge SLA clock state
  API->>Repo: Purge time entries (delete/archive/skip)
  API->>Repo: Purge tickets
  API->>Repo: Purge milestones
  API->>Repo: Purge projects
  API->>Repo: Purge quotes (delete/archive/skip)
  API->>Repo: Purge parties/org (if safe)
  API->>Ledger: Update purge_status + reasons
  API-->>Admin: 200 summary (purged/archived/skipped)
```
