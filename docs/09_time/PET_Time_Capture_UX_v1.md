# PET Time Capture UX v1 (Staff)

## Purpose
Design a mobile-first, low-friction time capture experience that drives daily user compliance.

## Core Principles
- ≤3 taps to log typical entry
- Context-first (suggestions > forms)
- Day completeness visibility
- Mobile-first interaction
- Same domain/API behavior

## Primary Screen: Today

### Header
- Date (Today)
- Completion bar (e.g. 6.5h / 8h)
- Actions: Add, Fill Gap

### Summary Strip
- Total Logged
- Billable %
- Entry Count
- Streak

### Timeline (Core Surface)
Each entry is a card:
- Time range
- Activity
- Project
- Duration
- Billable badge

Interactions:
- Tap = edit
- Swipe right = duplicate
- Swipe left = delete (confirm)

### Gap Indicators
Unlogged time blocks shown explicitly:
- Tap to fill prefilled entry

## Add Entry Flow

Bottom sheet interaction:

Step 1: Select activity
- Recent items
- Assigned tickets
- Search

Step 2: Duration
- Quick presets (15m, 30m, 1h, etc)
- Slider optional

Step 3: Save (instant)

## Smart Suggestions
- Recent entries
- Assigned work
- Yesterday pattern
- Future: calendar integration

## Visual Language
- Card-based UI
- Clear hierarchy
- Billable (green), Non-billable (neutral)
- Gap (warning)

## Behavioral Design
- Completion progress
- End-of-day nudges
- Streak reinforcement

## Constraints
- No API changes
- No domain logic changes
- Same entity model
- Same validation rules

## Relationship to Admin UI
- Admin: control (table + hybrid)
- Staff: capture (cards + timeline)

## Implementation Phases

### Phase 1 (MVP)
- Today screen
- Timeline cards
- Add entry flow
- Gap detection
- Summary strip

### Phase 2
- Smart suggestions improvements
- Streaks
- Week view

### Phase 3
- Calendar integration
