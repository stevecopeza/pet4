# PET – CRM: Leads → Qualification → Opportunities

## Implementation Status

> **Current implementation** covers Lead capture, qualification states (`new` → `qualified` → `converted` / `disqualified`), and direct Lead → Quote conversion via `ConvertLeadToQuoteCommand`. The Opportunity entity, Gold/Silver/Bronze classification, and structured Qualification record described below are **future roadmap** — not yet implemented. See `06_features/PET_Sales_Dashboard_v1.md` for the implemented Lead → Quote conversion mechanics.
>
> **Known code/doc gap**: The `Lead` entity constructor currently requires `customerId` (non-nullable `int`). This contradicts the design principle below that Leads should not require a Customer. The code must be updated to make `customerId` nullable before Leads can be created without an existing Customer record.

## Purpose of this Document
This document defines the **CRM feature domain** in PET, covering:

- Lead capture
- Qualification
- Opportunity management

It describes **behaviour, constraints, and measurement**, not UI polish or implementation detail.

This document **must comply** with all prior foundations, architecture, domain, and cross‑cutting rules.

---

## Scope and Intent

The CRM domain exists to:

- Capture potential business without friction
- Apply structured discipline as certainty increases
- Control pre‑sales investment explicitly
- Produce measurable, reviewable outcomes

CRM in PET is not about contact storage. It is about **deciding where time and effort are justified**.

---

## Lead Capture

### Lead Characteristics

Leads are intentionally permissive.

Rules:
- Minimal required fields
- Malleable schema
- May be incomplete, messy, or speculative

Valid examples:
- An email address
- A name and vague company reference
- A URL
- “Someone I met at a conference”

Leads do **not** require a Customer, Site, or Contact.

---

### Lead Sources

Lead source is a malleable, typed field.

Typical sources include:
- Email
- Phone call
- In‑person
- Social media
- Website

Source affects reporting, not behaviour.

---

### Lead Measurement

Lead‑level KPIs include:
- Volume by source
- Time to qualification
- Disqualification rate

Leads produce events on:
- Creation
- Update
- Qualification decision

---

## Qualification

### Purpose

Qualification introduces **discipline**.

It answers:
- Is this real?
- Is this worth our time?
- How much effort is justified?

---

### Qualification Record

Qualification is a distinct entity.

Rules:
- Schema‑driven required fields
- Versioned requirements
- Explicit completion

Qualification may include:
- Environment understanding
- Budget signals
- Decision‑maker clarity
- Timeline expectations

---

### Opportunity Class

During qualification, an opportunity is classified as:

- Gold
- Silver
- Bronze

This classification:
- Controls allowed pre‑sales effort
- Influences quote depth
- Affects scheduling priority

Classification changes are events.

---

### Qualification Outcomes

Possible outcomes:
- Qualified → Opportunity created
- Disqualified → Lead archived

Disqualification requires a reason.

---

## Opportunity Management

### Opportunity Creation

Opportunities:
- Require an associated Customer
- May reference Sites and Contacts
- Inherit classification from Qualification

Opportunities represent **intent worth investing in**.

---

### Resource Allocation

Pre‑sales work:
- Is planned and time‑boxed
- Appears in the universal calendar
- Competes with delivery work

Sales effort is not free and is measured.

---

### Opportunity Measurement

KPIs include:
- Conversion rate (by class)
- Pre‑sales time spent
- Quote win / loss outcomes

---

## Transition Rules

> **Current implementation note**: The v1 implementation allows direct Lead → Quote conversion without an intermediate Opportunity entity. Leads in `new` or `qualified` status may be converted directly to Quotes via `POST /pet/v1/leads/{id}/convert`. The rules below describe the full future model.

- Leads must be Qualified before Opportunity creation
- Opportunities must exist before Quotes
- Skipping stages is forbidden

Illegal transitions are hard‑blocked.

---

## What This Prevents

- Unbounded sales effort
- Pipeline inflation
- Unmeasured pre‑sales cost
- CRM noise without intent

---

## Out of Scope

This document does not define:
- Quote construction
- Pricing logic
- Project creation

Those are covered in subsequent feature documents.

---

**Authority**: Normative

This document defines PET’s CRM behaviour from Lead to Opportunity.

