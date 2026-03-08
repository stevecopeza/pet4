# PET SLA & Work Orchestration Feature Flag Governance v1.0

Date: 2026-02-26

## Purpose

Define controlled activation mechanisms for:

-   SLA Scheduler
-   WorkItem Projection Listener
-   Queue Visibility
-   Priority Engine

This enables behavioural rollback without schema rollback.

------------------------------------------------------------------------

## 1. Required Feature Flags

The canonical list below covers all flags seeded and checked at runtime.
For demo-critical subsystem flags (escalation, advisory, resilience),
see also `docs/09_demo/PET_DemoCritical_Rollout_FeatureFlags_v1.md`.

### SLA & Work Orchestration Flags

  ------------------------------------------------------------------------------
  Flag Key                       Default                Controls
  ------------------------------ ---------------------- ------------------------
  pet_sla_scheduler_enabled      false                  Cron-based SLA
                                                        evaluation

  pet_work_projection_enabled    false                  Ticket → WorkItem
                                                        listener

  pet_queue_visibility_enabled   false                  Queue endpoints & UI

  pet_priority_engine_enabled    false                  PriorityScoringService
                                                        activation
  ------------------------------------------------------------------------------

### Helpdesk & Support Flags

  ------------------------------------------------------------------------------
  Flag Key                           Default            Controls
  ---------------------------------- ------------------ ------------------------
  pet_helpdesk_enabled               false              Helpdesk controllers,
                                                        ticket API routes,
                                                        admin pages

  pet_helpdesk_shortcode_enabled     false              Front-end helpdesk
                                                        shortcodes
  ------------------------------------------------------------------------------

### Subsystem Activation Flags

  ------------------------------------------------------------------------------
  Flag Key                           Default            Controls
  ---------------------------------- ------------------ ------------------------
  pet_escalation_engine_enabled      false              Escalation rules,
                                                        triggering, UI surfaces

  pet_advisory_enabled               false              Advisory signal
                                                        aggregation + reporting

  pet_advisory_reports_enabled       false              Advisory report
                                                        generation endpoints

  pet_resilience_indicators_enabled  false              Resilience / utilization
                                                        indicators + SPOF
  ------------------------------------------------------------------------------

### Integration Flags

  ------------------------------------------------------------------------------
  Flag Key                               Default        Controls
  -------------------------------------- -------------- ------------------------
  pet_pulseway_enabled                   false          Master switch for
                                                        Pulseway RMM integration

  pet_pulseway_ticket_creation_enabled   false          Auto ticket creation
                                                        from Pulseway alerts
  ------------------------------------------------------------------------------

Flags MUST:

-   Default to false on upgrade
-   Be stored in config table (`wp_pet_settings`, not transient)
-   Be environment overridable

The demo seed (`DemoSeedService::seedFeatureFlags`) sets 10 core flags
to enabled on seed execution. The 2 Pulseway integration flags are
seeded by `CreatePulsewayIntegrationTables` (defaulting to false).

Total flags in system: 12.

------------------------------------------------------------------------

## 2. Activation Order

1.  Enable projection
2.  Enable SLA scheduler
3.  Enable priority engine
4.  Enable queue visibility

Order must not change.

------------------------------------------------------------------------

## 3. Behavioural Rollback

If issue detected:

-   Disable queue visibility first
-   Disable priority engine
-   Disable scheduler
-   Disable projection last

Schema remains intact.

------------------------------------------------------------------------

## 4. Operational Monitoring

On activation monitor:

-   Duplicate SLA events
-   Duplicate WorkItems
-   Long-running cron jobs
-   DB lock contention

------------------------------------------------------------------------

## Acceptance Criteria

-   Flags respected at runtime
-   No feature activates implicitly
-   Safe toggle on/off without fatal errors
