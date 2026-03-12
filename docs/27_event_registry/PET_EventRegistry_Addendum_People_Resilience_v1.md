# PET Event Registry Addendum --- People Resilience v1.0

Date: 2026-02-26

## Events

-   ResilienceAnalysisExecutedEvent (new; optional but recommended)
-   AdvisorySignalRaisedEvent (used to emit SPOF/coverage signals)

## Dispatch Points

-   PeopleResilienceAnalyzerService dispatches
    ResilienceAnalysisExecutedEvent per run
-   For each detected SPOF/gap, dispatch AdvisorySignalRaisedEvent
    (idempotent by run_id + requirement_id)

## Acceptance Criteria

-   Signals are not duplicated across repeated runs with same run_id
-   Registry includes new event(s)
