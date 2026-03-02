# PET – Activity Feed and Audit Model

## Purpose of this Document
This document defines how PET represents **what happened**, **when it happened**, and **who was involved**.

The activity feed is not a social stream. It is an **operational lens over immutable events**.

---

## Core Principle

**The activity feed is a read‑only projection of events.**

Events are immutable. Reactions and commentary are additive and never alter the original fact.

---

## Event → Feed Projection

### Event

- Immutable
- Domain‑level fact
- Timestamped and attributed
- Context‑rich

Events are the source of truth.

---

### Feed Item

A **Feed Item** is a rendered view of one or more events.

Properties:
- Derived from events
- Deterministic
- Non‑editable

Feed items may aggregate multiple low‑level events for readability, but must never distort meaning.

---

## Immutability Rules

- Feed items cannot be edited or deleted
- Ordering is chronological by event time
- Historical feed remains visible forever

Archival affects visibility filters, not existence.

---

## Reactions and Commentary

Users may **react to** feed items.

Reactions include:
- Comments
- Mentions
- Acknowledgements

Rules:
- Reactions are separate entities
- Reactions reference feed items or events
- Reactions do not modify the underlying event

This preserves factual integrity while enabling collaboration.

---

## Contextual Scoping

The feed supports scoped views:

- Personal ("things I touched")
- Team
- Project
- Customer
- Organisation‑wide

Scope is derived from event context.

---

## Audit Guarantees

For every feed item, PET must be able to answer:

- Who performed the action
- When it occurred
- What entity was affected
- What the previous state was
- What the new state is

If this cannot be answered, the event is invalid.

---

## Noise Control

Rules:
- Only domain‑significant events generate feed items
- UI‑only interactions are excluded
- Batch events may be summarised

The feed favours signal over volume.

---

## Security and Visibility

- Feed visibility respects permission boundaries
- Lack of permission hides the feed item entirely
- No redacted or partial feed items

Users should never infer hidden information.

---

## What This Prevents

- Retroactive history edits
- Narrative rewriting of events
- Conflicting versions of truth

---

**Authority**: Normative

This document defines PET’s activity and audit model.

