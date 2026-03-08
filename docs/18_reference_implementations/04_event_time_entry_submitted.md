# Reference Implementation – Time Entry Submitted Event

## Purpose
Demonstrates how a **domain event** is structured and consumed.

---

## Event Class

```php
final class TimeEntrySubmitted
{
    public function __construct(
        public readonly TimeEntryId $id,
        public readonly EmployeeId $actor,
        public readonly int $minutes
    ) {}
}
```

---

## Consumption

- Recorded in `domain_events`
- Drives KPI recalculation
- Appears in activity feed

---

## Key Rules

- Immutable
- Factual
- No derived values

---

**Authority**: Reference

