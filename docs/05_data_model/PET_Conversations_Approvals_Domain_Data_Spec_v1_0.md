# PET Conversations & Approvals — Domain + Data Spec (v1.0)

**Status:** Draft (implementation-ready)  
**Date:** 2026-02-23  
**Constraints:** PET DDD layering; custom tables only; forward-only migrations.

## 1) Core concepts
- **Conversation**: anchored thread with subject/subject_key, participants, state (open/resolved), and an append-only event stream.
- **Decision**: governance artifact requested within a conversation, with policy + state machine.

## 2) Anchoring
Conversation has exactly one anchor:
- `context_type`: `quote | quote_line_item | project | ticket | ...`
- `context_id`: UUID

Other contexts may reference via link/notification/activity, but do not multi-anchor.

## 3) Domain events (append-only)
Conversation events:
- ConversationCreated, MessagePosted, ParticipantAdded/Removed, ConversationResolved/Reopened, RedactionApplied, (VisibilityChanged if needed)

Decision events:
- DecisionRequested, DecisionResponded, DecisionCancelled, (DecisionExpired optional)

## 4) State machines

```mermaid
stateDiagram-v2
  [*] --> Open
  Open --> Resolved: ConversationResolved
  Resolved --> Open: ConversationReopened
```

```mermaid
stateDiagram-v2
  [*] --> Pending: DecisionRequested
  Pending --> Approved: DecisionResponded(approve)
  Pending --> Rejected: DecisionResponded(reject)
  Pending --> Cancelled: DecisionCancelled
  Approved --> [*]
  Rejected --> [*]
  Cancelled --> [*]
```

## 5) Approval policy (v1)
- `policy_mode = any_of`
- selectors: roles, teams, explicit users (+ optional fallback)
- Eligible approver set is **materialized** at request time (snapshot) for audit stability.

## 6) Hard-block enforcement
Application-layer check for protected actions (minimum: Quote “Send to customer”):
- If required decision type(s) are not Approved for the relevant context/version → throw `ACTION_GATED_BY_DECISION`.

## 7) Data model (custom tables)
- `pet_conversations`
- `pet_conversation_participants`
- `pet_conversation_events` (append-only)
- `pet_conversation_read_state` (last_seen_event_id)
- `pet_decisions`
- `pet_decision_events` (append-only)

Indexing requirements:
- context lookup (`context_type`,`context_id`)
- paging (`conversation_id`, occurred_at or sequential id)
- decision state lookup (`context_type`,`context_id`,`decision_type`,`state`)

## 8) Concurrency
- Decision responses: transaction + row lock (`SELECT ... FOR UPDATE`)
- Respond only when Pending; idempotent “already finalized” response otherwise.

## 9) Redaction
- No deletion.
- Redaction is an additive event referencing fields to mask + reason + actor.
