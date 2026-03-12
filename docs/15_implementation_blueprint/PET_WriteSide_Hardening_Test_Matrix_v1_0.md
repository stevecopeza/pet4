# PET Write-Side Hardening Test Matrix v1.0

## Required Tests

1.  AcceptQuote double concurrency
2.  Listener exception rollback
3.  Duplicate event idempotency
4.  Projection replay
5.  Outbox double worker
6.  Migration parity
7.  SLA version binding

All must fail before fix and pass after.
