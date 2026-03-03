# PET – List Management & Bulk Actions

## Purpose
Defines the standard interaction patterns for managing lists of entities (People, Quotes, Projects, Articles, etc.), including selection, bulk operations, and single-item actions.

## Core Capabilities

### 1. Selection Model
- **Checkbox Column**: The first column of any manageable list MUST be a checkbox column.
- **Select All**: The header checkbox toggles selection for all *visible* items (current page).
- **Persistence**: Selection state is managed by the container component (`selectedIds` array).
- **Component**: `DataTable` prop `selection={{ selectedIds, onSelectionChange }}`.

### 2. Bulk Actions
- **Visibility**: Bulk action controls appear ONLY when one or more items are selected.
- **Placement**: A specialized toolbar appears above the table when items are selected (e.g., "3 items selected").
- **Standard Actions**:
  - **Bulk Archive**: Soft-delete multiple items via sequential API calls.
  - **Bulk Delete**: Hard-delete (restricted permission, rarely used).
- **Implementation**: Sequential `DELETE` requests to avoid server timeouts/overload.

### 3. Row Actions
- **Clickable Primary Column**: The primary identifier column (e.g., Name, ID) MUST be clickable and trigger the primary action (Edit or View).
- **Location**: The last column of the table is reserved for "Actions".
- **Delivery**: Row actions are rendered via the **KebabMenu** (⋯ dropdown) component for all entities with more than one action. See [KebabMenu Row Actions](docs_22_ui_components_06_kebab_menu_actions.md) for full specification.
- **Standard Actions** (in order):
  - **View/Tasks**: Primary navigation action.
  - **Edit**: Opens the entity for modification in the reusable Form component.
  - **Divider**: Separates non-destructive from destructive actions.
  - **Archive/Delete**: Destructive action, rendered with `danger: true`.
- **Consistency**: All entity lists share this pattern. Entities with only a single action (Skills, Certifications, KPI Definitions) use a plain button instead of KebabMenu.

## UI/UX Standards

### Edit Interface
- **Mechanism**: "Edit" button populates the reusable `*Form` component with the row's data.
- **Form Reuse**: The same form component is used for both "Add New" and "Edit" actions.
- **State Constraint**: Editing may be disabled based on entity state (e.g., Quotes can only be edited in 'draft' state).

### Delete/Archive Flow
- **Safety**: "Archive" actions MUST require confirmation (browser alert).
- **Preference**: Prefer "Archive" (soft delete) over "Hard Delete" to preserve referential integrity (e.g., historical quotes linked to an employee).
- **Restoration**: Archived items are filtered out by default but exist in the database.

## Implementation Status
This pattern is fully implemented for (all using KebabMenu delivery):
- **Contacts**: `Contacts.tsx` (Edit, Archive)
- **Customers**: `Customers.tsx` (Edit, Archive, Bulk Archive)
- **Sites**: `Sites.tsx` (Edit, Archive)
- **Leads**: `Leads.tsx` (Edit, Convert, Archive)
- **Employees**: `Employees.tsx` (Edit, Archive, Bulk Archive)
- **Teams**: `Teams.tsx` (View, Edit, Archive, Bulk Archive)
- **Quotes**: `Quotes.tsx` (Edit [Draft only], Archive, Bulk Archive)
- **Projects**: `Projects.tsx` (Edit, Archive, Bulk Archive)
- **Calendars**: `Calendars.tsx` (Edit, Delete)
- **Knowledge**: `Knowledge.tsx` (Edit, Archive, Bulk Archive)
- **Support**: `Support.tsx` (View/Edit, Archive, Bulk Archive)
- **Time Entries**: `TimeEntries.tsx` (Edit, Delete, Bulk Delete)
- **Finance**: `Finance.tsx` (View, Edit)

Entities with single action (plain button, no KebabMenu):
- **Skills**: `Skills.tsx` (Edit)
- **Certifications**: `Certifications.tsx` (Edit)
- **KPI Definitions**: `KpiDefinitions.tsx` (Edit)

**Authority**: Normative
