# Quote Builder UX Overhaul — Design Specification v1.1

Status: Design  
Scope: Replace split-brain quote editor with inline spreadsheet-style editing.  
Changelog: v1.1 — added Unit column, pricing derivation indicators, section margin display, richer project summary, validation/error states, Phase 0, sticky section headers, undo/revert.

---

# 1. Problem Statement

The current quote builder in `QuoteDetails.tsx` (5,138 lines) has three structural UX problems that make quote creation slow and disorienting:

**P1 — Split-brain layout.** A read-only summary table (Type | Details | Qty | Value) sits above a full-width editor panel that opens below it. The user must mentally map between "which summary row am I looking at?" and "where is the form I'm editing?" — the two panes duplicate information and force constant vertical scrolling.

**P2 — All-or-nothing editing.** When a block is expanded, the entire editor form (including every unit in every phase for project blocks) opens at once. Two units already fills the viewport. A realistic project block with 3 phases and 8 units produces an overwhelming wall of inputs.

**P3 — Backwards field ordering.** The natural mental model is: "What's the work → Who does it → What does it cost." The current layout leads with Catalog Service and Role dropdowns (the "how") before showing Description (the "what"). The selection cascade is logically correct but visually inverted.

---

# 2. Design Principles

- **Single surface** — the table is the editor; no separate preview pane.
- **Inline, row-level editing** — only the active row expands; everything else stays compact.
- **Left-to-right = thought flow** — Description → Catalog → Role → Owner → Qty → Unit → Price → Total.
- **Progressive disclosure** — metadata (role, owner) shows as compact badges in read mode; full controls appear only on edit.
- **Keyboard-friendly** — Tab moves through cells; Enter saves; Escape cancels.
- **Derived values are visible** — users can always tell whether a price was calculated or manually overridden.

---

# 3. Current State (what exists)

**Section table (read mode)**

Each section renders a `<table>` with columns: Type | Details | Qty | Value | Actions (kebab).

- Service blocks show description + qty in the Details cell.
- Project blocks render a nested phases→units sub-table inside the Details cell (black header bars, Item/Qty/Unit/Value columns) — this is dense even in read mode.
- The kebab menu offers Edit, Discuss, Delete.

**Block editor (expanded row)**

Clicking Edit inserts a `<tr colSpan={5}>` below the summary row containing the full form:

- Service blocks: Catalog Service dropdown (full width), then a 5-column grid: Description | Qty | Sell Value | Role | Owner/Team. Smart optgroup dropdown for owner.
- Project blocks: Description input, Phases header + Add Phase button, then for each phase: name input + move/delete buttons, then for each unit: Catalog dropdown, Role dropdown, Owner/Team dropdown, Description, Qty, Unit Price, Total, move/delete buttons — all fully expanded.
- A "Commercial Summary" bar at the bottom with Save/Cancel.

**Key file references**

- `QuoteDetails.tsx` — the monolith (5,138 lines)
- `types.ts` — `QuoteBlock`, `QuoteSection` interfaces
- `SqlQuoteBlockRepository.php` — block persistence
- `SqlQuoteSectionRepository.php` — section persistence

---

# 4. Proposed Design

## 4A. Single-Pane Spreadsheet Layout

Replace the current summary-table-above + editor-below with a single table per section where each row is either in **read mode** (compact) or **edit mode** (expanded inline). Only one row can be in edit mode at a time.

**Read-mode row columns (left to right):**

| Description | Role | Owner/Team | Qty | Unit | Unit Price | Total | Actions |

- **Description** (2fr) — the line item text. For project blocks: shows project name with a `▸ 3 phases · 8 units · 72h` summary chip.
- **Role** (badge) — compact pill, e.g. `Consultant`. Grey if unset.
- **Owner/Team** (badge) — compact pill, e.g. `Ava Consultant` or `Delivery`. Grey if unset.
- **Qty** (number) — right-aligned.
- **Unit** — display label: `hours`, `days`, `licenses`, `months`. Prevents ambiguity when scanning.
- **Unit Price** (currency) — right-aligned. Shows a derivation indicator (see §4G).
- **Total** (currency, bold) — right-aligned, auto-calculated.
- **Actions** — kebab menu (Edit, Discuss, Revert, Delete).

This eliminates the "Type" column — block type is communicated through a small icon prefix in the description cell (e.g. wrench icon for service, folder icon for project).

## 4B. Inline Row Editing — Service Blocks

Clicking a service block row (or pressing Enter on a focused row) transitions that row only from read mode to edit mode. The row expands to roughly 2× height, with inputs replacing the static values:

```
| [Description input] [Catalog ▾] | [Role ▾] | [Owner/Team ▾] | [Qty ▲▼] | [Unit ▾] | [Price ▲▼] | $total | ✓ ✕ |
```

**Layout detail:**

- **Description cell** becomes wider. A text input for the description, with a small "Catalog: [dropdown]" selector below it (or as a linked icon that opens a picker). Selecting a catalog item pre-fills description, price, and unit.
- **Role cell** becomes a dropdown. Changing the role triggers the owner-options fetch.
- **Owner/Team cell** becomes the smart optgroup dropdown (Recommended Teams, Recommended Employees, Other Teams, Other Employees) — exactly as built today.
- **Qty** and **Price** become inline number inputs with stepper arrows.
- **Unit cell** becomes a dropdown: hours | days | licenses | months.
- **Total** remains read-only (auto-calculated).
- **Actions** become Save (✓) and Cancel (✕) icon buttons.

The row has a subtle highlight (left border accent + light background) to distinguish it from read-mode rows.

## 4C. Inline Row Editing — Project Blocks

Project blocks are the most complex. The proposed approach:

**Read mode:** A single summary row showing:

```
▸ Website Rebuild | PM | Delivery | – | – | – | $5,200.00 | ⋮
```

With a disclosure triangle `▸` in the description cell and a summary chip: `3 phases · 8 units · 72h`. Clicking the triangle (not the Edit action) toggles the phase accordion below the row — a set of sub-rows showing each phase as a header and its units as compact read rows. This is read-only browsing, not editing.

**Edit mode (row-level):** Clicking Edit on the project row opens a slim editor strip for the project-level fields (description). Phases appear as an accordion below.

**Edit mode (unit-level):** Within the phase accordion, clicking a unit row puts that unit into inline edit mode — same pattern as a service block row. Only one unit edits at a time. The unit row expands with:

```
| Description | Catalog ▾ | Role ▾ | Owner/Team ▾ | Qty | Unit ▾ | Unit Price | Total | ✓ ✕ |
```

Phase management (add phase, reorder, delete) lives in a small toolbar above each phase's unit list.

This means the user is never looking at a wall of 8 fully-expanded unit forms. They see the phase structure, click into one unit, edit it, save it, move to the next.

## 4D. Section Chrome

Each section is a collapsible card with:

- **Header:** Section name (editable inline on click) | item count badge | section total (right-aligned) | **margin %** (right-aligned).
  - Example: `Implementation | 12 items | $14,200 | 32% margin`
  - Margin is calculated as `(total - cost) / total × 100`. Displayed only when cost data is available.
  - Section headers are **sticky** — they remain visible when scrolling within a long section so the user always knows which section they are in.
- **Footer:** `+ Add Block` button (opens the block-type picker as a popover, not a full card).
- Totals (`show_total_value`, `show_item_count`, `show_total_hours`) render in the section header, not as separate rows.

## 4E. Keyboard Navigation

- **Tab** cycles through editable cells in the active row.
- **Enter** on a read-mode row opens it for editing.
- **Enter** in edit mode saves.
- **Escape** cancels edit mode and reverts to the last saved state.
- **Arrow keys** in the phase accordion navigate between units.

## 4F. Commercial Summary Bar

Remove the per-block "Commercial Summary" bar. Instead:

- Each section header shows a running section total and margin (see §4D).
- The **quote-level summary** (Total Value, Base Cost, Margin %) at the top of the page updates in real time as drafts change.

## 4G. Pricing Derivation Indicators

Prices in the system can be either **derived** (calculated from the rate card based on catalog item and role) or **manually overridden**. The UI must make this distinction visible.

**Behaviour:**

1. Catalog item selected → default price loaded from catalog. Price is **derived**.
2. Role changed → price recalculates from rate card. Price remains **derived**.
3. User manually edits the price field → price becomes **locked** (override).
4. User selects a different catalog item → price resets to derived.

**Visual indicators (read mode):**

- Derived price: `$150/hr` with a small link icon (🔗) — indicates the value comes from the rate card.
- Manual override: `$150/hr` with a small edit icon (✎) — indicates the user has set this value.

**Visual indicators (edit mode):**

- Derived price: the price input has a subtle "auto" label or background tint. Editing it switches to override.
- Override price: a small "Reset to rate card" link appears below the input to revert to derived.

**Data model note:** The block `payload_json` already stores unit prices. An additional boolean flag (`price_override: true/false`) or a sentinel approach (storing the derived price alongside the override) is needed. Exact storage TBD during implementation.

## 4H. Validation and Error States

Inline editing requires clear error presentation without disrupting the table layout.

**Field-level validation:**

- Invalid fields show a red border and a tooltip on hover/focus with the error message.
- Example: Qty field with value `0` → red border, tooltip: "Quantity must be at least 1."
- Example: Description empty → red border, tooltip: "Description is required."

**Row-level validation:**

- The Save (✓) button is disabled while any field in the row has a validation error.
- A brief error summary appears below the row if multiple fields are invalid: "Fix 2 errors before saving."

**Server-side errors:**

- If a save fails (network error, 422 response), the row stays in edit mode.
- A red banner appears below the row: "Save failed: [server message]. Try again."
- The user can retry or cancel.

## 4I. Undo and Revert

- **Escape** while editing cancels all draft changes and reverts the row to its last saved state.
- The kebab menu includes a **Revert** option that restores the block to its last persisted state. This covers the case where a user saves a change and immediately regrets it (one level of undo).
- Revert operates on the last saved snapshot. It does not provide multi-step undo history.

---

# 5. Component Decomposition

The current 5,138-line monolith should be broken into focused components:

- **QuoteSections.tsx** — section list, ordering, add/delete section.
- **BlockRow.tsx** — the read-mode row for any block type. Renders description, badges, numbers, pricing indicators.
- **BlockRowEditor.tsx** — the edit-mode row. Contains the inline input strip. Delegates to type-specific sub-components.
- **ServiceBlockEditor.tsx** — inline editor for `OnceOffSimpleServiceBlock`.
- **ProjectBlockEditor.tsx** — project-level editor + phase accordion.
- **ProjectUnitRow.tsx** — read/edit for a single unit within a phase.
- **RoleBadge.tsx** / **OwnerBadge.tsx** — compact pill display for role and owner.
- **PriceCell.tsx** — renders price value with derivation indicator (derived vs. override icon).
- **SmartOwnerDropdown.tsx** — the optgroup-based owner selector (extracted from current inline code, reused by both service and project unit editors).
- **InlineValidation.tsx** — shared validation display: red borders, tooltips, error summaries.

This is a refactoring roadmap — each component can be extracted incrementally while the monolith continues to work.

---

# 6. Migration Strategy

This is a large UI change. Recommended approach:

**Phase 0 — Stabilise rendering.** Extract pure rendering logic (row rendering, totals calculation, badge formatting) from the monolith into presentational helper functions and small components. No structural change — the existing layout and behaviour remain identical. Goal: reduce the monolith's complexity before restructuring, and create a stable baseline for testing.

**Phase A — Extract shared components.** Pull `SmartOwnerDropdown`, `RoleBadge`, `OwnerBadge`, `PriceCell`, `InlineValidation` out of the monolith into standalone components. No visual change — same behaviour, just decoupled.

**Phase B — Service block inline editing.** Rebuild the service block read/edit row as `BlockRow` + `ServiceBlockEditor`. Wire into existing section table. Add Unit column. Add pricing derivation indicators. Remove the old `colSpan` editor panel for service blocks. Validate with E2E tests.

**Phase C — Project block inline editing.** Rebuild the project block as `ProjectBlockEditor` with phase accordion and `ProjectUnitRow`. Add richer project summary chip (`3 phases · 8 units · 72h`). Validate with E2E tests.

**Phase D — Section chrome and summary bar.** Add margin % to section headers. Make section headers sticky. Collapse the quote-level summary into the page header. Remove per-block commercial summary bar.

**Phase E — Validation, undo, keyboard navigation and polish.** Add inline validation states. Add kebab Revert action. Implement Tab/Enter/Escape keyboard flow.

---

# 7. What Does NOT Change

- The backend API (`/quotes/{id}/blocks`, `/quotes/{id}/sections`) is unaffected.
- The block type picker (FAB → type selection popover) stays — just re-styled as a popover instead of a fixed card.
- The conversation panel (discuss a line item) stays — triggered from the kebab menu.
- Payment schedule, cost adjustments, malleable fields — all stay as-is.
- The data model (`payload_json` structure) is unchanged, with the minor addition of a `price_override` flag per unit.

---

# 8. Future Considerations (Out of Scope for v1)

These items were identified during review but are deferred to subsequent iterations:

- **Bulk editing** — multi-select rows to change owner, adjust prices by percentage, assign roles across a phase. High value but significant scope; requires multi-select UI and batch API support.
- **Section navigation rail** — a left sidebar listing sections for jump-to navigation. Sticky section headers (§4D) address the immediate need; a full rail can be added if quotes routinely exceed 10+ sections.
- **Virtualised rendering** — libraries like `react-window` for large DOMs. Component decomposition should resolve current performance issues; virtualisation should only be added if profiling shows a need after the refactor.
- **Autosave** — saving drafts on blur. Risky for financial data where partial saves of half-edited rows could cause problems. Consider autosave for draft state only (local, not persisted) in a future iteration.
- **Cmd+D duplicate row** — keyboard shortcut for duplicating a line item. Nice polish, low priority.
