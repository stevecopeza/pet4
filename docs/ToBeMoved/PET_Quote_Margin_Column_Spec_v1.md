# PET Quote Margin Column Specification (v1)
Status: Proposed for implementation
Scope: Quote section/block listings on Quotes & Sales detail surface
Authority: Additive extension to existing quote block model
## 1. Purpose
Expose line-level margin visibility in quote section tables so users can immediately compare sell value vs cost value where authoritative cost snapshots exist.
This is a read-model/display enhancement. It does not replace quote totals logic, payment logic, acceptance flow, or baseline creation flow.
## 2. In-Scope Surface
- Admin route: `/wp-admin/admin.php?page=pet-quotes-sales`
- Quote detail screen section/block listings (`QuoteDetails` section tables)
- Quote block and project unit/phase rows rendered from quote block payload read model
## 3. Out of Scope
- Quote-level totals algorithm changes
- Payment schedule behavior changes
- Contract/baseline generation logic changes
- Destructive data migration
- Any UI-only business-rule computation for margin
## 4. Margin Definitions
### 4.1 Amount
- `margin_amount = line_total_sell_value - line_total_cost_value`
### 4.2 Percentage
- `margin_pct = margin_amount / line_total_sell_value * 100`
- Percentage is nullable and not shown when `line_total_sell_value = 0`
## 5. Row-Type Eligibility and Rules (A)
The following mapping applies to quote block read rows:
1. `OnceOffSimpleServiceBlock`:
- Supported when authoritative cost snapshot exists (`unitCost` and quantity, or `totalCost`)
2. `HardwareBlock` / `RepeatHardwareBlock`:
- Supported when authoritative cost snapshot exists (`unitCost` and quantity, or `totalCost`)
3. `RepeatServiceBlock`:
- Supported when authoritative recurring cost snapshot exists (`internalCostPerPeriod` with term/cadence derivation, or `totalCost`)
4. `OnceOffProjectBlock` parent row:
- Supported only when aggregate authoritative cost exists as:
  - explicit parent `totalCost`, or
  - complete child-unit authoritative snapshots that can be safely aggregated
5. Project phase/unit rows (inside project block payload):
- Supported when phase/unit authoritative cost snapshots exist
6. `TextBlock`:
- Unsupported (always no margin)
7. `PaymentPlanBlock`:
- Unsupported (always no margin)
8. `PriceAdjustmentBlock` (commercial adjustments):
- Unsupported for line margin in this feature version unless explicit cost semantics are present in payload snapshot
## 6. Authoritative Cost Source Rules (B)
Cost-source precedence for block payload snapshot enrichment:
1. Existing persisted snapshot in payload (`unitCost`, `totalCost`, etc.) is authoritative.
2. If snapshot absent and `catalogItemId` is present, snapshot from persisted catalog item unit cost at write time.
3. If still absent and `roleId` is present, snapshot from persisted role `base_internal_rate` at write time.
4. Recurring rows may use persisted recurring snapshot fields (`internalCostPerPeriod`) for derived `totalCost`.
5. If none of the above yields a valid deterministic cost, no cost snapshot is created and margin remains unavailable.
No guessed, heuristic, or synthetic fallback values are permitted.
## 7. Accepted Quote Snapshot Behavior (C)
- Margin read model must use persisted quote/block snapshots only.
- Accepted quotes must not recompute margin from mutable live catalog/rate/role sources.
- Historical rows lacking authoritative cost snapshots remain margin-unavailable and display neutral empty-state output.
## 8. Missing Cost Behavior (D)
- If authoritative cost basis is absent/ambiguous:
  - `lineCostValue = null`
  - `marginAmount = null`
  - `marginPercentage = null`
  - `hasMarginData = false`
- UI displays em dash (`—`) consistently for margin cell.
## 9. Totals Impact (E)
- Section totals and quote totals are not changed by this feature.
- Existing totals remain driven by current quote totals model.
- Margin column is row/line visibility only for this version.
## 10. API / Read Model Contract
Additive fields on quote block read rows:
- `lineSellValue: ?float`
- `lineCostValue: ?float`
- `marginAmount: ?float`
- `marginPercentage: ?float`
- `hasMarginData: bool`
Payload enrichment for project rows may include additive unit/phase margin metadata for rendering, without removing existing payload fields.
## 11. Negative Guarantees
- No destructive schema changes
- No recalculation of accepted history from mutable master data
- No mutation of historical truth to backfill guessed costs
- No removal/renaming of existing API fields
- No UI-only margin business logic
- No implicit inclusion of unsupported financial adjustments in margin
## 12. Stress-Test Scenarios
1. Supported service block with authoritative cost snapshot:
- Margin amount/percentage render correctly.
2. Supported hardware block with snapshot from catalog at write time:
- Margin stable after later catalog updates.
3. Project parent with complete child snapshots:
- Parent aggregate margin renders correctly.
4. Project parent with partial/absent child snapshots:
- Parent margin remains unavailable (`—`).
5. Text block / payment-plan / adjustment block:
- Margin is consistently unavailable (`—`).
6. Zero sell value row with valid cost:
- Margin amount may exist; percentage remains null.
7. Accepted quote after role/catalog rate changes:
- Existing line margins do not drift.
8. API consumer not aware of new fields:
- Existing behavior unaffected (additive compatibility).
#### Margin Semantics Clarification
1) Margin meaning:
- Margin reflects delivery cost vs sell value.
- Margin is not financial/accounting margin.
- Margin excludes commercial adjustments unless explicitly modeled in the quote payload snapshot.
2) Accepted quote behavior:
- Margin is computed from snapshotted cost captured at quote build time.
- Accepted quotes never recalculate margin from live Role/Catalog source data.
3) Project parent rule:
- Parent project margin is shown only when all child phases/units have authoritative cost snapshots.
- If any child is missing authoritative cost, parent margin is hidden (`—`).
4) Historical data:
- Quotes without authoritative cost snapshots display hidden margin (`—`).
- No backfilling or inferred cost values are performed.

