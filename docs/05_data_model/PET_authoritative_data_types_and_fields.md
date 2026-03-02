# PET – Authoritative Data Types and Fields (from provided docs)

**Scope rule:** This list includes every data type (table) that is explicitly defined or explicitly required by the provided documentation pack. Where the docs name a table but do not define its columns, that is recorded as **“Fields not specified in docs”**.

---

## Commercial Engine

### `opportunities`

_Fields not specified in docs._

Sources: `08_implementation_blueprint/docs_08_implementation_blueprint_07_commercial_task_breakdown.md`

### `quotes`

| Field | Type / Notes |
|---|---|
| `valid_from` | date |
| `total_internal_cost` | decimal 14,2 |
| `customer_id` | UUID, FK |
| `id` | UUID, PK |
| `supersedes_quote_id` | UUID, FK, nullable |
| `total_sell_value` | decimal 14,2 |
| `status` | (enum: draft, pending_approval, approved, sent, accepted, |
| `currency` | char 3 |
| `total_margin` | decimal 14,2 |
| `version_number` | int |
| `created_by` | UUID |
| `created_at` | datetime |
| `quote_number` | varchar 50, unique |
| `valid_until` | date |
| `opportunity_id` | UUID, FK, required |
| ``quote_number`` | (varchar 50, unique) |
| `title` | varchar 255, required |
| `description` | text, required |
| `updated_at` | datetime |

Sources: `05_data_model/docs_05_data_model_06_complete_field_definitions.md`, `05_data_model/docs_05_data_model_quote_schema.md`

### `quote_components`

| Field | Type / Notes |
|---|---|
| `id` | UUID |
| `quote_id` | UUID |
| ``component_type`` | (enum: catalog, implementation, recurring, adjustment) |
| `sort_order` | int |
| `sell_value` | decimal 14,2 |
| `internal_cost` | decimal 14,2 |

Sources: `05_data_model/docs_05_data_model_06_complete_field_definitions.md`

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

Sources: `05_data_model/docs_05_data_model_quote_schema.md`

### `quote_milestones`

| Field | Type / Notes |
|---|---|
| `id` | UUID, PK |
| `quote_component_id` | UUID, FK |
| `name` | varchar 255 |
| `description` | text |
| `sequence` | int |

Sources: `05_data_model/docs_05_data_model_implementation_blueprint_schema.md`

### `quote_tasks`

| Field | Type / Notes |
|---|---|
| `internal_cost_snapshot` | decimal 14,2 |
| `id` | UUID, PK |
| `title` | varchar 255 |
| `Internal` | cost ceiling derived at sale. |
| `duration_hours` | decimal 8,2 |
| `description` | text |
| `role_catalog_item_id` | UUID, FK |
| `base_rate_snapshot` | decimal 12,2 |
| `sell_value_snapshot` | decimal 14,2 |
| `sequence` | int |
| `All` | rate values snapshotted. |
| `sell_rate_snapshot` | decimal 12,2 |
| `milestone_id` | UUID, FK |
| ``department_snapshot`` | (varchar 255) |

Sources: `05_data_model/docs_05_data_model_06_complete_field_definitions.md`, `05_data_model/docs_05_data_model_implementation_blueprint_schema.md`

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

Sources: `05_data_model/docs_05_data_model_recurring_services_schema.md`

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

Sources: `05_data_model/docs_05_data_model_contract_schema.md`

### `contract_payment_schedule`

| Field | Type / Notes |
|---|---|
| `id` | UUID, PK |
| `contract_id` | UUID, FK |
| `due_date` | date |
| `amount` | decimal 14,2 |
| `trigger_reference` | varchar 255 |
| `status` | enum: pending, invoiced, paid, overdue |

Sources: `05_data_model/docs_05_data_model_payment_plan_schema.md`

### `project_baselines`

| Field | Type / Notes |
|---|---|
| `id` | UUID, PK |
| `project_id` | UUID, FK |
| `version_number` | int |
| `source_contract_id` | UUID, FK |
| `internal_cost_ceiling` | decimal 14,2 |
| `created_at` | datetime |

Sources: `05_data_model/docs_05_data_model_baseline_variance_schema.md`

### `variance_orders`

| Field | Type / Notes |
|---|---|
| `id` | UUID, PK |
| `project_id` | UUID, FK |
| `amount` | decimal 14,2 |
| `reason` | text |
| `approved_by` | UUID |

Sources: `05_data_model/docs_05_data_model_baseline_variance_schema.md`

### `procurement_intents`

| Field | Type / Notes |
|---|---|
| `id` | UUID, PK |
| `supplier_id` | UUID, FK |
| `contract_id` | UUID, FK |
| `bundling_group_id` | UUID, nullable |
| `status` | enum: draft, confirmed, ordered, received |

Sources: `05_data_model/docs_05_data_model_procurement_intent_schema.md`

### `procurement_forecast`

_Fields not specified in docs._

Sources: `08_implementation_blueprint/docs_08_implementation_blueprint_07_commercial_task_breakdown.md`

### `cost_adjustments`

_Fields not specified in docs._

Sources: `08_implementation_blueprint/docs_08_implementation_blueprint_07_commercial_task_breakdown.md`

### `forecast_capacity`

_Fields not specified in docs._

Sources: `08_implementation_blueprint/docs_08_implementation_blueprint_07_commercial_task_breakdown.md`

### `quote_activity`

_Fields not specified in docs._

Sources: `08_implementation_blueprint/docs_08_implementation_blueprint_07_commercial_task_breakdown.md`

### `quote_payment_plan_rules`

| Field | Type / Notes |
|---|---|
| `id` | UUID, PK |
| `quote_id` | UUID, FK |
| `trigger_type` | enum |
| `configuration_json` | json |

Sources: `05_data_model/docs_05_data_model_payment_plan_schema.md`

### `service_catalog_items`

| Field | Type / Notes |
|---|---|
| `id` | UUID, PK |
| `name` | varchar 255, required |
| `department_id` | UUID, FK, required |
| `base_internal_rate` | decimal 12,2, required |
| `recommended_sell_rate` | decimal 12,2, required |
| `skill_level_id` | UUID, FK, optional |
| `status` | enum: active, archived |
| `created_at` | datetime |
| `updated_at` | datetime |

Sources: `05_data_model/docs_05_data_model_service_catalog_schema.md`


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

Sources: `26_work_domain/docs_26_work_domain_14_implementation_guide.md`

### `pet_skills`

| Field | Type / Notes |
|---|---|
| `id` | BIGINT, PK |
| `capability_id` | BIGINT, FK -> pet_capabilities |
| `name` | VARCHAR |
| `description` | TEXT |
| `status` | VARCHAR: 'active', 'archived' |
| `created_at` | DATETIME |

Sources: `26_work_domain/docs_26_work_domain_14_implementation_guide.md`

### `pet_proficiency_levels`

| Field | Type / Notes |
|---|---|
| `id` | BIGINT, PK |
| `level_number` | INT |
| `name` | VARCHAR |
| `definition` | TEXT |
| `created_at` | DATETIME |

Sources: `26_work_domain/docs_26_work_domain_14_implementation_guide.md`

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
| `created_at` | DATETIME |
| `published_at` | DATETIME, NULL |

Sources: `26_work_domain/docs_26_work_domain_14_implementation_guide.md`

### `pet_role_skills`

| Field | Type / Notes |
|---|---|
| `role_id` | BIGINT, FK -> pet_roles |
| `skill_id` | BIGINT, FK -> pet_skills |
| `min_proficiency_level` | INT |
| `importance_weight` | INT |
| ``primary` | key` (role_id, skill_id) |

Sources: `26_work_domain/docs_26_work_domain_14_implementation_guide.md`

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

Sources: `26_work_domain/docs_26_work_domain_14_implementation_guide.md`

### `pet_certifications`

| Field | Type / Notes |
|---|---|
| `id` | BIGINT, PK |
| `name` | VARCHAR |
| `issuing_body` | VARCHAR |
| `expiry_months` | INT |
| `status` | VARCHAR |

Sources: `26_work_domain/docs_26_work_domain_14_implementation_guide.md`

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

Sources: `26_work_domain/docs_26_work_domain_14_implementation_guide.md`

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

Sources: `26_work_domain/docs_26_work_domain_14_implementation_guide.md`

### `pet_employees`

_Fields not specified in docs._

Sources: `26_work_domain/docs_26_work_domain_14_implementation_guide.md`


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

Sources: `05_data_model/docs_05_data_model_teams.md`

### `pet_team_members`

| Field | Type / Notes |
|---|---|
| `id` | bigint PK |
| `team_id` | bigint FK |
| `employee_id` | bigint FK |
| `role` | enum(member, lead) |
| `assigned_at` | datetime |
| `removed_at` | datetime nullable |

Sources: `05_data_model/docs_05_data_model_teams.md`

### `pet_assets`

| Field | Type / Notes |
|---|---|
| `id` | bigint PK |
| `entity_type` | varchar |
| `entity_id` | bigint |
| `file_path` | varchar |
| `version` | int |
| `created_at` | datetime |

Sources: `05_data_model/docs_05_data_model_assets.md`


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

Sources: `25_schema_management_backend/docs_25_schema_management_backend_02_data_model_updates.md`

### `pet_migrations`

_Fields not specified in docs._

Sources: `05_data_model/docs_05_data_model_04_migration_execution_model.md`


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

Sources: `05_data_model/docs_05_data_model_03_domain_events_schema.md`


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
