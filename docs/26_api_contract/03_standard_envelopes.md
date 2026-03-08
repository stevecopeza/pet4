# Standard REST Envelopes

## Pagination
- Query: page (default 1), per_page (max 100), sort_by, sort_direction (asc|desc)
- Response:
```
{
  "data": [...],
  "meta": {
      "page": 1,
      "per_page": 20,
      "total": 134,
      "total_pages": 7
  }
}
```

## Error Envelope
```
{
  "error": {
      "code": "VALIDATION_ERROR",
      "message": "Human readable",
      "details": {...}
  }
}
```

## Status Codes
- 400 validation
- 401 auth
- 403 permission
- 404 not found
- 409 illegal state transition
- 500 server error

**Authority**: Normative
