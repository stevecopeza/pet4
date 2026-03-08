# PET UI Contract --- Escalation & Risk v1.0

Date: 2026-02-26 Target location: docs/ToBeMoved/

## Admin Screens

### 1) Escalations Dashboard

-   Filters: status, severity, source_type, date range
-   Table columns:
    -   Opened At, Severity, Status, Source, Rule, Target, Headline
-   Row opens detail

### 2) Escalation Detail

-   Header: severity, status, source link
-   Timeline: transitions (append-only)
-   Actions:
    -   ACK (manager+)
    -   RESOLVE (manager+)
-   Notes captured per transition

### 3) Escalation Rules Manager (admin)

-   List rules
-   Create/Edit rule
-   Enable/Disable toggle
-   Validate criteria_json schema minimally

### 4) Settings

-   Master enable: pet_escalation_enabled
-   Auto trigger toggles
-   Default cooldown

## Shortcodes

-   \[pet_escalations_my\] (agent/manager)
-   \[pet_escalations_wallboard\] (read-only, auto-refresh)

## Behaviour Rules

-   UI never edits history; only issues commands
-   Actions require explicit confirmation (irreversible state
    transitions)

## Acceptance Criteria

-   UI surfaces are usable without custom styling changes
-   Role gating enforced
