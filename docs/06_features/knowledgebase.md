# PET – Knowledgebase

## Purpose of this Document
This document defines how PET captures, evolves, and exploits **organisational knowledge**.

The Knowledgebase exists to reduce repeated effort and improve future outcomes.

---

## Core Principles

- Knowledge is versioned
- Authorship and history matter
- Knowledge is contextual, not generic

---

## Knowledge Article Model

Articles represent:
- Solutions
- Explanations
- Procedures

They are not static wiki pages.

---

## Contextual Linking

Articles may be linked to:
- Tickets
- Projects
- Tasks
- Customers

Context improves discoverability and relevance.

---

## Lifecycle

Articles follow the defined state machine.

Revisions:
- Create new versions
- Preserve historical meaning

---

## Comments and Feedback

Articles support:
- Comments
- Ratings

Rules:
- Comments are additive
- Article content is not silently edited

---

## Operational Feedback Loop

When resolving:
- Tickets
- Project issues

Users are prompted to:
- Link existing knowledge
- Create new articles where gaps exist

---

## Measurement

Knowledge KPIs include:
- Reuse frequency
- Support deflection
- Resolution acceleration

---

## What This Prevents

- Knowledge loss
- Repeated problem solving
- Tribal memory

---

## Technical Implementation

- **API**: `/pet/v1/articles` (GET, POST)
- **API**: `/pet/v1/articles/{id}` (PUT, DELETE/Archive)
- **Frontend**: `Knowledge.tsx`, `ArticleForm.tsx` (Unified Add/Edit component).
- **Malleable Fields**: Supported (Schema: `article`).

---

**Authority**: Normative

This document defines PET’s knowledge management model.

