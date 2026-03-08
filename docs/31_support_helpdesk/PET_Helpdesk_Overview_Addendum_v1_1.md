# PET Helpdesk Overview -- Additive Amendment v1.1

Date: 2026-02-24 Status: ADDITIVE CLARIFICATION (No breaking changes)

This document amends the following: -
PET_Helpdesk_Overview_Shortcode_Spec_v1_0.md -
PET_Helpdesk_Overview_UI_Contract_v1_0.md -
PET_Helpdesk_Overview_Data_Mapping_Checklist_v1_0.md

This amendment introduces clarifications only. No structural or
behavioural redesign.

------------------------------------------------------------------------

## 1) Knowledge Scope Lock (Non-Negotiable Clarification)

The Helpdesk Overview shortcode:

-   Does NOT create knowledge articles.
-   Does NOT suggest knowledge promotion.
-   Does NOT link or convert tickets into knowledge artifacts.
-   Does NOT perform any advisory or analytical extraction.

Explicit statement:

> The Helpdesk Overview shortcode is strictly operational visibility
> over existing ticket and SLA read models. Knowledge promotion and
> article generation are separate workflows and are explicitly out of
> scope.

------------------------------------------------------------------------

## 2) Updated Definition: "Unassigned"

Previous definition allowed for: - No assignee person AND no team/queue.

This is superseded.

Revised definition:

> A ticket is considered "Unassigned" when it has a valid queue/team
> assignment but has no named individual owner.

Implications: - queue_id is mandatory. - owner_user_id is nullable. -
Unassigned KPI counts tickets where owner_user_id is NULL.

This aligns with the agreed support ownership model: - All tickets
belong to a team. - Individuals pull or are assigned explicitly.

------------------------------------------------------------------------

## 3) Timezone Clarification for SLA Display

Where SLA banding requires calculation of time remaining:

-   All `(next_due_at - now)` calculations must use the server's
    canonical timezone.
-   UI must not use browser-local time for SLA risk calculations.
-   Wallboard mode must behave deterministically across screens.

This ensures: - Stable risk band classification - No inconsistent breach
rendering across environments

------------------------------------------------------------------------

## 4) Architectural Reinforcement

The following remain strictly enforced:

-   No domain logic in shortcode layer.
-   No recalculation of SLA policy.
-   No mutation endpoints.
-   No schema changes.
-   Read-model only.

This amendment strengthens alignment with PET's event-backed operational
model.

------------------------------------------------------------------------

## Versioning

This amendment is versioned v1.1. It does not replace v1.0 documents. It
is additive and binding.
