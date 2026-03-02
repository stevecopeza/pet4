# PET – Dashboards and KPI Views

## Purpose of this Document
This document defines **how operational truth is surfaced** in PET via dashboards and KPI views.

Dashboards in PET are not reporting toys. They are **decision instruments** derived directly from immutable events and declarative KPIs.

---

## Core Principles

- Dashboards never compute truth; they render it
- Every number must be explainable down to events
- Views are role‑aware, not user‑custom free‑for‑alls
- KPIs drive attention, not vanity

---

## Dashboard Architecture

Dashboards are composed of:

- KPI widgets (derived, versioned)
- Trend visualisations (time‑based)
- Exception lists (breaches, variances, risks)
- Contextual drill‑downs (read‑only)

Dashboards do **not** allow data mutation.

---

## Role‑Based Dashboards

### Executive Dashboard

Audience:
- Executives

Focus:
- Business health
- Risk
- Trend direction

Typical KPIs:
- Revenue vs capacity
- Delivery efficiency
- SLA compliance
- Utilisation

---

### Sales Dashboard

Audience:
- Salespeople
- Sales managers

Focus:
- Pipeline quality and value
- Conversion efficiency
- Actionable daily priorities

Implemented KPIs:
- Pipeline Value (draft + sent quotes)
- Quotes Sent
- Win Rate (accepted vs rejected)
- Revenue MTD
- Active Leads
- Avg Deal Size

Attention items surface aging quotes, stale leads, and ready-to-send drafts.

See: `06_dashboards/03_sales_dashboard.md` and `04_features/PET_Sales_Dashboard_v1.md`.

---

### Delivery / PM Dashboard

Audience:
- Project managers

Focus:
- Execution against sold commitments
- Variance detection

Typical KPIs:
- Planned vs actual hours
- Project variance count
- Estimate accuracy

---

### Support Dashboard

Audience:
- Support managers

Focus:
- SLA pressure
- Support load

Typical KPIs:
- Tickets by priority
- SLA breach rate
- Mean time to resolution

---

### HR / People Dashboard

Audience:
- Managers

Focus:
- Capacity
- Load
- Sustainability

Typical KPIs:
- Utilisation
- Overtime trends
- Sick / leave impact

---

## KPI Widget Behaviour

Rules:
- Each widget references a KPI definition + version
- Threshold breaches are visually prioritised
- Widgets support drill‑down to contributing events

Widgets do not support ad‑hoc filtering beyond scope.

---

## Time Windows

Dashboards support:
- Fixed windows (MTD, QTD, YTD)
- Rolling windows (last 30 / 90 days)

Window selection does not alter KPI definitions.

---

## Exception‑Driven Design

Dashboards emphasise:
- Variances
- Breaches
- Trends worsening over time

Normal behaviour is visually de‑emphasised.

---

## Drill‑Down Rules

Drill‑downs:
- Are read‑only
- Respect permission boundaries
- Preserve KPI version context

Users can always trace **why a number exists**.

---

## What Dashboards Never Do

- Accept manual adjustments
- Mask bad data
- Hide breaches
- Recalculate KPIs on the fly

---

**Authority**: Normative

This document defines how PET surfaces truth through dashboards.

