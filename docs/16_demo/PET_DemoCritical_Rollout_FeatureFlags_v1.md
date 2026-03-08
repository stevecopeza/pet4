# PET Demo-Critical Areas --- Rollout & Feature Flags v1.0

Date: 2026-02-26 Target location: docs/ToBeMoved/

## Purpose

Provide controlled activation and safe rollback for demo-critical
subsystems: - Escalation & Risk - Support Helpdesk - Advisory Layer -
People Resilience

This plan is additive and assumes forward-only migrations.

------------------------------------------------------------------------

## 1. Master Feature Flags (config-backed)

All flags default **OFF** on upgrade.

  ----------------------------------------------------------------------------------
  Flag Key                                             Default Controls
  ------------------------------- ---------------------------- ---------------------
  pet_escalation_enabled                                 false Escalation rules,
                                                               triggering, UI
                                                               surfaces

  pet_helpdesk_enabled                                   false Helpdesk
                                                               shortcodes/admin
                                                               pages

  pet_advisory_enabled                                   false Advisory signals
                                                               aggregation +
                                                               reporting UI

  pet_people_resilience_enabled                          false Resilience analysis +
                                                               SPOF surfaces
  ----------------------------------------------------------------------------------

## 2. Subsystem Flags

### Escalation & Risk

  --------------------------------------------------------------------------------------------
  Flag Key                                                       Default Controls
  ----------------------------------------- ---------------------------- ---------------------
  pet_escalation_auto_on_sla_warning                              true\* Trigger on
                                                                         TicketWarningEvent

  pet_escalation_auto_on_sla_breach                               true\* Trigger on
                                                                         TicketBreachedEvent

  pet_escalation_cooldown_default_minutes                            240 Default cooldown for
                                                                         rules
  --------------------------------------------------------------------------------------------

\* only meaningful when pet_escalation_enabled=true

### Support Helpdesk

  --------------------------------------------------------------------------------------------
  Flag Key                                                       Default Controls
  ----------------------------------------- ---------------------------- ---------------------
  pet_helpdesk_show_wallboard                                      false Wallboard shortcode
                                                                         availability

  pet_helpdesk_require_assignee_on_create                           true Ticket create
                                                                         validation (UI/API)
  --------------------------------------------------------------------------------------------

### Advisory

  --------------------------------------------------------------------------------------------
  Flag Key                                                       Default Controls
  ----------------------------------------- ---------------------------- ---------------------
  pet_advisory_allow_manual_generation                            true\* UI buttons to
                                                                         generate reports

  pet_advisory_allow_scheduled_generation                          false Cron generation

  pet_advisory_default_period_days                                    90 Default QBR period
  --------------------------------------------------------------------------------------------

\* only meaningful when pet_advisory_enabled=true

### People Resilience

  ----------------------------------------------------------------------------------------------------
  Flag Key                                                               Default Controls
  ------------------------------------------------- ---------------------------- ---------------------
  pet_people_resilience_spof_min_people_default                                2 Default
                                                                                 minimum_people for
                                                                                 new requirements

  pet_people_resilience_escalate_on_critical_spof                          false Escalate CRITICAL
                                                                                 SPOF

  pet_people_resilience_analysis_schedule_enabled                          false Cron analysis
  ----------------------------------------------------------------------------------------------------

------------------------------------------------------------------------

## 3. Activation Order (mandatory)

1.  Enable pet_helpdesk_enabled (read-only surfaces, no new automation)
2.  Enable pet_escalation_enabled (but keep auto triggers off until
    verified)
3.  Enable pet_people_resilience_enabled (manual analysis only)
4.  Enable pet_advisory_enabled (manual report generation)
5.  Enable escalation auto triggers:
    -   pet_escalation_auto_on_sla_warning
    -   pet_escalation_auto_on_sla_breach
6.  Optionally enable scheduled jobs:
    -   advisory scheduled generation
    -   resilience analysis schedule

------------------------------------------------------------------------

## 4. Behavioural Rollback Order (mandatory)

1.  Disable scheduled jobs (advisory/resilience)
2.  Disable escalation auto triggers
3.  Disable advisory surfaces
4.  Disable resilience surfaces
5.  Disable escalation surfaces
6.  Disable helpdesk surfaces

Schema remains intact.

------------------------------------------------------------------------

## 5. Monitoring Requirements (demo and production)

Track during activation: - Duplicate escalations (same rule/source) -\>
must remain 0 - Duplicate advisory signals/report versions -\> must
remain 0 - Assignment invariant violations -\> must remain 0 - Query
performance (overview endpoints) -\> bounded by pagination and indexes

------------------------------------------------------------------------

## Acceptance Criteria

-   No subsystem activates implicitly
-   Flags are checked at runtime in Application/UI layers
-   Rollback is behavioural and safe
