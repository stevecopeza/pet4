# PET People Resilience --- Full Implementation Spec v1.0

Date: 2026-02-26 Target location: docs/ToBeMoved/ Related existing docs:
docs/16 People Resilience/\*

## Goal

Identify capability coverage and single points of failure (SPOF) and
surface them as advisory signals and optional escalations.

## Domain Extensions

-   CapabilityRequirement (team/department/role → required skill/cert,
    minimum_people)
-   SPOF signals derived from requirements + current capabilities

## Application Layer

-   PeopleResilienceAnalyzerService
    -   computeSpofIndicators()
    -   computeCoverageByTeam()

Dispatch: - AdvisorySignalRaisedEvent for SPOF and coverage gaps -
Optional escalation trigger for CRITICAL SPOF

## Infrastructure

Tables: - pet_capability_requirements

Projections: - Team resilience summary - SPOF list

## Settings / Configuration

-   pet_people_resilience_enabled (master)
-   pet_people_resilience_spof_min_people_default (default 2)
-   pet_people_resilience_escalate_on_critical_spof (default false)
-   pet_people_resilience_analysis_schedule_enabled (default false)

## API Contract (separate doc required)

-   GET /resilience/summary
-   GET /resilience/spof
-   POST /resilience/requirements
-   PATCH /resilience/requirements/{id}
-   POST /resilience/analyze (manual run)

## UI Contract

Admin: - Requirements manager - Resilience dashboard - Manual "Run
Analysis"

Shortcodes: - \[pet_resilience_team\] - \[pet_resilience_wallboard\]

## Tests

-   SPOF detection correctness
-   Deterministic analysis (same inputs =\> same outputs)
-   Idempotent signal dispatch by run-id

## Acceptance Criteria

-   SPOFs visible and actionable
-   Advisory can include resilience indicators
