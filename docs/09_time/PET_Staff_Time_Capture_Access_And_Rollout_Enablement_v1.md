# PET Staff Time Capture Access & Rollout Enablement v1

**Target location:** `plugins/pet/docs/09_time/PET_Staff_Time_Capture_Access_And_Rollout_Enablement_v1.md`

## 0. Purpose

This document defines the next PET package required to take the new Staff Time Capture surface from an internal/admin-only MVP to a safe, real staff-facing rollout.

The Staff Time Capture UX MVP now exists as a separate surface. The remaining problem is not screen design. It is **access, permissioning, rollout safety, and identity resolution**.

This package must determine how staff can access the new surface safely without weakening PET’s security, source-of-truth, or role boundaries.

This is **not** a redesign of time-entry domain logic.  
This is **not** an API contract redesign unless strictly required and explicitly approved.  
This is an **access and rollout package**.

PET principles remain binding:

- APIs remain authoritative
- domain/application layers enforce legality
- no business logic in UI
- no fake access workarounds
- no weakening of permission boundaries
- backward compatibility
- additive rollout

---

# 1. Scope of This Work Package

## 1.1 Included

This package covers:

1. safe access model for staff time capture
2. identity resolution for “current employee”
3. permission model for read/create/update operations needed by staff time capture
4. rollout gating and deployment safety
5. route/surface exposure model
6. tests for access correctness and rollback safety

## 1.2 Excluded

This package does **not** include:

- redesign of time-entry domain logic
- redesign of admin TimeEntries surface
- week view
- swipe gestures
- new staff UX features beyond access enablement
- broad auth redesign for PET as a whole
- customer portal architecture
- mobile app architecture

---

# 2. Problem Statement

The new Staff Time Capture surface currently depends on existing endpoints that are effectively admin-protected.

That means the UX exists, but real staff rollout is blocked.

The core question is:

> How should PET expose staff time capture safely to non-admin users while preserving:
- existing business rules
- auditability
- role boundaries
- API authority
- rollout safety

---

# 3. Access Model Principles

## 3.1 Staff time capture must be real, not simulated

The system must not fake staff access through admin assumptions or UI-only workarounds.

## 3.2 Access must be least-privilege

Staff should get access only to:

- their own time-capture surface
- their own relevant suggestions/context
- only the mutation actions required for their own entries

They must not receive broad admin data access.

## 3.3 Identity must be explicit

The system must safely resolve:

- current logged-in WordPress user
- mapped PET employee
- allowed team/role context if needed

No loose or ambiguous identity matching.

## 3.4 Rollout must be gated

This surface must be deployable in a way that can be:

- enabled safely
- disabled safely
- tested with limited cohorts first if desired

---

# 4. Key Design Questions

This package must explicitly answer:

1. What capability/permission model should gate staff time capture?
2. Should access be through:
   - existing admin app route
   - dedicated non-admin page
   - shortcode/page surface
   - another safe surface
3. Which endpoints must become accessible to staff, and with what restrictions?
4. How is “current employee” resolved and enforced?
5. What happens if:
   - a user has no mapped employee
   - a mapped employee is inactive
   - a user tries to view or post time for another employee
6. How is rollout feature-gated?

---

# 5. Access and Identity Constraints

## 5.1 Self-only access

For v1 rollout, staff time capture should be **self-only**.

That means staff users may only:
- read their own time entries
- create their own time entries
- update their own allowed time entries if update is supported
- use suggestion/context data filtered to their own scope

## 5.2 No broad employee visibility

A staff user must not be able to browse:
- all employees
- all tickets without restriction
- all time entries
- admin-only views

## 5.3 Employee mapping must be enforced

Access must depend on a reliable mapping from WordPress user to PET employee.

If no valid mapping exists, the surface must fail safely with a clear message.

---

# 6. Rollout Model

## 6.1 Feature flag required

This package should define and gate rollout behind a dedicated feature flag, for example:

- `pet_staff_time_capture_enabled`

This flag must control:
- route/page availability
- menu/surface visibility
- any newly exposed API paths for this feature

## 6.2 Backward compatibility

The existing admin TimeEntries and admin-only surfaces must remain unaffected.

## 6.3 Safe fallback

If feature flag is off, or access requirements are not met, the staff surface must not appear and must not partially function.

---

# 7. API / Route Exposure Expectations

This package must identify the minimal safe set of backend surfaces needed for staff rollout.

Likely needs include:

- read current user’s employee-scoped time entries
- read current user’s allowed ticket suggestions/context
- create time entry for current employee only

This package must prefer:
- restricted self-scoped routes
over
- exposing broad admin endpoints to non-admins

If new restricted endpoints are needed, they must preserve existing domain rules and use the same underlying command/query paths where possible.

---

# 8. UI Surface Expectations

The package must define where staff time capture lives.

Options may include:
- a dedicated staff page in the admin app
- a dedicated non-admin page
- a shortcode-backed surface
- another safe PET-owned route

The chosen path must:
- be easy for staff to access daily
- not require broad admin UI exposure
- align with rollout safety

---

# 9. Tests Required

This package must add tests for:

- self-only access enforcement
- employee mapping resolution
- denied access when no valid employee exists
- denied access to other employees’ time data
- feature-flag-off behavior
- backward compatibility for admin time-entry surfaces

---

# 10. Prohibited Behaviours

- Must not grant broad admin endpoint access to staff just to make the UI work.
- Must not rely on client-side filtering for access safety.
- Must not weaken domain validation or auditability.
- Must not create ambiguous “maybe this user is this employee” logic.
- Must not break existing admin flows.
- Must not expose time capture partially when rollout is disabled.

---

# 11. Expected Outcome

If implemented correctly, PET will have:

- a real staff-facing time capture surface
- a safe self-only access model
- explicit identity resolution
- controlled feature-gated rollout
- no weakening of admin or domain boundaries

This enables the staff time UX to move from prototype/MVP into real operational adoption.
