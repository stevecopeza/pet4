# PET Staff Portal — My Work Section Plan
Version: v1.0
Status: **COMPLETE — 2026-04-21**
Date: 2026-04-21
Author: Steve Cope / AI Agent

---

## Status Summary

| Item | Status | Est. hours |
|---|---|---|
| P0: `pet_staff` capability prerequisite | ✅ Done | 0.5h |
| P1: My Queue (`#my-queue`) | ✅ Done | 2–3h |
| P1: My Deliverables (`#my-deliverables`) | ✅ Done | 2–3h (shared component with My Queue) |
| P2: Activity (`#activity`) | ✅ Done | 2h |
| P3: Calendar (`#calendar`) | ✅ Done | 2–3h |
| P4: Log Time (`#log-time`) | ✅ Done | 2–3h |
| P5: Knowledge Base (`#knowledge-base`) | ✅ Done | 1.5–2h |
| P6: Conversations (`#conversations`) | ✅ Done | 4–5h |
| PortalShell nav + routing additions | ✅ Done | 1h |
| **Total** | | **~18–20h** |

---

## Context

The Staff Portal (`pet.cope.zone/portal`) is a React SPA (hash router, Vite build, `src/UI/Portal/`). As of 2026-04-21 it serves commercial/people workflows for users with `pet_sales`, `pet_hr`, or `pet_manager` capabilities. Regular staff — technicians, consultants, field engineers — have no portal access at all. This sprint adds a **My Work** section visible to any logged-in staff member, covering their queue, deliverables, calendar, time logging, messaging, activity feed, and internal knowledge base.

---

## MEMORY.md Corrections

Two items in `MEMORY.md` under "Key Known Gaps" are stale and should be removed:

1. **"SupportOperational.tsx (backend done, UI stub)"** — FALSE. `SupportOperational.tsx` is 843 lines and fully implemented.
2. **"Lead entity still requires customerId (contradicts docs — should be optional)"** — FALSE. The `MakeLeadCustomerIdNullable` migration exists, the entity stores `?int`, and the handler validates it as optional.

---

## Section 1: Prerequisite — `pet_staff` Capability

This is a hard prerequisite. Without it, regular staff cannot reach the portal at all and none of the seven pages are accessible.

### Problem

`usePortalUser.ts` derives `hasPortalAccess` as:

```ts
hasPortalAccess: isSales || isHr || isManager || isAdmin
```

A user with only `pet_staff` resolves `hasPortalAccess = false` and is rejected by the portal guard. The shortcode `getUserPortalCaps()` never checks for or emits `pet_staff`. `EmployeeController::provisionEmployee()` only permits `pet_sales|pet_hr|pet_manager` as valid portal role values.

### Required Changes

**File: `src/UI/Portal/Shortcode/PortalShortcode.php`**

Add `pet_staff` to the caps array checked in `getUserPortalCaps()`:

```php
$caps = ['pet_sales', 'pet_hr', 'pet_manager', 'manage_options', 'pet_staff'];
```

**File: `src/UI/Portal/hooks/usePortalUser.ts`**

Add `isStaff` flag and include it in `hasPortalAccess`:

```ts
const isStaff   = caps.includes('pet_staff');
// ...
hasPortalAccess: isSales || isHr || isManager || isAdmin || isStaff,
isStaff,
```

Also extend the `PortalUser` interface:

```ts
interface PortalUser {
  // ... existing fields
  isStaff: boolean;
}
```

**File: `src/UI/Rest/Controller/EmployeeController.php`**

In `provisionEmployee()`, extend `$allowedCaps`:

```php
$allowedCaps = ['pet_sales', 'pet_hr', 'pet_manager', 'pet_staff'];
```

### Verification

After this change, log in as a WP user who has only `pet_staff` and navigate to `pet.cope.zone/portal`. The portal shell should load and the My Work section should be visible. Commercial and People sections should remain invisible (those require `pet_sales`, `pet_hr`, or `pet_manager`).

---

## Section 2: Nav Redesign

### Current Nav

```
COMMERCIAL    (pet_sales | pet_hr | pet_manager | manage_options)
  Customers, Catalog, Leads, Quotes, Approvals

PEOPLE        (pet_hr | pet_manager | manage_options)
  Employees
```

### New Nav (after this sprint)

```
COMMERCIAL    (pet_sales | pet_hr | pet_manager | manage_options)
  Customers, Catalog, Leads, Quotes, Approvals

PEOPLE        (pet_hr | pet_manager | manage_options)
  Employees

MY WORK       (any logged-in staff — pet_staff | pet_sales | pet_hr | pet_manager | manage_options)
  My Queue         → #my-queue
  My Deliverables  → #my-deliverables
  Calendar         → #calendar
  Log Time         → #log-time
  Conversations    → #conversations    [unread badge]
  Activity         → #activity
  Knowledge Base   → #knowledge-base
```

The My Work section appears for every user who has `hasPortalAccess = true`, i.e., any user who can log in at all. This means manager/sales/HR users see all three sections; pure `pet_staff` users see only My Work.

### PortalShell Changes (`src/UI/Portal/PortalShell.tsx`)

1. Add `NAV_MY_WORK: NavItem[]` array containing all seven items. `requiresCap` for the section: `(u: PortalUser) => u.hasPortalAccess`.
2. Add SVG icons for each item (inline SVGs, same style as existing — 16×16, `currentColor` stroke).
3. Add "MY WORK" section label rendered between the People section and the sidebar footer.
4. Conversations nav item renders a numeric badge using `pendingCount` from `GET /pet/v1/conversations/unread-counts`, fetched once on mount in `PortalApp` and passed down as a prop.

### PortalApp Changes (`src/UI/Portal/PortalApp.tsx`)

Add seven lazy imports:

```ts
const MyQueuePage        = lazy(() => import('./pages/MyQueuePage'));
const MyDeliverablesPage = lazy(() => import('./pages/MyDeliverablesPage'));
const CalendarPage       = lazy(() => import('./pages/CalendarPage'));
const LogTimePage        = lazy(() => import('./pages/LogTimePage'));
const ConversationsPage  = lazy(() => import('./pages/ConversationsPage'));
const ActivityPage       = lazy(() => import('./pages/ActivityPage'));
const KnowledgeBasePage  = lazy(() => import('./pages/KnowledgeBasePage'));
```

Add seven switch cases (plus optional `#conversations/:uuid` for deep-link to a thread):

```ts
case 'my-queue':        return <MyQueuePage />;
case 'my-deliverables': return <MyDeliverablesPage />;
case 'calendar':        return <CalendarPage />;
case 'log-time':        return <LogTimePage />;
case 'conversations':   return <ConversationsPage uuid={hashParam} />;
case 'activity':        return <ActivityPage />;
case 'knowledge-base':  return <KnowledgeBasePage />;
```

---

## Section 3: Page Specifications

### Page 1 — My Queue (`#my-queue`)

**Purpose:** Support tickets currently assigned to the logged-in user.

**Audience:** All staff (any portal cap).

**Backend:**

```
GET /pet/v1/tickets?assigned_user_id={wpUserId}&lifecycle_owner=support
```

Supported query params on the endpoint: `status`, `lifecycle_owner`, `assigned_user_id`.

Ticket response shape:

```ts
{
  id: number;
  customerId: number;
  siteId: number | null;
  subject: string;
  description: string;
  status: string;
  priority: string;
  ticketMode: string;
  assignedUserId: number | null;
  category: string | null;
  lifecycleOwner: 'support' | 'project';
  createdAt: string;       // ISO 8601
  resolvedAt: string | null;
  queueId: number | null;
  referenceCode: string;   // e.g. "TKT-042"
  projectId: number | null;
  quoteId: number | null;
  slaId: number | null;
  slaName: string | null;
  malleableData: Record<string, unknown>;
}
```

**UI:**

- Filter tabs: **All | Open | In Progress | Pending** (filters `status` client-side on the loaded set).
- Ticket card fields: reference code, subject, customer name (resolve from `customerId`), priority badge (colour-coded: critical=red, high=amber, medium=blue, low=grey), SLA name, age (relative from `createdAt`).
- Click card → full ticket detail. Reuse `TicketDetails` component from admin if it has no WP-admin CSS coupling, otherwise render an inline detail panel with the same fields.
- Row actions (visible on hover or via kebab menu):
  - **Pull** — `POST /pet/v1/tickets/{id}/assign` with `{ assignedUserId: wpUserId }` (assigns to self if not already).
  - **Return to Queue** — `POST /pet/v1/tickets/{id}/assign` with `{ assignedUserId: null }`.
  - **Resolve** — `POST /pet/v1/tickets/{id}/close` (existing endpoint, built 2026-04-21).
- Empty state: "No support tickets assigned to you."

**Complexity:** Low — 2–3h.

---

### Page 2 — My Deliverables (`#my-deliverables`)

**Purpose:** Project/delivery tickets assigned to the logged-in user.

**Audience:** All staff (any portal cap).

**Backend:**

```
GET /pet/v1/tickets?assigned_user_id={wpUserId}&lifecycle_owner=project
```

Same ticket response shape as My Queue.

**UI:**

Shares the `TicketListPage` base component (see Section 4 — Shared Components). Differences from My Queue:

- Filter tabs: **All | Planned | In Progress | Blocked**.
- Card fields: project name (looked up via `ticket.projectId` against `GET /pet/v1/projects/{id}` or a pre-fetched project map), subject, rollup indicator (`isRollup` badge from `malleableData`), sold value formatted as `£x,xxx` (from `malleableData.soldValueCents` if present), parent/child relationship indicator.
- Same row actions as My Queue (Pull / Return to Queue / Resolve).
- Empty state: "No project work assigned to you."

**Complexity:** Low — 2–3h (shares component pattern with My Queue; most work is the card layout difference).

---

### Page 3 — Calendar (`#calendar`)

**Purpose:** Agenda view of the logged-in user's upcoming work — all tickets with a due date, grouped by day.

**Audience:** All staff.

**Backend:**

```
GET /pet/v1/tickets?assigned_user_id={wpUserId}
```

Fetch all assigned tickets (no `lifecycle_owner` filter — include both support and project). Filter client-side to those where `dueAt` is not null. Group by date.

**Important:** `CalendarController` (`GET /pet/v1/calendars`) manages SLA working-hours calendar configuration. Do not use it here.

**UI:**

Two sections:

1. **Overdue** — red section header; tickets where `dueAt < now`, sorted ascending (oldest first).
2. **Upcoming — next 14 days** — tickets where `dueAt >= now && dueAt <= now+14d`, grouped by date with day labels: "Today", "Tomorrow", "Mon 27 Apr", etc.

Each item shows: ticket reference code, subject, customer name, source badge (Support / Project, derived from `lifecycleOwner`), time of day if present in `dueAt`.

Click item → ticket detail (same pattern as My Queue).

Empty state: "No upcoming work with due dates."

**Complexity:** Low-Medium — 2–3h.

---

### Page 4 — Log Time (`#log-time`)

**Purpose:** Desktop-optimised time logging for staff in the portal.

**Audience:** All staff.

**Backend (already fully built — built as part of Option B, 2026-04-21):**

| Endpoint | Description |
|---|---|
| `GET /pet/v1/staff/time-capture/context` | Returns `{ employee: {...}, ticketSuggestions: [{ticketId, referenceCode, subject, isBillableDefault}] }` |
| `GET /pet/v1/staff/time-capture/entries` | Returns array of time entries for the current user |
| `POST /pet/v1/staff/time-capture/entries` | Creates entry: `{ ticketId, start, end, isBillable, description }` |

**Reference implementation:** `src/UI/Staff/pages/TimeCapturePage.tsx` — the mobile SPA built in Option B uses these exact endpoints. This portal page is a desktop-layout port of that logic; no new REST work is needed.

**UI — Two-column layout:**

Left column (form):
- Ticket selector — searchable dropdown populated from `ticketSuggestions`.
- Date picker — defaults to today.
- Start time + End time inputs — "Now" shortcut button for each.
- Duration badge — auto-calculated from start/end, displayed in real time.
- Billable checkbox — defaults to `isBillableDefault` from the selected ticket suggestion.
- Notes textarea.
- Save button — `POST /pet/v1/staff/time-capture/entries`.

Right column (today's entries):
- List of today's entries (filter `GET /entries` client-side by date).
- Running total duration at the top of the list.
- Each entry: ticket reference, start–end, duration, billable indicator, description snippet.

**Complexity:** Medium — 2–3h. The logic is already proven in the mobile SPA; this is primarily a two-column layout exercise.

---

### Page 5 — Conversations (`#conversations`)

**Purpose:** Staff messaging threads — view, reply, mark as read, respond to decisions.

**Audience:** All staff.

**Backend (fully built — `ConversationController`):**

| Endpoint | Description |
|---|---|
| `GET /pet/v1/conversations/me?limit=20` | Conversation list: `{ uuid, context_type, context_id, subject, state, created_at }` |
| `GET /pet/v1/conversations/{uuid}/messages` | Message thread for a conversation |
| `POST /pet/v1/conversations/{uuid}/messages` | Send reply: `{ body: string }` |
| `POST /pet/v1/conversations/{uuid}/read` | Mark read: `{ last_seen_event_id: int }` |
| `GET /pet/v1/conversations/unread-counts` | Badge counts per conversation |
| `GET /pet/v1/decisions/pending` | Decisions awaiting response: `{ uuid, decision_type, conversation_id, state, payload, requested_at, requester_id }` |
| `POST /pet/v1/decisions/{uuid}/respond` | Submit decision response |
| `POST /pet/v1/conversations/{uuid}/resolve` | Close thread |
| `POST /pet/v1/conversations/{uuid}/reopen` | Reopen thread |

**UI — Two-panel layout:**

Left panel (280px wide, fixed):
- Scrollable conversation list.
- Each row: subject, context label (e.g. "TKT-042" or "PRJ-007"), state badge (open / resolved), unread dot, relative timestamp.
- Clicking a row loads the thread in the right panel (or navigates to `#conversations/{uuid}` for deep-linking).

Right panel (remaining width):
- Message thread rendered chronologically.
- Each message: sender name + avatar/initials, timestamp, body text.
- Reply area at bottom: textarea + Send button. On send, `POST .../messages` then refresh thread.
- Thread actions: Resolve / Reopen button (top right).
- **Pending decisions** appear as distinct cards inline in the thread at the correct chronological position. Each decision card shows: `decision_type`, `payload` summary, Respond button → modal with response options.
- On thread load, `POST .../read` with `last_seen_event_id` of the last message.

Nav item badge: numeric unread count from `GET /conversations/unread-counts`, fetched on mount in `PortalApp` and refreshed every 60 seconds.

Empty state (no conversations): "No conversations yet."
Empty state (no thread selected): "Select a conversation to view messages."

**Complexity:** High — 4–5h. This is the richest UI of the seven pages.

---

### Page 6 — Activity (`#activity`)

**Purpose:** Personal activity feed showing events involving the current user.

**Audience:** All staff.

**Backend:**

```
GET /pet/v1/activities?limit=50
```

The controller already scopes results to `get_current_user_id()` via `findRelevantForUser()`. No additional filtering params are needed on the initial load.

Response item shape:

```ts
{
  id: number;
  occurred_at: string;          // ISO 8601
  actor_type: string;
  actor_id: number;
  actor_display_name: string;
  actor_avatar_url: string | null;
  event_type: string;
  severity: 'info' | 'warning' | 'critical';
  reference_type: string;       // e.g. "ticket", "project", "quote"
  reference_id: number;
  reference_url: string | null;
  customer_id: number | null;
  customer_name: string | null;
  headline: string;
  subline: string | null;
  tags: string[];
  sla: unknown | null;
  meta: Record<string, unknown>;
}
```

**UI:**

- Chronological list, newest first.
- Grouped by day: "Today", "Yesterday", "Mon 21 Apr", etc.
- Each item: actor avatar (image if `actor_avatar_url`, else initials fallback) + actor name, `headline` text (primary), `subline` text (smaller, muted), relative timestamp (right-aligned), severity dot (info=blue, warning=amber, critical=red), reference link (e.g. "TKT-042" linking to `reference_url`).
- Filter bar above list: **All | Support | Project | Commercial** — maps to `reference_type` (client-side filter on loaded set).
- "Load more" button at the bottom — increments `limit` by 50 and re-fetches.
- Empty state: "No recent activity."

**Complexity:** Low-Medium — 2h.

---

### Page 7 — Knowledge Base (`#knowledge-base`)

**Purpose:** Browse and read internal KB articles.

**Audience:** All staff (read-only).

**Backend (`ArticleController`):**

| Endpoint | Description |
|---|---|
| `GET /pet/v1/articles` | Article list: `[{ id, title, content, category, status, createdAt, updatedAt }]` |
| `GET /pet/v1/articles/{id}` | Single article with full content |

Only articles with `status = 'published'` should be displayed (filter client-side if the endpoint does not filter by default; confirm by checking `ArticleController`).

**UI:**

List view:
- Search bar at top — client-side filter against `title` and `content` (strip HTML before matching).
- Articles grouped by `category` — rendered as section headers (or accordions if category count > 6).
- Each article row: title, excerpt (first 120 chars of content with HTML stripped), category pill.

Article detail (click → full-page or right panel):
- Title (h1), category pill, last updated date.
- Content rendered as HTML (`dangerouslySetInnerHTML` — content is trusted internal data).
- Back button returns to list view.
- If using a right panel: the list remains visible on the left; article occupies the remaining width.

Empty state (no articles): "No articles yet."
Empty state (search returns nothing): "No articles match your search."

**Complexity:** Low — 1.5–2h.

---

## Section 4: Shared Components

### `TicketListPage` base component

My Queue and My Deliverables share the same structural pattern: fetch tickets by `assigned_user_id` + `lifecycle_owner`, display filter tabs, render a card list, handle row actions. Extract a parameterised base component to avoid duplication.

Proposed signature:

```ts
interface TicketListPageProps {
  lifecycleOwner: 'support' | 'project';
  statusTabs: { label: string; value: string | null }[];
  renderCard: (ticket: Ticket, onAction: (action: string) => void) => ReactNode;
  emptyMessage: string;
}
```

`MyQueuePage` and `MyDeliverablesPage` each compose `TicketListPage` with their own `statusTabs` and `renderCard` implementations. The fetch logic, loading state, error state, and action dispatch (`pull` / `return` / `resolve`) live in the base component.

---

## Section 5: New Files

### Files to create

```
src/UI/Portal/pages/MyQueuePage.tsx
src/UI/Portal/pages/MyDeliverablesPage.tsx
src/UI/Portal/pages/CalendarPage.tsx
src/UI/Portal/pages/LogTimePage.tsx
src/UI/Portal/pages/ConversationsPage.tsx
src/UI/Portal/pages/ActivityPage.tsx
src/UI/Portal/pages/KnowledgeBasePage.tsx
src/UI/Portal/components/TicketListPage.tsx   (shared base — see Section 4)
```

### Files to modify

```
src/UI/Portal/PortalApp.tsx                    (7 new lazy imports + route cases)
src/UI/Portal/PortalShell.tsx                  (MY WORK nav section, icons, unread badge)
src/UI/Portal/hooks/usePortalUser.ts           (isStaff flag, hasPortalAccess update, PortalUser interface)
src/UI/Portal/Shortcode/PortalShortcode.php    (getUserPortalCaps includes pet_staff)
src/UI/Rest/Controller/EmployeeController.php  (provisionEmployee allows pet_staff)
```

---

## Section 6: Build Order

| Priority | Item | Est. time | Notes |
|---|---|---|---|
| P0 | `pet_staff` capability fix | 0.5h | Hard prerequisite — implement and test first |
| P1 | My Queue + My Deliverables | 4–5h | Highest daily use; extract `TicketListPage` base here |
| P2 | Activity | 2h | Simple read-only; quick win after P1 |
| P3 | Calendar | 2–3h | Same underlying data as P1; different presentation |
| P4 | Log Time | 2–3h | Port mobile SPA logic to desktop layout |
| P5 | Knowledge Base | 1.5–2h | Simplest read path; good end-of-sprint task |
| P6 | Conversations | 4–5h | Most complex; save for when simpler pages are stable |
| — | PortalShell + PortalApp wiring | 1h | Can be done alongside P1 or as its own pass at the end |

Implement and verify P0 before any other item. The portal guard will reject `pet_staff` users until P0 is in place, making all portal page development untestable in a real browser session.

---

## Section 7: Done Criteria

### Global

- [ ] `pet_staff` capability is recognised by the portal — a WP user with only `pet_staff` can reach `pet.cope.zone/portal` and sees the My Work nav section.
- [ ] Commercial and People sections remain invisible to pure `pet_staff` users.
- [ ] `pet_sales`, `pet_hr`, and `pet_manager` users see all three sections (Commercial, People, My Work) without regression.
- [ ] `EmployeeController::provisionEmployee()` accepts `pet_staff` as a valid `portalRole` value.
- [ ] `tsc --noEmit` passes with no new errors.
- [ ] `phpstan analyse` passes at configured level.

### My Queue

- [ ] Support tickets assigned to the current user load correctly.
- [ ] Filter tabs (All / Open / In Progress / Pending) narrow the displayed list correctly.
- [ ] Pull action assigns the ticket to the current user.
- [ ] Return to Queue action removes the assignment.
- [ ] Resolve action calls `POST /tickets/{id}/close` and removes the ticket from the list.
- [ ] Empty state renders when no support tickets are assigned.

### My Deliverables

- [ ] Project tickets assigned to the current user load correctly.
- [ ] Filter tabs (All / Planned / In Progress / Blocked) work.
- [ ] Project name resolves from `projectId` and is visible on the card.
- [ ] Empty state renders when no project tickets are assigned.

### Calendar

- [ ] Only tickets with a non-null `dueAt` are shown.
- [ ] Overdue section is distinct and sorted ascending.
- [ ] Upcoming 14-day section is grouped by day with correct day labels.
- [ ] Empty state renders when no due dates are present.

### Log Time

- [ ] Ticket suggestions load from `GET /staff/time-capture/context`.
- [ ] Submitting the form creates an entry via `POST /staff/time-capture/entries`.
- [ ] Today's entries display in the right column with a running total.
- [ ] "Now" shortcuts populate start/end time fields with the current time.
- [ ] Duration badge updates in real time as start/end change.

### Conversations

- [ ] Conversation list loads from `GET /conversations/me`.
- [ ] Selecting a conversation loads the message thread.
- [ ] Sending a reply calls `POST /conversations/{uuid}/messages` and appends the message.
- [ ] Marking as read calls `POST /conversations/{uuid}/read`.
- [ ] Pending decisions appear as distinct cards and can be responded to.
- [ ] Resolve / Reopen actions work.
- [ ] Unread badge on the nav item updates on mount and refreshes every 60 seconds.
- [ ] Empty state renders when no conversations exist.

### Activity

- [ ] Activity items load for the current user (scoped server-side).
- [ ] Items are grouped by day with correct labels.
- [ ] Filter bar narrows by reference type.
- [ ] "Load more" fetches the next 50 items.
- [ ] Severity dot colour is correct for each level.
- [ ] Empty state renders when no activity exists.

### Knowledge Base

- [ ] Article list loads from `GET /articles`.
- [ ] Articles are grouped by category.
- [ ] Search bar filters by title and content (HTML stripped).
- [ ] Clicking an article renders the full content as HTML.
- [ ] Back button returns to the list.
- [ ] Empty state renders when no articles exist.
