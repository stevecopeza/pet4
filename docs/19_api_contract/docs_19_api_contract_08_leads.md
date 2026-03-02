# API Contract — Leads

## Standard CRUD

### GET /pet/v1/leads

List all leads.

**Response**: Array of Lead objects.

### POST /pet/v1/leads

Create a new lead.

**Required fields**: `customerId`, `subject`

**Optional fields**: `description`, `source`, `assignedTo`, `estimatedValue`

**Response**: 201 Created.

### PUT /pet/v1/leads/{id}

Update an existing lead.

**Response**: 200 OK.

### DELETE /pet/v1/leads/{id}

Archive a lead (soft delete).

**Response**: 200 OK.

---

## Lifecycle Endpoints

### POST /pet/v1/leads/{id}/convert

Convert a lead to a quote.

**Preconditions**:
- Lead must exist
- Lead status must be `new` or `qualified`
- Idempotent: if lead is already `converted`, returns the existing linked quote ID

**Behaviour**:
1. Creates a Quote in `draft` state with:
   - `customerId` from Lead
   - `title` prefixed with "Quote: {lead subject}"
   - `description` from Lead
   - `lead_id` = Lead ID
2. Transitions Lead status to `converted` (sets `convertedAt`)
3. Returns the new Quote ID

**Response (201)**:
```json
{
  "quoteId": 42
}
```

**Error responses**:
- 404: Lead not found
- 422: Lead status not eligible for conversion (e.g. already disqualified)

---

## Lead States

- `new` — initial state
- `qualified` — assessed as worth pursuing
- `converted` — successfully converted to a Quote
- `disqualified` — assessed as not worth pursuing

Valid transitions:
- `new` → `qualified` | `converted` | `disqualified`
- `qualified` → `converted` | `disqualified`
- `converted` — terminal
- `disqualified` — terminal

---

## Sales Metrics (via Dashboard)

Lead-related metrics are included in the `GET /pet/v1/dashboard` response under the `sales` block:

- `activeLeads` — count of leads in `new` + `qualified` states

Source: `LeadRepository::countActive()`

---

**Authority**: Normative
