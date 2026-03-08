# PET – Sales & Pipeline Dashboard

STATUS: IMPLEMENTED

## Purpose
Provides visibility into **pipeline quality, conversion efficiency, and sales focus areas**.

Persona question: **"What do I need to focus on today to make target (or at least sell more)?"**

This is the fourth persona tab (💰 Sales) alongside Manager, Support, and Project Manager.

---

## Audience
- Salespeople
- Sales Managers

---

## KPI Strip (6 cards)

- **Pipeline Value** — Sum of `totalValue` for quotes in `draft` + `sent` states
- **Quotes Sent** — Count of quotes in `sent` state
- **Win Rate** — `accepted / (accepted + rejected)` as percentage; displays `--` if no terminal quotes
- **Revenue MTD** — Month-to-date revenue from accepted quotes
- **Active Leads** — Count of leads in `new` + `qualified` states
- **Avg Deal Size** — Mean `totalValue` of accepted quotes

---

## Needs Attention (Action Items)

Sorted by urgency, highest priority first:

1. **Aging Sent Quotes** — quotes in `sent` state with `updatedAt` > 3 days ago (severity: warning, label: "FOLLOW UP")
2. **Stale Leads** — leads in `new` state with `createdAt` > 7 days ago (severity: unassigned, label: "QUALIFY")
3. **Ready-to-Send Drafts** — quotes in `draft` state with ≥ 1 component (severity: info, label: "FINISH & SEND")

---

## Pipeline Summary

Quotes grouped by state with count and total value:
- Draft | Sent | Accepted | Rejected

---

## Activity Stream

Filtered to commercial event types:
- `QUOTE_DRAFTED`, `QUOTE_SENT`, `QUOTE_ACCEPTED`, `QUOTE_REJECTED`, `CONTRACT_CREATED`

Uses shared `ActivityStream` component with customer badges, actor avatars, and time-ago display.

---

## Data Sources

- **API**: `GET /pet/v1/dashboard` returns `sales` block with `pipelineValue`, `quotesSent`, `winRate`, `revenueMtd`, `activeLeads`, `avgDealSize`, `quotesByState`
- **Frontend**: Data loader fetches `leads` and `quotes` lists for attention items and pipeline display

---

**Authority**: Normative

See also: `06_features/PET_Sales_Dashboard_v1.md` for the full feature specification.

