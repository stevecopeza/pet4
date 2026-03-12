# Reference Implementation – Schema Versioned Migration

## Purpose
Shows how to add a new field without breaking history.

---

## Example

```php
if (!$schema->hasColumn('leads', 'industry_code')) {
    $schema->addColumn('leads', 'industry_code', 'varchar(50)');
}
```

---

## Rules

- Idempotent
- Forward-only
- No data destruction

---

**Authority**: Reference

