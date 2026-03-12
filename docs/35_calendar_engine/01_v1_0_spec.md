# PET Calendar Engine v1.0 -- Developer Execution Specification

Status: Authoritative Scope: Business Time Computation, SLA Timing,
Capacity Foundations Audience: Developers, Architects

  ---------------------------
  1\. ARCHITECTURAL PURPOSE
  ---------------------------

The Calendar Engine is a domain service responsible for:

-   SLA business-time computation
-   Escalation timing
-   Capacity planning foundations
-   Future staff availability modelling

It is NOT a UI calendar. It is a time computation engine.

  ----------------------------
  2\. CORE DESIGN PRINCIPLES
  ----------------------------

1.  Multi-calendar per organisation supported.
2.  Calendars are versioned once referenced by SLA.
3.  Business time is computed precisely (minute-accurate).
4.  All timestamps stored in UTC.
5.  Computation uses calendar-specific timezone.
6.  SLA snapshots must include calendar snapshot.
7.  Capacity calendars are separate from SLA calendars.

  ---------------------
  3\. DATABASE SCHEMA
  ---------------------

  : calendars - id (UUID, PK) - organisation_id (UUID, FK) - name
  (varchar 255) - type (enum: business_hours, 24x7, holiday_only,
  capacity) - timezone (varchar 64, required, IANA format) -
  version_number (int) - status (enum: draft, published, deprecated,
  archived) - created_at (datetime) - updated_at (datetime)

Unique constraint: (organisation_id, name, version_number)

Table: calendar_working_windows - id (UUID, PK) - calendar_id (UUID,
FK) - weekday (int, 0-6) - start_time (time) - end_time (time) -
window_type (enum: standard, overtime) - rate_multiplier (decimal 5,2,
nullable)

Supports: - Multiple windows per day - Cross-midnight windows (end_time
\< start_time)

Table: calendar_holidays - id (UUID, PK) - calendar_id (UUID, FK) -
holiday_date (date) - name (varchar 255) - recurring_flag (boolean) -
region (varchar 100)

  ----------------------
  4\. VERSIONING RULES
  ----------------------

  : calendar_special_events - id (UUID, PK) - calendar_id (UUID, FK) -
  event_date (date) - event_type (enum: sick, absent,
  public_holiday_override) - description (text)

-   Draft calendars editable.
-   Published calendars immutable.
-   If referenced by SLA, new version required for modification.
-   SLA snapshot must include calendar snapshot structure.

  ---------------------------------------
  5\. BUSINESS TIME COMPUTATION SERVICE
  ---------------------------------------

Domain Service Interface:

-   calculateBusinessMinutes(startUTC, endUTC, calendarId)
-   addBusinessMinutes(startUTC, minutes, calendarId)
-   isBusinessTime(timestampUTC, calendarId)
-   nextBusinessMinute(timestampUTC, calendarId)

Algorithm (Pseudo):

1.  Convert UTC to calendar timezone.
2.  Iterate minute-by-minute (or window-optimized stepping).
3.  Check if within working window.
4.  Exclude holidays and special events.
5.  Accumulate minutes.

Must handle: - DST transitions - Leap years - Cross-midnight windows -
Weekend overtime windows - Overlapping holidays

  --------------------------------
  6\. OVERTIME & WEEKEND SUPPORT
  --------------------------------

Working windows may define: - Standard hours (rate_multiplier = 1.0) -
Overtime hours (rate_multiplier \> 1.0)

Calendar does not calculate payroll. It exposes rate multiplier metadata
for future capacity/cost models.

  ---------------------
  7\. SLA INTEGRATION
  ---------------------

SLA references: - response_calendar_id - resolution_calendar_id

On SLA publish: - Calendar snapshot captured.

Breach detection always uses SLA snapshot calendar, not live calendar.

  --------------------------
  8\. CAPACITY INTEGRATION
  --------------------------

Capacity planning uses separate calendar type (capacity).

Supports: - Standard availability - Overtime blocks - Special absence
events (sick, leave)

Capacity calendar must not override SLA calendar.

  ------------------
  9\. API CONTRACT
  ------------------

POST /calendars PUT /calendars/{id} POST /calendars/{id}/publish POST
/calendars/{id}/deprecate GET /calendars?status=published

Validation: - Cannot publish without at least one working window. -
Cannot modify published calendar. - Cannot delete calendar referenced by
SLA.

  ------------------
  10\. UX CONTRACT
  ------------------

Calendar Builder UI: - Weekly grid layout - Add multiple windows per
day - Mark overtime windows - Holiday management panel - Special events
panel - Timezone selection dropdown

Warnings: - Publishing locks calendar version. - Changes require new
version.

  -------------------------
  11\. PERMISSIONS MATRIX
  -------------------------

Commercial Management: - Create/Edit/Publish calendars

Support: - View calendars

Sales: - No calendar editing

  ---------------------------------
  12\. PERFORMANCE CONSIDERATIONS
  ---------------------------------

-   Cache computed working windows per day.
-   Pre-calculate holiday lookup sets.
-   Avoid per-minute DB calls.
-   Use in-memory evaluation per request.

  ---------------------
  13\. TEST SCENARIOS
  ---------------------

1.  SLA 4-hour response across weekend.
2.  Ticket created before closing time.
3.  Cross-midnight shift (22:00--06:00).
4.  DST boundary case.
5.  Holiday on recurring annual date.
6.  Overtime window applied correctly.
7.  Calendar deprecated while bound to SLA (new version required).

  ---------------------------
  14\. IMPLEMENTATION ORDER
  ---------------------------

1.  Migrations
2.  Domain entities & invariants
3.  Calendar service implementation
4.  SLA integration
5.  Capacity hook integration
6.  UI builder
7.  Performance validation

  ----------------------
  END OF SPECIFICATION
  ----------------------
