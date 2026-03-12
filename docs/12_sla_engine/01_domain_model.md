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
archived) - response_time_target_minutes (int, nullable — NULL for
tiered SLAs) - resolution_time_target_minutes (int, nullable — NULL
for tiered SLAs) - operating_hours_calendar_id (UUID, nullable — NULL
for tiered SLAs) - tier_transition_cap_percent (int, default 80) -
escalation_policy_json (json) - exclusions_json (json) -
service_credit_model_json (json) - created_at (datetime) - updated_at
(datetime)

Constraints: - (name, version_number) unique - Published versions
immutable

## Entity: SLATier (new)

Fields: - id (UUID, PK) - sla_id (UUID, FK) - priority (int, required)
- calendar_id (UUID, FK, required) - response_target_minutes (int,
required) - resolution_target_minutes (int, required) -
escalation_rules[] (per-tier)

Constraints: - (sla_id, priority) unique

For single-tier SLAs: the flat fields on SLA are used (backward
compatible). For tiered SLAs: the tiers[] array is authoritative and
flat fields are NULL.

See docs_27_sla_engine_08_tiered_sla_spec.md for complete tier model,
transition algorithm, and carry-forward rules.

## Contract Binding

-   RecurringServiceComponent stores sla_id + sla_version_number
-   On QuoteAccepted → SLA snapshotted into Contract
-   Snapshot includes full tiers[] array with embedded calendar snapshots
