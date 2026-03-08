# Reference Implementation – Quote Aggregate

## Purpose
Demonstrates how a **high-risk domain aggregate** is implemented with strict invariants, state transitions, and event emission.

---

## Aggregate Responsibilities

- Own quote lifecycle
- Enforce immutability after acceptance
- Emit domain events on transitions

---

## States

- Draft
- Sent
- Accepted (terminal)
- Rejected (terminal)

---

## Example (PHP – simplified)

```php
final class Quote
{
    private QuoteId $id;
    private QuoteState $state;

    public function accept(EmployeeId $actor): QuoteAccepted
    {
        if (!$this->state->canAccept()) {
            throw new DomainException('Quote cannot be accepted in current state');
        }

        $this->state = QuoteState::accepted();

        return new QuoteAccepted($this->id, $actor);
    }
}
```

---

## Key Rules Demonstrated

- Explicit state guards
- No setters
- Domain event returned, not persisted here

---

**Authority**: Reference

