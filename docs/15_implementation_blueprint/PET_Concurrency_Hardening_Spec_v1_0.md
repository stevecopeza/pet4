# PET Concurrency Hardening Specification v1.0

## AcceptQuote

-   Must use SELECT ... FOR UPDATE or optimistic locking.

## Outbox

-   Use SELECT ... FOR UPDATE SKIP LOCKED OR transition status to
    'processing' before dispatch.

## Required Tests

-   Double accept produces single project
-   Two workers cannot dispatch same outbox row
