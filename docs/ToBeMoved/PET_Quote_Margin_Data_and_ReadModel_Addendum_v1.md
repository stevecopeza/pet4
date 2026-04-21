# PET Quote Margin Data and Read-Model Addendum (v1)
Status: Proposed for implementation
Scope: Additive block payload snapshot enrichment + additive quote block read fields
## 1. Objective
Enable authoritative line-level margin computation by ensuring quote block payloads can carry persisted cost snapshots and by exposing additive read-model fields for margin.
## 2. Write-Side Additions (Additive)
Introduce write-side payload enrichment for quote block create/update flows:
- Use sanctioned persisted sources to populate missing cost snapshots.
- Preserve existing snapshot fields when already present.
- Never overwrite historical snapshots with guessed values.
### 2.1 Snapshot fields (payload, additive)
Depending on block type, payload may include:
- `unitCost` (per-unit cost snapshot)
- `totalCost` (line/aggregate cost snapshot)
- For project units/phases:
  - unit-level `unitCost`, `totalCost`
  - phase-level `phaseTotalCost`
  - parent-level `totalCost`
### 2.2 Source mapping
- `catalogItemId` → `CatalogItem.unit_cost` snapshot at save time
- `roleId` → `Role.base_internal_rate` snapshot at save time
- recurring snapshot fields (`internalCostPerPeriod`) → deterministic derived aggregate cost
If none resolve, leave cost snapshot absent.
## 3. Read-Side Additions (API / DTO)
For each quote block returned by quote endpoints, add:
- `lineSellValue: ?float`
- `lineCostValue: ?float`
- `marginAmount: ?float`
- `marginPercentage: ?float`
- `hasMarginData: bool`
Project payload read enrichment may include additive calculated margin metadata for units/phases to support nested rendering without UI business logic.
## 4. Read Computation Rules
- Compute using persisted payload snapshots only.
- Do not perform live repository lookups during read serialization for margin.
- Parent project rows compute margin only if complete authoritative aggregate cost exists.
## 5. Backward Compatibility
- Existing block shape and existing fields remain untouched.
- Added fields are nullable/additive.
- Consumers unaware of new fields continue to function unchanged.
## 6. Migration / Historical Data Policy
- No destructive migration.
- No mandatory historical backfill from mutable live sources.
- Existing rows without authoritative cost snapshots continue to return null margin fields and render as `—`.
## 7. Accepted Quote Safety
- Accepted quote rows rely on persisted snapshots and remain stable over time.
- Later edits to catalog items, roles, or rate cards must not alter historical margin output for previously snapshotted rows.
