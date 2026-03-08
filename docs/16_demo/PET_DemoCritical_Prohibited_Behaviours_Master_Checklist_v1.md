# PET Demo-Critical Areas --- Prohibited Behaviours Master Checklist v1.0

Date: 2026-02-26

## Purpose

Consolidate all negative guarantees across Escalation, Helpdesk,
Advisory, and People Resilience. Implementation must explicitly prove
enforcement of each item.

------------------------------------------------------------------------

# Global Prohibitions (All Areas)

-   MUST NOT mutate operational truth from read-side surfaces.
-   MUST NOT auto-create domain artifacts on page render.
-   MUST NOT bypass Domain layer invariants from UI or API.
-   MUST NOT introduce backward-incompatible schema changes.
-   MUST NOT overwrite immutable records.
-   MUST NOT create duplicate domain artifacts under retries.

------------------------------------------------------------------------

# Escalation & Risk

-   MUST NOT auto-create escalation on Ticket creation.
-   MUST NOT create duplicate OPEN escalations for same rule+source.
-   MUST NOT mutate escalation fields after OPEN (only append
    transitions).
-   MUST NOT change Ticket state due to escalation.
-   MUST NOT dispatch EscalationTriggeredEvent twice per OPEN.

------------------------------------------------------------------------

# Support Helpdesk

-   MUST NOT render hardcoded arrays in production mode.
-   MUST NOT allow dual assignment (team + employee simultaneously).
-   MUST NOT mutate ticket status/priority from overview surface.
-   MUST NOT auto-assign unless explicitly configured.
-   MUST NOT create SLA clock state from helpdesk render.

------------------------------------------------------------------------

# Advisory Layer

-   MUST NOT modify ticket/project/time entities during report
    generation.
-   MUST NOT overwrite advisory reports (insert-only versioning).
-   MUST NOT auto-generate reports on page load.
-   MUST NOT mutate advisory signals after insert.
-   MUST NOT treat advisory outputs as operational truth.

------------------------------------------------------------------------

# People Resilience

-   MUST NOT modify skills/certifications based on analysis.
-   MUST NOT auto-run analysis on render.
-   MUST NOT duplicate SPOF signals for same run.
-   MUST NOT auto-escalate non-critical SPOFs.
-   MUST NOT expose PII beyond permission scope.

------------------------------------------------------------------------

# Enforcement Requirement

For each prohibited behaviour: - Corresponding automated test must
exist. - Code review must confirm guard location
(Domain/Application/Infrastructure). - Feature flag gating must be
validated where applicable.

Completion requires explicit verification against this checklist.
