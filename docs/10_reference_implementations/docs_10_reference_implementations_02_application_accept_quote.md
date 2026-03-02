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

The Ticket Backbone quote flow specification defines how quote acceptance creates draft, baseline, and execution tickets, and how projects and tasks align to those tickets.

Related documents:

- `03_commercial/04_Quote_to_Ticket_to_Project_Flow_v1.md`
- `08_implementation_blueprint/PET_Ticket_Backbone_Implementation_Roadmap_v1.md`
- `08_implementation_blueprint/11_TRAE_Prompt_Ticket_Backbone_Implementation_ADD_ONLY_v1.md` (implementation prompt, not to be used until explicitly enabled)

---

**Authority**: Reference
