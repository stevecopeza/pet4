# PET – WordPress Menu and Navigation Structure

## Purpose of this Document
This document defines how **PET is surfaced within the WordPress Admin UI**, including:

- Top-level menu placement
- Submenu structure
- Navigation flow between functional areas
- Dashboard entry points

The goal is **clarity, predictability, and minimal cognitive load**, while respecting WordPress conventions.

---

## Design Principles

- PET is a first-class system, not a scattered plugin
- One top-level menu entry only
- Functional grouping over technical grouping
- Dashboards are entry points, not reports

---

## Top-Level Menu

**PET**

- Icon: neutral, system-oriented (not sales or marketing)
- Position: below "Dashboard", above "Posts"

This establishes PET as an operational system, not content tooling.

---

## Primary Navigation Structure

```
PET
├─ Overview (Landing Dashboard)
├─ Dashboards
│  ├─ Executive
│  ├─ Sales
│  ├─ Delivery
│  ├─ Support
│  └─ People
├─ Staff (Tabbed Interface)
│  ├─ Org
│  ├─ Teams
│  ├─ People (Employees List)
│  └─ KPIs
├─ Customers
│  ├─ Leads
│  ├─ Qualifications
│  ├─ Opportunities
│  └─ Customers
├─ Quotes & Sales
│  ├─ Quotes
│  ├─ Sales (Won / Lost)
│  └─ Products & Catalogues
├─ Delivery
│  ├─ Projects
│  ├─ Milestones
│  └─ Tasks
├─ Time
│  ├─ My Timesheets
│  ├─ Team Timesheets (Manager only)
│  └─ Time Reports (Read-only)
├─ Support
│  ├─ Tickets
│  ├─ SLAs
│  └─ Support Dashboards
├─ Knowledge
│  ├─ Knowledgebase
│  └─ Article Drafts
├─ Activity
│  └─ Activity Feed
└─ Settings
   ├─ Schemas & Malleable Fields
   ├─ KPI Definitions
   ├─ Rates & Cost Models
   ├─ Integrations
   └─ System Status
```

---

## Landing Behaviour

### PET → Overview

The **Overview** page is role-aware:

- Executives see a condensed Executive Dashboard
- Managers see Delivery / People emphasis
- Individual contributors see:
  - My Tasks
  - My Time
  - My Tickets

Overview is **not configurable per user** beyond role.

---

## Dashboard Flow

- Dashboards are accessible only via **PET → Dashboards**
- Each dashboard:
  - Is read-only
  - Links to underlying records (subject to permissions)

Dashboards do not allow creation or editing actions.

---

## Functional Area Flow

### CRM Flow

```
Leads → Qualification → Opportunities → Quotes
```

Navigation enforces this order; skipping stages is not supported.

---

### Quotes & Sales Flow

```
Quotes → Acceptance → Sales → Delivery Projects
```

Quote immutability is reflected in UI (locked states).

---

### Delivery Flow

```
Projects → Milestones → Tasks → Time Entries
```

Time entry is accessible directly, but always resolves back to Tasks.

---

### Support Flow

```
Tickets → Resolution → Knowledge Articles
```

Knowledge creation is encouraged post-resolution.

---

## Time Entry UX Access

Time entry is intentionally accessible from:

- PET → Time → My Timesheets
- Project Task views
- Ticket views

But always lands in the same Timesheet UX.

---

## Settings Isolation

Settings are:
- Accessible only to authorised roles
- Segregated from operational screens

No operational actions are hidden inside Settings.

---

## What This Prevents

- Menu sprawl
- Feature discovery by accident
- Bypassing lifecycle stages
- Dashboards turning into edit screens

---

**Authority**: Normative

This document defines PET’s WordPress admin navigation structure.

