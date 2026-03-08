STATUS: AUTHORITATIVE — IMPLEMENTED
SCOPE: Sales Dashboard Persona + Lead→Quote Flow
VERSION: v1

# Sales Dashboard Feature Specification (v1)

## Persona

**Sales** — the fourth dashboard persona alongside Manager, Support, and Project Manager.

Persona question: **"What do I need to focus on today to make target (or at least sell more)?"**

This view is action-oriented. It surfaces the highest-impact items a salesperson should act on right now.

------------------------------------------------------------------------

## 1. KPI Strip

Six key metrics displayed as cards:

- **Pipeline Value** — Sum of `totalValue` for all quotes in `draft` + `sent` states. Represents active commercial opportunity.
- **Quotes Sent** — Count of quotes in `sent` state. These are awaiting client response and represent the most immediate conversion opportunity.
- **Win Rate** — `accepted / (accepted + rejected)` as a percentage. If no terminal quotes exist, display `--`.
- **Revenue MTD** — Month-to-date revenue from accepted quotes (already computed by DashboardController).
- **Active Leads** — Count of leads in `new` + `qualified` states. Represents top-of-funnel volume.
- **Avg Deal Size** — Mean `totalValue` of accepted quotes. Helps calibrate expectations.

------------------------------------------------------------------------

## 2. Needs Attention (Action Items)

Sorted by urgency, highest priority first:

### 2.1 Aging Sent Quotes (Follow Up)
Quotes in `sent` state where `updatedAt` is more than 3 days ago.
- Severity: warning
- Label: "FOLLOW UP"
- Timer: days since sent
- Rationale: Sent quotes that go cold are the most wasted effort.

### 2.2 Stale Leads (Qualify or Disqualify)
Leads in `new` state where `createdAt` is more than 7 days ago.
- Severity: unassigned
- Label: "QUALIFY"
- Timer: days since created
- Rationale: Unqualified leads age poorly and should be decided on.

### 2.3 Ready-to-Send Drafts (Finish and Send)
Quotes in `draft` state that have at least one component.
- Severity: info
- Label: "FINISH & SEND"
- Timer: none
- Rationale: Quotes with substance that haven't been sent represent blocked revenue.

------------------------------------------------------------------------

## 3. Pipeline Summary

Visual summary of quotes grouped by state:

- **Draft** — count and total value
- **Sent** — count and total value
- **Accepted** — count and total value (won)
- **Rejected** — count and total value (lost)

Each state shown as a labelled bar or card with count and dollar total.

------------------------------------------------------------------------

## 4. Activity Stream

Filtered to commercial event types:
- `QUOTE_DRAFTED`
- `QUOTE_SENT`
- `QUOTE_ACCEPTED`
- `QUOTE_REJECTED`
- `CONTRACT_CREATED`

Uses the same `ActivityStream` component as other views with customer badges, actor avatars, and time-ago display.

------------------------------------------------------------------------

## 5. Data Requirements

### 5.1 API: Sales Metrics Block
Extend `GET /pet/v1/dashboard` response to include a `sales` object:

```
{
  "sales": {
    "pipelineValue": 45000.00,
    "quotesSent": 3,
    "quotesAccepted": 2,
    "quotesRejected": 1,
    "activeLeads": 4,
    "avgDealSize": 15400.00
  }
}
```

New repository methods:
- `QuoteRepository::countByState(string $state): int`
- `QuoteRepository::sumValueByStates(array $states): float`
- `QuoteRepository::avgAcceptedValue(): float`
- `LeadRepository::countActive(): int`

### 5.2 Frontend Data
The dashboard data loader adds:
- `api('leads')` — full lead list (for attention items)
- `api('quotes')` — full quote list (for pipeline + attention)

Both passed to SalesView as props.

------------------------------------------------------------------------

## 6. Lead → Quote Conversion

### Command
`ConvertLeadToQuoteCommand(int $leadId, ?string $currency = 'USD')`

### Handler Behaviour
1. Load Lead by ID. Throw if not found.
2. Verify Lead status is `new` or `qualified`. Throw DomainException otherwise.
3. Create Quote:
   - `customerId` from Lead
   - `title` from Lead subject (prefixed, e.g. "Quote: {subject}")
   - `description` from Lead description
   - `lead_id` set to Lead ID
   - `state` = draft
4. Update Lead status to `converted` (sets `convertedAt`).
5. Return new Quote ID.

### REST Endpoint
`POST /pet/v1/leads/{id}/convert`

Response: `{ "quoteId": 42 }`

### Database
New nullable column `lead_id` on `{prefix}pet_quotes` table (migration).

------------------------------------------------------------------------

## 7. Seed Data

### Demo Leads
6 leads seeded across customers:

- 2 converted (linked to existing quotes Q1, Q4 via `lead_id`)
- 2 qualified (for attention items — "ready to convert")
- 1 new, stale (created > 7 days ago — attention item)
- 1 disqualified (for pipeline completeness)

### Feed Events
Additional commercial feed events:
- Lead created events
- Lead qualified events
- Lead conversion events

------------------------------------------------------------------------

## 8. Invariants

- `lead_id` is nullable — quotes may be created directly for a Customer without a Lead
- A converted Lead cannot be re-qualified or re-converted
- Lead conversion is atomic (Lead status update + Quote creation in same transaction)
- Win Rate excludes draft/sent/archived quotes — only accepted and rejected count
- Pipeline Value includes only non-terminal quotes (draft + sent)

------------------------------------------------------------------------

## 9. Future Considerations

- Opportunity entity between Lead and Quote (when pipeline complexity warrants it)
- Revenue target/quota setting per salesperson per period
- Weighted pipeline forecast (probability × value for sent quotes)
- Sales team view (aggregate across multiple salespeople)
- Lead source analytics (which channels produce the best conversion rates)

------------------------------------------------------------------------

**Authority**: Normative

This document specifies the Sales Dashboard persona and Lead→Quote conversion mechanics.
