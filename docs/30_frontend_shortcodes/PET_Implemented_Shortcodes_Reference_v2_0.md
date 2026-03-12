# PET Implemented Shortcodes Reference
Version: v2.0
Status: **CURRENT** — reflects all shortcodes implemented as of March 2026
Supersedes: `PET_Demo_Shortcodes_Implementation_Spec_v1_0.md` (which covered only the original 4 demo shortcodes)

This document is the **canonical implementation reference** for all frontend shortcodes currently registered and functional in PET.

For detailed specs on individual shortcodes, see also:
- `PET_Activity_Stream_Shortcode_Spec_v1_0.md`
- `PET_Helpdesk_Overview_Shortcode_Spec_v1_0.md`

For the binding contract standard governing all shortcodes, see:
- `PET_Shortcode_Contract_Standard_v1_0.md`

---

## Implementation summary

| # | Shortcode | Audience | Mode | CSS | Added |
|---|-----------|----------|------|-----|-------|
| 1 | `[pet_my_profile]` | Staff | MIX (view + edit personal details) | `my-profile.css` | v1.0 |
| 2 | `[pet_my_work]` | Staff | RO | `shortcodes.css` | v1.0 |
| 3 | `[pet_my_calendar]` | Staff | RO | `shortcodes.css` | v1.0 |
| 4 | `[pet_activity_stream]` | Mixed | RO | `activity-stream.css` | v1.0 |
| 5 | `[pet_activity_wallboard]` | Mixed | RO | `activity-stream.css` | v1.0 |
| 6 | `[pet_helpdesk]` | Staff/Manager | RO | `helpdesk-overview.css` | v1.0 |
| 7 | `[pet_my_conversations]` | Mixed | RO | `shortcodes.css` | v2.0 |
| 8 | `[pet_my_approvals]` | Staff/Manager | RO | `shortcodes.css` | v2.0 |
| 9 | `[pet_knowledge_base]` | Mixed | RO | `shortcodes.css` | v2.0 |

### CSS assets

- `assets/my-profile.css` — Dedicated styles for My Profile (avatar, skill bars, cert cards, edit form).
- `assets/shortcodes.css` — Shared design system for My Work, My Calendar, Conversations, Approvals, Knowledge Base. Provides CSS variables under `.pet-sc`, KPI strip, card/item layout, pills, SLA timers, empty states, responsive breakpoints.
- `assets/activity-stream.css` — Dedicated styles for Activity Stream and Activity Wallboard.
- `assets/helpdesk-overview.css` — Dedicated styles for the Helpdesk Overview shortcode.

### Registration

All shortcodes are registered in `src/UI/Shortcode/ShortcodeRegistrar.php` via `add_shortcode()` inside an `init` action hook.

---

## 1. `[pet_my_profile]`

**Purpose:** Displays the logged-in user's profile with roles, skills, and certifications. Supports view/edit toggle for personal details.

### Attributes

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `show_roles` | string | `1` | Show roles & teams section |
| `show_skills` | string | `1` | Show skills section |
| `show_certs` | string | `1` | Show certifications section |

### UI features
- Gravatar avatar with online status dot
- View mode (default): read-only display of all sections
- Edit mode: 2-column form for personal details only (name, email, phone, title)
- Role pills (blue) and team pills (purple)
- Skill proficiency bars: self-rating (blue) and manager-rating (purple), /5 scale
- Certification cards with auto-computed status badges (Active / Expiring Soon / Expired)

### Data sources
- `EmployeeRepository` for employee record
- `TeamRepository` for team memberships
- `PersonSkillRepository` + `SkillRepository` for skills
- `PersonCertificationRepository` + `CertificationRepository` for certifications
- WordPress `get_userdata()` as fallback for name/email

### Example
```
[pet_my_profile]
[pet_my_profile show_skills="0" show_certs="0"]
```

---

## 2. `[pet_my_work]`

**Purpose:** "My Day" work surface showing assigned tickets, project tasks, and department queue items with KPIs.

### Attributes
None (user-scoped automatically).

### UI features
- KPI strip: My Tickets, At Risk, Due Today, Tasks, Queue
- Tabbed panels: **My Items** / **Department Queue**
- Card-based item rows with source-type icons (ticket, task, work, escalation)
- SLA timer badges (breach = red, warn = amber, ok = green)
- Due date urgency colouring (overdue, today, upcoming)
- Status pills per item

### Data sources
- `WorkItemRepository` — all work items (used for assignment mapping and SLA data)
- `TicketRepository` — active tickets matched to user via work item assignments
- `CustomerRepository` — customer names for ticket context
- `EmployeeRepository` — employee record
- `TeamRepository` — team membership for department queue

### Example
```
[pet_my_work]
```

---

## 3. `[pet_my_calendar]`

**Purpose:** Agenda-style calendar showing upcoming work items for the next 14 days.

### Attributes
None.

### UI features
- Friendly date headers: "Today", "Tomorrow", or day name (e.g. "Wednesday, Mar 5")
- Today header highlighted with accent background
- Timeline layout with vertical line and coloured dots by source type:
  - Ticket = blue
  - Work = teal
  - Escalation = red
- Time labels, type badges, and linked titles
- Item count summary

### Data sources
- `WorkItemRepository` — items with `getDueAt()` in the next 14 days, filtered to current user's assignments

### Example
```
[pet_my_calendar]
```

---

## 4. `[pet_activity_stream]`

**Purpose:** Chronological, scoped, read-only feed of operational events.

> Full spec: `PET_Activity_Stream_Shortcode_Spec_v1_0.md`

### Attributes

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `mode` | string | `default` | `default`, `compact`, or `wallboard` |
| `limit` | int | `20` | Max events (1–200) |
| `scope` | string | `my` | `my`, `team`, or `org` |
| `team` | string | `current` | `current`, `all`, or team ID |
| `types` | string | `all` | CSV event types or `all` |
| `window_days` | int | `14` | Lookback window in days |
| `show_filters` | bool | `true` | Show filter chips |
| `refresh` | int | `0` | Auto-refresh interval in seconds (0 = off) |
| `title` | string | `Activity` | Heading label |
| `empty_message` | string | `No recent activity.` | Empty state text |
| `link_mode` | string | `open` | `open` or `none` |

### UI features
- Events grouped by time period: Today, Yesterday, This Week, Older
- Filter chips showing scope, window, and event types
- Auto-refresh via inline script when `refresh > 0`
- Activity cards with type icon, actor, description, and timestamp

### Data sources
- Internal REST call to `GET /pet/v1/activity`

### Example
```
[pet_activity_stream limit="20" scope="my" window_days="14"]
[pet_activity_stream mode="wallboard" refresh="60" scope="org"]
```

---

## 5. `[pet_activity_wallboard]`

**Purpose:** Full-screen rolling activity feed designed for lobby TVs and wallboard displays. Simplified variant of the activity stream with forced wallboard styling and mandatory auto-refresh.

### Attributes

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `limit` | int | `20` | Max events (1–50) |
| `refresh` | int | `30` | Auto-refresh interval in seconds (minimum 1) |

### UI features
- Wallboard-optimised card layout (large typography)
- Auto-refresh enabled by default (30s)
- Events sorted by most recent first
- No filters or interactive controls

### Data sources
- Internal REST call to `GET /pet/v1/activity` (last 24 hours)

### Example
```
[pet_activity_wallboard]
[pet_activity_wallboard limit="15" refresh="20"]
```

---

## 6. `[pet_helpdesk]`

**Purpose:** Live SLA health dashboard with KPIs, critical/at-risk swim lanes, and ticket flow.

> Full spec: `PET_Helpdesk_Overview_Shortcode_Spec_v1_0.md`

### Attributes

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `mode` | string | `manager` | `manager` or `wallboard` |
| `team` | string | `all` | `all` or team identifier |
| `window_days` | int | `14` | Ticket age window |
| `refresh` | int | `60` | Auto-refresh seconds (wallboard defaults to 30) |
| `limit_critical` | int | `6` | Max critical cards shown |
| `limit_risk` | int | `8` | Max at-risk cards shown |
| `risk_bands` | string | `breached,<30m,<2h,today` | Urgency tier labels |
| `show_flow` | bool | `true` | Show queue flow panel (ignored in wallboard mode) |
| `title` | string | `Helpdesk Overview` | Heading label |
| `scope` | string | `support+sla_project` | `support_only` or `support+sla_project` |

### UI features
- Manager mode: KPI row (open, critical, at-risk, breached), swim lanes (Critical / At Risk / Normal), ticket flow (recent created/resolved), search and "My Tickets" filter
- Wallboard mode: large typography, dark theme, three-column layout, auto-refresh
- Feature-flag gated via `FeatureFlagService`

### Data sources
- `HelpdeskOverviewQueryService` (aggregates tickets, SLA state, and flow data)

### Example
```
[pet_helpdesk]
[pet_helpdesk mode="wallboard" refresh="30" team="all"]
[pet_helpdesk mode="manager" team="support" window_days="7"]
```

---

## 7. `[pet_my_conversations]`

**Purpose:** Shows the current user's recent conversations with context badges and state indicators.

### Attributes

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `limit` | int | `10` | Max conversations shown |
| `title` | string | `My Conversations` | Heading label |

### UI features
- Summary line: total conversations and open count
- Card layout with speech-bubble icon
- Context type badge: Ticket (blue), Project (purple), General (grey)
- Context ID reference (e.g. `#42`)
- State pill: Open (green) / Resolved (grey)
- Relative timestamps (e.g. "3 days ago")

### Data sources
- `ConversationRepository::findRecentByUserId()`

### Example
```
[pet_my_conversations]
[pet_my_conversations limit="5" title="Recent Threads"]
```

---

## 8. `[pet_my_approvals]`

**Purpose:** Lists pending approval decisions awaiting the current user, with urgency indicators based on wait time.

### Attributes

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `title` | string | `Pending Approvals` | Heading label |

### UI features
- Summary line: count of pending decisions
- Card layout with warning icon
- Decision type label (e.g. "Change Order", "Budget Approval")
- Truncated description/reason from payload (max 80 chars)
- Urgency indicator colour-coded by days pending:
  - ≥5 days = red (high)
  - ≥2 days = amber (medium)
  - <2 days = grey (low)
- "Review" action button linking to the associated conversation
- "All caught up" empty state when no pending decisions

### Data sources
- `DecisionRepository::findPendingByUserId()`
- Decision entity exposes: `decisionType()`, `payload()`, `requestedAt()`, `conversationId()`

### Example
```
[pet_my_approvals]
[pet_my_approvals title="Awaiting Your Review"]
```

---

## 9. `[pet_knowledge_base]`

**Purpose:** Searchable knowledge base articles grouped by category, with excerpts.

### Attributes

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `category` | string | *(empty)* | Filter to a specific category (empty = all) |
| `limit` | int | `20` | Max articles shown |
| `title` | string | `Knowledge Base` | Heading label |

### UI features
- Summary line: article count and category count
- Live client-side search filter (filters by title, category, and excerpt)
- Articles grouped by category with count badges
- Article cards with book icon, linked title, and content excerpt (max 120 chars)
- Category pill on each card

### Data sources
- `ArticleRepository::findAll()` or `ArticleRepository::findByCategory()`
- Article entity exposes: `id()`, `title()`, `category()`, `content()`

### Example
```
[pet_knowledge_base]
[pet_knowledge_base category="security" limit="10"]
[pet_knowledge_base title="Help Articles"]
```

---

## Global rules (all shortcodes)

These rules are inherited from `PET_Shortcode_Contract_Standard_v1_0.md`:

1. **Authentication required** — all shortcodes check `is_user_logged_in()` and render an empty state if not authenticated.
2. **View-by-default** — only `pet_my_profile` supports editing, and only for personal details.
3. **Graceful degradation** — data source failures are caught, logged via `error_log()`, and render a user-friendly empty/error state. No fatal errors.
4. **Scope: SELF** — all shortcodes are scoped to the current user unless the shortcode explicitly supports broader scope (e.g. `pet_activity_stream scope="team"`).
5. **No direct DB writes** — mutations go through domain commands/REST endpoints.
6. **Immutable records protected** — no shortcode can edit accepted quotes, submitted time, SLA outcomes, or event history.

---

## Admin reference page

All shortcodes are listed with copy-to-clipboard on the WordPress admin page:
**PET → Shortcodes** (`admin.php?page=pet-shortcodes`)
