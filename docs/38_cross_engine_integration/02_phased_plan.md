# PET SLA + Calendar + Cross-Engine Integration

## Phased Implementation Plan v1.0

Status: Execution Plan Scope: SLA Engine, Calendar Engine (v1.1),
Cross-Engine Integration Audience: Technical Leads, Developers

  ----------
  OVERVIEW
  ----------

This phased plan ensures controlled rollout of:

1.  Calendar Engine (deterministic time foundation)
2.  SLA Engine (contractual enforcement layer)
3.  Cross-Engine Integration (horizontal cohesion)
4.  Observability and validation

Phases are ordered to minimise architectural risk.

  --------------------------------------
  PHASE 1 --- CALENDAR CORE FOUNDATION
  --------------------------------------

Goal: Deterministic, versioned business-time engine operational.

1.  Database migrations
    -   calendars
    -   calendar_working_windows
    -   calendar_holidays
    -   calendar_versions (if separated)
    -   indexes & constraints
2.  Domain layer
    -   Calendar entity
    -   Versioning state machine
    -   Overlap validation logic
    -   Snapshot generation logic
3.  Business Time Service
    -   calculateBusinessMinutes()
    -   addBusinessMinutes()
    -   nextBusinessMinute()
    -   isBusinessTime()
4.  Edge-case engine
    -   DST forward/backward
    -   Cross-midnight windows
    -   Holiday recurrence
    -   Leap year validation
5.  Unit tests (mandatory)
    -   30+ deterministic edge-case tests
    -   DST tests
    -   Weekend boundary tests

Deliverable: - Fully deterministic Calendar Engine isolated & tested.

  ---------------------------------------
  PHASE 2 --- SLA DOMAIN IMPLEMENTATION
  ---------------------------------------

Goal: SLA as first-class versioned commercial entity.

1.  Database migrations
    -   slas
    -   sla_escalation_rules
    -   sla_service_credit_rules
    -   contract_sla_snapshots
2.  Domain entities
    -   SLA
    -   SLAEscalationRule
    -   SLAServiceCreditRule
3.  Publish workflow
    -   Validation enforcement
    -   KPI template generation
    -   Calendar snapshot embedding
4.  Snapshot binding
    -   QuoteAccepted → Contract snapshot logic
    -   Immutable storage of SLA + calendar snapshot
5.  Escalation engine
    -   Threshold evaluation
    -   Event dispatch
    -   Idempotency enforcement
6.  API implementation
    -   Full REST endpoints
    -   DTO validation
    -   Publish guardrails

Deliverable: - SLA Engine functional and snapshot-safe.

  --------------------------------------
  PHASE 3 --- TICKET ↔ SLA INTEGRATION
  --------------------------------------

Goal: Deterministic SLA clock on tickets.

1.  Ticket entity updates
    -   SLA binding required at creation
    -   SLA clock state tracking (Active / Paused / Closed)
2.  Pause/resume logic
    -   Waiting on customer pauses SLA clock
    -   Reopen resumes elapsed time
3.  Breach detection
    -   Calendar-based minute calculation
    -   Escalation threshold evaluation
    -   Event emission
4.  Activity feed integration
    -   Breach warnings visible
    -   Escalation events recorded
5.  KPI binding
    -   Role-bound SLA KPIs generated
    -   Compliance tracking per role

Deliverable: - SLA enforcement live on support tickets.

  ---------------------------------------------
  PHASE 4 --- CAPACITY & OVERTIME INTEGRATION
  ---------------------------------------------

Goal: Calendar used by delivery layer without violating SLA rules.

1.  Capacity calendar type implementation
2.  Overtime window metadata exposure
3.  Task scheduling validation
4.  Margin warning integration for overtime tasks
5.  Forecast smoothing alignment

Deliverable: - Capacity and SLA engines separated but coherent.

  -------------------------------------------
  PHASE 5 --- CROSS-ENGINE VALIDATION LAYER
  -------------------------------------------

Goal: Enforce horizontal invariants.

1.  Integration tests:
    -   Ticket pause across DST boundary
    -   ChangeOrder SLA upgrade
    -   Calendar version replacement
    -   Overtime task impact on margin
    -   Forecast replacement on quote version
2.  Invariant audit:
    -   Contract snapshot precedence
    -   SLA immutability enforcement
    -   Calendar immutability enforcement
    -   Escalation idempotency
3.  Performance validation:
    -   Business-minute calc under load
    -   SLA dashboard aggregation timing

Deliverable: - Horizontal integrity verified.

  ----------------------------------------
  PHASE 6 --- DASHBOARDS & OBSERVABILITY
  ----------------------------------------

Goal: Operational transparency.

1.  SLA Compliance Dashboard
2.  Escalation Trend Dashboard
3.  Overtime Exposure Report
4.  Capacity vs SLA Pressure View
5.  Advisory signal extraction

Deliverable: - System observability and leadership visibility.

  ----------------------------------------------
  PHASE 7 --- HARDENING & PRODUCTION READINESS
  ----------------------------------------------

1.  Concurrency control validation
2.  Idempotency validation (events & publish)
3.  Security audit (permissions)
4.  Performance stress testing
5.  Migration dry-run rehearsal

Deliverable: - Production-ready subsystem.

  ---------------------------
  DEPENDENCY ORDER (STRICT)
  ---------------------------

Calendar Core → SLA Engine → Ticket Integration → Capacity Integration →
Cross-Engine Validation → Dashboards → Hardening

No deviation permitted.

  -----------------
  END OF DOCUMENT
  -----------------
