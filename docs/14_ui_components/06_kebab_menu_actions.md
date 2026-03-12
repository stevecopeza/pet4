# PET – KebabMenu Row Actions

## Purpose
Defines the standard row-level action dropdown (⋯ KebabMenu) used across all entity list views where more than one action is available.

## Component
`src/UI/Admin/components/KebabMenu.tsx`

Import: `import KebabMenu, { KebabMenuItem } from './KebabMenu';`

## Item Types
The KebabMenu supports three item types:

- **action** — A clickable menu item. Supports `danger: true` for destructive actions (renders red), `disabled` with optional `disabledReason` tooltip, and `hasNotification` for badge dots.
- **toggle** — A checkbox toggle (label + checked state).
- **divider** — A visual separator between groups of actions.

## Standard Action Order
When building the `items` array, follow this order:
1. Primary actions (View, Edit)
2. Divider (if destructive actions follow)
3. Destructive actions (Archive, Delete) with `danger: true`

Conditional items (e.g., actions only available in certain entity states) are built dynamically before passing to the component.

## Delivery
The KebabMenu renders in the **Actions** column of the `DataTable`. It is passed via the `actions` callback prop on `DataTable`, which receives the row item and returns a `ReactNode`.

## CSS
Styles are defined in `src/UI/Admin/styles.css` under the `.pet-kebab-*` namespace:
- `.pet-kebab-wrap` — Relative positioning container
- `.pet-kebab-trigger` — The ⋯ button (with `--light` variant for dark backgrounds)
- `.pet-kebab-menu` — Absolutely-positioned dropdown panel
- `.pet-kebab-item` — Standard menu item (with `--danger` variant)
- `.pet-kebab-toggle` — Toggle item with checkbox
- `.pet-kebab-divider` — Horizontal separator

The `DataTable` component uses `overflowX: 'visible'` to prevent the dropdown from being clipped by the table container.

## Adoption
The KebabMenu is the standard row action pattern for all entity lists with more than one action.

Entities using KebabMenu:
- Contacts, Customers, Sites, Leads
- Employees, Teams
- Quotes, Projects
- Calendars
- Knowledge
- Support
- Time Entries
- Finance

Entities with a single action (using a plain button instead):
- Skills, Certifications, KPI Definitions

## Interaction
- Click the ⋯ trigger to open.
- Click outside or press Escape to close.
- Clicking a menu item executes its action and closes the menu.
- `e.stopPropagation()` prevents row click-through.

**Authority**: Normative
