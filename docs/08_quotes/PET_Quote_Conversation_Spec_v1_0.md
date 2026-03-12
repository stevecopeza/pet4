# PET Quote & Line Item Conversation Initiation

Version: 1.0\
Generated: 2026-02-24 14:48:48 UTC

------------------------------------------------------------------------

## 1. Functional Specification

### Objective

Enable explicit, version-safe, participant-scoped initiation of
Conversations from: - Quote (header level) - Quote Line Item (row level)

No implicit creation. No version leakage. Append-only history.

------------------------------------------------------------------------

## 2. Context Model

  -------------------------------------------------------------------------------------
  Level     context_type   context_id    context_version    subject_key
  --------- -------------- ------------- ------------------ ---------------------------
  Quote     quote          quote_id      quote_version      quote:{quote_id}
  Header                                                    

  Quote     quote          quote_id      quote_version      quote_line:{line_item_id}
  Line                                                      
  -------------------------------------------------------------------------------------

Line items do NOT create new contexts.

------------------------------------------------------------------------

## 3. Process Flows

### A. Quote Header Conversation Flow

``` mermaid
flowchart TD
    A[User clicks Discuss] --> B[GET conversation by context + subject_key]
    B -->|Exists| C[Open Conversation Panel]
    B -->|Not Exists| D[POST create conversation]
    D --> C
```

### B. Quote Line Item Conversation Flow

``` mermaid
flowchart TD
    A[User clicks Line Speech Icon] --> B[Build subject_key quote_line:id]
    B --> C[GET conversation]
    C -->|Exists| D[Open Panel]
    C -->|Not Exists| E[POST create]
    E --> D
```

### C. Send Quote Gating

``` mermaid
flowchart TD
    A[User clicks Send Quote] --> B[SendQuoteHandler]
    B --> C[ActionGatingService check]
    C -->|Pending decision| D[Throw ACTION_GATED_BY_DECISION]
    C -->|Approved| E[Proceed]
```

------------------------------------------------------------------------

## 4. UI Specification

### Quote Header

-   Discuss button (right aligned)
-   RAG badge (per-user, per-conversation):
    -   Red: Last message from another user, >8h without response
    -   Amber: Last message from another user, ≤8h old
    -   Green: Last message from current user (responded)
    -   Blue: Conversation resolved
    -   None: No conversation exists or user is not a participant

### Quotes List View — Dual Indicator

The Quotes list renders two independent indicators per quote in the Title column:

**Header dot:** 10px coloured circle reflecting the header conversation status (`subject_key = 'quote:{quote_id}'`). Click opens the header conversation drawer. Only renders when `status !== 'none'`.

**Child badge:** Chat-bubble icon + count, coloured by `child_worst_status`. Only renders when `child_discussion_count > 0`. Click navigates into QuoteDetails where per-block/section notification dots are visible.

Layout: `[header dot?] [child badge?] [title text]`

Both indicators are logically and visually independent. A quote may show:
- Both (header conversation + active line-item discussions)
- Header dot only (no active child discussions)
- Child badge only (no header conversation, but line items have discussions)
- Neither (no conversations at all)

### Line Item

-   Speech bubble icon per row
-   Hover preview snippet
-   Small RAG indicator

### Conversation Drawer

-   Slide-over panel
-   Decision state summary
-   Timeline (append-only)
-   Composer at bottom

------------------------------------------------------------------------

## 5. Edge Cases

  Scenario                Behaviour
  ----------------------- -------------------------------------------------
  Accepted Quote          Conversation allowed (read/write additive only)
  New Quote Version       Does NOT inherit threads automatically
  Non-participant         404
  Duplicate subject_key   Open existing thread

------------------------------------------------------------------------

## 6. Architecture Overview

``` mermaid
flowchart LR
    UI[Quote Screen UI] --> REST[ConversationController]
    REST --> APP[Application Layer]
    APP --> DOMAIN[Conversation + Decision Aggregates]
    DOMAIN --> DB[(Persistence)]
    APP --> GATE[ActionGatingService]
```
