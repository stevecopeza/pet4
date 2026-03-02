# PET Support & Operations Phase Strategy v1.0

Status: Strategic Direction Document Scope: SLA Engine, Calendar Engine,
and Support Architecture Rollout Audience: Project Owner, Technical
Leads, Developers

  ------------------------------
  1\. CURRENT STATE ASSESSMENT
  ------------------------------

The current documentation (SLA Engine, Calendar Engine, Cross-Engine
Integration) describes a sophisticated enterprise-grade architecture.

Dev feedback is correct:

There is a 100% gap between documentation and current implementation.

This is not a failure. This is a phase boundary.

The Commercial & Project architecture has been defined and partially
implemented. The SLA / Calendar / Support architecture represents the
next major system phase.

We are at Step 0 of Support & Operations implementation.

  -----------------------
  2\. STRATEGIC OPTIONS
  -----------------------

Option A --- Defer SLA & Calendar entirely Risk: - Time logic
retrofitted later - SLA implementation becomes fragmented - Capacity
planning lacks deterministic time backbone

Option B --- Implement Full SLA Engine immediately Risk: - High
complexity - Slows commercial stabilisation - Overbuilds before
operational volume exists

Option C --- Build Foundational Calendar + Minimal SLA Skeleton
(RECOMMENDED)

This balances architectural integrity with delivery velocity.

  -------------------------------------
  3\. WHY CALENDAR CANNOT BE DEFERRED
  -------------------------------------

Calendar underpins:

-   SLA timing
-   Escalation logic
-   Capacity planning
-   Forecast smoothing
-   Time tracking realism
-   Cross-engine time coherence

Calendar is infrastructural. It is not a "Support module".

Delaying it increases long-term refactor cost.

  --------------------------------------------------
  4\. WHY FULL SLA SHOULD NOT BE BUILT IMMEDIATELY
  --------------------------------------------------

Full SLA implementation includes:

-   Escalation threshold engine
-   KPI auto-generation
-   Service credit computation
-   Advisory breach modelling
-   Advanced dashboards

These are heavy layers.

They are not required for architectural correctness in Phase 1.

  ---------------------------------
  5\. RECOMMENDED PHASED APPROACH
  ---------------------------------

PHASE A --- Calendar Core Foundation

Implement: - Calendar entity - Working windows (multi-window,
cross-midnight) - Deterministic business-minute computation -
Versioning - Snapshot structure

Exclude: - Capacity special events - Advanced forecast integration - UI
complexity beyond essentials

Deliverable: Deterministic, production-safe time engine.

  ---------------------------------------------------------------------
  PHASE B --- SLA Skeleton
  ---------------------------------------------------------------------
  PHASE C --- Ticket Clock Integration

  Implement: - SLA binding on ticket creation - SLA clock state
  tracking - Pause/resume on "Waiting on Customer" - Basic breach
  detection event - Activity feed notification

  Exclude: - Multi-tier escalation - Advanced analytics

  Deliverable: Operational SLA enforcement in support flow.
  ---------------------------------------------------------------------

PHASE D --- Advanced Layer (Future Phase)

Implement: - Escalation thresholds - KPI automation - Service credit
engine - Advisory breach detection - Overtime exposure modelling

This phase is additive and does not alter prior invariants.

  ---------------------
  6\. RISK MANAGEMENT
  ---------------------

Risk if Deferred Too Long: - Time logic duplicated across modules - SLA
disputes due to ambiguous time computation - Capacity forecasting
inaccuracies

Risk if Built Too Fast: - Delivery slowdown - Overengineering -
Misalignment with real operational needs

Balanced approach mitigates both.

  ---------------------------------
  7\. RECOMMENDED EXECUTION ORDER
  ---------------------------------

1.  Calendar Core (Foundational)
2.  SLA Skeleton
3.  Ticket Integration
4.  Advanced SLA Features (Later Phase)

Strict separation between foundation and advanced layers.

  ----------------
  8\. CONCLUSION
  ----------------

The existing documentation represents architectural end-state design.

The implementation strategy should: - Build foundational time
infrastructure now - Implement SLA binding minimally but correctly -
Layer complexity progressively - Protect development velocity

The gap identified by dev is accurate. It represents the beginning of
the Support & Operations phase.

Proceed deliberately, not reactively.

  -----------------
  END OF DOCUMENT
  -----------------
