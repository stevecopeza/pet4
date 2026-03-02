# PET Conversations — Notifications & Activity Rules (v1.0)

**Status:** Draft (implementation-ready)  
**Date:** 2026-02-23

## Notification types
1) **Mentions**
- Trigger: MessagePosted with mentions
- Recipients: mentioned users (permission-checked)
2) **Decision requires response**
- Trigger: DecisionRequested
- Recipients: materialized eligible approver set
3) **Decision outcome**
- Trigger: DecisionResponded finalizes
- Recipients: requester (+ optionally participants/watchers)
4) **Resolved/Reopened**
- Trigger: ConversationResolved/Reopened
- Recipients: participants (low priority)

## Activity stream (per context)
- Collapsed groups by `subject_key`
- Fields:
  - latest_event_at, latest_snippet
  - unread_count (per user)
  - pending_decision_count
  - requires_my_approval_count

## Unread semantics
- per (conversation_id, user_id): events after last_seen_event_id
- Mark seen when panel opens and latest loaded.

## Safety
- Never compute or return counts for conversations user cannot access (no inference).
