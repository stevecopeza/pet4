# PET Quote Detail — Component Display Gap (v1)

Status: Documented defect
Severity: Functional gap — quote body invisible for component-only quotes

---

# 1. Problem Statement

The Quote detail page (`QuoteDetails.tsx`) shows "No sections defined yet." for any quote whose content lives in the **component** model rather than the **section/block** model. This includes all demo-seeded quotes and any quote created via the domain API (e.g. `AddComponentHandler`).

The header, KPI cards, and payment schedule render correctly because they derive values from the component data. But the quote **body** — the actual line items, milestones, tasks, and catalog items — is invisible.

---

# 2. Root Cause — Two Parallel Data Models

The quote system has two independent content models that evolved at different times:

## 2.1 Component Model (Domain Layer — Older)
- Stored in `pet_quote_components` table.
- Domain entities: `ImplementationComponent`, `CatalogComponent`, `OnceOffServiceComponent`, `RecurringServiceComponent`.
- Created via `AddComponentHandler`.
- Used by the demo seed (`DemoSeedService::seedCommercial`).
- Used by the acceptance flow (`AcceptQuoteHandler`) to derive tickets and projects.
- Serialized in the API response under the `components` key.

## 2.2 Section/Block Model (UI Builder — Newer)
- Stored in `pet_quote_sections` and `pet_quote_blocks` tables.
- Domain entities: `QuoteSection`, `QuoteBlock`.
- Created via `AddQuoteSectionHandler` + `CreateQuoteBlockHandler`.
- Used by the manual quote builder UI (the floating "+" button workflow).
- Serialized in the API response under the `sections` and `blocks` keys.

These two models coexist on every quote. The `serializeQuote` method in `QuoteController.php` returns both `components` AND `sections`/`blocks`. However, the frontend only renders sections/blocks.

---

# 3. Specific Gaps

## 3.1 Frontend rendering (`QuoteDetails.tsx`)
- The "Quote Sections" region (line 1874+) iterates over `sectionsForRendering` (derived from `quote.sections`).
- When `sectionsForRendering` is empty, it shows the fallback message "No sections defined yet." (line 2492).
- There is **no rendering path** for `quote.components` anywhere in the JSX body.
- Components are only used for the KPI math (line 1665: `quote.components.reduce(...)` for cost) and the metadata strip (line 1795: `Components: N` count).

## 3.2 API serializer (`QuoteController::serializeQuote`)
- `CatalogComponent` → serialized with `items` array ✓
- `OnceOffServiceComponent` → serialized with `units` or `phases` array ✓
- `RecurringServiceComponent` → **NOT serialized** (only base fields: type, section, description, sellValue, internalCost)
- `ImplementationComponent` → **NOT serialized** (milestones and tasks are lost)

The `ImplementationComponent` branch is entirely missing from the serializer. This means even if the frontend tried to render components, the Q1 demo quote would only have `{ type, section, description, sellValue, internalCost }` — no milestones or tasks.

## 3.3 Frontend types (`types.ts`)
- `QuoteComponent` interface has `items`, `units`, and `phases` but no `milestones` field.
- `ImplementationComponent` data has no type representation on the frontend.

---

# 4. What Is Affected

- **Demo quotes**: Q1 through Q7 all use `AddComponentHandler`. All appear with empty bodies on the detail page.
- **API-created quotes**: Any quote built via the PHP domain layer (scripts, integrations, future CLI tools) will have the same gap.
- **Manually built quotes**: Work correctly because the UI creates sections + blocks.
- **Quote acceptance**: Unaffected — `AcceptQuoteHandler` reads components directly from the domain model, not from blocks.
- **Baselines**: Unaffected — baselines snapshot components directly.
- **Totals/KPIs**: Unaffected — these derive from component sellValue/internalCost which serializes correctly.

---

# 5. Fix Strategy — Read-Only Component Renderer

Add a **Component Summary** panel to `QuoteDetails.tsx` that renders `quote.components` as a read-only view when components exist. This sits between the Description and the Quote Sections heading.

### 5.1 Backend changes
- Add `ImplementationComponent` serialization to `QuoteController::serializeQuote` (milestones → tasks with durationHours, sellRate, sellValue, internalCost).
- Add `RecurringServiceComponent` serialization (serviceName, cadence, termMonths, renewalModel, sellPricePerPeriod, internalCostPerPeriod).

### 5.2 Frontend type changes
- Add `milestones` to `QuoteComponent` in `types.ts` (array of `{ id, title, description, tasks: [...] }`).
- Add recurring fields to `QuoteComponent`.

### 5.3 Frontend rendering
- New `ComponentSummary` section in `QuoteDetails.tsx`.
- Renders per-component, grouped by component `section` label.
- For `implementation`: collapsible milestone → task table (task, hours, rate, value).
- For `catalog`: item table (description, qty, unit price, total).
- For `once_off_service` (simple): unit table.
- For `once_off_service` (complex): phase → unit table.
- For `recurring`: service summary card.
- Each component shows its section label, description, and total sellValue.
- Read-only in all quote states (this is a display view, not an editor).

### 5.4 Interaction with sections/blocks
- The component renderer is always shown when components exist, independent of sections.
- Sections/blocks continue to render below as they do today.
- This means a quote could show both components AND blocks — which is correct, since they are independent structures.

---

# 6. Files Involved

Backend:
- `src/UI/Rest/Controller/QuoteController.php` — add ImplementationComponent + RecurringServiceComponent serialization

Frontend:
- `src/UI/Admin/types.ts` — extend QuoteComponent interface
- `src/UI/Admin/components/QuoteDetails.tsx` — add component rendering section

---

END
