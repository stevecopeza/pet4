# PET Activity Stream — UI Contract (Endpoints, Mapping, Rendering Rules)
Version: v1.0  
Status: **Binding**

## Shortcodes
### `[pet_activity_stream]`
Attributes (all optional):
- `range` = `24h|7d|30d` (default `7d`)
- `density` = `compact|comfortable|executive` (default `comfortable`)
- `limit` = integer (default `50`, max `200`)
- `reference_type` = comma list
- `severity` = comma list

### `[pet_activity_wallboard]`
Attributes:
- `limit` default `20` max `50`
- `refresh` seconds default `30`
- `mode` forced `wallboard` (dark, high-contrast)
- Filters NOT exposed on wallboard

---

## REST API (Read-only)
Namespace: `pet/v1`

### GET `/activity`
Query params:
- `from` ISO-8601 UTC optional
- `to` ISO-8601 UTC optional
- `range` optional (`24h|7d|30d`)
- `limit` optional (default 50, max 200)
- `page` optional (if paging supported)
- `q` optional (search)
- `event_type[]` optional multi
- `severity[]` optional multi
- `reference_type[]` optional multi
- `actor_id[]` optional multi
- `customer_id[]` optional multi

Response:
```json
{
  "items": [ActivityEvent],
  "next_page": 2,
  "meta": { "range": "7d", "limit": 50 }
}
```

### Security / Scoping (Binding)
- Must require authentication.
- Must scope results to what the user may see:
  - Tickets/projects/quotes visibility rules apply.
- Wallboard uses same auth rules; it is not public.

---

## Event Type → Icon/Color/Tag Matrix (Binding)
Icons are conceptual; implement with existing icon system or minimal SVGs.

| event_type | default severity | icon | accent color | default tag |
|---|---|---|---|---|
| SLA_BREACH_RECORDED | breach | clock-alert | red | SLA Breach |
| SLA_RISK_DETECTED | risk | timer | orange | SLA Risk |
| ESCALATION_TRIGGERED | attention | flag | orange | Escalation |
| QUOTE_ACCEPTED | info | receipt | green | Quote |
| CHANGE_ORDER_APPROVED | commercial | approval | purple | Commercial |
| TIMESHEET_SUBMITTED | info | clock-check | green | Time |
| TIME_ENTRY_LOGGED | info | clock | green | Time Entry |
| MILESTONE_COMPLETED | info | milestone | blue | Milestone |
| TICKET_STATUS_CHANGED | info | ticket | blue | Ticket |
| TICKET_COMMENT_ADDED | info | message | blue | Comment |
| ANNOUNCEMENT_PUBLISHED | info | megaphone | blue | Announcement |
| ANNOUNCEMENT_ACKNOWLEDGED | info | check | green | Acknowledged |

If an event_type is unknown:
- icon: `dot`
- color: blue
- tag: `Activity`

---

## Event Card Mapping
UI must map fields from `ActivityEvent` (see Event Contract) to:
- Avatar
- Headline / subline
- Tags
- Reference pill
- SLA countdown (if present)
- Timestamp

