# API Contract -- Quotes

## Lifecycle Endpoints

### POST /quotes

Create new Quote draft.

### POST /quotes/{id}/clone

Deep clone quote and increment version.

### POST /quotes/{id}/submit

Move draft → pending_approval. Validates approval rules.

### POST /quotes/{id}/approve

Approve pending quote.

### POST /quotes/{id}/send

Mark quote as sent to customer.

### POST /quotes/{id}/accept

Accept quote. Emits QuoteAccepted event. Triggers Contract creation.

### POST /quotes/{id}/reject

Reject quote.

------------------------------------------------------------------------

## Component Management

### POST /quotes/{id}/components

Add component (catalog \| implementation \| recurring \| adjustment).

### POST /quotes/{id}/milestones

Add milestone to ImplementationComponent.

### POST /quotes/{id}/tasks

Add task to milestone.

### POST /quotes/{id}/payment-plan/generate

Generate canonical payment schedule.

------------------------------------------------------------------------

## Preconditions & Rules

-   Accepted quotes immutable.
-   Approval required if rule triggered.
-   Idempotency required on accept endpoint.
-   All state transitions validated against current status.

------------------------------------------------------------------------

## Lead Linkage

Quotes may carry a `lead_id` (nullable) linking to the source Lead.

-   Set automatically when a quote is created via `POST /leads/{id}/convert`.
-   NULL for quotes created directly (no lead origin).
-   Included in quote serialization as `leadId`.

See also: `docs_19_api_contract_08_leads.md` for the Lead conversion endpoint.
