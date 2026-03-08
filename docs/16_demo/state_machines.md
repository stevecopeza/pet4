# PET Demo State Machines v1.0

Version: 1.0\
Date: 2026-02-14\
Status: Binding (Demo Transition Clarity)

## Purpose

Provide explicit, visual state machines for demo-critical aggregates to
reduce ambiguity in seed execution, tests, and live demos.

## Scope

-   Quote
-   Time Entry
-   Ticket / SLA Clock (conceptual pairing for demo transitions)

> Note: These diagrams describe *observed/required demo transitions* and
> must align with domain invariants. If the codebase differs, the code
> must be brought into alignment per binding docs.

------------------------------------------------------------------------

## Quote State Machine (Demo View)

``` mermaid
stateDiagram-v2
  [*] --> Draft

  Draft --> Ready: validateReadiness()\n(all readiness gates pass)
  Draft --> Draft: editDraft()\n(lines, metadata, notes)

  Ready --> Draft: revertToDraft()\n(if supported)\nOR explicit clone/edit flow
  Ready --> Accepted: accept()\n(payment schedule + gates pass)

  Accepted --> [*]: immutable\n(no edits)\n(corrections via versioning/CO)
```

### Notes (Demo Requirements)

-   Seed must **not** attempt `Accepted` unless `Ready` gates pass.
-   If revert is not supported, demo edits must be shown via
    **clone-to-draft** or versioning.
-   Any failed `accept()` must degrade to `Ready` (or `Draft`) and be
    recorded in step results.

------------------------------------------------------------------------

## Time Entry State Machine (Demo View)

``` mermaid
stateDiagram-v2
  [*] --> Draft

  Draft --> Submitted: submit()\n(valid refs + totals)
  Draft --> Draft: editDraft()\n(duration, refs)

  Submitted --> [*]: immutable\n(no edits)\n(corrections via compensating entries)
```

### Notes (Demo Requirements)

-   Demo must show immutability: an attempted edit after `Submitted`
    should fail (or require a compensating entry workflow).
-   Seed should create at least one submitted anchor
    (`DEMO Time - W1 (Submitted)`).

------------------------------------------------------------------------

## Ticket + SLA Clock (Demo View)

Tickets and SLA clocks are separate concerns but shown together because
demo success depends on their coordination.

``` mermaid
stateDiagram-v2
  state "Ticket" as T {
    [*] --> Open
    Open --> InProgress: startWork()\n(if implemented)
    InProgress --> Resolved: resolve()\n(if implemented)
    Resolved --> Closed: close()\n(if implemented)
    Open --> Closed: closeDirect()\n(if allowed)
  }

  state "SLA Clock" as S {
    [*] --> NotInitialized
    NotInitialized --> Running: initializeClock()\n(policy assigned)
    Running --> Running: evaluate()\n(idempotent)
    Running --> Warning: evaluate()\n(approaching breach)\n(if implemented)
    Running --> Breached: evaluate()\n(breach_at passed)\n(if implemented)
    Warning --> Breached: evaluate()\n(breach_at passed)
    Breached --> Breached: evaluate()\n(idempotent)
  }
```

### Notes (Demo Requirements)

-   Seed must assign an SLA policy/config before initializing the clock.
-   The SLA engine must be demonstrated as **idempotent**: run
    `evaluate()` twice and confirm stable outcomes.
-   If Warning/Breached signaling is not implemented, the demo
    requirement reduces to: Running + idempotent evaluate + feed/signal
    presence (if any).

------------------------------------------------------------------------

## Optional: Change Order (if in scope)

If Change Orders exist in current implementation, use:

``` mermaid
stateDiagram-v2
  [*] --> Draft
  Draft --> Submitted: submit()\n(if implemented)
  Draft --> Approved: approve()\n(if direct approve is allowed)
  Submitted --> Approved: approve()
  Approved --> [*]: immutable\n(corrections via new CO)
```
