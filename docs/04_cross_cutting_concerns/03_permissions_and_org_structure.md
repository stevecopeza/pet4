# PET – Permissions and Organisational Structure

## Purpose of this Document
This document defines how **authority, visibility, and access** are enforced in PET.

Permissions are not discretionary. They are derived from organisational reality.

---

## Core Principle

**Permissions follow structure, not objects.**

Access is determined by:
- Role
- Team membership
- Managerial hierarchy

There is no per‑object sharing model.

---

## Organisational Model

### Employee

- Every Employee has one primary role
- An Employee may belong to multiple Teams
- An Employee may manage one or more Teams

Employment state (active, inactive, archived) gates access.

---

### Team

- Teams are logical groupings of Employees
- Each Team may have one or more Managers
- Teams may be nested (optional, explicit)

Teams are the primary unit of visibility aggregation.

---

### Org Chart

The organisational chart is:
- Explicit
- Versioned
- Time‑aware

Historical reporting uses the org structure valid at the time of the event.

---

## Role Categories

Roles are **coarse‑grained** and additive.

Examples:
- Executive
- Manager
- Project Manager
- Engineer
- Sales
- Support
- Admin

Roles grant *capabilities*, not data access.

---

## Visibility Rules

### Managers

Managers automatically:
- See all activity, KPIs, and work for their teams
- See aggregated metrics for subordinate teams

No explicit grants are required.

---

### Individual Contributors

Individuals may:
- See their own data
- See team‑level aggregates where permitted

They may not see peer details unless structurally justified.

---

## Customer and Project Visibility

Visibility to Customers and Projects is derived from:
- Assigned role (sales, PM, support)
- Team association
- Active involvement

Manual sharing is not supported.

---

## Sensitive Data Boundaries

Certain data is restricted regardless of role:

- Pay and compensation
- Performance notes
- Disciplinary history

Access requires explicit role capability.

---

## Self‑Service Rules

Employees may edit:
- Personal contact information
- Availability
- Non‑sensitive preferences

They may not edit:
- Pay
- Role history
- KPI source events

---

## Enforcement Model

- All permission checks occur in the domain layer
- UI visibility is secondary
- API calls are subject to the same checks

Circumvention is treated as a system fault.

---

## WordPress Admin Capability (`manage_options`)

**Explicit statement: WordPress admins are trusted operators with full system visibility and mutation rights.**

In the current single-tenant deployment, any user with `manage_options` can:
- Read all data across all customers (quotes, tickets, leads, time entries, opportunities)
- Create, update, and delete any record
- Run WP-CLI commands (migrate, seed — with environment guards)
- Approve quotes (if they have an employee record)

This is a deliberate, documented choice — not an oversight.

**Why this is acceptable now:**
- Single MSP deployment with trusted staff only
- No external or untrusted admin users
- `manage_options` is WordPress’s highest privilege, already tightly controlled

**What this means for future development:**
Any new REST endpoint or command that reads or mutates data must pass the same `manage_options` check. Do not introduce lower-capability endpoints (e.g., `read` or `edit_posts`) without an explicit security review, as they would expose the same unscoped data to a wider audience.

**Revisit trigger:** if a non-staff user (e.g., a customer portal admin) ever needs WordPress admin access, this model must be revisited before granting it.

---

## What This Prevents

- ACL sprawl
- Accidental data leakage
- Inconsistent visibility rules
- Managerial blind spots

---

**Authority**: Normative

This document defines PET’s permission model.

