# PET – Screen-Level Contracts

## Purpose of this Document
This document defines **what actions are permitted on each PET screen**.

A screen-level contract specifies:
- Allowed actions
- Forbidden actions
- Read-only vs mutating behaviour

This prevents UI drift from violating domain rules.

---

## Global Rules

- Screens never bypass domain rules
- If an action is not listed, it is forbidden
- Mutating actions always emit domain events

---

## Overview Screen

Allowed:
- View KPIs
- Navigate to underlying records

Forbidden:
- Create, edit, or delete any entity

---

## Dashboard Screens (All Roles)

Allowed:
- View KPI widgets
- Drill down (read-only)

Forbidden:
- Editing
- Exporting raw data without permission

---

## Leads Screen

Allowed:
- Create Lead
- Edit Lead (pre-qualification)
- Disqualify Lead

Forbidden:
- Creating Opportunities directly

---

## Opportunities Screen

Allowed:
- Update opportunity details
- Allocate pre-sales time
- Progress to Quote

Forbidden:
- Bypassing qualification

---

## Quotes Screen

Allowed:
- Create Draft Quote
- Edit Draft / Sent Quotes
- Send Quote

Forbidden:
- Editing Locked Quotes

---

## Projects Screen

Allowed:
- View project status
- Adjust task planning (within constraints)

Forbidden:
- Increasing sold hours
- Completing with unresolved variance

---

## Time Screens

Allowed:
- Create Draft Time Entries
- Submit Time Entries
- Create Compensating Entries

Forbidden:
- Editing submitted entries

---

## Tickets Screen

Allowed:
- Create Ticket
- Log time
- Resolve Ticket

Forbidden:
- Logging time on closed tickets

---

## Settings Screens

Allowed:
- Schema configuration
- KPI definition changes

Forbidden:
- Operational data changes

---

**Authority**: Normative

This document defines PET’s screen-level contracts.

