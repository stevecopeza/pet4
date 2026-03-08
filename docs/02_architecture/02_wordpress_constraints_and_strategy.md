# PET – WordPress Constraints and Strategy

## Purpose of this Document
This document defines **how PET uses WordPress**, and just as importantly, **what WordPress is not allowed to decide**.

The goal is to leverage WordPress for what it is good at, while preventing it from corrupting PET’s domain integrity.

---

## Explicit Position on WordPress

WordPress is treated as:
- A hosting runtime
- An authentication and user management system
- A plugin lifecycle manager
- A UI delivery mechanism

WordPress is **not** treated as:
- A domain model
- A workflow engine
- A data integrity authority

---

## Data Storage Strategy

### Core Rule
**All core operational data lives in custom database tables.**

This includes:
- Leads, opportunities, quotes
- Projects, milestones, tasks
- Time entries
- Tickets and SLAs
- KPI source events

WordPress tables (`posts`, `postmeta`) are not suitable for these concerns.

---

## When WordPress Tables Are Acceptable

WordPress native storage may be used for:
- CMS content (marketing pages)
- Public‑facing knowledgebase articles (rendering only)
- Media attachments

Even in these cases, PET maintains its own authoritative references.

---

## Database Design Principles

- Tables are **explicitly versioned**
- Foreign keys are logical (enforced in domain layer)
- Soft deletion via archived flags only
- No EAV models for core data

Schema evolution is handled via controlled migrations.

---

## Custom Post Types (CPTs)

CPTs are avoided for:
- Commercial artifacts
- Time‑based records
- SLA‑relevant entities

CPTs may be used as **read‑only projections** if required for UI or SEO.

---

## REST API Strategy

PET exposes a **domain‑level REST API**:
- All state‑changing operations pass through domain validation
- WordPress REST routes are adapters, not logic containers

Direct DB writes from UI or API are forbidden.

---

## Authentication and Identity

WordPress users map to PET employees.

Rules:
- WordPress user ≠ domain employee
- Employment state is managed by PET
- Role and team logic lives in PET, not WP roles

---

## Permissions Strategy

- WordPress capabilities are coarse‑grained
- Fine‑grained permissions are enforced in the domain layer
- UI visibility does not equal permission

This prevents privilege escalation via UI hacks.

---

## Cron and Background Processing

WordPress cron may be used for:
- KPI recalculation
- SLA checks
- Integration retries

Rules:
- Jobs must be idempotent
- Failures are recorded as events

---

## Upgrade and Migration Strategy

- All schema changes are explicit
- Migrations are forward‑only
- Failed migrations halt plugin activation

Skipping versions is supported.

---

## What This Protects Against

- Performance collapse at scale
- Meta‑table corruption
- Impossible migrations
- Accidental data loss

---

**Authority**: Normative

This document defines PET’s contract with WordPress.

