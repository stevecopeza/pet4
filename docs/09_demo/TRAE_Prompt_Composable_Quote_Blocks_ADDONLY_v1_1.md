# TRAE PROMPT — Composable Quote Blocks (ADD-ONLY v1.1)

clear

You are implementing a composable block-based Quote UX in PET.

## Constraints
- ADD-ONLY changes
- No refactors or renames
- Strict domain layering
- Forward-only migrations

## Replace
Remove the modal "Add Component" selection UI behavior.

## Implement
1) Floating round "+" button (FAB).
2) Flyout menu with direct block types:
   - Once-off Product
   - Once-off Simple Services
   - Once-off Project
   - Repeat Product
   - Repeat Services
   - Quote Price Adjustment
   - Payment Plan
   - Text Block

## Block Model
Introduce additive entity: QuoteBlock (ordered).

Subtypes:
- OnceOffSimpleServiceBlock
- OnceOffProjectBlock
- RepeatServiceBlock
- RepeatHardwareBlock
- HardwareBlock
- PriceAdjustmentBlock
- PaymentPlanBlock
- TextBlock

## Invariants
- Simple block: no phases allowed
- Project block: phases allowed
- Phase subtotal derived only
- Quote total derived from priced blocks
- RepeatServiceBlock supports mode flag:
    - SLA
    - ScheduledWork

## ScheduledWork behavior
On acceptance:
- Create first ticket occurrence only.
- When occurrence closes → schedule next.
- No bulk generation of future tickets.

## Activity
All creation events emit domain events and project into Activity Stream.

## Tests Required
- Block ordering preserved
- Totals correct after reordering
- Project block phases derive subtotal correctly
- Scheduled work generates next ticket only on close

Deliver minimal, additive implementation only.
