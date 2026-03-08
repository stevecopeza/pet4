# PET Activity Stream Shortcode Spec v1.0

Status: **IMPLEMENTATION SPEC (read-only, design-locked)**  
Applies to: PET WordPress plugin (frontend shortcode)  
Principles: **Immutable history, read-only projections, additive-only changes**

---

## 1) Shortcode

### Tag
- `[pet_activity_stream]`

### Purpose
Provide a chronological, scoped, **read-only** feed of operational events (ticket, project, SLA, commercial, approvals, time submissions, system notices).

### Non-goals
- No editing/deleting events
- No “mark as read” mutations from this view (unless already implemented elsewhere and explicitly reused)
- No domain logic inside UI
- No schema changes / migrations

---

## 2) Modes (desktop/mobile density)

The shortcode supports **display density variants** via `mode`.

- `mode="default"` (default): balanced, desktop-first
- `mode="compact"`: smaller cards for embedding (portal)
- `mode="wallboard"`: large typography, low density, auto-refresh friendly

> **Mobile** is responsive CSS under the same modes; no separate shortcode required.

---

## 3) Attributes (public API)

All attributes are optional.

| Attribute | Type | Default | Notes |
|---|---:|---:|---|
| `mode` | string | `default` | `default` \| `compact` \| `wallboard` |
| `limit` | int | `20` | Max events shown |
| `scope` | string | `my` | `my` \| `team` \| `org` (see auth) |
| `team` | string | `current` | `current` \| `all` \| `<team_id>` |
| `types` | string | `all` | CSV list of event types (see §6) |
| `window_days` | int | `14` | Optional lookback window |
| `show_filters` | bool | `true` | Only affects `default/compact`; wallboard hides filters |
| `refresh` | int | `0` | Seconds; if >0 enables auto-refresh (wallboard default recommendation: 60) |
| `title` | string | `Activity` | Heading label |
| `empty_message` | string | `No recent activity.` | Empty state |
| `link_mode` | string | `open` | `open` \| `none` (disable click-through) |

### Examples
- `[pet_activity_stream]`
- `[pet_activity_stream mode="compact" limit="10"]`
- `[pet_activity_stream scope="team" team="support" types="ticket,sla,escalation" limit="30"]`
- `[pet_activity_stream mode="wallboard" refresh="60" scope="org" limit="12" show_filters="false"]`

---

## 4) Authentication and Authorisation

### Authentication
- If user is not logged in: render a compact “Sign in required” message (no fatal).

### Authorisation (scope rules)
- `scope="my"`: show events where the user is actor OR assignee/participant OR explicitly relevant per existing projection rules.
- `scope="team"`: requires manager/team-lead capability (prefer PET capability model if present; fallback `manage_options` for demo safety).
- `scope="org"`: requires admin/ops capability (prefer PET capability; fallback `manage_options`).

**Never** show events outside the user’s permissioned visibility. Rely on existing read-model scoping if available.

---

## 5) Data Sources (read model only)

### Golden rule
The shortcode performs **no domain decisions**. It reads **existing event/activity projections** (preferred) or an existing query endpoint/repository used by admin “activity feed” if present.

