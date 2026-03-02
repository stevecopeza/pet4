# PET SLA Domain Model Specification v1.0

Status: Authoritative Domain: Commercial Lifecycle: Draft → Published →
Deprecated → Archived

## Core Principles

-   SLA is a commercial obligation, not configuration.
-   Published SLAs are immutable.
-   Contracts bind to SLA snapshot at acceptance.
-   KPI targets are role-bound.
-   SLA version drift is impossible.

## Entity: SLA

Fields: - id (UUID, PK) - name (varchar 255, required) - description
(text) - tier (enum: bronze, silver, gold, custom) - version_number
(int, required) - status (enum: draft, published, deprecated,
archived) - response_time_target_minutes (int, required) -
resolution_time_target_minutes (int, required) -
operating_hours_calendar_id (UUID, required) - escalation_policy_json
(json) - exclusions_json (json) - service_credit_model_json (json) -
created_at (datetime) - updated_at (datetime)

Constraints: - (name, version_number) unique - Published versions
immutable

## Contract Binding

-   RecurringServiceComponent stores sla_id + sla_version_number
-   On QuoteAccepted → SLA snapshotted into Contract
