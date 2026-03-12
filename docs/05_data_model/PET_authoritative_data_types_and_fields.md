# PET – Authoritative Data Types and Fields (from provided docs)

**Scope rule:** This list includes every data type (table) that is explicitly defined or explicitly required by the provided documentation pack. Where the docs name a table but do not define its columns, that is recorded as **“Fields not specified in docs”**.

---

## Commercial Engine

### `opportunities`

_Fields not specified in docs._

Sources: `15_implementation_blueprint/07_commercial_task_breakdown.md`

### `quotes`

| Field | Type / Notes |
|---|---|
| `valid_from` | date |
| `total_internal_cost` | decimal 14,2 |
| `customer_id` | UUID, FK |
| `id` | UUID, PK |
| `supersedes_quote_id` | UUID, FK, nullable |
| `total_sell_value` | decimal 14,2 |
| `status` | enum: draft, sent, accepted, rejected, archived |
| `currency` | char 3 |
| `total_margin` | decimal 14,2 |
| `version_number` | int |
| `created_by` | UUID |
| `created_at` | datetime |
| `quote_number` | varchar 50, unique |
| `valid_until` | date |
| `opportunity_id` | UUID, FK, nullable (Opportunity entity not yet implemented) |
| ``quote_number`` | (varchar 50, unique) |
| `title` | varchar 255, required |
| `description` | text, required |
| `contract_id` | BIGINT, FK → contracts, nullable (NEW — rate card resolution context) |
| `updated_at` | datetime |

Quote `contract_id` is set at quote creation. If non-null, rate card resolution tries contract-specific cards first, then global fallback. If null, only global rate cards are considered.

Sources: `05_data_model/06_complete_field_definitions.md`, `05_data_model/quote_schema.md`, `07_commercial/07_Products_Roles_ServiceTypes_and_RateCards_v2.md`

### `quote_components`

| Field | Type / Notes |
|---|---|
| `id` | UUID |
| `quote_id` | UUID |
| ``component_type`` | (enum: catalog, implementation, recurring, adjustment) |
| `sort_order` | int |
| `sell_value` | decimal 14,2 |
| `internal_cost` | decimal 14,2 |

Sources: `05_data_model/06_complete_field_definitions.md`

### `quote_catalog_items`

| Field | Type / Notes |
|---|---|
| `id` | UUID, PK |
| `component_id` | UUID, FK |
| `catalog_item_id` | UUID, FK, nullable |
| `type` | enum: product, service |
| `description` | varchar 255 |
| `sku` | varchar 50, nullable |
| `role_id` | int, nullable |
| `quantity` | decimal 12,2 |
| `unit_sell_price` | decimal 14,2 |
| `unit_internal_cost` | decimal 14,2 |
| `wbs_snapshot` | json |
| `**Products**:` | Must have `sku`. Cannot have `wbs_snapshot`. |
| `**Services**:` | Must have `role_id`. |

Sources: `05_data_model/quote_schema.md`

### `quote_milestones`

| Field | Type / Notes |
|---|---|
| `id` | UUID, PK |
| `quote_component_id` | UUID, FK |
| `name` | varchar 255 |
| `description` | text |
| `sequence` | int |

Sources: `05_data_model/implementation_blueprint_schema.md`

### `quote_tasks`

| Field | Type / Notes |
|---|---|
| `id` | UUID, PK |
| `milestone_id` | UUID, FK |
| `title` | varchar 255 |
| `description` | text |
| `duration_hours` | decimal 8,2 |
| `role_id` | BIGINT, FK → pet_roles |
| `service_type_id` | BIGINT, FK → pet_service_types (NEW) |
| `rate_card_id` | BIGINT, FK → pet_rate_cards, nullable (NEW — provenance) |
| `base_internal_rate` | decimal 12,2 (snapshot from Role) |
| `sell_rate` | decimal 12,2 (snapshot from RateCard) |
| `internal_cost_snapshot` | decimal 14,2 (derived: duration_hours × base_internal_rate) |
| `sell_value_snapshot` | decimal 14,2 (derived: duration_hours × sell_rate) |
| `sequence` | int |
| `department_snapshot` | varchar 255 |

All rate values snapshotted at line creation. Internal cost ceiling derived at sale. `role_catalog_item_id` renamed to `role_id`; now references `pet_roles` directly. `service_type_id` and `rate_card_id` are new fields for the refactored model.

Sources: `05_data_model/06_complete_field_definitions.md`, `05_data_model/implementation_blueprint_schema.md`, `07_commercial/07_Products_Roles_ServiceTypes_and_RateCards_v2.md`

### `quote_recurring_services`

| Field | Type / Notes |
|---|---|
| `id` | UUID, PK |
| `quote_component_id` | UUID, FK |
| `service_name` | varchar 255 |
| `sla_snapshot_json` | json |
| `cadence` | enum |
| `term_months` | int |
| `renewal_model` | enum |
| `sell_price` | decimal 14,2 |
| `internal_cost` | decimal 14,2 |

Sources: `05_data_model/recurring_services_schema.md`

### `contracts`

| Field | Type / Notes |
|---|---|
| `id` | UUID, PK |
| `originating_quote_id` | UUID, FK |
| `customer_id` | UUID, FK |
| `status` | enum: draft, active, suspended, terminated, completed |
| `effective_date` | date |
| `commercial_snapshot_json` | json |
| `sla_snapshot_json` | json |
| `baseline_id` | UUID, FK |

Sources: `05_data_model/contract_schema.md`

### `contract_payment_schedule`

| Field | Type / Notes |
|---|---|
| `id` | UUID, PK |
| `contract_id` | UUID, FK |
| `due_date` | date |
| `amount` | decimal 14,2 |
| `trigger_reference` | varchar 255 |
| `status` | enum: pending, invoiced, paid, overdue |

Sources: `05_data_model/payment_plan_schema.md`

### `project_baselines`

| Field | Type / Notes |
|---|---|
| `id` | UUID, PK |
| `project_id` | UUID, FK |
| `version_number` | int |
| `source_contract_id` | UUID, FK |
| `internal_cost_ceiling` | decimal 14,2 |
| `created_at` | datetime |

Sources: `05_data_model/baseline_variance_schema.md`

### `variance_orders`

| Field | Type / Notes |
|---|---|
| `id` | UUID, PK |
| `project_id` | UUID, FK |
| `amount` | decimal 14,2 |
| `reason` | text |
| `approved_by` | UUID |

Sources: `05_data_model/baseline_variance_schema.md`

### `procurement_intents`

| Field | Type / Notes |
|---|---|
| `id` | UUID, PK |
| `supplier_id` | UUID, FK |
| `contract_id` | UUID, FK |
| `bundling_group_id` | UUID, nullable |
| `status` | enum: draft, confirmed, ordered, received |

Sources: `05_data_model/procurement_intent_schema.md`

### `procurement_forecast`

_Fields not specified in docs._

Sources: `15_implementation_blueprint/07_commercial_task_breakdown.md`

### `cost_adjustments`

_Fields not specified in docs._

Sources: `15_implementation_blueprint/07_commercial_task_breakdown.md`

### `forecast_capacity`

_Fields not specified in docs._

Sources: `15_implementation_blueprint/07_commercial_task_breakdown.md`

### `quote_activity`

_Fields not specified in docs._

Sources: `15_implementation_blueprint/07_commercial_task_breakdown.md`

### `quote_payment_plan_rules`

| Field | Type / Notes |
|---|---|
| `id` | UUID, PK |
| `quote_id` | UUID, FK |
| `trigger_type` | enum |
| `configuration_json` | json |

Sources: `05_data_model/payment_plan_schema.md`

### `service_catalog_items`

> **⚠️ SUPERSEDED** — This entity has been removed. Service economics are now modelled via `pet_roles` (internal cost), `pet_service_types` (classification), and `pet_rate_cards` (sell pricing). See `07_commercial/07_Products_Roles_ServiceTypes_and_RateCards_v2.md`.

_Table retained for legacy read access only. No new rows should be created._

Sources: `05_data_model/service_catalog_schema.md` (historical)

### `pet_catalog_products` (NEW)

| Field | Type / Notes |
|---|---|
| `id` | BIGINT, PK, auto-increment |
| `sku` | VARCHAR(50), nullable, unique when non-null |
| `name` | VARCHAR(255), required |
| `description` | TEXT, nullable |
| `category` | VARCHAR(100), nullable |
| `unit_price` | DECIMAL(14,2), required |
| `unit_cost` | DECIMAL(14,2), required |
| `status` | ENUM('active', 'archived'), default 'active' |
| `created_at` | DATETIME, immutable |
| `updated_at` | DATETIME |

Products only — no labour/service items. Replaces product-type rows from `pet_catalog_items`.

Sources: `07_commercial/07_Products_Roles_ServiceTypes_and_RateCards_v2.md`

### `pet_service_types` (NEW)

| Field | Type / Notes |
|---|---|
| `id` | BIGINT, PK, auto-increment |
| `name` | VARCHAR(255), required, unique |
| `description` | TEXT, nullable |
| `status` | ENUM('active', 'archived'), default 'active' |
| `created_at` | DATETIME, immutable |
| `updated_at` | DATETIME |

Classification of labour categories (Consulting, Support, Training, etc.).

Sources: `07_commercial/07_Products_Roles_ServiceTypes_and_RateCards_v2.md`

### `pet_rate_cards` (NEW)

| Field | Type / Notes |
|---|---|
| `id` | BIGINT, PK, auto-increment |
| `role_id` | BIGINT, FK → pet_roles, required |
| `service_type_id` | BIGINT, FK → pet_service_types, required |
| `sell_rate` | DECIMAL(12,2), required |
| `contract_id` | BIGINT, FK → contracts, nullable (null = global rate) |
| `valid_from` | DATE, nullable (null = open start) |
| `valid_to` | DATE, nullable (null = open end / no expiry) |
| `status` | ENUM('active', 'archived'), default 'active' |
| `created_at` | DATETIME, immutable |
| `updated_at` | DATETIME |

Composite index on `(role_id, service_type_id, contract_id, valid_from)`. No overlapping date ranges per tuple.

Sources: `07_commercial/07_Products_Roles_ServiceTypes_and_RateCards_v2.md`


---

## Work Domain (People, Skills, Roles, Certifications)

### `pet_capabilities`

| Field | Type / Notes |
|---|---|
| `id` | BIGINT, PK |
| `name` | VARCHAR |
| `description` | TEXT |
| `parent_id` | BIGINT, NULL |
| `status` | VARCHAR: 'active', 'archived' |
| `created_at` | DATETIME |

Sources: `34_work_domain/14_implementation_guide.md`

### `pet_skills`

| Field | Type / Notes |
|---|---|
| `id` | BIGINT, PK |
| `capability_id` | BIGINT, FK -> pet_capabilities |
| `name` | VARCHAR |
| `description` | TEXT |
| `status` | VARCHAR: 'active', 'archived' |
| `created_at` | DATETIME |

Sources: `34_work_domain/14_implementation_guide.md`

### `pet_proficiency_levels`

| Field | Type / Notes |
|---|---|
| `id` | BIGINT, PK |
| `level_number` | INT |
| `name` | VARCHAR |
| `definition` | TEXT |
| `created_at` | DATETIME |

Sources: `34_work_domain/14_implementation_guide.md`

### `pet_roles`

| Field | Type / Notes |
|---|---|
| `id` | BIGINT, PK |
| `name` | VARCHAR |
| `version` | INT |
| `status` | VARCHAR: 'draft', 'published', 'deprecated', 'archived' |
| `level` | VARCHAR |
| `description` | TEXT |
| `success_criteria` | TEXT |
| `base_internal_rate` | DECIMAL(12,2), NULL (required for published roles) |
| `created_at` | DATETIME |
| `published_at` | DATETIME, NULL |

`base_internal_rate` represents the hourly internal cost of a person in this role. Must be > 0 for published roles. Roles do NOT carry sell rates.

Sources: `34_work_domain/14_implementation_guide.md`, `07_commercial/07_Products_Roles_ServiceTypes_and_RateCards_v2.md`

### `pet_role_skills`

| Field | Type / Notes |
|---|---|
| `role_id` | BIGINT, FK -> pet_roles |
| `skill_id` | BIGINT, FK -> pet_skills |
| `min_proficiency_level` | INT |
| `importance_weight` | INT |
| ``primary` | key` (role_id, skill_id) |

Sources: `34_work_domain/14_implementation_guide.md`

### `pet_person_skills`

| Field | Type / Notes |
|---|---|
| `id` | BIGINT, PK |
| `employee_id` | BIGINT, FK -> pet_employees.id |
| `skill_id` | BIGINT, FK -> pet_skills.id |
| `review_cycle_id` | BIGINT, NULL |
| `self_rating` | INT |
| `manager_rating` | INT |
| `effective_date` | DATE |
| `created_at` | DATETIME |

Sources: `34_work_domain/14_implementation_guide.md`

### `pet_certifications`

| Field | Type / Notes |
|---|---|
| `id` | BIGINT, PK |
| `name` | VARCHAR |
| `issuing_body` | VARCHAR |
| `expiry_months` | INT |
| `status` | VARCHAR |

Sources: `34_work_domain/14_implementation_guide.md`

### `pet_person_certifications`

| Field | Type / Notes |
|---|---|
| `id` | BIGINT, PK |
| `employee_id` | BIGINT, FK -> pet_employees.id |
| `certification_id` | BIGINT, FK -> pet_certifications.id |
| `obtained_date` | DATE |
| `expiry_date` | DATE, NULL |
| `evidence_url` | VARCHAR, NULL |
| `status` | VARCHAR: 'valid', 'expired' |

Sources: `34_work_domain/14_implementation_guide.md`

### `pet_person_role_assignments`

| Field | Type / Notes |
|---|---|
| `id` | BIGINT, PK |
| `employee_id` | BIGINT, FK -> pet_employees.id |
| `role_id` | BIGINT, FK -> pet_roles.id |
| `start_date` | DATE |
| `end_date` | DATE, NULL |
| `allocation_pct` | INT |
| `status` | VARCHAR: 'active', 'completed' |
| `created_at` | DATETIME |

Sources: `34_work_domain/14_implementation_guide.md`

### `pet_employees`

_Fields not specified in docs._

Sources: `34_work_domain/14_implementation_guide.md`


---

## Teams and Visual Assets

### `pet_teams`

| Field | Type / Notes |
|---|---|
| `id` | bigint PK Immutable |
| `name` | varchar Required, unique per parent |
| `parent_team_id` | bigint nullable Self-reference |
| `manager_id` | bigint nullable FK to pet_employees |
| `escalation_manager_id` | bigint nullable FK to pet_employees |
| `status` | enum(active, archived) No deletion |
| `visual_type` | enum(system, upload) Core |
| `visual_ref` | varchar Core |
| `visual_version` | int Core |
| `visual_updated_at` | datetime |
| `created_at` | datetime Immutable |
| `archived_at` | datetime nullable |

Sources: `05_data_model/teams.md`

### `pet_team_members`

| Field | Type / Notes |
|---|---|
| `id` | bigint PK |
| `team_id` | bigint FK |
| `employee_id` | bigint FK |
| `role` | enum(member, lead) |
| `assigned_at` | datetime |
| `removed_at` | datetime nullable |

Sources: `05_data_model/teams.md`

### `pet_assets`

| Field | Type / Notes |
|---|---|
| `id` | bigint PK |
| `entity_type` | varchar |
| `entity_id` | bigint |
| `file_path` | varchar |
| `version` | int |
| `created_at` | datetime |

Sources: `05_data_model/assets.md`


---

## Schema Management and Migrations

### `pet_schema_definitions`

| Field | Type / Notes |
|---|---|
| `id` | PK |
| ``entity_type`` |  |
| ``version`` |  |
| ``schema_json`` |  |
| ``created_at`` |  |
| ``created_by_employee_id`` |  |
| `status` | varchar(20), Nullable: No |
| `published_at` | datetime, Nullable: Yes |
| `published_by` | bigint, Nullable: Yes |

Sources: `32_schema_management/02_data_model_updates.md`

### `pet_migrations`

_Fields not specified in docs._

Sources: `05_data_model/04_migration_execution_model.md`


---

## Event Backbone

### `domain_events`

| Field | Type / Notes |
|---|---|
| `id` | PK |
| `event_type` | string, namespaced |
| `occurred_at` | timestamp |
| `recorded_at` | timestamp |
| `actor_employee_id` | nullable for system events |
| `context_type` | string |
| `context_id` | FK reference id |
| `payload` | JSON |
| `schema_version` | int |
| ``lead.created`` |  |
| ``lead.qualified`` |  |
| ``opportunity.classified`` |  |
| ``quote.sent`` |  |
| ``quote.accepted`` |  |
| ``project.variance_detected`` |  |
| ``time_entry.submitted`` |  |
| ``sla.breached`` |  |

Sources: `05_data_model/03_domain_events_schema.md`


---

## SLA Automation (Pre‑Demo Hardening)

### `sla`

_Fields not specified in docs._

Sources: `08_implementation_blueprint/pre_demo_execution/04_hardening_addendum.md`

### `sla_versions`

_Fields not specified in docs._

Sources: `08_implementation_blueprint/pre_demo_execution/04_hardening_addendum.md`

### `sla_escalation_rules`

_Fields not specified in docs._

Sources: `08_implementation_blueprint/pre_demo_execution/04_hardening_addendum.md`

### `ticket_sla_bindings`

_Fields not specified in docs._

Sources: `08_implementation_blueprint/pre_demo_execution/04_hardening_addendum.md`

### `sla_clock_state`

| Field | Type / Notes |
|---|---|
| `ticket_id` | FK |
| `warning_at` | datetime |
| `breach_at` | datetime |
| `paused_flag` | boolean |
| `escalation_stage` | int |
| `last_evaluated_at` | datetime |
| `last_event_dispatched` | enum: none, warning, breached |

Sources: `08_implementation_blueprint/pre_demo_execution/04_hardening_addendum.md`


---
