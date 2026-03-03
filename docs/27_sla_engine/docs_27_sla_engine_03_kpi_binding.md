# SLA--KPI Binding Specification

## Automatic KPI Generation

Upon SLA Publish: System generates role-bound KPI templates:

-   ResponseTimeCompliance
-   ResolutionTimeCompliance
-   BreachCount
-   EscalationCount

For tiered SLAs, additional KPI templates are generated:

-   BreachCountByTier (breach count per tier priority)
-   TierTransitionCount (how often tickets cross tier boundaries)
-   ResponseTimeComplianceByTier (compliance measured per tier)

## Role Binding

KPIs are assigned to roles. Role assignments determine accountable
individuals.

## Measurement Source

KPI engine reads from Contract.sla_snapshot_json.

For tiered SLAs: breach events include `tier_priority`, allowing
KPI aggregation per tier. See docs_27_sla_engine_08_tiered_sla_spec.md
§7 for breach event structure.
