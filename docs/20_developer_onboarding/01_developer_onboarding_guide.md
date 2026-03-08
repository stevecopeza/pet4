# PET – Developer Onboarding Guide

## Purpose of this Document
This guide onboards a developer into PET **without tribal knowledge**.

If you follow this guide, you will:
- Understand how PET is meant to work
- Know where code is allowed to live
- Know what *not* to do
- Be productive without breaking invariants

This document is **mandatory reading** before writing code.

---

## What PET Is (and Is Not)

PET is:
- A domain-driven business system
- Event-backed and audit-safe
- Long-lived and backward-compatible

PET is NOT:
- A CRUD WordPress plugin
- A UI-first application
- A place to "just fix it in the frontend"

---

## Required Reading (In Order)

Before touching code, read:

1. `00_foundations/` – philosophy and invariants
2. `01_architecture/` – boundaries and ownership
3. `02_domain_model/` – entities and lifecycles
4. `05_data_model/` – tables, events, migrations
5. `07_ui_structure/` – what the UI may and may not do
6. `09_integrations/` – external system rules
7. `11_stress_tests/` – what must never break

If something you want to do conflicts with these docs, **your idea is wrong**.

---

## Codebase Orientation

### Directory Layout

```
src/
├─ Domain/          # Business truth
├─ Application/     # Use cases
├─ Infrastructure/  # DB, integrations
└─ UI/              # REST, admin UI
```

Rule of thumb:
> If you are unsure where code goes, it probably belongs in **Application**.

---

## Golden Rules (Non-Negotiable)

1. **Never mutate history**
2. **Never bypass state machines**
3. **Never put business logic in UI or Infrastructure**
4. **Never delete domain data**
5. **Never trust external systems as authoritative**

Violating any of these is a hard stop.

---

## How to Add a New Feature (Correctly)

1. Identify the domain entity involved
2. Define or update state transitions (Domain)
3. Add a command + handler (Application)
4. Persist via repository (Infrastructure)
5. Expose via REST/UI (UI)
6. Emit and consume events
7. Check stress-test scenarios

Skipping steps leads to breakage later.

---

## Working with Events

- Events represent facts
- They are immutable
- They are never edited or deleted

If you think an event is "wrong", emit a new one.

---

## Working with the Database

- Use repositories only
- Migrations are forward-only
- Schema versions matter

Never write ad-hoc SQL in handlers or controllers.

---

## UI Development Rules

- UI is read-first
- Mutations go through commands
- Hard errors are expected

If the UI feels "strict", that is intentional.

---

## Integration Rules

- Integrations react to events
- Failures are visible
- No silent retries

Never assume an external call succeeded.

---

## How to Know You’re Doing It Right

You are probably correct if:
- You added a domain event
- You didn’t touch WordPress APIs directly
- History is preserved
- KPIs still reconcile

You are probably wrong if:
- You needed a global helper
- You changed existing data
- You bypassed a handler

---

## When You Are Unsure

- Re-read the stress-test scenarios
- Look at reference implementations
- Ask for clarification *before coding*

PET is designed to punish shortcuts later. Respect it now.

---

**Authority**: Normative

This document defines how developers safely work on PET.

