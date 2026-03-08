# PET Unified Work Orchestration

## Deterministic Priority Scoring Formula Specification v1.0

Status: Authoritative Scope: Composite Priority Engine for Unified Work
Surface Audience: Architects, Developers

  -------------
  1\. PURPOSE
  -------------

Define a deterministic, explainable, and auditable priority scoring
model that ranks:

-   Project Tasks
-   Support Tickets
-   SLA Escalations
-   Administrative Items

The scoring model must:

-   Be reproducible
-   Be transparent
-   Support manager override
-   Never violate SLA precedence rules

  ------------------------------
  2\. PRIORITY SCORE STRUCTURE
  ------------------------------

Final Priority Score (0 -- 1000 scale):

PRIORITY_SCORE = SLA_COMPONENT + DEADLINE_COMPONENT +
ESCALATION_COMPONENT + SCHEDULE_COMPONENT + ROLE_WEIGHT_COMPONENT -
WAITING_PENALTY + MANAGER_OVERRIDE_ADJUSTMENT

All components deterministic and documented below.

  -----------------------------
  3\. SLA COMPONENT (Max 500)
  -----------------------------

Applies only to tickets bound to SLA.

Let: - T = SLA target minutes - E = elapsed business minutes - R =
remaining minutes = T - E

If ticket status = Waiting: SLA_COMPONENT = 0

Else:

If R \<= 0: SLA_COMPONENT = 500 (hard max -- breach)

Else:

    PercentRemaining = R / T
    SLA_COMPONENT = 500 * (1 - PercentRemaining)

Result: - Near 0 when just created - Near 500 when close to breach

Guarantee: Any active SLA-bound ticket will outrank project tasks once
remaining time is low enough.

  ----------------------------------
  4\. DEADLINE COMPONENT (Max 250)
  ----------------------------------

Applies to project tasks with due date.

Let: - D = minutes until scheduled_due_utc

If D \<= 0: DEADLINE_COMPONENT = 250

Else:

    DEADLINE_COMPONENT = 
        250 * (1 - min(D / DEADLINE_WINDOW, 1))

Where: DEADLINE_WINDOW = configurable (default 5 working days)

  ------------------------------------
  5\. ESCALATION COMPONENT (Max 150)
  ------------------------------------

If EscalationLevel = 0: ESCALATION_COMPONENT = 0

If EscalationLevel = 1: ESCALATION_COMPONENT = 75

If EscalationLevel \>= 2: ESCALATION_COMPONENT = 150

Escalation always additive to SLA score.

  ----------------------------------
  6\. SCHEDULE COMPONENT (Max 100)
  ----------------------------------

If task scheduled for today: SCHEDULE_COMPONENT = 100

If task scheduled within 24 hours: SCHEDULE_COMPONENT = 60

Else: SCHEDULE_COMPONENT = 0

Prevents long-term items from crowding near-term commitments.

  ------------------------------------
  7\. ROLE WEIGHT COMPONENT (Max 50)
  ------------------------------------

Role weight defined in Role configuration:

Example: - Senior Engineer = 1.0 - Junior Engineer = 0.8 - Department
Head = 1.2

ROLE_WEIGHT_COMPONENT = 50 \* RoleWeight

Allows strategic weighting of critical roles.

  --------------------------------
  8\. WAITING PENALTY (Max -400)
  --------------------------------

If status = Waiting: WAITING_PENALTY = 400

Ensures waiting items drop in ranking but remain visible.

SLA clock paused during waiting state.

  ----------------------
  9\. MANAGER OVERRIDE
  ----------------------

Manager may apply override:

MANAGER_OVERRIDE_ADJUSTMENT = ± configurable value (max ±300)

Rules: - Justification required - Logged in audit trail - Visible in
UI - Cannot suppress SLA breach priority below 300

  ----------------------
  10\. TIE-BREAK RULES
  ----------------------

If PRIORITY_SCORE equal:

1.  Higher SLA risk wins
2.  Earlier created_at wins
3.  Escalation level wins
4.  Manual override wins

  ---------------------------
  11\. INVARIANT GUARANTEES
  ---------------------------

-   Active SLA breach will always rank \>= 500.
-   Project task cannot outrank SLA breach unless manager override.
-   Waiting tickets cannot outrank active SLA tickets.
-   Escalation increases priority but does not reset SLA timer.

  -------------------------------
  12\. PERFORMANCE REQUIREMENTS
  -------------------------------

-   Score must compute \< 1ms per item.
-   Bulk ranking must support 1000 items \< 50ms.
-   Score recalculated on:
    -   Status change
    -   Time tick (minute boundary)
    -   Escalation event
    -   Assignment change

  ---------------------
  13\. TEST SCENARIOS
  ---------------------

1.  Ticket at 95% SLA window vs Project due tomorrow.
2.  Breached ticket vs escalated ticket.
3.  Waiting ticket vs low-priority task.
4.  Manager override applied.
5.  Multiple escalations at same time.

  ----------------------
  END OF SPECIFICATION
  ----------------------
