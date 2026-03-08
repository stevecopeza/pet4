# Reference Implementation – Accept Quote Use Case

## Purpose
Shows how a **user intent** flows from UI to domain safely using commands and handlers.

---

## Flow

UI → Command → Handler → Domain → Event → Persistence

---

## Example

```php
final class AcceptQuoteHandler
{
    public function handle(AcceptQuote $command): void
    {
        $this->transaction->run(function () use ($command) {
            $quote = $this->quotes->get($command->quoteId);
            $event = $quote->accept($command->actor);
            $this->quotes->save($quote);
            $this->events->record($event);
        });
    }
}
```

---

## Key Rules

- Transaction boundary here
- Domain emits events
- Handler persists both state and event

---

## See also: Ticket Backbone

The Ticket Backbone quote flow specification defines how quote acceptance creates one ticket per sold labour item with immutable `sold_minutes` and `is_baseline_locked = 1`. No draft tickets are created during quoting. No baseline/execution clone model.

Related documents:

- `00_foundations/02_Ticket_Architecture_Decisions_v1.md`
- `03_commercial/04_Quote_to_Ticket_to_Project_Flow_v1.md` (v2)
- `08_implementation_blueprint/PET_Ticket_Backbone_Implementation_Roadmap_v1.md` (v2)
- `08_implementation_blueprint/11_TRAE_Prompt_Ticket_Backbone_Implementation_ADD_ONLY_v1.md` (implementation prompt, not to be used until explicitly enabled)

---

**Authority**: Reference
