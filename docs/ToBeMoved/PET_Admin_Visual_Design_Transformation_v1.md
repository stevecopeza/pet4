# PET Admin Visual Design Transformation v1

**Target location:** `plugins/pet/docs/ToBeMoved/PET_Admin_Visual_Design_Transformation_v1.md`

## 0. Purpose

This document defines the next PET UI phase: **visual design transformation**.

This phase begins **after** structural UI modernization and consolidation. Its purpose is to make PET visibly feel like a coherent product by applying a consistent, modern visual language across the admin UI.

This is **not** a backend change, not a workflow redesign, and not a domain redesign.

It is a **behavior-preserving visual transformation**.

The goal is to move PET from:
- internally modernized but visually mixed
to:
- visually coherent, product-like, and recognizably “PET”

This package must preserve PET principles:

- APIs remain authoritative
- no business logic in UI
- no workflow changes
- no source-of-truth changes
- no mutation from read-only surfaces
- backward compatibility in behavior
- additive, controlled rollout

---

# 1. Scope of This Work Package

## 1.1 Included

This package covers:

1. visual language definition for PET admin surfaces
2. transformation of selected legacy-modernized screens into PET visual style
3. page composition improvements
4. stronger visual hierarchy
5. improved spacing, density, cards, headers, and action surfaces
6. consistent visual use of shared primitives already built

## 1.2 Excluded

This package does **not** include:

- API changes
- domain/application changes
- workflow changes
- interaction-sequencing changes
- business-rule changes
- customer portal redesign
- dashboard business logic changes
- replacing source truth with UI-only state

---

# 2. Visual Transformation Principle

The purpose is to change **how PET looks and feels**, not how it behaves.

The transformation must:

- preserve current behavior
- preserve current endpoints and payloads
- preserve current permissions and gating
- preserve existing state transitions
- preserve existing action ordering

The transformation may change:

- page shells
- panel/card structure
- spacing
- headers
- visual grouping
- table chrome and density
- button hierarchy
- filter/toolbars
- summary strip layout
- typography scale
- badge presentation
- empty/loading/error visual treatment

---

# 3. PET Visual Language

## 3.1 Target look and feel

The visual target is the existing stronger PET-style surfaces, especially:
- dashboards
- modern PM-style cards
- card-based summary blocks
- strong header/action hierarchy
- clean spacing and lighter visual density
- clearer color semantics for status/risk

These are the reference direction for transformation.

## 3.2 Visual characteristics to apply

### A. Card-first composition
Screens should prefer:
- page header
- action/filter strip
- content panels/cards
- then detailed tables/lists inside structured surfaces

### B. Strong hierarchy
Pages should clearly separate:
- page title / context
- summary / high-level actions
- list content
- detail content

### C. Cleaner spacing
Reduce cramped admin-table feeling by improving:
- section spacing
- row padding
- card padding
- action spacing
- tab spacing

### D. Table de-emphasis
Tables remain valid where appropriate, but should no longer dominate the visual language as raw WP-admin tables.

### E. Consistent status signaling
Badges, statuses, priorities, risk states, and lifecycle markers must use the shared PET visual language consistently.

### F. Modern action surfaces
Primary actions, destructive actions, and secondary actions should be visually distinct and consistent.

---

# 4. Transformation Constraints

## 4.1 Non-negotiable behavior preservation

Visual transformation must not change:

- API contracts
- endpoint order/sequence
- business legality
- domain lifecycle rules
- command/read boundaries
- feature-flag semantics
- role/scope permissions

## 4.2 Shared primitives are mandatory

Transformation must reuse the already-established shared primitives:
- PageShell
- Card / Panel
- ActionBar
- Dialog / ConfirmationDialog
- ToastProvider / useToast
- LoadingState / EmptyState / ErrorState
- DataTable
- StatusBadge
- Tabs
- form layout primitives where applicable

No new competing visual system should be introduced.

## 4.3 No “fake dashboarding”

Visual modernization must not fabricate summary data or visual indicators disconnected from persisted/API truth.

---

# 5. Initial Visual Transformation Targets

The first visual transformation set should be:

1. `Customers.tsx`
2. `Projects.tsx`
3. `TimeEntries.tsx`

These are chosen because they are:
- already structurally modernized
- high visibility
- still visually old
- lower risk than quote builder/detail flows

These three modules should become the **reference implementation** for PET’s visual design language outside dashboards.

---

# 6. Expected Visual Changes in First Target Set

For each target screen, visual transformation should consider:

## 6.1 Page shell and header
- stronger title area
- clearer page-level action placement
- improved page width/structure
- better header-to-content separation

## 6.2 Summary/action strip
Where useful, add top-level summary or action grouping surfaces using cards/panels rather than bare controls floating above tables.

## 6.3 Filter/search/action area
Normalize filter and action areas into a cleaner strip or action bar with consistent spacing and grouping.

## 6.4 Content area
Replace flat “table on white page” feeling with:
- contained panel/card surfaces
- consistent inner spacing
- better visual grouping

## 6.5 Empty/loading/error states
These should feel product-grade, not admin placeholders.

---

# 7. Rollout Order

## Phase V1 — visual reference implementation
- Customers
- Projects
- TimeEntries

## Phase V2 — secondary visually important admin screens
- Employees
- Settings
- Knowledge
- Approvals

## Phase V3 — operational and specialist screens
- Activity
- Conversations
- Escalations
- Finance
- PulsewayRmm

## Phase V4 — highest complexity surfaces
- QuoteDetails
- other dense detail/builder experiences

---

# 8. Test Expectations

Visual transformation must add or update tests sufficient to ensure:

- no workflow changes
- action availability remains correct
- key structures still render from real API data
- migrated pages continue to use shared primitives
- no reintroduction of browser-native dialogs
- no regression in loading/error/empty states

This package does **not** require snapshot-heavy visual testing by default, but it must preserve the already-established modernization regression approach.

---

# 9. Prohibited Behaviours

- Must not change backend behavior under cover of UI redesign.
- Must not introduce business logic into UI.
- Must not invent new visual primitives competing with the existing foundation.
- Must not fabricate summary metrics disconnected from API truth.
- Must not redesign quote/sales/support workflows in this package.
- Must not turn visual cleanup into uncontrolled refactoring.
- Must not mix old and new visual systems inconsistently within a transformed screen.

---

# 10. Outcome

If implemented correctly, PET should start to visibly feel like one product instead of:
- modern dashboards plus old CRUD admin pages

The first transformed modules should become the reference standard for the rest of the admin UI.
