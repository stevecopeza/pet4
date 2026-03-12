clear

You are executing PET implementation work for the **Conversations & Approvals (v1.0)** capability.

STRICT RULES:
- IMPLEMENTATION ONLY. Do NOT redesign, reinterpret, improve, or generalise.
- Follow PET strict layer boundaries (Domain / Application / Infrastructure / UI).
- Custom DB tables only; no WP posts/postmeta as operational truth.
- Forward-only migrations; fail-fast; no down migrations.
- Append-only history: no edits/deletes of conversation or decision items. Corrections are additive events only.
- Anchor conversations to exactly ONE context. Other contexts may reference but must not multi-anchor.
- Internal-only participation for v1 (no customer/external users).
- Approval gating must hard-block protected actions (at minimum “Send Quote to Customer”) until required decision(s) are Approved.
- Do not modify documentation unless explicitly instructed. Your task is implementation that conforms to the provided docs.

SCOPE LOCK:
Implement ONLY what is required by these v1.0 documents (do not add extra features):
1) docs/**PET_Conversations_Approvals_Feature_Spec_v1_0.md**
2) docs/**PET_Conversations_Approvals_Domain_Data_Spec_v1_0.md**
3) docs/**PET_Conversations_Panel_UI_Contract_v1_0.md**
4) docs/**PET_Conversations_Notifications_Activity_Rules_v1_0.md**
5) docs/**PET_Conversations_Approvals_StressTests_v1_0.md**

IMPLEMENTATION TASKS (in order):

STEP 1 — Migrations (Infrastructure)
- Add forward-only migrations to create these custom tables:
  - pet_conversations
  - pet_conversation_participants
  - pet_conversation_events
  - pet_conversation_read_state
  - pet_decisions
  - pet_decision_events
- Include required indexes per Domain+Data spec.
- Ensure types are compatible with current PET DB conventions (UUID storage, json columns, datetime).
- Ensure migration names follow repo conventions.

STEP 2 — Domain layer
- Add domain types for:
  - Conversation (state: open/resolved)
  - ConversationEvent (typed)
  - Decision (state machine: pending→approved/rejected/cancelled; expired optional but not required to implement behaviour)
  - ApprovalPolicy (v1 any_of), including eligible approver snapshot materialisation.
- Add domain events exactly as specified (names and payloads).
- Enforce invariants:
  - append-only (no mutation methods for historical items)
  - resolve/reopen allowed transitions
  - decision responds only when pending
  - eligibility snapshot is stored at request time

STEP 3 — Application layer (Commands + Handlers)
Implement commands/handlers:
- CreateConversation(context_type, context_id, subject, subject_key)
- PostMessage(conversation_id, body, mentions[], attachments_meta[])
- ResolveConversation(conversation_id)
- ReopenConversation(conversation_id)
- RequestDecision(conversation_id, decision_type, payload_json, policy_json)
  - must resolve eligible approvers set and persist snapshot
  - must auto-add eligible approvers as participants (emit ParticipantAdded events)
- RespondDecision(decision_id, outcome approve/reject, comment optional)
  - idempotent: if already finalised return DECISION_ALREADY_FINALIZED with existing terminal state
- MarkConversationSeen(conversation_id, last_seen_event_id)

Also implement protected action gate check service:
- For Quote “Send to customer” (existing flow), add a hard-block check:
  - If required decision type (send_to_customer_gate) for that quote version/context is not Approved -> throw ACTION_GATED_BY_DECISION including decision_id and remediation link data.

STEP 4 — Infrastructure repositories
- SQL repositories for each table.
- Ensure concurrency safety for RespondDecision:
  - lock decision row (SELECT ... FOR UPDATE) inside transaction
- Paging queries for conversation events.
- Efficient unread counts using read_state + indexed event ids.

STEP 5 — REST API (UI layer)
Expose REST endpoints (pattern consistent with existing PET controllers):
- GET /conversations?context_type=&context_id= (returns collapsed groups by subject_key with latest snippet, unread, pending counts)
- POST /conversations (create)
- GET /conversations/{conversation_id} (returns state summary + pending decisions + recent events)
- GET /conversations/{conversation_id}/events?cursor= (paged timeline)
- POST /conversations/{conversation_id}/messages
- POST /conversations/{conversation_id}/resolve
- POST /conversations/{conversation_id}/reopen
- POST /conversations/{conversation_id}/decisions
- POST /decisions/{decision_id}/respond
- POST /conversations/{conversation_id}/seen

Return stable error codes:
- CONVERSATION_NOT_FOUND
- CONVERSATION_FORBIDDEN
- DECISION_FORBIDDEN
- DECISION_ALREADY_FINALIZED
- ACTION_GATED_BY_DECISION

STEP 6 — Minimal UI wiring (Admin SPA)
- Add a minimal ConversationPanel component (drawer/sheet can be basic) that:
  - shows summary + recent events
  - can page timeline
  - can post message
  - can request/respond to decision
  - can resolve/reopen
- Add inline badge hook points for Quote, Quote Line Item, Project, Ticket screens (may be placeholders if not all screens exist yet), but DO NOT redesign other screens.

STEP 7 — Tests (must align with Stress Tests)
Add tests to cover:
- permission non-leak (no context access => no counts, no detail)
- participant-only visibility
- mention auto-add
- approver auto-add on RequestDecision
- decision concurrency: double approve; approve vs reject; idempotent retry
- hard-block send quote when pending gate exists
- paging works for large histories (at least query-level tests)
- redaction event renders masked fields (domain + projection test)

Constraints:
- Do NOT add customer/external participation.
- Do NOT add multi-context anchoring.
- Do NOT add multi-step approvals.
- Do NOT implement delete/edit of messages or decisions.

DELIVERABLE:
- A single PR-quality implementation that compiles and passes tests.
- Include any new files required by the above steps, but only those strictly necessary.
