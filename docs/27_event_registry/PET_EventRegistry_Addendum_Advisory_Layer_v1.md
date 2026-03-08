# PET Event Registry Addendum --- Advisory Layer v1.0

Date: 2026-02-26 Target location: docs/ToBeMoved/

## New/Confirmed Events

-   AdvisorySignalRaisedEvent
-   AdvisoryReportGeneratedEvent
-   AdvisoryReportPublishedEvent (optional)

## Dispatch Points

-   AdvisoryGenerator emits AdvisorySignalRaisedEvent when creating
    signals
-   AdvisoryReportService emits AdvisoryReportGeneratedEvent on report
    insert

## Projections

-   Dashboard advisory summary (counts by severity)
-   Customer risk list (derived from signals)

## Acceptance Criteria

-   Events listed in registry
-   FeedProjection can record report generation and critical signals
