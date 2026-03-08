# PET Cross-Engine Integration Specification v1.0

Status: Authoritative Scope: Horizontal Integration Across Commercial,
SLA, Calendar, Tickets, Projects, Time Tracking, Capacity, KPI,
Procurement, Activity, Advisory Audience: Senior Developers, Architects

  -------------
  1\. PURPOSE
  -------------

This specification defines the integration contracts between all major
PET engines to eliminate ambiguity and cross-module drift.

No engine may override another engine's invariant.

  -----------------------------------------
  2\. TICKET ↔ SLA ↔ CALENDAR INTEGRATION
  -----------------------------------------

2.1 SLA Binding

-   Each Ticket MUST be explicitly bound to an SLA snapshot at creation.
-   Binding is derived from Contract + Service Line.
-   Multi-SLA contracts require explicit SLA selection per ticket.

2.2 SLA Clock Model

States: - Active - Paused (Waiting on Customer) - Closed

Rules:

-   SLA business-time clock runs only in Active state.
-   If ticket set to "Waiting on Customer", SLA clock pauses.
-   If ticket reopened, SLA clock resumes from prior elapsed time.
-   SLA clock never restarts unless ChangeOrder introduces new SLA.

2.3 Calendar Usage

-   SLA computation always uses SLA-bound calendar snapshot.
-   Ticket timezone ignored for SLA measurement.

  -----------------------------------------------
  3\. PROJECT ↔ CALENDAR ↔ CAPACITY INTEGRATION
  -----------------------------------------------

3.1 Task Scheduling

-   Project tasks default to Capacity Calendar.
-   Tasks may not be scheduled outside working window unless explicitly
    flagged as overtime.

3.2 Overtime Handling

-   Overtime windows generate margin impact warnings.
-   Overtime multiplier exposed to cost model.
-   Overtime does NOT alter SLA clock.

3.3 Capacity Interaction

Capacity layers: - Forecast (QuoteApproved) - Committed
(ContractActive) - Scheduled (Task assigned) - Actual (TimeEntry)

All capacity scheduling uses Capacity Calendar only.

  ----------------------------------------
  4\. TIME ENTRY ↔ SLA ↔ KPI INTEGRATION
  ----------------------------------------

4.1 Time Entries

-   Time entries do NOT influence SLA compliance directly.
-   SLA compliance measured using ticket timestamps only.
-   Time entries influence:
    -   Cost
    -   Margin
    -   Utilisation KPIs

4.2 KPI Binding

-   SLA KPIs bound to roles.
-   Role assignments determine accountable individuals.
-   KPI engine reads SLA + Calendar snapshots only.

  ----------------------------------------
  5\. PROCUREMENT ↔ CALENDAR INTEGRATION
  ----------------------------------------

-   Procurement lead times may optionally use Business Calendar.
-   Procurement deadlines default to calendar-aware computation.
-   Supplier SLA not yet in scope (future extension).

  --------------------------------------------
  6\. ESCALATION ↔ ACTIVITY FEED INTEGRATION
  --------------------------------------------

Escalation Events: - SLABreachWarning - SLABreachOccurred -
SLAEscalationTriggered

Rules:

-   All escalation events emit global domain events.
-   Activity Feed shows metadata summary.
-   Role-based visibility enforced.
-   Repeat breaches escalate severity level.

  ---------------------------------------------
  7\. COMMERCIAL ↔ SLA ↔ CONTRACT INTEGRATION
  ---------------------------------------------

-   SLA snapshot stored at QuoteAccepted.
-   SLA amendment requires ChangeOrder.
-   Contract binds to specific SLA version + calendar snapshot.
-   Deprecated SLAs cannot attach to new Quotes.

  -------------------------------------
  8\. FORECAST ↔ CALENDAR INTEGRATION
  -------------------------------------

-   Forecast smoothing uses Capacity Calendar.
-   SLA Calendar not used for project capacity projection.
-   Forecast expiry aligned to Quote validity date.

  --------------------------------
  9\. ADVISORY LAYER INTEGRATION
  --------------------------------

Advisory metrics derive from:

-   Repeated SLA breaches
-   Escalation frequency
-   Capacity saturation trends
-   Overtime margin erosion
-   SLA tier vs breach correlation

Advisory outputs are read-only and do not mutate operational state.

  ---------------------------------
  10\. INVARIANT PRECEDENCE RULES
  ---------------------------------

Hierarchy:

1.  Contract Snapshot
2.  SLA Snapshot
3.  Calendar Snapshot
4.  Domain State Machine
5.  UI State

No lower layer may override higher-layer invariant.

  ----------------------
  11\. EDGE CASE RULES
  ----------------------

-   Ticket reassignment does NOT reset SLA clock.
-   Ticket priority change does NOT change SLA unless explicitly
    re-bound.
-   SLA calendar version changes do NOT affect existing contracts.
-   Overtime scheduling does NOT impact SLA compliance.
-   Capacity over-allocation generates warning but not block
    (configurable).

  ------------------------------------
  12\. TEST SCENARIOS (CROSS-MODULE)
  ------------------------------------

1.  Ticket paused for customer response; SLA clock pauses.
2.  Ticket reopened; SLA clock resumes correctly.
3.  Task scheduled in overtime; margin warning triggered.
4.  SLA breach triggers escalation + activity feed event.
5.  ChangeOrder modifies SLA; contract updated; new snapshot created.
6.  Forecast hours reflect updated WBS; capacity recalculated.
7.  Advisory detects repeated breaches in Gold-tier SLA.

  ----------------------
  END OF SPECIFICATION
  ----------------------
