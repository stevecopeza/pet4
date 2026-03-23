# PET Demo Seed Readiness Gates v1.2

Version: 1.2\
Date: 2026-03-23\
Status: Binding (Domain Preconditions for Demo Transitions)

## Purpose

Enumerate **transition-specific** preconditions required by domain
invariants. Seed must satisfy these before calling transitions.

## Gate Format

Each gate includes: - Transition - Required data - Validation method -
Demo autofill allowed? (Yes/No; reference Autofill Rules doc)

------------------------------------------------------------------------

## Quotes

### Gate: Quote Draft → Ready

**Required:** - Customer exists and is Active - Quote has at least 1
line item - Totals compute to non-zero - Currency set (if applicable)

**Validate via:** - `Quote->validateReadiness()` (or equivalent domain
readiness validator)

**Autofill allowed:** Yes (minimal line items, deterministic names)

### Gate: Quote Ready → Accepted

**Required:** - All Draft→Ready requirements met - **Payment schedule
exists** - Payment schedule **totals equal** quote total (no rounding
drift) - Any required milestones exist (if the domain demands it) - Any
required approvals/flags are satisfied (if implemented)

**Validate via:** - `Quote->accept()` must not throw - If available:
`Quote->validateAcceptable()` pre-check

**Autofill allowed:** Yes (payment schedule per Autofill Rules)

------------------------------------------------------------------------

## Projects

### Gate: Create Project from Accepted Quote

**Required:** - Quote state == Accepted - Quote is immutable (no further
draft edits) - Sold constraints are derivable (budget/hours/items) -
Required customer link exists

**Validate via:** - Project creation handler must succeed and create the
project with sold constraints

**Autofill allowed:** No (project must derive from accepted quote, not
invented)

------------------------------------------------------------------------

## Milestones

### Gate: Milestone Planned → Completed

**Required:** - Milestone exists and belongs to project - Completion
rules satisfied (e.g., all tasks done if applicable)

**Validate via:** - Domain transition call

**Autofill allowed:** Yes (create minimal milestone/task structure)

------------------------------------------------------------------------

## Time

### Gate: Time Draft → Submitted

**Required:** - Time entry references an existing project/ticket -
Duration and rate fields valid - Any "sold constraints" checks pass or
variance is explicitly recorded (if implemented) - Once submitted, entry
becomes immutable

**Validate via:** - `TimeEntry->submit()` or handler must succeed

**Autofill allowed:** Yes (create minimal valid time entries)

------------------------------------------------------------------------

## SLA

### Gate: SLA Clock Initialize

**Required:** - Ticket exists - Ticket has SLA policy assigned (directly
or via contract) - SLA clock state persistence available (table
exists) - Unique clock per ticket enforced

**Validate via:** - Repository can create/read clock state - Evaluate
can run idempotently

**Autofill allowed:** Yes (assign default SLA policy for demo ticket)

### Gate: SLA Evaluate → Breach/Warning Signal

**Required:** - Clock initialized - `breach_at` computed or computable -
Concurrency protection present (FOR UPDATE) - Evaluation produces
deterministic state

**Validate via:** - Execute evaluation twice; outputs must match
(idempotent) - Optional: simulate time passage if test harness supports
it

**Autofill allowed:** No for outcome; Yes for required config inputs

------------------------------------------------------------------------

## Advisory (Derived)

Advisory outputs must be derived from events; no readiness gates
beyond: - required projections exist - events exist for anchor artifacts

------------------------------------------------------------------------

## Demo Operations (Operational)

### Gate: Clean Demo Baseline Contract Pass

**Transition:** `POST /pet/v1/system/demo/clean-baseline` (or alias)

**Required:**  
- `confirm=CLEAN_DEMO_BASELINE` provided  
- Purge phase succeeds (`all_purges_succeeded=true`)  
- Baseline post-contract invariants pass (`contract.violations=[]`)

**Validate via:**  
- Response status `201`  
- `overall=PASS`  
- `registry.active_seed_runs=1`  
- `contract.violations=[]`

**Autofill allowed:** No

### Gate: Health/Diagnostics Semantic Alignment

**Transition:** `GET /pet/v1/system/demo/health` + `GET /pet/v1/system/demo/diagnostics`

**Required:**  
- Active run counts are semantically aligned:  
  - health `seed.active_runs_count`  
  - diagnostics `registry_summary.active_runs_count`  
- Shared duplicate integrity classes are coherent across surfaces:
  - duplicate employee emails
  - duplicate skill pairs
  - duplicate certification pairs

**Validate via:**  
- Compare both endpoint responses in same operator cycle  
- Confirm no contradictory state between readiness reasons and diagnostics issues

**Autofill allowed:** No
