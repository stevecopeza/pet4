# Reference Implementation – QuickBooks Invoice Intent

## Purpose
Shows how a domain event triggers an external integration safely.

---

## Trigger

- InvoiceIntentCreated event

---

## Adapter Example

```php
final class QuickBooksInvoiceAdapter
{
    public function handle(InvoiceIntentCreated $event): void
    {
        $this->client->sendInvoice($event->payload());
    }
}
```

---

## Failure Rules

- Failures emit events
- No retries without visibility

---

**Authority**: Reference

