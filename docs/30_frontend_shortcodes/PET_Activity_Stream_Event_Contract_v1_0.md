# PET Activity Stream — Event Contract
Version: v1.0  
Status: **Binding** (implementation must conform)

## Purpose
Define the canonical **read-only projection contract** consumed by:
- `[pet_activity_stream]` shortcode (frontend)
- PET Admin “Activity” views (optional)
- Wallboard mode (read-only)

This contract is derived from immutable domain events and projections. **No UI writes. No mutations.**

---

## Authority & Layering
- **Domain** emits immutable domain events.
- **Infrastructure / Projections** transform events into `ActivityEvent` rows.
- **UI** reads projection via repository/endpoint and renders.

**UI must not infer business rules.** UI only applies display rules specified in this document.

---

## Canonical Data Shape (DTO)
All fields are required unless explicitly marked optional.

### `ActivityEvent`
| Field | Type | Notes |
|------|------|------|
| `id` | string (UUID) | Stable unique id of the activity row |
| `occurred_at` | string (ISO-8601 UTC) | e.g. `2026-02-21T06:54:12Z` |
| `actor_type` | enum | `employee` \| `system` \| `integration` |
| `actor_id` | string\|null | Required if `actor_type=employee` |
| `actor_display_name` | string | Render-ready |
| `actor_avatar_url` | string\|null | Optional; may be null |
| `event_type` | enum | See **Event Types** |
| `severity` | enum | `info` \| `attention` \| `risk` \| `breach` \| `commercial` |
| `reference_type` | enum | `ticket` \| `project` \| `milestone` \| `task` \| `quote` \| `time_entry` \| `timesheet` \| `change_order` \| `commercial_adjustment` \| `announcement` \| `knowledge` |
| `reference_id` | string | Render-safe identifier, e.g. `#1042`, `Q-221`, `PRJ-21` |
| `reference_url` | string\|null | Optional deep link URL |
| `customer_id` | string\|null | Optional |
| `customer_name` | string\|null | Optional render-ready name |
| `company_logo_url` | string\|null | Optional, used in UI if present |
| `headline` | string | Short primary text (render-ready) |
| `subline` | string\|null | Secondary text, may be null |
| `tags` | array<string> | e.g. `["SLA Risk","Escalation"]` |
| `sla` | object\|null | Optional; only present for ticket SLA contexts |
| `meta` | object | Additional metadata (render-safe, no secrets) |

### `ActivityEvent.sla` (optional)
Present only when `reference_type=ticket` AND SLA context exists.

| Field | Type | Notes |
|------|------|------|
| `clock_state` | enum | `active` \| `paused` \| `stopped` \| `breached` |
| `target_at` | string\|null | ISO UTC, if known |
| `breach_at` | string\|null | ISO UTC, if known |
| `seconds_remaining` | int\|null | Negative if breached |
| `kind` | enum\|null | `respond` \| `resolve` |
| `policy_name` | string\|null | e.g. `Gold` |

---

## Event Types (Allowed)
This is the allowed `event_type` set. Additions require explicit spec update.

### Ticket / SLA
- `TICKET_CREATED`
- `TICKET_ASSIGNED`
- `TICKET_STATUS_CHANGED`
- `TICKET_COMMENT_ADDED`
- `ESCALATION_TRIGGERED`
- `SLA_RISK_DETECTED`
- `SLA_BREACH_RECORDED`

### Project Delivery
- `PROJECT_CREATED`
- `MILESTONE_PLANNED`
- `MILESTONE_COMPLETED`
- `TASK_CREATED`
- `TASK_STATUS_CHANGED`

### Commercial
- `QUOTE_SENT`
- `QUOTE_ACCEPTED`
- `CHANGE_ORDER_SUBMITTED`
- `CHANGE_ORDER_APPROVED`
- `COMMERCIAL_ADJUSTMENT_CREATED`
- `COMMERCIAL_ADJUSTMENT_APPROVED`

### Time
- `TIME_ENTRY_LOGGED`
- `TIMESHEET_SUBMITTED`

### Governance / Comms
- `ANNOUNCEMENT_PUBLISHED`
- `ANNOUNCEMENT_ACKNOWLEDGED`

---

## Projection Guarantees
- Projection rows are **append-only** (no destructive edits).
- Corrections appear as new rows (compensating records) rather than editing prior rows.
- `headline` and `subline` must be **render-ready** (no HTML, no unsafe content).
- `meta` must contain **no secrets** (no tokens, passwords, confidential payloads).

---

## API Consumption (Read-only)
Consumers may load:
- Latest N events
- Paged events
- Filtered by `reference_type`, `severity`, `actor_id`, `customer_id`, date range

Exact endpoint definitions are in `PET_Activity_Stream_UI_Contract_v1_0.md`.

