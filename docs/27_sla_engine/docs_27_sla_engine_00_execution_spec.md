# PET SLA Engine v1.0 -- Developer Execution Specification

Status: Authoritative Scope: SLA Builder → Versioning → Contract
Snapshot → KPI Binding → Escalation → Reporting Audience: Developers,
Architects, Technical Leads

  ------------------------------
  1\. ARCHITECTURAL PRINCIPLES
  ------------------------------

1.  SLA is a first-class Commercial Domain entity.
2.  Published SLA versions are immutable.
3.  Contracts bind to SLA snapshot at acceptance.
4.  KPI definitions auto-generated and bound to roles.
5.  Breach detection is SLA-calendar aware.
6.  Escalations are event-driven and structured.
7.  No SLA drift across versions or contracts.

  ---------------------
  2\. DATABASE SCHEMA
  ---------------------

  : slas - id (UUID, PK) - name (varchar 255, required) - tier (enum:
  bronze, silver, gold, custom) - description (text) - version_number
  (int, required) - status (enum: draft, published, deprecated,
  archived) - response_time_target_minutes (int, required) -
  resolution_time_target_minutes (int, required) - calendar_id (UUID, FK
  required) - created_at (datetime) - updated_at (datetime)

Unique constraint: (name, version_number)

Table: sla_escalation_rules - id (UUID, PK) - sla_id (UUID, FK) -
threshold_percent (int, required) \# e.g. 80 = 80% of SLA window -
escalation_role_id (UUID) - escalation_level (int) - notify_method
(enum: email, sms, internal)

Table: sla_exclusions - id (UUID, PK) - sla_id (UUID, FK) -
exclusion_type (enum: public_holiday, customer_delay, third_party) -
description (text)

Table: sla_service_credit_rules - id (UUID, PK) - sla_id (UUID, FK) -
breach_threshold_percent (int) - credit_percentage (decimal 5,2) -
max_credit_cap_percent (decimal 5,2)

Table: contract_sla_snapshots - id (UUID, PK) - contract_id (UUID) -
sla_id (UUID) - sla_version_number (int) - snapshot_json (json,
required) - bound_role_id (UUID)

Indexes: - sla_id - contract_id - status

  ------------------
  3\. DOMAIN MODEL
  ------------------

Entity: SLA Methods: - publish() - deprecate() - archive() -
validateForPublish() - generateKpiTemplates()

Invariant: - Cannot edit when status = published

Entity: SLAEscalationRule - evaluateThreshold(currentPercent)

Entity: SLAServiceCreditRule - calculateCredit(breachPercent)

  -------------------------
  4\. KPI AUTO-GENERATION
  -------------------------

On SLA Publish:

Generate KPI Templates: - ResponseTimeCompliance -
ResolutionTimeCompliance - BreachCount - EscalationCount

Each KPI: - Bound to Role - Reads from contract_sla_snapshots -
Aggregation interval configurable (daily/monthly)

  --------------------------------
  5\. BREACH DETECTION ALGORITHM
  --------------------------------

Input: - Ticket created_at - First response_at - Resolution_at - SLA
snapshot - Calendar engine

Steps: 1. Calculate business minutes between created_at and response_at.
2. Compare to response_time_target_minutes. 3. Calculate business
minutes between created_at and resolution_at. 4. Compare to
resolution_time_target_minutes. 5. Compute percent_of_target = elapsed /
target. 6. If percent \>= escalation threshold → trigger escalation
event. 7. If percent \>= 100% → record breach.

Time calculation MUST use SLA calendar and UTC normalization.

  -----------------------
  6\. ESCALATION ENGINE
  -----------------------

Events: - SLABreachWarning - SLABreachOccurred - SLAEscalationTriggered

On threshold match: - Determine escalation_role_id - Dispatch
notification - Record escalation log entry

Escalations are idempotent per threshold level.

  ------------------
  7\. API CONTRACT
  ------------------

POST /slas - Create Draft SLA

PUT /slas/{id} - Update Draft SLA

POST /slas/{id}/publish - Publish SLA (validates & auto-generates KPIs)

POST /slas/{id}/deprecate

GET /slas?status=published

POST /contracts/{id}/bind-sla - Bind SLA version to contract - Snapshot
stored

Validation Errors: - Cannot publish without escalation rules - Cannot
publish without calendar - Cannot modify published SLA

  -----------------
  8\. UX CONTRACT
  -----------------

SLA Builder: Sections: - Overview - Targets - Operating Calendar -
Escalations - Exclusions - Service Credits

Warnings: - Publishing creates immutable version. - Deprecated SLAs
cannot be used in new quotes. - Archived SLAs hidden but retained
historically.

Sales UI: - Select SLA tier only (no editing)

  ------------------------
  9\. PERMISSIONS MATRIX
  ------------------------

Commercial Management: - Create/Edit Draft SLA - Publish SLA - Deprecate
SLA

Sales: - Select Published SLA in Quote

Support: - View SLA snapshot (read-only)

Finance: - View service credit exposure

  ---------------------
  10\. TEST SCENARIOS
  ---------------------

1.  Publish SLA without escalation rule → blocked.
2.  Modify published SLA → blocked.
3.  Ticket breaches at 80% threshold → warning triggered.
4.  Ticket exceeds 100% → breach logged.
5.  SLA deprecated → cannot attach to new Quote.
6.  Contract termination → SLA snapshot remains for history.

  ---------------------------
  11\. IMPLEMENTATION ORDER
  ---------------------------

1.  Migrations
2.  Domain Entities & Invariants
3.  SLA Builder UI
4.  KPI Template Generation
5.  Escalation Engine
6.  Contract Snapshot Logic
7.  Reporting Dashboard

  ----------------------
  END OF SPECIFICATION
  ----------------------
