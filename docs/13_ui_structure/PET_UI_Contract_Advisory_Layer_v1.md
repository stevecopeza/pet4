# PET UI Contract --- Advisory Layer v1.0

Date: 2026-02-26

## Admin Pages

### Advisory Dashboard

-   KPI cards: total signals (7/30/90d), critical count, top subjects
-   Lists: latest critical signals, latest generated reports
-   Links to report viewer

### Report Generator

-   Choose period (defaults from settings)
-   Buttons:
    -   Generate QBR Snapshot
    -   Generate Maturity Snapshot
-   Shows generated report id and link

### Report Viewer (read-only)

-   Sections (QBR):
    -   SLA performance
    -   Escalations summary
    -   Capacity signals (if available)
    -   SPOF summary (from resilience)
-   Printable layout

## Shortcodes

-   \[pet_advisory_snapshot\] (latest report summary)
-   \[pet_advisory_wallboard\] (top risks, trends; read-only)

## Acceptance Criteria

-   Reports are viewable and print-friendly
-   Generation is role-gated and explicit
