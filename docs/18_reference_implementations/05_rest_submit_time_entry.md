# Reference Implementation – REST Submit Time Entry

## Purpose
Shows how the UI submits time safely via REST.

---

## Controller Example

```php
final class SubmitTimeEntryController
{
    public function __invoke(Request $request)
    {
        $command = SubmitTimeEntry::fromRequest($request);
        $this->handler->handle($command);
        return new JsonResponse(['status' => 'ok']);
    }
}
```

---

## Rules

- Controller is thin
- Validation before command creation
- Domain errors propagate

---

**Authority**: Reference

