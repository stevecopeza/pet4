# Admin Time WOW Enhancement Spec

## Purpose
Visual-only enhancement pass for the admin TimeEntries screen.

## Goals
- Improve scanability
- Add strong visual hierarchy
- Use color as meaning (not decoration)
- Make rows feel like “stories” instead of raw data

## Constraints
- No API changes
- No domain changes
- No backend dependencies
- No logic drift
- Preserve all behavior

## Key Enhancements
1. Stronger summary cards (visual emphasis)
2. Filter bar as a structured control surface
3. Row composition:
   - Identity (employee)
   - Context (ticket)
   - Signals (badges)
   - Duration emphasis
4. Color system:
   - Billable → green
   - Non-billable → neutral/amber
   - Correction → purple/blue
   - Attention → red/amber

## Expected Outcome
- Faster scanability
- Higher signal density
- Feels like a product, not a table
