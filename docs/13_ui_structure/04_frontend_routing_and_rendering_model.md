# PET – Frontend Routing and Rendering Model

## Purpose of this Document
This document defines **how PET screens are rendered** within WordPress, and when to use:
- React / Vue SPA modules
- Classic WordPress admin pages

---

## Core Principle

PET is an **application embedded in WordPress**, not a collection of admin forms.

---

## Rendering Strategy

### SPA (React or Vue)

Used for:
- Dashboards
- Timesheets UX
- Project planning
- Ticket handling

Characteristics:
- Client-side routing
- REST API driven
- High-interaction surfaces

---

### Classic WP Pages

Used for:
- Settings
- Schema configuration
- KPI definitions
- Low-frequency admin tasks

Characteristics:
- Server-rendered
- Form-based

---

## Routing Model

- One WP admin page per PET functional area
- SPA handles internal routing
- WordPress handles authentication and capability gating

---

## URL Strategy

Examples:
- `/wp-admin/admin.php?page=pet-dashboard`
- `/wp-admin/admin.php?page=pet-dashboards`
- `/wp-admin/admin.php?page=pet-delivery`
- `/wp-admin/admin.php?page=pet-delivery#project=123`

Hash or history routing may be used internally.

Delivery deep-link contract:
- `#project=<id>` on `pet-delivery` opens project detail workspace for that project.
- PM dashboard drill-through must target this URL contract for project-specific navigation.

---

## Security Enforcement

Rules:
- UI routing does not imply permission
- All API calls enforce domain permissions
- Direct URL access without permission returns hard errors

---

## What This Prevents

- Fragmented UX
- Logic duplication
- Permission leaks

---

**Authority**: Normative

This document defines how PET renders and routes its UI.

