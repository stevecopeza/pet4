# PET Structural Realignment Master Specification v1.0

Date: 2026-02-27\
Status: HARDENING REQUIRED

## Purpose

This specification realigns PET with its documented guarantees: - Atomic
write-side cascades - Idempotent projections - Deterministic
migrations - Durable domain event persistence - Version-bound SLA
evaluation

## Non-Negotiable Guarantees

1.  All command handlers execute inside a single DB transaction.
2.  Aggregate save + event dispatch + listener side-effects are atomic.
3.  Core domain events are appended to pet_domain_event_stream.
4.  AcceptQuote is concurrency-safe.
5.  Outbox dispatch is concurrency-safe.
6.  CLI and web activation migrations are identical.
7.  SlaClockState binds to a real SLA version.
8.  All listeners are idempotent.

## Implementation Order

1.  Transaction boundary introduction
2.  AcceptQuote concurrency locking
3.  Listener idempotency
4.  Event stream canonical append
5.  Outbox locking
6.  SLA version binding
7.  Migration list unification
8.  Hardening tests
