# PET – Support & SLA Dashboard

## Purpose
Provides real-time visibility into **support load and SLA risk**.

---

## Audience
- Support Managers
- Support Agents (individual workload)
- Operations Leads

---

## Core KPIs

- Open Tickets by Priority
- SLA Breach Rate
- Mean Time to Resolution
- Support Hours by Customer

---

## Implemented KPIs (Admin Dashboard SPA — Support Persona)

The Support persona view in the admin dashboard SPA (`docs_06_dashboards_and_views_07_admin_dashboard_spa_spec_v1.md`) renders these KPI tiles:

- **My Open Tickets** — count of tickets assigned to current user via WorkItem projection
- **Breached (Mine)** — subset of my tickets where `sla_time_remaining < 0`
- **Due Within 1hr** — subset of my tickets where `0 < sla_time_remaining <= 60`
- **Unassigned Queue** — tickets with WorkItem but no `assigned_user_id`

---

## Focus Areas
- Breach prevention
- Load balancing
- Customer risk
- Individual agent workload management

---

## Default Time Windows
- Today
- Last 30 Days

---

## Implemented Views

### My Tickets by SLA Urgency
Attention cards for the current user's assigned tickets, sorted by `sla_time_remaining` ascending (most urgent first). Each card shows:
- Ticket subject
- Customer name + priority
- SLA timer with colour coding (red/amber/green)
- Status badge: BREACHED / DUE SOON / ON TRACK
- **Clickable** — opens Ticket Detail panel

### Unassigned Queue
Separate panel showing tickets with team/queue assignment but no individual owner. Clickable for drill-down. Only shown when non-empty.

### Ticket Activity Stream
Filtered feed events for ticket-related activity: ticket_created, ticket_assigned, ticket_status_changed, sla_warning, sla_breached, ticket_resolved.

---

## Drill-Down
- **Ticket Detail panel** — full ticket view with Description, Work Log (time entries), Discussion, Activity, Assignment, Customer, SLA Detail, Metadata, Signals. See `docs/07 Ui Structure/docs_07_ui_structure_07_ticket_detail_view_contract_v1.md`.
- Ticket timelines
- SLA event chains

---

## Wallboard Mode
Designed for large screens/TVs in operational centers.

- **High Contrast**: Dark theme for readability at a distance.
- **Columns**: Critical (Breached), At Risk (Due Soon), Normal (Open).
- **Auto-Refresh**: Reloads every 30s by default.
- **Activation**: `[pet_helpdesk mode="wallboard"]`

---

**Authority**: Normative

