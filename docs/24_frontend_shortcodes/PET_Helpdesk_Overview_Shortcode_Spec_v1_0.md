# PET Helpdesk Overview Shortcode Spec v1.0

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

