# PET Conversations Panel — UI Contract (v1.0)

**Status:** Draft (implementation-ready)  
**Date:** 2026-02-23

## 1) Component: ConversationPanel
Mountable on Quote / Quote Line Item / Project / Ticket / Notifications.

### Inputs
- `context_type`, `context_id`
- optional `conversation_id`
- optional `subject_key`
- optional `focus_event_id`

### Outputs
- create/update callbacks (optional)
- unread count updates (optional)

## 2) Default UX (locked)
- Summary-first view (latest + pending decisions) → “View timeline”
- Collapsed activity groups by subject_key
- Decision cards rendered in-stream
- Resolve/reopen supported
- Unread tracked by last_seen_event_id
- No delete/edit

## 3) Item rendering
Timeline renders:
- messages
- decision references + decision cards
- participant changes
- resolve/reopen
- redaction markers

## 4) Deep links
Must open panel to conversation + event without leaking existence to unauthorized users.

## 5) Error codes
- CONVERSATION_NOT_FOUND
- CONVERSATION_FORBIDDEN
- DECISION_FORBIDDEN
- DECISION_ALREADY_FINALIZED
- ACTION_GATED_BY_DECISION
