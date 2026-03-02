# PET Helpdesk Overview UI Contract v1.0

Status: **IMPLEMENTATION CONTRACT**  
Shortcode: `[pet_helpdesk]`

This file defines the required DOM structure and CSS token strategy so the implementation matches the design.

## Root wrapper
```html
<div class="pet-shortcode pet-helpdesk pet-helpdesk--mode-manager" data-refresh="60" data-team="all">
  ...
</div>
```

## Manager mode structure (required)
- Header: breadcrumbs, title, chips, context
- KPI tile row (6 tiles)
- Grid:
  - Critical panel (left, tall)
  - At Risk panel (right, top)
  - Flow panel (right, bottom)

Use these required class names:
- `pet-helpdesk__header`, `pet-helpdesk__breadcrumbs`, `pet-helpdesk__title-row`, `pet-helpdesk__title`
- `pet-helpdesk__chips`, `pet-helpdesk__chip`, `pet-helpdesk__context`
- `pet-helpdesk__kpis`, `pet-helpdesk__kpi`, `pet-helpdesk__kpi-label`, `pet-helpdesk__kpi-value`, `pet-helpdesk__kpi-sub`
- `pet-helpdesk__grid`, `pet-helpdesk__panel`, `pet-helpdesk__panel-head`, `pet-helpdesk__panel-title`, `pet-helpdesk__badge`
- `pet-helpdesk__cards`, `pet-helpdesk__card`, `pet-helpdesk__card-title`, `pet-helpdesk__card-meta`, `pet-helpdesk__card-tags`
- `pet-helpdesk__table` (flow)

Ticket card severity modifiers:
- `pet-helpdesk__card--danger`
- `pet-helpdesk__card--warn`
- `pet-helpdesk__card--neutral`

## Wallboard mode structure (required)
- Top brand strip
- Big KPI tiles row
- 3 columns: Critical / At Risk / Normal
- Bottom ticker line

Required wallboard classes:
- `pet-helpdesk__wb-top`, `pet-helpdesk__wb-brand`, `pet-helpdesk__wb-context`
- `pet-helpdesk__wb-kpis`
- `pet-helpdesk__wb-cols`, `pet-helpdesk__wb-col`
- `pet-helpdesk__wb-ticker`
- `.pet-helpdesk--mode-wallboard` (high contrast dark theme)

## CSS token strategy (required)
Define variables on `.pet-helpdesk`:
- colors: bg/card/border/text/muted + accent/danger/warn/neutral
- radii: md/lg
- spacing scale
- type scale

All selectors must be namespaced under `.pet-helpdesk`.
No global selectors.

