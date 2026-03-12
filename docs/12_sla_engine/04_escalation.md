# SLA Escalation & Notification Model

## Escalation Types

-   Time-based breach
-   Imminent breach warning
-   Repeat breach threshold
-   Tier transition warning (tiered SLAs — ticket approaching tier boundary)

## Escalation Routing

Escalation rules define: - Role to notify - Notification method -
Escalation tier

For tiered SLAs: escalation rules are defined **per SLA tier**.
Thresholds are evaluated against the **current tier's target** with
carry-forward percentage accounted for. When a ticket transitions
between tiers, escalation state is re-evaluated against the new tier's
rules and thresholds.

See docs_27_sla_engine_08_tiered_sla_spec.md §6 for worked examples.

## Events

-   SLABreachWarning
-   SLABreachOccurred
-   SLAEscalationTriggered
-   SLATierTransitioned (tiered SLAs — includes from/to tier, carry%)

All escalation and breach events include `tier_priority` in their
payload when originating from a tiered SLA.
