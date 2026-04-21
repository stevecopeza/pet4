# PET UI Contract — Quote Margin Column (v1)
Status: Proposed for implementation
Surface: Quotes & Sales → Quote detail section block tables
## 1. Visible UI/UX Change
YES.
Quote section tables now include a visible `Margin` column in row listings, positioned between `Total` and `Actions` (or nearest stable equivalent when nested layout requires).
## 2. Column Placement
Primary section table header order:
1. Description
2. Role
3. Owner/Team
4. Qty
5. Unit
6. Unit Price
7. Total
8. Margin
9. Actions
Nested project unit rows align to the same concept by including a margin cell before action controls.
## 3. Label and Display Rules
- Header label: `Margin`
- Cell display when available:
  - Primary text: money amount (e.g. `$320.00`)
  - Secondary text (optional): percentage (e.g. `16.0%`)
- Percentage is visually secondary (smaller/muted style)
## 4. Empty / Unsupported Behavior
- If margin is unavailable, unsupported, or ambiguous: display em dash `—`
- No placeholders that imply synthetic values
- No fallback calculations in UI from live master entities
## 5. Formatting
- Currency formatting follows existing quote money formatting conventions (2 decimals)
- Negative margin values remain explicitly signed and visible
- Percentage omitted when null (including zero-sell rows)
## 6. Responsive / Narrow Layout
- Margin column remains present in desktop section tables
- If responsive constraints require compacting nested layouts, margin remains represented via the same margin value cell semantics (not dropped from business rows)
## 7. Interaction Behavior
- Existing edit, discuss, move, and delete actions remain unchanged
- Adding margin must not break row click-to-edit, keyboard behavior, or kebab menu actions
## 8. Compatibility Guarantees
- No existing columns removed for this feature
- No change to quote totals cards/section total chips behavior
- No change to payment schedule UI behavior

