# PET Quote Section Model (v1.0)

Status: Updated Architecture  
Scope: Introduce Section as grouping container above Quote Blocks.

---

# 1. Definition

A Section is a top-level grouping container inside a Quote.

Structure:

Quote
  - Section
      - Block
      - Block
  - Section
      - Block

Sections are NOT nestable.
Blocks may exist outside sections.

---

# 2. Section Header UX

Visual:
- Full-width black header
- Default title: "New Section"
- Inline editable title

Section toggles:
- Show total value
- Show item count
- Show total hours

---

# 3. Section Totals

Derived from contained blocks.

If section contains:
- Once-off blocks → include in once-off total
- Recurring blocks → include in recurring total

Display rule:
- If only once-off → show once-off total
- If recurring exists → show:
    Once-off total
    Recurring total (/period)

---

# 4. Adjustments

If Price Adjustment block is inside a Section:

- If Section contains other content → applies only to that Section.
- If Section contains ONLY the adjustment → applies to entire Quote.

---

# 5. Drag / Drop Rules

Allowed:
- Reorder Sections
- Reorder Blocks inside Section
- Drag blocks between Sections
- Clone Section (clones all contained blocks)
- Edit / Clone / Delete individual blocks

Deletion rule:
- Cannot delete Section containing blocks.
- Must move or delete blocks first.

---

# 6. Totals Model

Quote total = Sum(all Section totals + root-level blocks)
Section total = Sum(block totals within section)

Project phase totals remain derived from Units only.

---

# 7. Delivery Semantics

Sections are presentation-only for now.
Acceptance ignores Section boundaries operationally.

---

# 8. Invariants

- Sections cannot contain Sections.
- Blocks can exist without Sections.
- Section totals derived only.
- Recurring totals not mixed with once-off totals.
- Cloning Section clones child blocks.

