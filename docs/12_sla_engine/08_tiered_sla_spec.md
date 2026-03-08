# PET Tiered SLA Specification v1.0

Status: Authoritative
Scope: Time-Band Tiered SLA Targets, Continuous Clock, Tier Transition Algorithm
Audience: Senior Developers, Architects, Commercial Management

  ---------------------
  1\. BUSINESS CONTEXT
  ---------------------

Customers fall into three commercial profiles:

- **Office-hours only**: Fast response during business hours. No commitment outside.
- **24/7 uniform**: Same response commitment around the clock. Premium pricing.
- **Tiered (hybrid)**: Fast response during office hours, slower but committed response outside office hours, and potentially different targets on public holidays. Priced between office-hours-only and full 24/7.

The existing SLA model (one SLA → one calendar → one set of targets) serves
profiles 1 and 2. This specification extends it to support profile 3 without
breaking the existing model — single-tier SLAs continue to work unchanged.

  -------------------
  2\. CORE CONCEPTS
  -------------------

### 2.1 SLA Tier

A tier defines a time band with its own targets and escalation rules.

Fields:
- `priority` (int, required) — evaluation order. Lower number = higher priority.
  The engine checks tiers in priority order; first matching calendar wins.
- `calendar_id` (UUID, FK) — the calendar whose working windows define this
  time band.
- `response_target_minutes` (int, required)
- `resolution_target_minutes` (int, required)
- `escalation_rules[]` — tier-specific escalation thresholds

Example configuration:

- Priority 1: Office Hours calendar → 60 min response, 240 min resolution
- Priority 2: After Hours calendar → 240 min response, 480 min resolution
- Priority 3: Public Holidays calendar → 480 min response, 960 min resolution

### 2.2 Tier Transition Cap

When a ticket crosses from one tier's time band into another, the percentage
of time consumed carries forward into the new tier. The carry-forward is
**capped** to guarantee a minimum response runway in the new tier.

- `tier_transition_cap_percent` (int, default 80) — stored on the SLA definition.
- Configurable per SLA. Commercial lever for premium customers.
- Rule: `carried_forward_percent = min(actual_percent_consumed, cap)`

### 2.3 Backward Compatibility

An SLA with exactly one tier and no `tier_transition_cap_percent` behaves
identically to the existing flat model. No migration of existing SLAs
is required.

  ------------------------------------
  3\. PROCESS FLOWS (MERMAID)
  ------------------------------------

### 3.1 Tier Transition State Machine

``` mermaid
stateDiagram-v2
    [*] --> TierSelected : Ticket Created
    TierSelected --> ClockTicking : Initial tier determined
    ClockTicking --> ClockTicking : Same tier (no boundary)
    ClockTicking --> TierTransition : Boundary crossed
    ClockTicking --> EscalationWarning : threshold% reached
    ClockTicking --> Breached : 100% reached
    TierTransition --> ClockTicking : Carry-forward applied
    Breached --> TierTransition : Boundary crossed (enters new tier at cap%)
    Breached --> Breached : No transition
    ClockTicking --> Paused : Ticket pending/on-hold
    Paused --> ClockTicking : Ticket resumed (re-evaluate tier)
    ClockTicking --> Resolved : Response/Resolution received
    EscalationWarning --> ClockTicking : Escalation dispatched
```

### 3.2 Tier Selection Flow

``` mermaid
flowchart TD
    A[Ticket Created] --> B{Evaluate Tier 1 calendar}
    B -->|Current time is business minute| C[Activate Tier 1]
    B -->|Not business minute| D{Evaluate Tier 2 calendar}
    D -->|Current time is business minute| E[Activate Tier 2]
    D -->|Not business minute| F{Evaluate Tier N calendar}
    F -->|Match| G[Activate Tier N]
    F -->|No match| H[Fallback: highest priority number]
    C --> I[Record active_tier, calculate due_at]
    E --> I
    G --> I
    H --> I
```

### 3.3 Boundary Crossing Flow

``` mermaid
flowchart TD
    A[Clock Evaluation] --> B{Current moment same tier?}
    B -->|Yes| C[Normal progression]
    B -->|No| D[Calculate actual_percent]
    D --> E{actual_percent >= 100%?}
    E -->|Yes| F[Record BREACH against outgoing tier]
    E -->|No| G[No breach]
    F --> H[carried_percent = cap]
    G --> I[carried_percent = min actual, cap]
    H --> J[Activate new tier]
    I --> J
    J --> K[equivalent_elapsed = carried% × new_target]
    K --> L[Recalculate due_at]
    L --> M[Record transition + activity log]
```

### 3.4 SLA Lifecycle Overview

``` mermaid
flowchart LR
    A[SLA Draft] --> B[Add Tiers]
    B --> C[Configure per-tier targets]
    C --> D[Configure per-tier escalation]
    D --> E[Set transition cap %]
    E --> F{Validate: full coverage?}
    F -->|Gaps| G[Block publish]
    F -->|Overlaps| G
    F -->|Valid| H[Publish SLA]
    H --> I[Bind to Contract via Quote]
    I --> J[Snapshot tiers + calendars]
    J --> K[Ticket created → tier selected]
    K --> L[Clock ticks per tier]
    L --> M[Transitions as boundaries crossed]
```

  -----------------------------------------
  4\. TIER TRANSITION ALGORITHM (CANONICAL)
  -----------------------------------------

### 4.1 Initial Tier Selection

When a ticket is created:

1. Evaluate each tier in `priority` order (ascending).
2. For each tier, call `isBusinessMinute(now, tier.calendar_snapshot)`.
3. First tier returning `true` becomes the **active tier**.
4. Record `active_tier_priority`, `tier_started_at`, `elapsed_business_minutes = 0`.
5. Calculate `response_due_at` and `resolution_due_at` using the active
   tier's calendar and targets.

If no tier matches (should not occur with correct configuration — see §3.5):
fall back to the tier with the **highest priority number** (least restrictive).

### 4.2 Boundary Crossing

The SLA clock evaluator runs periodically (cron) and on ticket events.
At each evaluation:

1. Determine which tier's calendar contains the current moment.
2. If it is the **same tier** as active → normal clock progression. No action.
3. If it is a **different tier** → tier transition occurs:

   a. Calculate `actual_percent = elapsed_business_minutes / active_tier_target`
   b. Calculate `carried_percent = min(actual_percent, tier_transition_cap / 100)`
   c. Activate the new tier.
   d. Calculate `equivalent_elapsed = carried_percent * new_tier_target`
   e. Recalculate `response_due_at` / `resolution_due_at` from this point
      using the new tier's calendar and remaining minutes.
   f. Record the transition in `sla_clock_tier_transitions`.
   g. Log an activity entry for audit.

### 4.3 Already-Breached Transition

If `actual_percent >= 1.0` (100%) at the point of transition:

1. The breach is recorded against the **outgoing tier**. This is a fact.
2. The ticket enters the new tier at `carried_percent = cap` (e.g., 80%).
3. This gives a short recovery window in the new tier.
4. If the ticket also breaches in the new tier, a **second breach event**
   is recorded — compounding severity in reporting.

### 4.4 Multiple Transitions

A ticket may cross multiple tier boundaries during its lifecycle. Each
transition applies the same algorithm independently. The carry-forward
cap applies at every boundary.

Example: Ticket raised Friday 16:30 (Office Hours).
- 16:30–17:00: 30 mins of 60 min target = 50%. Office Hours → After Hours.
- Carry 50% into After Hours (240 min). 120 mins consumed. 120 remain.
- If ticket survives to Saturday (Public Holiday): calculate % consumed in
  After Hours, cap at 80%, carry into Public Holidays tier.

### 4.5 Catch-All Tier

There is no mandatory "default" tier. The SLA definition is a commercial
commitment — every moment must be covered by exactly one tier's calendar.

Validation at SLA publish: the union of all tier calendars must provide
complete time coverage (no gaps). If gaps exist, publish is blocked with
a validation error.

  -------------------------------------------
  5\. CLOCK STATE MODEL (EXTENDED)
  -------------------------------------------

### 5.1 sla_clock_state (amended)

Existing fields retained. New fields:

- `active_tier_priority` (int) — currently active tier
- `tier_elapsed_business_minutes` (int) — minutes elapsed in current tier
- `carried_forward_percent` (decimal 5,2) — percent carried from previous tier
- `total_transitions` (int, default 0) — count of tier transitions

### 5.2 sla_clock_tier_transitions (new table)

- `id` (UUID, PK)
- `ticket_id` (UUID, FK)
- `from_tier_priority` (int)
- `to_tier_priority` (int)
- `actual_percent_at_transition` (decimal 5,2)
- `carried_percent` (decimal 5,2)
- `transitioned_at` (datetime, UTC)

This table provides the audit trail. Combined with the activity log entry,
it gives full traceability.

  ----------------------------
  6\. SNAPSHOT STRUCTURE
  ----------------------------

The SLA snapshot (bound to contracts) MUST include:

```
{
  "sla_id": UUID,
  "sla_version": int,
  "tier_transition_cap_percent": 80,
  "tiers": [
    {
      "priority": 1,
      "calendar_snapshot": { ... },
      "response_target_minutes": 60,
      "resolution_target_minutes": 240,
      "escalation_rules": [ ... ]
    },
    {
      "priority": 2,
      "calendar_snapshot": { ... },
      "response_target_minutes": 240,
      "resolution_target_minutes": 480,
      "escalation_rules": [ ... ]
    }
  ]
}
```

Each tier embeds its own calendar snapshot. No live calendar lookup
permitted during SLA evaluation.

  ----------------------------
  7\. ESCALATION RULES
  ----------------------------

Escalation thresholds are defined **per tier** and evaluated against
the **current tier's target** with carried-forward percentage.

Example:
- Tier 1: Escalate at 80% of 60 min = at 48 mins.
- Ticket transitions to Tier 2 at 50% consumed.
- Tier 2: Escalate at 80% of 240 min = at 192 mins.
- 50% already consumed = 120 mins. Escalation fires at 192 mins total
  (72 more after-hours minutes).

Escalation events include `tier_priority` in their payload so downstream
consumers know which tier triggered the escalation.

  ----------------------------
  8\. BREACH DETECTION
  ----------------------------

Breach is at 100% of the **current tier's target** accounting for
carried-forward percentage.

A single ticket may accumulate **multiple breach events** if it breaches
in one tier and then, after transition, breaches again in a subsequent tier.

Breach events include:
- `tier_priority` — which tier was breached
- `tier_calendar_id` — which calendar applied
- `target_minutes` — the target that was breached
- `actual_minutes` — total equivalent minutes consumed

  ----------------------------
  9\. MANUAL TIER OVERRIDE
  ----------------------------

An authorised agent may manually override the active tier for a ticket.

Rules:
- Override applies the carry-forward algorithm as if a natural transition
  occurred at the point of override.
- The override is recorded in `sla_clock_tier_transitions` with an
  `override_reason` field.
- An activity log entry is created with the agent ID and reason.
- The overridden tier's targets and escalation rules take effect immediately.

  ----------------------------
  10\. SCHEMA ADDITIONS
  ----------------------------

### Table: sla_tiers

- `id` (UUID, PK)
- `sla_id` (UUID, FK)
- `priority` (int, required) — evaluation order
- `calendar_id` (UUID, FK, required)
- `response_target_minutes` (int, required)
- `resolution_target_minutes` (int, required)

Unique constraint: (sla_id, priority)

### Table: sla_tier_escalation_rules

- `id` (UUID, PK)
- `sla_tier_id` (UUID, FK)
- `threshold_percent` (int, required)
- `escalation_role_id` (UUID)
- `escalation_level` (int)
- `notify_method` (enum: email, sms, internal)

### Table: sla_clock_tier_transitions

- `id` (UUID, PK)
- `ticket_id` (UUID, FK)
- `from_tier_priority` (int)
- `to_tier_priority` (int)
- `actual_percent_at_transition` (decimal 5,2)
- `carried_percent` (decimal 5,2)
- `override_reason` (text, nullable)
- `transitioned_at` (datetime, UTC)

### Amended: sla_clock_state

Add columns:
- `active_tier_priority` (int)
- `tier_elapsed_business_minutes` (int)
- `carried_forward_percent` (decimal 5,2)
- `total_transitions` (int, default 0)

### Amended: slas

Add column:
- `tier_transition_cap_percent` (int, default 80)

The existing `response_time_target_minutes`, `resolution_time_target_minutes`,
and `calendar_id` fields are retained for backward compatibility with
single-tier SLAs. For tiered SLAs, these fields are NULL and the tiers
table is authoritative.

  ----------------------------
  11\. API CONTRACT
  ----------------------------

### POST /slas (tiered)

```
{
  "name": "Premium Tiered",
  "tier": "gold",
  "tier_transition_cap_percent": 80,
  "tiers": [
    {
      "priority": 1,
      "calendar_id": "uuid-office-hours",
      "response_target_minutes": 60,
      "resolution_target_minutes": 240,
      "escalation_rules": [
        { "threshold_percent": 80, "action": "notify_manager" }
      ]
    },
    {
      "priority": 2,
      "calendar_id": "uuid-after-hours",
      "response_target_minutes": 240,
      "resolution_target_minutes": 480,
      "escalation_rules": [
        { "threshold_percent": 80, "action": "notify_manager" }
      ]
    }
  ]
}
```

### POST /slas (single-tier, backward compatible)

Existing payload unchanged. Internally creates one tier from the flat fields.

### POST /tickets/{id}/override-tier

```
{
  "tier_priority": 1,
  "reason": "Customer escalated to office-hours response"
}
```

Requires authorised role. Logged in activity.

  ----------------------------
  12\. VALIDATION RULES
  ----------------------------

At SLA publish:

- At least one tier required.
- Each tier must reference a valid, published calendar.
- Tier priorities must be unique within the SLA.
- Tier calendars must provide complete time coverage (no uncovered gaps).
- `tier_transition_cap_percent` must be between 1 and 99.
- Each tier must have at least one escalation rule.
- Overlapping calendar windows across tiers are rejected.

  ----------------------------
  13\. EDGE CASES
  ----------------------------

Case: Single-tier SLA → no transitions possible. Behaves identically
to existing model.

Case: Ticket created exactly at tier boundary → the tier whose calendar
contains the boundary minute wins (evaluated in priority order).

Case: Tier transition during a paused clock (e.g., ticket in "pending"
status) → transition is evaluated when clock resumes. Percentage is
calculated from business minutes elapsed before pause.

Case: Manual override to the same tier that is already active → no-op.
No transition recorded.

Case: Carry-forward cap results in effective elapsed exceeding new tier
target → impossible by design. Cap of 80% guarantees minimum 20% runway.

Case: All tier calendars exclude the same moment (e.g., midnight DST gap)
→ clock does not tick. No transition. Next business minute in any tier
calendar resumes evaluation.

  -----------------------------------------------
  14\. LIFECYCLE INTEGRATION CONTRACT
  -----------------------------------------------

### When does a Tier exist?

Tiers exist as children of an SLA definition. They are created during
SLA drafting and are immutable once the parent SLA is published.

### Render Rules

- Tiers are rendered in the SLA Builder UI only when SLA mode is "tiered".
- Single-tier SLAs render the flat target fields (backward compatible UI).
- In ticket detail views, the **active tier** is displayed with its
  targets and elapsed percentage. Previous tiers are shown in the
  transition history.
- Tier transition cap is rendered as a global SLA-level setting,
  not per tier.

### Creation Rules

- Tiers are created explicitly by the user in the SLA Builder.
- Tiers are NEVER auto-created. Switching SLA mode to "tiered" presents
  an empty tier list; the user must add tiers manually.
- A tier cannot be created without a calendar reference.
- A tier cannot be created without response and resolution targets.
- A tier cannot be created without at least one escalation rule.

### Mutation Rules

- Tiers on a **draft** SLA are fully editable (add, remove, reorder, change).
- Tiers on a **published** SLA are immutable. Any change requires a new
  SLA version.
- The `tier_transition_cap_percent` follows the same immutability rule.
- Tier deletion on a draft SLA cascades to its escalation rules.
- Changing a tier's calendar on a draft SLA does not affect other tiers.

``` mermaid
flowchart TD
    A[SLA Draft Created] --> B{Mode?}
    B -->|Single-tier| C[Flat targets + single calendar]
    B -->|Tiered| D[Empty tier list]
    D --> E[User adds Tier 1 + calendar + targets + escalation]
    E --> F[User adds Tier 2 + calendar + targets + escalation]
    F --> G[User sets transition cap %]
    G --> H{Publish?}
    H -->|Validate| I{Full calendar coverage?}
    I -->|No gaps, no overlaps| J[Published — immutable]
    I -->|Gaps or overlaps| K[Publish blocked]
    J --> L[Bind to Contract via Quote]
    L --> M[Snapshot: tiers + calendars frozen]
    M --> N[Ticket lifecycle: tier selection + transitions]
```

  -----------------------------------------------
  15\. PROHIBITED BEHAVIOURS
  -----------------------------------------------

- Must NOT auto-create tiers when SLA mode is set to "tiered".
- Must NOT allow modification of tiers on a published SLA.
- Must NOT allow a tiered SLA to be published with calendar coverage gaps.
- Must NOT allow overlapping calendar windows across tiers.
- Must NOT allow `tier_transition_cap_percent` of 0 or 100.
  (0 = no carry-forward, mathematically invalid. 100 = no cap, defeats
  purpose of the guarantee.)
- Must NOT switch SLA mode from single-tier to tiered (or vice versa)
  on a published SLA. Requires a new version.
- Must NOT re-fire escalation events that were already dispatched in
  a previous tier when a transition occurs.
- Must NOT carry forward escalation state across tiers. Escalation
  thresholds reset relative to the new tier's targets (with carried %).
- Must NOT allow a tier without at least one escalation rule.
- Must NOT perform live calendar lookups during SLA evaluation.
  All evaluation uses snapshot data only.
- Must NOT auto-assign a tier on manual override without an explicit
  reason. The `override_reason` field is required.
- Must NOT allow a ticket to exist with a tiered SLA but no
  `active_tier_priority` in its clock state.
- Must NOT silently ignore a tier transition. Every transition MUST
  produce both a `sla_clock_tier_transitions` record and an activity
  log entry.
- Must NOT allow the same calendar to be used in multiple tiers of
  the same SLA.

  -----------------------------------------------
  16\. STRESS-TEST SCENARIOS (CROSS-BOUNDARY)
  -----------------------------------------------

These are lifecycle integration tests, not unit invariant checks.

### SLA Definition Lifecycle

1.  New draft SLA, mode set to tiered → tier list is empty. No defaults.
2.  Add one tier without a calendar → save blocked.
3.  Add two tiers with overlapping calendars → publish blocked.
4.  Add two tiers with a 2-hour gap in coverage → publish blocked.
5.  Publish valid tiered SLA → all tiers and escalation rules frozen.
6.  Edit published tiered SLA → blocked. Must create new version.
7.  Clone published tiered SLA → new draft with tiers copied, editable.
8.  Deprecate tiered SLA → existing contract snapshots unaffected.
9.  Delete a tier from draft SLA → its escalation rules cascade-deleted.
10. Change tier calendar on draft → other tiers unaffected.

### Contract & Quote Binding

11. Bind tiered SLA to quote → snapshot includes all tiers + calendars.
12. Accept quote → contract snapshot immutable.
13. SLA deprecated after contract acceptance → contract snapshot unchanged.
14. New version of SLA published → existing contracts still use old snapshot.

### Ticket Lifecycle (Tier Transitions)

15. Ticket created during office hours (Tier 1) → active_tier = 1.
    Close ticket within Tier 1 → no transition, no transition records.
16. Ticket created at 16:50 (10 min before close). 60 min target.
    At 17:00 (10/60 = 16.7%) → transitions to Tier 2.
    Verify: carried_percent = 16.7%, equivalent_elapsed = 40 mins of 240.
    Verify: response_due_at recalculated correctly.
17. Ticket created at 06:00 (after hours, Tier 2). 240 min target.
    At 08:00 (120/240 = 50%) → transitions to Tier 1.
    Verify: carried_percent = 50%, equivalent_elapsed = 30 mins of 60.
    Verify: response_due_at = 08:30.
18. Ticket created at 16:00 (office hours, Tier 1). Not responded.
    At 17:00 (60/60 = 100%) → BREACH logged against Tier 1.
    Transitions to Tier 2 at cap (80%). 20% of 240 = 48 mins remaining.
    Verify: breach event has tier_priority = 1.
    Verify: new response_due_at = 17:48.
19. Ticket crosses three tiers: Office (16:30) → After Hours (17:00) →
    Public Holiday (midnight). Verify two transition records, each with
    independent carry-forward calculation.
20. Ticket in Tier 1, clock paused (status = pending). Time passes,
    office hours end. Clock resumed during after hours.
    Verify: tier re-evaluated on resume, transition to Tier 2 with
    correct elapsed from before pause.

### Manual Override

21. Agent overrides from Tier 2 to Tier 1 with reason.
    Verify: transition record has override_reason populated.
    Verify: activity log entry with agent ID.
    Verify: Tier 1 targets now apply, carried % correct.
22. Agent attempts override to same active tier → no-op, no record.
23. Agent attempts override without reason → blocked.

### Escalation Across Tiers

24. Tier 1: escalation at 80% = 48 mins. Ticket at 40 mins (67%),
    transitions to Tier 2. Tier 2 escalation at 80% = 192 mins.
    Carried 67% = 160.8 mins equivalent. Escalation should NOT fire
    at transition (67% < 80% of Tier 2).
25. Same as above but ticket at 50 mins (83%) in Tier 1. Escalation
    fires in Tier 1. Transitions to Tier 2. Tier 2 escalation at 80%.
    Carried 80% (capped). Already at 80% → Tier 2 escalation fires
    immediately. Verify: two escalation events, different tier_priority.

### Reporting & KPI

26. Tiered SLA with 3 breaches across 2 tiers → report shows breach
    count per tier, not just total.
27. Compliance report for tiered SLA → compliance calculated per tier
    independently.
28. Tier transition frequency report → shows average transitions per
    ticket and which tier boundaries are most common.

  ----------------------------
  17\. INVARIANT TESTS
  ----------------------------

1.  Ticket in office hours, responded within tier 1 target → no transition.
2.  Ticket crosses office hours → after hours boundary. Verify carry-forward
    percentage and recalculated due time.
3.  Ticket crosses after hours → office hours boundary. Verify tighter
    target applies with correct carry-forward.
4.  Ticket breaches tier 1, transitions to tier 2 at cap. Verify breach
    logged against tier 1, tier 2 starts at cap%.
5.  Ticket crosses three tiers (office → after hours → public holiday).
    Verify each transition applies cap independently.
6.  Manual tier override with reason. Verify transition logged, activity
    log entry created, new targets applied.
7.  Single-tier SLA → no transitions, identical to existing behaviour.
8.  Publish SLA with gap in tier calendar coverage → blocked.
9.  Publish SLA with overlapping tier calendars → blocked.
10. Clock paused during tier boundary → transition evaluated on resume.
11. Carry-forward cap at configurable value (60%, 90%) → verify correct
    runway in new tier.

  ----------------------
  END OF SPECIFICATION
  ----------------------
