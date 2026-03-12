# PET Conversations & Approvals — Stress-Test Scenarios (v1.0)

**Status:** Draft (pre-dev validation)  
**Date:** 2026-02-23

These scenarios are designed to validate PET’s immutability, permission safety, and concurrency invariants **before** implementation proceeds.

---

## 1. Permission & visibility leak tests

### 1.1 No context access (hard non-leak)
- **Given** user U has no access to ticket T1
- **When** U requests activity stream for T1
- **Then** response must not reveal:
  - conversation existence
  - counts (unread/pending)
  - subject labels
  - decision indicators
- **And** direct deep link to any conversation under T1 returns a generic forbidden response that does not confirm existence.

### 1.2 Participant-only visibility (default mode)
- **Given** user U has context access to quote Q1
- **And** conversation C1 exists but U is not a participant
- **When** U loads Q1 activity stream
- **Then** C1 must not appear
- **When** U is added as participant (`ParticipantAdded`)
- **Then** C1 becomes visible and is included in activity stream.

### 1.3 Mention auto-add
- **Given** conversation C1 exists and U is not a participant
- **When** U is mentioned in a `MessagePosted`
- **Then** U becomes a participant (auto-add event) and receives a notification
- **And** U can open C1 (permission passes).

### 1.4 Approver auto-add on DecisionRequested
- **Given** conversation C1 exists and approver set resolves to users A,B
- **When** a `DecisionRequested` is created
- **Then** A and B become participants (auto-add) and receive “requires your response” notifications.

---

## 2. Immutability & audit tests

### 2.1 No edit/delete operations
- **Given** message M1 exists
- **When** any update/delete is attempted (API or UI)
- **Then** the operation must hard-fail (or endpoint must not exist)
- **And** no mutation occurs in stored event payloads.

### 2.2 Additive corrections only
- **Given** a user posted incorrect info
- **When** they “correct” themselves
- **Then** a new `MessagePosted` is added, not a mutation.

### 2.3 Redaction preserves audit skeleton
- **Given** an event contains PII requiring removal
- **When** `RedactionApplied` is emitted targeting that event + fields
- **Then** rendering masks those fields
- **And** retains:
  - original event ordering
  - actor + timestamps
  - redaction actor + timestamp + reason code
- **And** no hard deletion occurs.

### 2.4 Integrity checks (optional but recommended)
- **Given** event hashing enabled
- **When** any stored payload is tampered with
- **Then** integrity verification fails and audit warnings surface (admin-only).

---

## 3. Concurrency & idempotency tests (decisions)

### 3.1 Double-approve race
- **Given** decision D is Pending
- **When** two eligible approvers approve simultaneously
- **Then** exactly one response finalizes D
- **And** the other returns `DECISION_ALREADY_FINALIZED`
- **And** D state ends Approved with a single finalizer recorded.

### 3.2 Approve vs Reject race
- **Given** decision D is Pending
- **When** A approves while B rejects simultaneously
- **Then** first write wins; second returns `DECISION_ALREADY_FINALIZED`
- **And** D ends in a single terminal state (Approved or Rejected)
- **And** timeline shows the finalized outcome only (optional: log rejected attempt as diagnostic event; must not alter terminal state).

### 3.3 Idempotent retry
- **Given** decision D is Pending
- **When** approver’s client retries the same approve request (network retry)
- **Then** response is idempotent and safe
- **And** only one finalization event exists.

### 3.4 Unauthorized responder
- **Given** user X is not in eligible approver set
- **When** X calls respond endpoint
- **Then** return `DECISION_FORBIDDEN`
- **And** no state transition occurs.

### 3.5 Eligibility snapshot stability
- **Given** decision D materialized eligible approvers set at request time
- **When** org roles/teams change later
- **Then** eligibility for D remains based on the snapshot (audit integrity).

---

## 4. Hard-block enforcement tests

### 4.1 Send Quote to Customer gate
- **Given** Quote Q has required decision type `send_to_customer_gate`
- **And** decision is Pending
- **When** user attempts “Send to customer”
- **Then** hard error `ACTION_GATED_BY_DECISION` and includes `decision_id` remediation link
- **And** no outbound send occurs
- **And** a domain event may be recorded for the blocked attempt (optional, admin-only).

### 4.2 Gate satisfied
- **Given** all required decisions for action are Approved
- **When** user attempts “Send to customer”
- **Then** action proceeds.

### 4.3 Gate regression (new decision requested after approval)
- **Given** previously approved gate decision exists for quote version V1
- **When** quote is cloned to V2 (new draft) and requires new gate approval
- **Then** V2 action must re-check decisions for V2, not reuse V1 approvals.

---

## 5. Subject collapsing & navigation tests

### 5.1 Collapse by subject_key not raw subject
- **Given** two conversations have same subject text but different subject_key
- **When** activity stream loads
- **Then** they must not collapse together.

### 5.2 Deep link focus event
- **Given** deep link contains focus_event_id
- **When** conversation panel opens
- **Then** timeline scrolls to that event (or loads the page containing it)
- **And** highlights it without changing ordering.

---

## 6. Performance & paging tests

### 6.1 Large history
- **Given** conversation has 10k events
- **When** panel opens
- **Then** default view shows summary + recent subset
- **And** timeline paging works without loading entire history.

### 6.2 Unread computation at scale
- **Given** many conversations per context
- **When** activity stream loads
- **Then** unread counts compute from `last_seen_event_id` efficiently (indexed), not by scanning full event history.

---

## 7. Failure-mode safety tests

### 7.1 Notifications do not leak existence
- **Given** user loses context access after notification queued
- **When** notification is delivered/viewed
- **Then** notification rendering must not reveal details and must be suppressed or generic.

### 7.2 Partial write prevention
- **Given** DecisionRequested and Conversation link event must be consistent
- **When** DB failure occurs mid-transaction
- **Then** system rolls back so there is no orphaned decision or broken timeline link.

