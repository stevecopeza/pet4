# Commercial Transition Rules

Defines how Leads convert to Quotes, and how Quotes convert into Contracts and Projects.

------------------------------------------------------------------------

## 0. Lead Conversion

Two paths to Quote creation:

### Path A: Lead → Quote (conversion)
On `ConvertLeadToQuote`:
1.  Verify Lead status is `new` or `qualified`.
2.  Create Quote with `lead_id` set, inheriting `customerId` and `subject` from Lead.
3.  Mark Lead as `converted` (sets `convertedAt`).
4.  Quote starts in `draft` state, ready for component building.

### Path B: Direct Quote creation
Quotes may be created directly for a Customer without a Lead.
`lead_id` remains NULL.

Both paths converge at the Quote lifecycle: draft → sent → accepted.

------------------------------------------------------------------------

## 1. Acceptance Sequence

On QuoteAccepted:

1.  Create Contract.
2.  Snapshot commercial totals.
3.  Snapshot SLA version.
4.  Generate canonical payment schedule.
5.  Create Baseline v1.
6.  Instantiate Project from Baseline.
7.  Generate ProcurementIntent.

All steps atomic within transaction boundary.

------------------------------------------------------------------------

## 2. WBS Mapping Rules

-   1 Quote Task → 1 Project Task (baseline version).
-   Milestone hierarchy preserved.
-   Duration copied exactly.
-   Internal cost ceiling calculated from all task
    internal_cost_snapshot values.

------------------------------------------------------------------------

## 3. Rate Handling

-   Internal base rates carried into Project baseline.
-   Sell price not editable post-acceptance.
-   Margin preserved for reporting.

------------------------------------------------------------------------

## 4. Recurring Services Conversion

-   RecurringServiceComponent → ServiceContract entity.
-   SLA bound via snapshot.
-   Renewal model copied.

------------------------------------------------------------------------

## 5. Payment Plan Linkage

-   If milestone-triggered, milestone_id stored.
-   Otherwise trigger_reference stored as static string.

------------------------------------------------------------------------

## 6. Variance vs Change

-   Delivery-only cost increase → VarianceOrder.
-   Commercial impact → ChangeOrder → new Quote → Contract Amendment →
    optional re-baseline.

------------------------------------------------------------------------

## 7. Invariants

-   No silent mutation of sold structure.
-   No automatic customer price changes.
-   Re-baseline explicit only.
-   All transitions evented.
