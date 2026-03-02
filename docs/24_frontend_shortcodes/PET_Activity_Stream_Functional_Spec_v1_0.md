# PET Activity Stream — Functional Spec (Unified Production Design)
Version: v1.0  
Status: **Binding** (implementation must conform)

## Goal
Deliver a **production-ready unified Activity Stream** UI that merges:
- Card-based readability (mobile-first)
- Tags/labels and event type color semantics
- Avatars + optional company logos
- SLA countdown indicators where applicable
- Grouping by time buckets
- Density modes
- Wallboard read-only display

This is a **read-only projection UI**: no editing, no deletion, no mutation.

---

## Surfaces
1) **Shortcode**: `[pet_activity_stream]`  
2) **Wallboard Shortcode** (optional but recommended): `[pet_activity_wallboard]`

Both must enforce auth and data scoping.

---

## Core UI Layout (Desktop)
### Header
- Title: “Activity”
- Search box: free-text over `headline`, `subline`, `reference_id`, `customer_name`, `actor_display_name`
- Filter dropdown (see filters below)
- Density selector: Compact / Comfortable (default) / Executive

### Grouped Feed
Buckets (in order):
1. Today
2. Yesterday
3. This Week
4. Older

Within bucket: newest first (`occurred_at DESC`).

### Activity Card Content
Required elements:
- Actor avatar (or system icon)
- Actor display name
- Headline (primary)
- Subline (secondary, optional)
- Reference pill (e.g. `#1042`, `Q-221`) linking to `reference_url` if provided
- Customer name and optional company logo (if present)
- Tag pills (e.g. SLA Risk, Escalation, Quote, Time)
- Timestamp badge (e.g. “2m ago”, “Today 08:41”)

SLA enhancement (only if `sla` present):
- Countdown badge showing `HH:MM:SS`
- Colored by threshold rules (see SLA countdown rules)

---

## Core UI Layout (Mobile)
- Title + tabs not required (single stream)
- Search (collapsible)
- Filter button
- Cards stack vertically
- Avatar left; time badge right; tags under headline/subline
- Group headers sticky optional

---

## Filters
Supported filters (all optional):
- Date range (last 24h, 7d, 30d, custom from/to)
- Event type (multi-select)
- Severity (multi-select)
- Reference type (multi-select)
- Actor (single / multi)
- Customer (single / multi)

Defaults:
- Date range: last 7 days

