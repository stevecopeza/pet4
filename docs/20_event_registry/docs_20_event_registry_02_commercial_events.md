# Domain Event Contracts

## QuoteAccepted

-   quote_id
-   version
-   customer_id
-   total_sell_value
-   total_internal_cost
-   timestamp

## ContractCreated

-   contract_id
-   originating_quote_id
-   baseline_id
-   effective_date

## VarianceOrderCreated

-   project_id
-   amount
-   reason
-   timestamp

## ChangeOrderCreated

-   original_contract_id
-   new_quote_id
-   requires_approval
