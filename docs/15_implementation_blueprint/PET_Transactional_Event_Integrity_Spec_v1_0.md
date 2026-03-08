# PET Transactional Event Integrity Specification v1.0

## Problem

Command handlers save aggregates and dispatch events without a
transaction boundary.

## Requirement

All command handlers must execute inside:

BEGIN TRANSACTION\
- Load aggregate (FOR UPDATE if needed)\
- Mutate aggregate\
- Save aggregate\
- Append event to event stream\
- Dispatch event\
COMMIT

Rollback if any listener fails.

## Required Tests

-   Listener failure rolls back aggregate
-   Partial cascade cannot persist
