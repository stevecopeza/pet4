# PET Quote — Composable Block UX (v1.1)

Status: Updated Demo Spec  
Scope: Replace "Add Component" modal flow with composable block model.

---

# 1. Core Principle

A Quote is an **ordered list of Blocks**.

Users construct quotes visually using a floating round **"+" button**.  
No generic “Add Component” wording. No nested type selection modal.

---

# 2. Add Button (Floating Action Button)

- Always visible (bottom-right floating action button)
- Round "+" icon
- On click → contextual flyout menu

---

# 3. Flyout Options

Direct intent-based entries:

1. Once-off Product  
2. Once-off Simple Services  
3. Once-off Project  
4. Repeat Product  
5. Repeat Services  
6. Quote Price Adjustment  
7. Payment Plan (placeholder)  
8. Text Block (.md formatted)

No secondary modal step.

---

# 4. Block Types

## 4.1 Once-off Simple Services
Flat container of Simple Units.

Simple Structure:

Component (Simple)
  - Unit A
  - Unit B (depends on A)

- Multiple units allowed
- Dependencies allowed
- Each unit priced
- No phases

---

## 4.2 Once-off Project (Complex)

Structured with Phases.

Complex Structure:

Component (Complex)
  - Phase A (subtotal derived)
      - Unit A1
      - Unit A2
  - Phase B (subtotal derived)
      - Unit B1

Pricing:
- Units hold commercial truth
- Phase displays rolled-up subtotal only

---

## 4.3 Repeat Services (Recurring Service Container)

Creates a Recurring Service Block with internal mode toggle:

Mode:
- SLA / Retainer
- Scheduled Work

### SLA Mode
- Recurring billing
- SLA clocks
- Support-driven behavior

### Scheduled Work Mode
Example: Engineer health check every 2 months
- Recurrence frequency
- On acceptance: create first occurrence ticket
- When closed → generate next occurrence
- Avoid pre-generating future tickets

Recommended generation mode: Generate next on close (Mode B)

---

## 4.4 Quote Price Adjustment
Replaces Cost Adjustment section.
- Positive or negative commercial adjustment
- Explicit reason field
- Included in margin calculations

---

## 4.5 Payment Plan (Placeholder)
Structural block for future milestone payment mapping.

---

## 4.6 Text Block
Markdown editor block:
- Bold
- Headers
- Subheaders
- Bullet lists
- Used for scoping, disclaimers, assumptions

Non-priced block.

---

# 5. Block Ordering

Users may drag/drop reorder all blocks freely.

Quote total recalculates automatically based on priced blocks only.

---

# 6. Acceptance Behavior

On QuoteAccepted:

- Simple Units → Tickets
- Complex Units → Project + Tickets
- Repeat Services → Contract + schedule logic
- Emit activity events

---

# 7. Why This Scales

- No modal decision trees
- New block types can be added without UX redesign
- Quote becomes composable document
- Clear visual containers
