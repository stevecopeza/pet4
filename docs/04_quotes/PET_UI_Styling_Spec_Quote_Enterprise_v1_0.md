
# PET UI Styling Specification — Enterprise Quote Experience (v1.0)

Purpose: Elevate the Quote UI to feel structured, premium, and enterprise-ready.

---

# 1. Visual Hierarchy Principles

- Strong container boundaries
- Clear subtotal bands
- Minimal modal interruption
- Consistent typography scale
- High information density without clutter

---

# 2. Layout Structure

## Page Layout

- Sticky header (Quote name, status badge, total)
- Scrollable block canvas
- Floating "+" action button (bottom-right)
- Sticky right-side summary panel (optional future enhancement)

---

# 3. Block Styling

## Block Container

- White background
- Subtle shadow (box-shadow: 0 2px 6px rgba(0,0,0,0.08))
- 12–16px internal padding
- 8–12px vertical spacing between blocks
- Rounded corners (6–8px radius)

## Block Header

- Left: Title (16–18px semibold)
- Right: Subtotal (bold, slightly larger)
- Type badge (muted pill, uppercase, 11px)

---

# 4. Phase Styling (Complex Projects)

- Indented inside project block
- Slightly tinted background (very light grey)
- Phase subtotal aligned right
- Expand/collapse chevron

---

# 5. Unit Rows

- Table-like alignment
- Columns:
  - Unit Name
  - Owner/Team
  - Due Date
  - Price
  - Margin indicator
- Dependency icon displayed subtly

Overdue = soft red badge  
Blocked = amber badge  
Negative margin = red margin text

---

# 6. Repeat Service Block Styling

Distinct accent color band on left edge:
- Blue = SLA
- Teal = Scheduled Work

Recurrence frequency displayed clearly under title.

---

# 7. Text Block Styling

- Markdown rendering
- Headers:
  - H1: 22px
  - H2: 18px
  - H3: 16px
- Light separator line after text block

---

# 8. Price Adjustment Block

- Neutral grey container
- Positive adjustments: green indicator
- Negative adjustments: red indicator
- Reason text visible beneath

---

# 9. Sticky Summary Bar (Optional Enhancement)

Right panel showing:

- Subtotal by block
- Total margin
- Quote total
- Recurring vs once-off split

---

# 10. Interaction Polishing

- Smooth expand/collapse transitions (150–200ms)
- Drag handle icon for reorder
- Inline editing (avoid full-page refresh)
- Totals update live

---

# 11. Enterprise Signals

To feel serious and mature:

- Avoid bright colors
- Use restrained palette (navy, slate, grey)
- Subtle animations only
- No cartoon icons
- Clear state badges
- Tight alignment grid

---

# 12. Demo Impact Notes

When demoing:

- Expand a complex block
- Collapse phases
- Reorder blocks live
- Show total recalculating
- Toggle Repeat Service mode

This conveys power and stability.
