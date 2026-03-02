# PET Calendar Engine v1.1 -- Fully Deterministic Execution Specification

Status: Authoritative (Supersedes v1.0) Scope: Deterministic
Business-Time Computation + SLA + Forecast + Capacity Integration
Audience: Senior Developers, Architects

  -------------
  1\. PURPOSE
  -------------

This specification defines the Calendar Engine as a deterministic,
contract-safe time computation system.

All SLA measurement, escalation timing, and forecast smoothing must rely
exclusively on this engine.

Ambiguity is not permitted.

  ---------------------------------
  2\. TIME MODEL (NON-NEGOTIABLE)
  ---------------------------------

1.  All timestamps stored in UTC (ISO 8601).
2.  Business-time computation occurs in the calendar's IANA timezone.
3.  SLA measurement ALWAYS uses SLA-bound calendar timezone.
4.  Ticket timezone is ignored for SLA computation.
5.  All calculations are minute-granular.
6.  Start minute is inclusive; end minute is exclusive. Example:
    10:00--10:01 = 1 business minute.

  ---------------------------------------------
  3\. DETERMINISTIC BUSINESS MINUTE ALGORITHM
  ---------------------------------------------

Function: calculateBusinessMinutes(startUTC, endUTC, calendarSnapshot)

Preconditions: - startUTC \< endUTC - calendarSnapshot immutable JSON

Steps:

1.  Convert startUTC and endUTC into calendar timezone.
2.  If endUTC \<= startUTC → return 0.
3.  Initialize counter = 0.
4.  Set pointer = floor_to_minute(start).
5.  While pointer \< end: if isBusinessMinute(pointer,
    calendarSnapshot): counter += 1 pointer += 1 minute
6.  Return counter.

Optimization Rule: Instead of minute iteration, window stepping may be
used: - Determine next working window - Add full window duration if
fully inside range - Fall back to minute iteration at boundaries

Edge Conditions:

DST Forward Jump (lost hour): - Missing hour produces zero business
minutes for skipped period.

DST Backward Repeat Hour: - Duplicate hour counted once using timezone
offset normalization.

Leap Year: - February 29 treated as normal date.

  ------------------------------------
  4\. SNAPSHOT STRUCTURE (MANDATORY)
  ------------------------------------

Calendar snapshot JSON MUST embed:

{ "calendar_id": UUID, "calendar_version": int, "timezone":
"Africa/Johannesburg", "working_windows": \[ { "weekday": 1,
"start_time": "08:00", "end_time": "17:00", "window_type": "standard",
"rate_multiplier": 1.0 } \], "holidays": \[ { "date": "2026-12-25",
"recurring": true, "name": "Christmas" } \] }

No live calendar lookup permitted during SLA evaluation.

  ------------------------
  5\. MULTI-WINDOW RULES
  ------------------------

1.  Overlapping windows are rejected at publish-time.
2.  Adjacent windows may exist but are merged during snapshot.
3.  Cross-midnight windows allowed:
    -   end_time \< start_time indicates overnight shift.

Example: 22:00--06:00 splits internally into: - 22:00--23:59 -
00:00--06:00

  --------------------
  6\. OVERTIME RULES
  --------------------

Overtime windows: - window_type = overtime - rate_multiplier \> 1.0

Calendar engine DOES NOT apply cost logic. Multiplier is metadata for
capacity or payroll modules.

  ------------------------------
  7\. VERSIONING STATE MACHINE
  ------------------------------

States: Draft → Published → Deprecated → Archived

Rules:

Draft: - Fully editable.

Published: - Immutable. - May be referenced by SLA.

Deprecated: - Cannot attach to new SLA. - Existing SLA snapshots
unaffected.

Archived: - Hidden in UI. - Retained for history.

New version creation: - Clone previous version. - Increment
version_number. - Must re-publish.

  ------------------------------
  8\. SLA INTEGRATION CONTRACT
  ------------------------------

SLA references: - response_calendar_snapshot -
resolution_calendar_snapshot

Breach calculation uses snapshot only.

Escalation threshold evaluation uses:

percent = businessMinutesElapsed / targetMinutes

Escalation triggers when: percent \>= threshold_percent

  --------------------------
  9\. FORECAST INTEGRATION
  --------------------------

Forecast smoothing uses calendarSnapshot only for:

-   Expected working window projection
-   Monthly aggregation alignment

Forecast never uses overtime windows unless explicitly configured.

  ---------------------------------
  10\. CAPACITY INTEGRATION RULES
  ---------------------------------

Capacity uses separate calendar type.

Special Events (sick, leave): - Do NOT belong in SLA calendar. - Belong
in capacity calendar only.

Calendar engine precedence:

SLA timing → SLA calendar Capacity planning → Capacity calendar

Never mixed.

  ------------------------------
  11\. API CONTRACT (DETAILED)
  ------------------------------

POST /calendars

Request: { "name": string, "type": "business_hours", "timezone":
"Africa/Johannesburg" }

PUT /calendars/{id}

POST /calendars/{id}/working-window { "weekday": 1, "start_time":
"08:00", "end_time": "17:00", "window_type": "standard",
"rate_multiplier": 1.0 }

POST /calendars/{id}/holiday { "date": "2026-12-25", "recurring": true,
"name": "Christmas" }

POST /calendars/{id}/publish

Validation Errors: - Overlapping windows - No working windows - Invalid
timezone - Cross-midnight malformed window

  ------------------
  12\. UX CONTRACT
  ------------------

Calendar Builder must:

-   Display weekly grid
-   Prevent overlapping window creation
-   Visually distinguish overtime
-   Warn before publish
-   Show version history
-   Prevent editing published version
-   Provide clone button for new version

  ---------------------------
  13\. PERFORMANCE CONTRACT
  ---------------------------

Performance Requirements:

-   calculateBusinessMinutes must process 30-day window \< 5ms average
-   Window stepping required for spans \> 1 day
-   Holiday lookup pre-indexed per year
-   Snapshot JSON cached per request lifecycle

  -----------------------
  14\. EDGE CASE MATRIX
  -----------------------

Case: Start outside business window → nextBusinessMinute used
internally.

Case: End inside closed window → only count until window boundary.

Case: Ticket created exactly at window end → zero business minutes
counted.

Case: Public holiday recurring on weekend → no double exclusion; holiday
precedence only once.

  ---------------------------------
  15\. TEST SCENARIOS (MANDATORY)
  ---------------------------------

1.  4-hour SLA across weekend boundary.
2.  SLA spanning DST forward jump.
3.  SLA spanning DST backward repeat.
4.  Cross-midnight shift response.
5.  Overtime window metadata preserved.
6.  Deprecated calendar attached to SLA → blocked.
7.  Publish calendar with overlap → blocked.

  ----------------------
  END OF SPECIFICATION
  ----------------------
