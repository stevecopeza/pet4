# PET WorkItem Assignment Invariant Correction — Final Spec

## Purpose

Resolve the baseline blocker revealed by PHPUnit so the repository, tests, and effective PET design are consistent before any Ticket Backbone work proceeds.

The current failure is:

- `Pet\Tests\Integration\Work\SqlWorkItemRepositoryAssignmentInvariantTest::testSaveRejectsDualAssignmentInvariantViolation`
- `InvalidArgumentException: Invalid work item persistence: both assigned_team_id and assigned_user_id are set.`
- `src/Infrastructure/Persistence/Repository/SqlWorkItemRepository.php:69`

This spec corrects that mismatch.

---

## Visual / UX Impact

**Visible UI/UX change in this phase:** No direct UI change is required.

This is a structural baseline correction.

---

## Core Design Decision

**Authoritative assignment model:**
A WorkItem may belong to a **team queue** and may also have an **individual assignee**.

That means the model is **not XOR**.

### Therefore

A valid WorkItem state may include:

- `assigned_team_id` only
- `assigned_user_id` only
- both `assigned_team_id` and `assigned_user_id`
- neither, where allowed by current lifecycle/creation rules

The repository must not reject a WorkItem merely because both fields are populated.

---

## Why This Is The Correct Rule

PET’s intended operating model is structural queue ownership plus optional person ownership.

That means:

- team assignment answers **which queue owns the work**
- user assignment answers **who is currently owning/executing it**
- these are related but not mutually exclusive concepts

Rejecting the dual state incorrectly collapses queue ownership into person ownership and breaks the intended routing model.

---

## Scope

### In scope
- Correct the persistence invariant in `SqlWorkItemRepository`
- Align integration/unit tests with the intended assignment model
- Check for any other immediate code/tests that still assume XOR assignment
- Keep the fix minimal and surgical
- Restore PHPUnit baseline for this blocker

### Out of scope
- Ticket Backbone implementation
- broader work orchestration redesign
- UI redesign
- queue rules redesign
- permissions model changes
- seed/demo redesign, except where tests or fixtures directly depend on the invalid XOR assumption

---

## Required Behaviour

### Valid states
The following states must be accepted by persistence:

1. `assigned_team_id != null`, `assigned_user_id == null`
2. `assigned_team_id == null`, `assigned_user_id != null`
3. `assigned_team_id != null`, `assigned_user_id != null`
4. `assigned_team_id == null`, `assigned_user_id == null`, only if already allowed elsewhere by current model

### Invalid states
This correction does **not** create a free-for-all. Existing invalid states unrelated to the XOR rule should remain invalid.

Examples:
- malformed IDs
- broken foreign key references, where enforced
- illegal status/state combinations if validated elsewhere

---

## Persistence Rule

Any repository-level guard that throws solely because both `assigned_team_id` and `assigned_user_id` are set must be removed or corrected.

The repository must reflect the effective domain rule, not an outdated assumption.

---

## Domain / Architecture Rule

If assignment invariants are meant to exist, they should be enforced according to current PET design, and ideally in the correct layer.

This phase should not introduce a large domain refactor. It should only remove the stale XOR constraint that blocks baseline correctness.

---

## Tests

## Required test updates
The failing test must be corrected to match the authoritative model.

### Replace invalid expectation
A test that expects rejection when both IDs are set is no longer valid.

### Required positive coverage
Add or update tests to prove:

1. save accepts team-only assignment
2. save accepts user-only assignment
3. save accepts team-plus-user assignment
4. repository round-trips both fields correctly

### Regression coverage
Search for and fix any other tests asserting XOR semantics for WorkItem assignment.

---

## Seed / Fixture Guidance

If any fixtures or test builders currently avoid dual assignment solely because of the stale invariant, they may be updated.

No broad seed/demo redesign is required in this phase, but this fix must not degrade demo data assumptions.

---

## Acceptance Criteria

This correction is complete only when all of the following are true:

1. `SqlWorkItemRepository` no longer rejects a WorkItem solely because both assignment fields are populated.
2. The failing PHPUnit test is updated or replaced appropriately.
3. Tests prove dual assignment is valid.
4. No new ambiguity is introduced in unrelated assignment logic.
5. PHPUnit passes for this blocker path.
6. The fix is minimal and does not widen scope into Ticket Backbone work.

---

## Prohibited Changes

- Do not start Ticket Backbone implementation in this phase.
- Do not redesign WorkItem architecture broadly.
- Do not change queue ownership semantics beyond this correction.
- Do not introduce new assignment rules unless strictly needed to align with current design.
- Do not widen this into a large cleanup.

---

## Deliverable

A narrow baseline-fix change that restores repository/test alignment so the larger Ticket Backbone phase can proceed on a cleaner foundation.
