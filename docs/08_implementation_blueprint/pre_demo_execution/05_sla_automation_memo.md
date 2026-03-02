# PET SLA Automation Implementation Memo v1.0

## Purpose

This memo defines the **correct execution approach for Phase 6.1 -- SLA
Automation Loop**.

The SLA automation layer must be:

-   Deterministic
-   Idempotent
-   Stateful
-   Concurrency-safe
-   Event-accurate

Do **not** implement this as a simple cron recalculation script.

------------------------------------------------------------------------

# 1. Required Migration Pack

## 1.1 New Table: `sla_clock_state`

``` text
id (UUID PK)
ticket_id (UUID FK, unique)
sla_version_id (UUID FK)
warning_at (datetime nullable)
breach_at (datetime nullable)
paused_flag (boolean default false)
escalation_stage (int default 0)
last_evaluated_at (datetime nullable)
last_event_dispatched (enum: none, warning, breached)
created_at
updated_at
```

### Index Requirements

-   UNIQUE(ticket_id)
-   INDEX(breach_at)
-   INDEX(last_evaluated_at)

------------------------------------------------------------------------

# 2. Implementation Order (Strict)

## Step 1 -- Migration Only

Create table and indexes.

Do NOT bind cron yet.

------------------------------------------------------------------------

## Step 2 -- Implement Domain Service (Without Cron)

Create:

    SlaAutomationService::evaluate(ticket)

Unit test this method independently.

------------------------------------------------------------------------

# 3. Deterministic Transition Model

The automation loop must only dispatch events when the state
transitions.

``` mermaid
stateDiagram-v2
    [*] --> Active
    Active --> Warning : warning threshold crossed
    Warning --> Breached : breach threshold crossed
    Breached --> Escalated : escalation rule triggered
    Warning --> Active : pause/resume recalculation
```

Events must fire **only on state change**.

------------------------------------------------------------------------

# 4. Idempotent Event Logic

Pseudocode:

``` pseudo
new_state = calculate_state(ticket)

persisted_state = load_sla_clock_state(ticket)

if new_state != persisted_state:
    dispatch_event(new_state)
    update_sla_clock_state(new_state)
```

Re-running evaluation must not produce duplicate events.

------------------------------------------------------------------------

# 5. Concurrency Protection (Mandatory)

WP Cron is not guaranteed to be single-threaded.

You must guard against concurrent execution.

## Recommended Approach

Use row-level locking:

``` sql
SELECT * FROM sla_clock_state
WHERE ticket_id = ?
FOR UPDATE;
```

Or enforce atomic update patterns.

Duplicate breach events are unacceptable.

------------------------------------------------------------------------

# 6. Escalation Stage Tracking

Use `escalation_stage` integer to prevent multiple escalations.

``` mermaid
flowchart TD
    Breached --> Stage1[Escalation Stage 1]
    Stage1 --> Stage2[Escalation Stage 2]
    Stage2 --> Final[Executive Escalation]
```

Escalation must increment stage only once per threshold.

------------------------------------------------------------------------

# 7. Automation Loop Flow

``` mermaid
sequenceDiagram
    participant Cron
    participant SlaAutomationService
    participant Ticket
    participant StateTable
    participant EventBus

    Cron->>SlaAutomationService: run()
    SlaAutomationService->>Ticket: recalc SLA
    SlaAutomationService->>StateTable: lock + load state
    SlaAutomationService->>EventBus: dispatch if transition
    SlaAutomationService->>StateTable: persist new state
```

------------------------------------------------------------------------

# 8. Binding Cron (Only After Unit Tests)

Once evaluation is proven deterministic:

Bind:

    wp_schedule_event(...)

Frequency: 1--5 minutes.

------------------------------------------------------------------------

# 9. Mandatory Test Cases

-   Warning fires once only.
-   Breach fires once only.
-   Escalation fires once per stage.
-   Double cron invocation does not duplicate events.
-   Ticket pause prevents breach progression.
-   Ticket closure stops evaluation.

------------------------------------------------------------------------

# 10. Exit Criteria

Phase 6.1 is complete only when:

-   Ticket configured to breach in 30 minutes auto-fires events.
-   No duplicate events occur.
-   Escalation stage increments correctly.
-   Concurrency tests pass.
-   Re-running cron produces stable results.

------------------------------------------------------------------------

# Final Directive

This is a deterministic automation engine, not a polling script.

If idempotency and concurrency protection are not implemented, Phase 6.1
is not complete.

END OF DOCUMENT
