# SLA Reporting & Dashboard Model

## Dashboard Components

-   Compliance % by SLA
-   Breach trend chart
-   Escalation history
-   Service credit exposure
-   Tier transition frequency (tiered SLAs)
-   Breach count by tier (tiered SLAs)
-   Average carry-forward % at transition (tiered SLAs)

## Aggregation

-   By Role
-   By Department
-   By Contract
-   By SLA Tier (bronze/silver/gold/custom)
-   By SLA Time-Band Tier (office hours / after hours / public holidays)

For tiered SLAs: breach and compliance metrics are reported both
aggregate (across all tiers) and per individual time-band tier.
This allows commercial teams to identify whether breaches concentrate
in specific time bands (e.g., after-hours understaffing).

See docs_27_sla_engine_08_tiered_sla_spec.md §7 for breach event
structure and tier metadata.

## Data Source

Derived from KPI engine & SLA snapshot data.
Tier transition data from `sla_clock_tier_transitions` table.
