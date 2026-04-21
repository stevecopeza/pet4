# PET Staff Portal — Architecture
Version: v1.0
Status: Approved for implementation
Date: 2026-04-20
Customer context: RPM go-live sprint

---

## Overview

The Staff Portal is a polished, front-end-accessible SPA mounted at `pet.cope.zone/portal` via a single WordPress shortcode `[pet_portal]`. It provides RPM's ~8 staff users (sales, HR, managers) with a full commercial workflow — customers, catalog, employees, leads, quotes — without requiring access to the WordPress admin panel.

The portal is **not a rewrite**. It reuses the existing React component library built for the admin panel (`src/UI/Admin/components/`), wrapped in a new portal-specific shell, routing layer, and permission model.

---

## URL & Entry Point

| Setting | Value |
|---|---|
| Portal URL | `pet.cope.zone/portal` |
| WordPress page | Create page with slug `/portal`, body: `[pet_portal]` |
| Shortcode class | `src/UI/Portal/Shortcode/PortalShortcode.php` |
| React entry | `src/UI/Portal/main.tsx` |
| Build output | `dist/portal.js` + `dist/portal.css` |

The shortcode renders a single `<div id="pet-portal-root">` and injects `window.petSettings` (identical shape to the admin panel — `apiUrl`, `nonce`, `currentUser`). The React app handles all routing client-side.

---

## Build Configuration

The existing `vite.config.ts` has a single entry (`src/UI/Admin/main.tsx`). It is extended to a **multi-entry build**:

```ts
// vite.config.ts
rollupOptions: {
  input: {
    admin:  path.resolve(__dirname, 'src/UI/Admin/main.tsx'),
    portal: path.resolve(__dirname, 'src/UI/Portal/main.tsx'),
  },
}
```

The portal entry imports components from `src/UI/Admin/components/` directly. Shared code is tree-shaken and deduplicated at build time. The portal bundle is expected to be ~60% of the admin bundle size since it omits internal-only sections (SLA engine, Pulseway, advisory, billing, etc.).

---

## Design System

The portal uses the **same design tokens** as the admin dashboard (`pet-dashboards` page). Visual language:

| Token | Value | Use |
|---|---|---|
| `--sc-accent` | `#2563eb` | Primary blue, CTAs, active nav |
| `--sc-border` | `#e5e7eb` | Card and table borders |
| `--sc-radius` | `12px` | Card corner radius |
| Font stack | System UI (SF Pro / Segoe UI / sans-serif) | All text |
| Background | `#f8fafc` | Page background |
| Card background | `#ffffff` | Content cards |

A `portal.css` file extends `shortcodes.css` with portal-specific layout (sidebar, header bar, route animations). It does **not** duplicate existing tokens — it imports and extends.

### Layout Structure

```
┌──────────────────────────────────────────────┐
│  🔷 PET Portal                  [User] [?]   │  ← Header bar (56px)
├────────────┬─────────────────────────────────┤
│ Customers  │                                 │
│ Catalog    │   <route-matched content>        │  ← Main area
│ Employees  │                                 │
│ Leads      │                                 │  ← Sidebar (240px fixed)
│ Quotes     │                                 │
│ Approvals  │                                 │
└────────────┴─────────────────────────────────┘
```

Nav items render only if the logged-in user holds the required capability (see Role Model below). Managers see all items including Approvals. HR sees Employees and Catalog. Sales sees Customers, Leads, Quotes.

---

## Role Model

Three new WordPress custom capabilities are registered on plugin activation:

| Capability | Role label | Assigned to |
|---|---|---|
| `pet_sales` | Sales Staff | Sales people |
| `pet_hr` | HR Staff | HR staff |
| `pet_manager` | Manager | Sales Manager, HR Manager |

Capabilities are **additive** — a manager also holds `pet_sales` and `pet_hr` so they can access all portal sections. The capabilities are set on the WordPress user record (not WP roles, which are global) so they can be applied per-user without disrupting existing admin users.

### Capability Matrix

| Portal Section | `pet_sales` | `pet_hr` | `pet_manager` | `manage_options` |
|---|---|---|---|---|
| Customers (CRUD) | ✅ | read-only | ✅ | ✅ |
| Catalog (CRUD) | read-only | ✅ | ✅ | ✅ |
| Employees (CRUD) | — | ✅ | ✅ | ✅ |
| Leads (CRUD) | ✅ | — | ✅ | ✅ |
| Quotes (list + view) | ✅ | — | ✅ | ✅ |
| Quote Builder | ✅ | — | ✅ | ✅ |
| Approvals queue | — | — | ✅ | ✅ |
| PDF Download | ✅ | — | ✅ | ✅ |

---

## Permission Layer

All existing REST controllers use `checkPermission()` returning `current_user_can('manage_options')`. This is **not changed** — doing so would break the admin panel.

A new helper is introduced:

```php
// src/UI/Rest/Support/PortalPermissionHelper.php
class PortalPermissionHelper
{
    public static function check(string ...$caps): bool
    {
        if (current_user_can('manage_options')) return true;
        foreach ($caps as $cap) {
            if (current_user_can($cap)) return true;
        }
        return false;
    }
}
```

Each controller targeted for portal access gets a second permission method alongside the existing one:

```php
// Example: CustomerController.php
public function checkPortalPermission(): bool
{
    return PortalPermissionHelper::check('pet_sales', 'pet_hr', 'pet_manager');
}
```

Routes that need portal access reference `checkPortalPermission` instead of `checkPermission`. Routes that are admin-only retain `checkPermission` unchanged.

### Controllers Requiring Portal Permission Updates

| Controller | Portal capabilities required |
|---|---|
| `CustomerController` | `pet_sales`, `pet_hr`, `pet_manager` |
| `ContactController` | `pet_sales`, `pet_hr`, `pet_manager` |
| `LeadController` | `pet_sales`, `pet_manager` |
| `QuoteController` | `pet_sales`, `pet_manager` |
| `CatalogItemController` | `pet_sales` (read), `pet_hr`, `pet_manager` (write) |
| `CatalogProductController` | `pet_sales` (read), `pet_hr`, `pet_manager` (write) |
| `EmployeeController` | `pet_hr`, `pet_manager` |
| `RoleController` | `pet_hr`, `pet_manager` (read) |
| `TeamController` | `pet_manager` (read) |

---

## WP User Creation on Employee Save

When HR creates a new employee via the portal, a WordPress user account is simultaneously created and linked. This is implemented server-side in `EmployeeController::createEmployee()`.

### Flow

1. HR submits employee form with: first name, last name, email, portal role (`pet_sales` | `pet_hr` | `pet_manager` | none)
2. Employee record saved to `pet_employees` as normal
3. If `portal_role` is not `none`:
   - `wp_create_user($username, wp_generate_password(), $email)` called
   - `wp_update_user(['display_name' => "$firstName $lastName"])` called
   - `$wpUser->add_cap($portalRole)` called
   - `wp_update_user(['ID' => $wpUserId, 'role' => 'subscriber'])` — minimal WP role
   - `wp_send_new_user_notifications($wpUserId, 'user')` — sends "set your password" email
   - `wp_user_id` persisted back to `pet_employees` row
4. If WP user creation fails (duplicate email, etc.), employee record is **still saved** but response includes a `wp_user_warning` field. UI shows a dismissible warning banner.

### Username generation

```php
$username = strtolower($firstName . '.' . $lastName); // e.g. "jane.smith"
// If taken: append incrementing suffix ("jane.smith2", etc.)
```

---

## PDF Generation

A new REST endpoint is registered: `GET /pet/v1/quotes/{id}/pdf`

The endpoint:
1. Loads the quote by ID, verifies the caller has `pet_sales` or `manage_options`
2. Loads all blocks in display order
3. Renders an A4 PDF using TCPDF (available via Composer; or fallback to `Dompdf` if already in the stack)
4. Returns the PDF binary with headers: `Content-Type: application/pdf`, `Content-Disposition: attachment; filename="Quote-{ref}.pdf"`

### PDF Template — Professional Generic Layout

| Section | Content |
|---|---|
| Header | Company name + logo placeholder, "QUOTE" label, quote reference number, date |
| Bill To | Customer name, address fields (from customer record) |
| Prepared By | Employee name who created the quote |
| Validity | 30 days from creation date (configurable via settings) |
| Block Table | Description \| Qty \| Unit \| Unit Price \| Total |
| Subtotals | Net total, any price adjustments shown as line items |
| Grand Total | Bold, prominent |
| Footer | Quote reference, T&Cs placeholder ("Subject to standard terms and conditions") |

The portal "Download PDF" button fires a direct browser navigation to the endpoint URL (with nonce in query param), triggering browser download. No server-side email — the PDF is sent from the user's own email client as an attachment.

---

## Component Reuse Strategy

The following existing admin components are imported directly into portal views. They receive `apiUrl` and `nonce` from `window.petSettings` — the same injection the admin panel uses — so they work without modification.

| Admin Component | Portal Use | Notes |
|---|---|---|
| `Customers.tsx` + `CustomerForm.tsx` | Customers section | Import as-is; strip admin-nav links |
| `Catalog.tsx` + `CatalogProducts.tsx` | Catalog section | Render read-only tabs for `pet_sales` |
| `Employees.tsx` + `EmployeeForm.tsx` | Employees section | Extend form with Portal Role field |
| `BlockRow.tsx` | Quote builder | No changes needed |
| `ServiceBlockEditor.tsx` | Quote builder | No changes needed |
| `ProjectBlockEditor.tsx` | Quote builder | No changes needed |
| `SmartOwnerDropdown.tsx` | Quote builder | No changes needed |
| `KebabMenu.tsx`, `PriceCell.tsx`, `RoleBadge.tsx`, `OwnerBadge.tsx` | Quote builder | No changes needed |
| `InlineValidation.tsx` | Quote builder | No changes needed |
| `foundation/Dialog.tsx` | All modals | No changes needed |
| `foundation/ToastProvider.tsx` | All notifications | No changes needed |
| `foundation/PageShell.tsx` | Portal section wrapper | Adapt for portal context |
| `foundation/states/*.tsx` | All loading/empty/error | No changes needed |

New components built only for the portal:
- `src/UI/Portal/main.tsx` — entry point, mounts `<PortalApp />`
- `src/UI/Portal/PortalApp.tsx` — router, shell, auth detection
- `src/UI/Portal/PortalShell.tsx` — sidebar nav, header bar, layout
- `src/UI/Portal/PortalQuoteEditor.tsx` — quote builder wrapper (adapts `Commercial.tsx` block-editing logic without admin chrome)
- `src/UI/Portal/PortalLeads.tsx` — leads list + create (extracted from `Commercial.tsx`)
- `src/UI/Portal/PortalApprovals.tsx` — approval queue (managers only)
- `src/UI/Portal/hooks/usePortalUser.ts` — reads capabilities from `window.petSettings`

---

## Security Constraints

- All REST endpoints are authenticated (nonce required, same as admin)
- No front-end logic determines what data is returned — all filtering is server-side
- Object-level authorization: `QuoteController` only returns quotes for the current user's customers (existing behaviour is system-wide for `manage_options`; portal users get scoped results)
- WP user creation is server-side only — the portal form passes intent, not the WP API call
- PDF endpoint validates quote ownership before generating

---

## Non-Goals for v1.0

The following are explicitly out of scope and will not be built in this sprint:

- Customer-facing quote acceptance portal (customers accept via staff action for now)
- Automated email sending (PDF downloaded, sent from email client)
- Mobile-optimised layout (tablet-friendly is acceptable)
- Helpdesk / ticket management from portal
- Time tracking from portal
- Knowledge base from portal
- Multi-tenant data isolation (RPM is single-tenant for v1.0)
