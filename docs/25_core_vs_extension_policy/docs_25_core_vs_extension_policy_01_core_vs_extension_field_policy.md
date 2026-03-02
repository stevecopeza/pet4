# PET – Core vs Extension Field Policy

## Purpose
Defines the authoritative boundary between **Core (Structural) Fields** and **Extension (Malleable) Fields** across all PET aggregates.

This policy prevents architectural drift, protects invariants, and preserves long-term survivability.

---

# 1. Governing Principle

A field MUST be Core (built-in, schema-backed, non-malleable) if it:

1. Participates in a state machine
2. Is referenced by another aggregate
3. Is required for invariants
4. Is emitted in domain events
5. Is used in integrations
6. Is used in KPI derivation
7. Defines identity
8. Enforces referential integrity

All other fields MAY be Extension (malleable).

---

# 2. Core Field Registry (Per Aggregate)

The following fields are structural and may NOT be created, modified, or shadowed via the field creator system.

## 2.1 Customer (Core Fields)
- customer_id
- legal_name
- status
- created_at

## 2.2 Site (Core Fields)
- site_id
- customer_id
- name
- status
- created_at

## 2.3 Contact (Core Fields)
- contact_id
- first_name
- last_name
- email
- phone
- status
- created_at

Affiliations are structural and managed separately (not malleable).

## 2.4 Quote (Core Fields)
- quote_id
- customer_id
- status
- version_number
- total_value
- currency
- created_at
- accepted_at

## 2.5 Project (Core Fields)
- project_id
- customer_id
- originating_quote_id
- status
- sold_hours
- sold_value
- start_date
- end_date
- created_at

## 2.6 Ticket (Core Fields)
- ticket_id
- customer_id
- site_id
- status
- priority
- opened_at
- closed_at
- sla_id

## 2.7 Time Entry (Core Fields)
- time_entry_id
- employee_id
- task_id
- start
- end
- is_billable
- description
- status
- created_at
- archived_at

## 2.8 Knowledge Article (Core Fields)
- article_id
- title
- status
- created_by
- created_at
- last_updated_at

## 2.9 Lead (Core Fields)
- lead_id
- status
- source_type
- created_at

## 2.10 Employee (Core Fields)
- employee_id
- wp_user_id
- first_name
- last_name
- email
- status
- hire_date
- manager_id

## 2.11 Team (Core Fields)
- team_id
- name
- parent_team_id
- manager_id
- escalation_manager_id
- status
- visual_type
- visual_ref
- visual_version
- created_at

## 2.12 Visual Asset (Core Fields)
- asset_id
- entity_type
- entity_id
- file_path
- version
- created_at

---

# 3. Extension (Malleable) Fields

Extension fields:
- Are versioned via the Schema Management system
- May be filtered and queried
- May appear in dashboards
- May NOT define relationships
- May NOT replace core fields
- May NOT enforce invariants
- May NOT drive state machines
- May NOT influence billing logic

---

# 4. Hard Enforcement Rules

1. The field creator system MUST block creation of any field whose name:
   - Matches a core field
   - Case-insensitive matches a core field
   - Semantically overlaps (e.g., "customerid", "customer_id_ref")
   
   The system MUST hard-fail on name collision.

2. Core fields MUST NOT be removable.

3. Core fields MUST NOT be redefined via extension schema.

4. Extension fields MUST NOT be referenced in foreign key constraints.

5. APIs MUST expose core and extension fields distinctly.

---

# 5. Implementation Mandate

- Core fields are defined via migrations and domain entities.
- Extension fields are stored in extension value tables or JSON (separate storage).
- Domain aggregates must distinguish clearly between:
  - Core properties
  - Extension properties

---

# 6. Architectural Safeguard

If a new feature proposal requires a malleable field to:
- Influence state transitions
- Affect billing
- Drive integrations
- Alter invariants

Then it MUST be promoted to a Core Field via documented migration.

---

**Authority**: Normative

This document is binding. Violation of this boundary compromises PET’s architectural integrity.

