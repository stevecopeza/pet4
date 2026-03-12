# PET Helpdesk Overview Shortcode Spec v1.0

> **Supersession note:** Sections describing the "Unassigned" ticket definition
> (§5) and wallboard refresh defaults (§2, §3, §8) are superseded by:
> `PET_Helpdesk_Overview_Additive_Clarifications_v1_1.md`
>
> Where v1.1 conflicts with this document, v1.1 is authoritative.

Status: **IMPLEMENTATION SPEC (demo-safe, read-only)**  
Owner: PET  
Scope: Frontend shortcode that renders an operational helpdesk overview for **manager** and **ops wallboard** use.

---

## 1) Shortcode

### Tag
- `[pet_helpdesk]`

### Purpose
Provide a single “control-room” overview of support operations:
- What’s **critical** (breached/overdue)
- What’s **at risk** (SLA due soon)
- Team/queue **load** and staffing pain (unassigned)
- Clear, glanceable information for managers and wallboards

### Non-goals
- No editing tickets
- No workflow changes
- No SLA calculations in UI
- No schema changes / migrations

---

## 2) Modes

The shortcode supports **two render modes**, controlled by an attribute.

### mode="manager" (default)
- Desktop layout
- Shows filter chips (team/window/bands)
- More density (cards + mini tables)
- Click-through links allowed (if detail URLs exist)

### mode="wallboard"
- TV/monitor layout for 3–5m viewing distance
- Large typography, high contrast dark theme (via `.pet-helpdesk--mode-wallboard`)
- **Columns**: Critical, At Risk, Normal (no Flow column)
- Reduced density
- Minimal UI chrome (no filters UI; values derived from attributes)
- Auto-refresh enabled by default (30s full page reload)

---

## 3) Attributes (public API)

All attributes are optional.

| Attribute | Type | Default | Notes |
|---|---:|---:|---|
| `mode` | string | `manager` | `manager` or `wallboard` |
| `team` | string | `all` | `all` or a **team identifier** (see Data Source mapping) |
| `window_days` | int | `14` | Used for ticket age windows and board context |
| `refresh` | int | `60` | Seconds; wallboard defaults to 30s |
| `limit_critical` | int | `6` | Maximum critical cards shown |
| `limit_risk` | int | `8` | Maximum at-risk cards shown |
| `risk_bands` | string | `breached,<30m,<2h,today` | Defines urgency tiers used in UI labels |
| `show_flow` | bool | `true` | Show team load / queue flow panel (Ignored in wallboard mode) |
| `title` | string | `Helpdesk Overview` | Heading label |
| `scope` | string | `support+sla_project` | `support_only` or `support+sla_project` |

### Example usages
- `[pet_helpdesk]`
- `[pet_helpdesk mode="wallboard" refresh="30" team="all" limit_critical="4" limit_risk="4"]`
- `[pet_helpdesk mode="manager" team="support" window_days="7"]`

---

## 4) Authentication and Authorisation

### Authentication
- If user is not logged in: render a compact “Sign in required” message (no fatal).
- Never leak ticket metadata to anonymous users.

### Authorisation
- Default expectation: user must have PET capability to view helpdesk overview.
- If PET capability model exists, use it. If not, fall back to `manage_options` (demo-safe).
- Regardless of mode, enforce “only what the user can see” by relying on existing PET read-model scoping (if present).

---

## 5) Data Sources and Derived Fields

### Golden rule
The shortcode does **no domain logic**. It only reads **existing projections/read models** and formats them.

### Required fields per ticket (render contract)
This is the minimal render contract. If any field is missing in the read model, the UI must gracefully degrade.

- `ticket_id` (internal id)
- `ticket_ref` (display ref like `#1042`)
- `subject/title`
- `customer_name`
- `site_name` (optional)
- `status` (Open/In Progress/Waiting/Closed)
- `priority` (P1/P2/P3 or High/Med/Low)
- `assignee_display` (person or team; optional)
- `queue/team_display` (optional)
- SLA fields (optional but strongly preferred):
  - `breach_at` (datetime) OR `is_breached` (bool)
  - `next_due_at` (datetime) (closest upcoming SLA checkpoint)
  - `time_to_due_seconds` (int) OR computable from `next_due_at`
  - `sla_label` (e.g., “Respond”, “Resolve”, “Gold” policy)

### Escalation fields (optional)
- `is_escalated` (bool) OR `escalation_count` (int)

### “Unassigned” definition
- A ticket is considered unassigned if **no assignee person** and **no assignee team/queue** is set.
- If only one of those concepts exists, use it; otherwise treat missing as unassigned.

---

## 6) SLA Risk Banding (UI-only mapping)

This is not recalculating SLA; it is mapping existing due/breach data into display bands.

Band mapping (recommended):
- **Breached**: `is_breached=true` OR `breach_at <= now`
- **<30m**: `0 < time_to_due <= 1800`
- **<2h**: `1800 < time_to_due <= 7200`
- **Today**: `now < next_due_at < end_of_day`

If `time_to_due_seconds` does not exist but `next_due_at` does, compute `(next_due_at - now)` in application layer (safe formatting only).

---

## 7) Layout Requirements

### Manager layout (desktop)
Top:
- Title + context line (mode/refresh/scope)
- Filter chips (non-interactive in v1 unless simple GET params already exist)

Row 1: KPI tiles
- Open
- Breached
- <30m to SLA
- <2h to SLA
- Unassigned
- Escalated

Body:
- Left column: **Critical** lane (breached/overdue)
- Right top: **At Risk** lane (due soon)
- Right bottom: **Team Load / Flow** mini-table (open/breached/<2h/unassigned per queue/team)

Ticket card requirements:
- Title
- Customer + Ref + Status
- Due/breach badge (e.g., “Overdue 3h”, “Due in 58m”)
- Tags/pills: Priority, SLA state, Escalated, Owner/Assignee

### Wallboard layout (TV)
- Dark background
- Big KPI tiles across the top (same KPIs)
- 3 columns:
  - CRITICAL
  - AT RISK
  - FLOW (per queue/team)
- Bottom ticker line summarising: breached, <30m, unassigned, oldest age

Wallboard rules:
- Use fewer items (max 4 critical, 4 risk by default)
- Large typography (title >= 28px, KPI numbers >= 44px equivalent)

---

## 8) Refresh Behaviour

### Manager mode
- No auto-refresh unless `refresh` attribute provided.
- If enabled, use a light JS timer to refresh the page or re-fetch a JSON endpoint if one already exists.

### Wallboard mode
- Auto-refresh enabled by default with `refresh=60`.
- Prefer soft refresh (re-fetch HTML via XHR and replace container) if feasible; otherwise full page refresh is acceptable for v1.

---

## 9) Implementation Guidance (for TRAE)

### Files (suggested; follow existing PET structure)
- `src/UI/Shortcode/HelpdeskOverviewShortcode.php`
- `src/UI/Shortcode/ShortcodeRegistrar.php` (only if not already present)
- `src/UI/ReadModel/HelpdeskOverviewQuery.php` (application-level query that talks to repositories)
- `src/UI/Templates/helpdesk-overview-manager.php`
- `src/UI/Templates/helpdesk-overview-wallboard.php`
- `assets/helpdesk-overview.css`
- `assets/helpdesk-overview.js` (optional; refresh)

No migrations. No domain changes.

### Data retrieval strategy (strictly read-model)
Preferred order:
1) If a dedicated ticket read-model repository exists, use it.
2) If there is an existing REST endpoint that returns scoped tickets with SLA state, reuse it internally.
3) As a last resort, use the existing SQL repository used by `TicketController` list endpoints (read-only).

### Sorting rules
- Critical lane:
  - Breached first, then most overdue (largest negative time_to_due)
- At-risk lane:
  - Nearest due first
- Team load:
  - Sort queues by breached desc, then at-risk desc

### Degrade gracefully
- If SLA fields missing: hide SLA badges and risk bands; still show Open/Status/Priority.
- If escalation missing: hide escalated tag and KPI tile can show 0.

---

## 10) Acceptance Criteria

### Functional
- `[pet_helpdesk]` renders in manager mode when logged in.
- `[pet_helpdesk mode="wallboard"]` renders wallboard mode.
- No ticket data is visible to anonymous users.
- No errors/fatal if some fields are missing.
- KPIs match counts derived from the same data list used for lanes.

### Visual
- Manager layout: KPI tiles + Critical + At Risk + Team Load present.
- Wallboard: top KPIs + 3 columns + bottom ticker.

### Performance
- Limit queries; do not load more than necessary for display.
- Prefer a single query + in-memory grouping where possible.

---

## 11) Test Guidance (minimum)

- Unit: attribute parsing defaults (mode/team/refresh/limits)
- Integration: shortcode renders for logged-in user (HTTP 200; contains key headings)
- Security: anonymous render does not contain ticket refs like `#` patterns
- Determinism: risk band mapping stable given fixed timestamps (use frozen time if tests support)

---
