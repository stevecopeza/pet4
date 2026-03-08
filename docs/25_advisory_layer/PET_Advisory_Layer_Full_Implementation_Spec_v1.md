# PET Advisory Layer --- Full Implementation Spec v1.0

Date: 2026-02-26

## Goal

Provide advisory outputs that aggregate operational truth into
executive-ready artifacts without mutating operational state.

## Advisory Outputs (v1)

-   QBR Snapshot (primary)
-   Delivery Maturity Snapshot (lightweight)

## Domain Model

-   AdvisorySignal (immutable)
-   AdvisoryReport (immutable, versioned)

Events: - AdvisorySignalRaisedEvent - AdvisoryReportGeneratedEvent -
AdvisoryReportPublishedEvent (optional)

Invariants: - Reports are immutable once generated; corrections create
new versions. - Signals are immutable.

## Application Layer

-   AdvisoryGenerator (existing) continues producing signals
-   AdvisoryAggregationService creates report render models
-   AdvisoryReportService generates reports (manual first)

Scheduling: - Manual generation UI for demo - Optional cron behind flag

## Infrastructure

Tables: - pet_advisory_signals - pet_advisory_reports

Indexes: - type, severity, detected_at, report_type, generated_at

## Settings / Configuration

-   pet_advisory_enabled (master)
-   pet_advisory_allow_manual_generation (default true)
-   pet_advisory_allow_scheduled_generation (default false)
-   pet_advisory_default_period_days (default 90)

## API Contract (separate doc required)

-   GET /advisory/signals
-   GET /advisory/reports
-   POST /advisory/reports/qbr-snapshot
-   POST /advisory/reports/maturity-snapshot
-   GET /advisory/reports/{id}

## UI Contract

Admin: - Advisory dashboard (signals summary) - Report generator (select
period, generate) - Report viewer (print-friendly)

Shortcodes: - \[pet_advisory_snapshot\] - \[pet_advisory_wallboard\]

## Tests

-   Deterministic report generation
-   Versioned immutability
-   Permission enforcement

## Acceptance Criteria

-   QBR Snapshot generated and viewable from UI
-   Includes SLA + Escalation + SPOF in one narrative
