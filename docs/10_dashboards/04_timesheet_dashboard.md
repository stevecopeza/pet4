# PET – Timesheet & Billing Dashboard

STATUS: IMPLEMENTED

## Purpose
Surfaces **where time is being spent, how much of it is billable, and what revenue it generates** — across customers, departments, and individual staff members.

Persona question: **"Where is the time going?"**

This is the fifth persona tab (⏱ Timesheets) alongside Manager, Sales, Support, and Project Manager.

---

## Audience
- Operations managers
- Finance / billing staff
- Team leads reviewing staff utilisation

---

## KPI Strip (6 cards)

- **Total Hours** — Sum of all positive-duration time entries (hours)
- **Billable Hours** — Subset where `billable = true`
- **Billing Value** — Billable hours × per-entry `billing_rate` (from `malleableData`)
- **Non-billable** — Total minus billable hours
- **Active Staff** — Distinct employees with ≥ 1 time entry
- **Avg Rate** — Billing value ÷ billable hours

---

## Customer Billing Table

Rows: Each customer (sorted by total billing value descending)

Columns:
- Customer name
- Month −3 (hours + $ value)
- Month −2 (hours + $ value)
- Month −1 (hours + $ value)
- Current month (hours + $ value)
- **Projection** — Current month extrapolated to full month based on days elapsed

Only billable entries contribute to the hours and value columns.

Customer is resolved via `TimeEntry → Ticket → Customer`.

---

## Department Breakdown Matrix

Rows: Support, Projects, Internal (derived from ticket `primary_container`)

Columns:
- Total Hours
- Billable Hours
- Billing Value
- Staff Count (distinct employees)
- Average Rate

---

## Staff Breakdown

Rows: Each employee with time entries, sorted by total hours descending.

Each row displays:
- Employee name
- Summary: total hours · billable hours · billing value
- **Stacked status bar** showing proportion of hours by entry status:
  - 🟢 Green = Locked (approved / finalised)
  - 🟠 Amber = Submitted (awaiting approval)
  - 🟣 Purple = Draft (work in progress)

Legend displayed below the grid.

**Rows are clickable** — clicking opens the staff drill-down view.

---

## Staff Timesheet Drill-Down

Reached by clicking a staff row. Displays individual timesheet for that employee.

### Personal KPI Strip (4 cards)
- Total Hours
- Billable Hours
- Billing Value
- Billable % (colour-coded: ≥70% green, ≥50% amber, <50% red)

### Weekly Summary Table
Last 4 weeks with columns: Week range, Hours, Billable, Value, Status (coloured pips showing count of locked/submitted/draft entries).

### Time Entry List
All entries for the employee sorted newest-first. Each entry shows:
- Customer name
- Status badge (draft / submitted / locked) + billable tag
- Description
- Duration and date/time range

A back button returns to the main Timesheets view.

---

## Data Sources

- **API**: `GET /pet/v1/time-entries` returns all time entries including `malleableData` with `billing_rate` and `department`
- **API**: `GET /pet/v1/employees` returns employee roster
- **Frontend**: Client-side aggregation — data loader fetches time-entries and employees alongside existing dashboard data; all slicing/grouping computed in the component

---

## Seed Data

The `DemoSeedService::seedTimeEntries()` method creates ~120-150 entries spanning 4 months:
- Month −3: lighter load, all entries locked
- Month −2: growing load, all entries locked
- Month −1: steady load, all entries submitted
- Current month: busiest, mixed statuses (submitted ~40%, draft ~30%, locked ~30%)

Distribution:
- All 8 employees participate
- Entries spread across all 4 customers via ticket linkage
- Billing rates derived from catalog: $150–$195/h depending on role
- ~70% billable, 30% non-billable
- Busier staff (Lead Tech, Consultant) get +2 entries/month; lighter roles (Finance) get −1
- Correction entries (reversal + re-log) included for B2 demo feature

---

**Authority**: Normative

See also: `06_features/PET_Sales_Dashboard_v1.md` for the equivalent sales dashboard spec.
