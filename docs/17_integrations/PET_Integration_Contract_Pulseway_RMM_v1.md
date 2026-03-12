# PET Lifecycle Integration Contract --- Pulseway RMM v1.0

Date: 2026-03-03
Status: Design / integration spec (no code)
Authority: Normative (once approved)

## Purpose

Define the integration contract between PET and Pulseway RMM, covering
render/creation/mutation rules, prohibited behaviours, transport,
idempotency, and stress-test scenarios.

**Scope now (A + B):**
- **A)** Monitoring-only sync — read-only ingestion of devices, notifications, org structure
- **B)** Ticket auto-creation — governed, idempotent ticket creation from Pulseway notifications/alerts

**Captured for later (C + D):**
- **C)** Bi-directional automation control (run scripts/tasks/workflows)
- **D)** Full RMM ↔ PET orchestration (asset + ticket + automation + advisory loops)

This document is REQUIRED prior to implementation.

------------------------------------------------------------------------

## Authority Boundary

- PET owns operational truth (tickets, time, SLA, commercial context)
- Pulseway is an **external signal source**, never a system of record for PET
- Inbound Pulseway data must be converted into explicit PET commands/events before any domain state changes
- Pulseway never mutates PET state directly
- PET ticket lifecycle remains PET-governed; Pulseway does not close/resolve/edit PET tickets

------------------------------------------------------------------------

## 1) Multi-Org Design

PET is single-tenant per WordPress install, but the integration must
support connecting to **multiple Pulseway organizations** (e.g. an MSP
managing several client Pulseway accounts from one PET instance).

Rules:
- Each Pulseway connection is identified by a unique `integration_id`
- Credentials (Token ID / Token Secret) are stored per integration, not globally
- All ingested records carry the `integration_id` discriminator
- Mapping tables (Pulseway org/site/group → PET company/site) are scoped per integration
- Initial implementation may connect one org; schema must not preclude multiple

------------------------------------------------------------------------

## 2) Transport

### Primary: Polling

Polling is the primary transport until outbound notification webhooks
are confirmed available in the target Pulseway plan.

- Scheduled via **system cron** (not WP-Cron) for reliability
- PET exposes a CLI/WP-CLI command that system cron invokes
- Polling interval: configurable per integration, default 5 minutes
- Uses cursor/time-window to avoid re-processing: `last_poll_cursor` stored per integration
- Must be rate-limit aware (see §8)

### Secondary: Webhook (when available)

If/when Pulseway outbound notification webhooks are confirmed:
- PET exposes: `POST /wp-json/pet/v1/integrations/pulseway/webhook`
- Verify: HTTPS + shared secret (exact mechanism TBD per Pulseway implementation)
- On receipt: persist immutable notification record (idempotent), then optionally enqueue device refresh
- Webhook and polling may run concurrently; idempotency ensures no duplicates

------------------------------------------------------------------------

## 3) Feature Flags & Configuration

### Feature flags (pet_settings table)

- `pet_pulseway_enabled` — master kill switch; all ingestion and ticket creation disabled when false
- `pet_pulseway_ticket_creation_enabled` — governs B only; A operates independently

### Integration configuration (new table: wp_pet_pulseway_integrations)

Per-integration record storing:
- `id` BIGINT PK
- `uuid` CHAR(36) UNIQUE
- `label` VARCHAR(128) — human-readable name
- `api_base_url` VARCHAR(255) — default `https://api.pulseway.com/v3/`
- `token_id_encrypted` TEXT — encrypted at rest
- `token_secret_encrypted` TEXT — encrypted at rest
- `poll_interval_seconds` INT DEFAULT 300
- `last_poll_at` DATETIME NULL
- `last_poll_cursor` TEXT NULL — opaque cursor/timestamp for incremental polling
- `last_success_at` DATETIME NULL
- `last_error_at` DATETIME NULL
- `last_error_message` TEXT NULL
- `consecutive_failures` INT DEFAULT 0
- `is_active` TINYINT(1) DEFAULT 1
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL
- `archived_at` DATETIME NULL

------------------------------------------------------------------------

## 4) Data Model — New Tables

All tables use `wp_pet_` prefix per existing convention.
All tables include standard audit columns (`created_at`, `archived_at`).
All tables use BIGINT AUTO_INCREMENT primary keys.

### 4.1) wp_pet_pulseway_integrations

Defined in §3 above.

### 4.2) wp_pet_external_notifications

Immutable log of all ingested notifications from any external system.

- `id` BIGINT PK
- `integration_id` BIGINT NOT NULL — FK to wp_pet_pulseway_integrations
- `external_system` VARCHAR(32) NOT NULL DEFAULT 'pulseway'
- `external_notification_id` VARCHAR(128) NULL — Pulseway's own ID if stable
- `dedupe_key` VARCHAR(128) NOT NULL — idempotency key (see §6.3)
- `device_external_id` VARCHAR(128) NULL — Pulseway device identifier
- `severity` VARCHAR(32) NULL — critical/warning/info as provided
- `category` VARCHAR(64) NULL — AV, disk, offline, patch, etc.
- `title` VARCHAR(512) NOT NULL
- `message` TEXT NULL
- `occurred_at` DATETIME NULL — when Pulseway says it happened
- `received_at` DATETIME NOT NULL — when PET ingested it
- `raw_payload_json` LONGTEXT NULL — full original payload, immutable
- `routing_status` VARCHAR(32) NOT NULL DEFAULT 'pending' — pending / routed / unroutable
- `created_at` DATETIME NOT NULL
- UNIQUE KEY `uniq_dedupe` (`external_system`, `dedupe_key`)

No updates. No deletes. Append-only.

### 4.3) wp_pet_external_assets

Read-side mirror of Pulseway device inventory. Not operational truth.

- `id` BIGINT PK
- `integration_id` BIGINT NOT NULL
- `external_system` VARCHAR(32) NOT NULL DEFAULT 'pulseway'
- `external_asset_id` VARCHAR(128) NOT NULL — Pulseway device ID
- `external_org_id` VARCHAR(128) NULL
- `external_site_id` VARCHAR(128) NULL
- `external_group_id` VARCHAR(128) NULL
- `display_name` VARCHAR(255) NULL
- `platform` VARCHAR(64) NULL
- `status` VARCHAR(32) NULL
- `last_seen_at` DATETIME NULL
- `raw_snapshot_json` LONGTEXT NULL — latest device snapshot
- `snapshot_updated_at` DATETIME NULL
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL
- `archived_at` DATETIME NULL
- UNIQUE KEY `uniq_asset` (`integration_id`, `external_system`, `external_asset_id`)

This table IS updated (snapshot refreshes). It is a read-side mirror only.

### 4.4) wp_pet_pulseway_org_mappings

Maps Pulseway organizational units to PET entities.

- `id` BIGINT PK
- `integration_id` BIGINT NOT NULL
- `pulseway_org_id` VARCHAR(128) NULL
- `pulseway_site_id` VARCHAR(128) NULL
- `pulseway_group_id` VARCHAR(128) NULL
- `pet_customer_id` BIGINT NULL — FK to wp_pet_customers
- `pet_site_id` BIGINT NULL — FK to wp_pet_sites
- `pet_team_id` BIGINT NULL — FK to wp_pet_teams (default assignment)
- `is_active` TINYINT(1) DEFAULT 1
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL
- `archived_at` DATETIME NULL
- UNIQUE KEY `uniq_mapping` (`integration_id`, `pulseway_org_id`, `pulseway_site_id`, `pulseway_group_id`)

Changes are additive; old mappings are archived, never deleted.

### 4.5) wp_pet_pulseway_ticket_rules

Governs which notifications create tickets and how (B only).

- `id` BIGINT PK
- `integration_id` BIGINT NOT NULL
- `rule_name` VARCHAR(128) NOT NULL
- `is_active` TINYINT(1) DEFAULT 1
- `match_severity` VARCHAR(255) NULL — comma-separated or JSON array; NULL = match all
- `match_category` VARCHAR(255) NULL — comma-separated or JSON array; NULL = match all
- `match_pulseway_org_id` VARCHAR(128) NULL — NULL = match all
- `match_pulseway_site_id` VARCHAR(128) NULL
- `match_pulseway_group_id` VARCHAR(128) NULL
- `output_ticket_kind` VARCHAR(50) NOT NULL DEFAULT 'incident'
- `output_priority` VARCHAR(32) NOT NULL DEFAULT 'medium'
- `output_queue_id` VARCHAR(64) NULL — PET team/queue for assignment
- `output_owner_user_id` VARCHAR(64) NULL
- `output_billing_context_type` VARCHAR(32) DEFAULT 'adhoc'
- `output_tags_json` TEXT NULL
- `dedupe_window_minutes` INT DEFAULT 60 — suppress duplicate tickets within window
- `quiet_hours_start` TIME NULL
- `quiet_hours_end` TIME NULL
- `sort_order` INT DEFAULT 0 — evaluation priority (first match wins)
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL
- `archived_at` DATETIME NULL

------------------------------------------------------------------------

## 5) Data Model — Existing Tables Used

### 5.1) wp_pet_tickets

Tickets created from Pulseway notifications use the existing ticket
entity and `CreateTicketCommand`. No schema changes required.

Fields leveraged on creation:
- `customer_id` — resolved via org mapping (§4.4)
- `site_id` — resolved via org mapping
- `subject` — `"[Pulseway] <device_name> — <alert_title>"`
- `description` — full alert message + external IDs + timestamps + raw context
- `priority` — from matched rule (§4.5)
- `status` — 'new'
- `primary_container` — 'support'
- `ticket_kind` — from matched rule (default 'incident')
- `intake_source` — 'pulseway'
- `queue_id` — from matched rule or org mapping default
- `billing_context_type` — from matched rule (default 'adhoc')
- `malleable_data` — includes `pulseway_notification_id`, `pulseway_device_id`, `integration_id`

This ensures all existing downstream behaviour (SLA assignment, work
projection, helpdesk visibility, billing context, department routing)
works without modification.

### 5.2) wp_pet_ticket_links

Used for external dedupe tracking. One row per Pulseway-originated ticket.

- `link_type` = 'external'
- `linked_id` = dedupe key from wp_pet_external_notifications
- `ticket_id` = the created PET ticket

Uniqueness on `(link_type, linked_id)` prevents duplicate ticket creation.
If insert conflicts → treat as success, return existing ticket_id.

### 5.3) wp_pet_external_mappings

Existing table. Used to map PET ticket IDs to Pulseway notification IDs
for traceability (same pattern as QuickBooks invoice mapping).

### 5.4) wp_pet_domain_events

Standard event recording. New event types registered (see §9).

------------------------------------------------------------------------

## 6) Option A — Monitoring-Only Sync

### 6.1) Goal

Make Pulseway operational data visible in PET without changing PET
operational truth:
- Device inventory (what endpoints exist, health, last-seen)
- Notification stream (alerts, warnings, informational)
- Organizational structure mapping

### 6.2) Non-goals (A)

- No automatic ticket creation (that is B)
- No remote actions (C)
- No governance/advisory derivations that change operational state

### 6.3) Ingestion — Notifications

**Source:** Pulseway REST API v3 — `GET /notifications` and `GET /devices/{id}/notifications`

**Idempotency key (dedupe_key):**
- If Pulseway provides a stable notification ID → use `pulseway:{notification_id}`
- Otherwise → `sha256(integration_id + device_id + type + occurred_at + message)`
- Stored as `dedupe_key` in `wp_pet_external_notifications`
- INSERT with UNIQUE constraint; duplicate = no-op

**Process (poll cycle):**
1. Read `last_poll_cursor` for integration
2. Fetch notifications since cursor (paginated, rate-limit aware)
3. For each notification:
   a. Compute dedupe_key
   b. INSERT IGNORE into `wp_pet_external_notifications`
   c. Determine routing_status:
      - If org mapping exists for device's org/site/group → 'routed'
      - If no mapping → 'unroutable' (persisted anyway; no data loss)
4. Update `last_poll_cursor`, `last_success_at`
5. On failure: increment `consecutive_failures`, record error, apply backoff

### 6.4) Ingestion — Device Snapshots

**Source:** Pulseway REST API v3 — `GET /devices` and `GET /devices/{id}`

**Process:**
- Scheduled refresh (less frequent than notifications; default every 15 minutes)
- UPSERT into `wp_pet_external_assets` keyed on `(integration_id, external_system, external_asset_id)`
- Store full response as `raw_snapshot_json` for forward compatibility
- Extract key fields: display_name, platform, status, last_seen_at

### 6.5) Ingestion — Org Structure

**Source:** Pulseway REST API v3 — Organizations / Sites / Groups endpoints

**Process:**
- Manual or infrequent scheduled sync
- Presents available Pulseway orgs/sites/groups in admin UI for mapping to PET customers/sites
- No auto-creation of PET customers/sites from Pulseway data

------------------------------------------------------------------------

## 7) Option B — Ticket Auto-Creation

### 7.1) Goal

Convert Pulseway notifications into PET tickets in a controlled,
governed, idempotent manner. No notification creates a ticket unless
an explicit rule matches.

### 7.2) Prerequisites

- Option A notification ingestion must be operational (B depends on persisted notification records)
- `pet_pulseway_enabled` = true
- `pet_pulseway_ticket_creation_enabled` = true
- At least one active ticket rule exists (§4.5)
- Org mapping exists for the notification's source (or a fallback mapping is configured)

### 7.3) Trigger Flow

1. Notification is persisted (A flow, §6.3)
2. If `pet_pulseway_ticket_creation_enabled` = false → stop
3. If notification `routing_status` = 'unroutable' → stop (no mapping, no ticket)
4. Evaluate ticket rules in `sort_order`:
   - Match severity, category, org/site/group filters
   - First matching rule wins
   - No match → no ticket
5. Check dedupe window:
   - Query `wp_pet_ticket_links` for `link_type='external'` with same `dedupe_key`
   - If found and created within rule's `dedupe_window_minutes` → no-op (return existing ticket_id)
6. Check quiet hours (if configured on rule):
   - If within quiet hours → defer (re-evaluate on next poll cycle)
7. Resolve PET context via org mapping:
   - `customer_id` from `wp_pet_pulseway_org_mappings.pet_customer_id`
   - `site_id` from mapping
   - `queue_id` from rule or mapping default
8. Build and dispatch `CreateTicketCommand`:
   - `customerId` = resolved customer
   - `siteId` = resolved site
   - `subject` = `"[Pulseway] {device_name} — {alert_title}"`
   - `description` = full message + external IDs + severity + link to Pulseway (if URL available)
   - `priority` = from rule
   - `malleableData` includes:
     - `ticket_mode` = 'support'
     - `intake_source` = 'pulseway'
     - `category` = notification category
     - `pulseway_notification_dedupe_key` = dedupe_key
     - `pulseway_device_id` = device external ID
     - `pulseway_integration_id` = integration ID
   - Ticket kind, billing context, queue from rule
9. On success:
   - INSERT into `wp_pet_ticket_links` (`link_type='external'`, `linked_id=dedupe_key`)
   - INSERT into `wp_pet_external_mappings` (system='pulseway', entity_type='notification_ticket')
   - Update notification `routing_status` → 'routed'
   - Domain event: `ExternalTicketCreatedEvent`
10. On failure:
    - Log error against the notification record
    - Do not mark as routed
    - Will be retried on next poll cycle (notification remains in 'pending' status)

### 7.4) Ticket Lifecycle (B)

- **Creation**: as above, via existing `CreateTicketCommand`
- **All subsequent mutation**: PET-governed only
- Pulseway does not close, resolve, reassign, or edit PET tickets
- Later phases (D) may support reconciliation, but never silent mutation

------------------------------------------------------------------------

## 8) Rate Limiting & Resilience

### Pulseway API rate limits (from Pulseway Help Center, confirmed)

General API:
- 100 requests per 5 seconds
- 1000 requests per 1 minute

GET Systems/Notifications:
- 3600 requests per 1 hour
- 86400 requests per 1 day

Publish Instance (not used for A/B, noted for future):
- 60 requests per 1 minute

### PET-side enforcement

- Shared per-integration throttle respecting the above limits
- Track request count per time window per integration
- Exponential backoff on 429 / 5xx responses:
  - Attempt 1: +1 minute
  - Attempt 2: +5 minutes
  - Attempt 3: +30 minutes
  - Attempt 4: +2 hours
  - Attempt 5: +6 hours
  - After 6 failures: mark integration as `degraded`, alert admin, stop polling until manual reset
- Circuit breaker: if `consecutive_failures` >= 6, halt polling for that integration
- Health stored per integration: `last_success_at`, `last_error_at`, `last_error_message`, `consecutive_failures`

------------------------------------------------------------------------

## 9) Event Registry Additions

### New event types

- `pulseway.notification_ingested`
  - Aggregate: ExternalNotification
  - Trigger: Notification persisted from Pulseway
  - Payload: notification_id, integration_id, dedupe_key, severity, device_external_id

- `pulseway.ticket_created_from_notification`
  - Aggregate: Ticket
  - Trigger: Ticket auto-created from Pulseway notification via rule match
  - Payload: ticket_id, notification_id, integration_id, rule_id, dedupe_key

- `pulseway.notification_unroutable`
  - Aggregate: ExternalNotification
  - Trigger: Notification persisted but no org mapping exists
  - Payload: notification_id, integration_id, device_external_id

- `pulseway.integration_health_changed`
  - Aggregate: PulsewayIntegration
  - Trigger: Integration enters/exits degraded state
  - Payload: integration_id, previous_status, new_status, consecutive_failures

------------------------------------------------------------------------

## 10) Security Model

- Token ID / Token Secret stored encrypted at rest (WordPress encryption API or dedicated secrets table)
- Per-integration credentials; never shared across integrations
- Secrets never logged; HTTP Authorization headers redacted in error logs
- Webhook endpoint (if enabled) requires:
  - HTTPS only
  - Shared secret verification (exact header/algo TBD per Pulseway implementation)
- API access scoped to read-only endpoints for A+B (devices, notifications, orgs/sites/groups)
- No automation endpoints (C) accessed until explicitly approved and implemented

------------------------------------------------------------------------

## 11) Operational Model

### 11.1) Cron Architecture

System cron (not WP-Cron) for reliability:
- Entry point: WP-CLI command `wp pet pulseway:poll`
- System cron calls this every N minutes (configurable, default 5)
- Command iterates active integrations, runs poll cycle for each
- Device snapshot refresh: separate command `wp pet pulseway:sync-devices` at lower frequency

### 11.2) Observability

Integration health page (admin UI):
- Per-integration: last_success_at, last_error_at, error message, consecutive_failures
- Notification backlog count (pending / routed / unroutable)
- Rate limit throttle status
- Ticket creation summary (last 24h: created / deduped / skipped)

Audit log entries for:
- Notification ingested
- Notification unroutable
- Ticket created / deduped
- Integration health state change
- Credential rotation

### 11.3) Failure Modes Explicitly Handled

- **Webhook delivery retries / duplicates**: dedupe_key prevents duplicate records
- **Out-of-order events**: occurred_at preserved from source; PET ordering is by received_at
- **Missing org mapping**: notification persisted with routing_status='unroutable'; no ticket; no data loss
- **Pulseway API partial outage**: cached device snapshots remain; notifications deferred; backoff engaged
- **Credential expiry/rotation**: integration marked degraded; admin notified; no silent data loss
- **Database transaction failure during ticket creation**: notification remains 'pending'; retried next cycle

------------------------------------------------------------------------

## 12) Render Rules

### Monitoring Dashboard (A) MUST render when

- `pet_pulseway_enabled` = true
- Viewer authenticated with admin or manager scope
- At least one active integration exists

### Monitoring Dashboard MUST NOT render when

- `pet_pulseway_enabled` = false
- Viewer lacks scope
- Schema prerequisites missing → fail fast with clear admin error, not fatal

### Ticket Detail (B) MUST show external origin when

- Ticket has `intake_source` = 'pulseway'
- Display: "External Origin: Pulseway", linked notification record(s), device snapshot panel

### Admin Configuration MUST render when

- Viewer is admin
- `pet_pulseway_enabled` = true (or admin needs access to enable it)

------------------------------------------------------------------------

## 13) Creation Rules

### External notification records MUST be created when

- `pet_pulseway_enabled` = true
- Poll cycle or webhook delivers a notification
- Idempotent insert (dedupe_key uniqueness)

### External notification records MUST NOT be created when

- `pet_pulseway_enabled` = false
- Rendering any UI (no read-side creation)

### Tickets from notifications MUST be created when

- `pet_pulseway_enabled` = true AND `pet_pulseway_ticket_creation_enabled` = true
- A matching rule exists (§7.3 step 4)
- Org mapping resolves to a PET customer
- Dedupe window has passed (no existing ticket for same dedupe_key within window)
- Not in quiet hours

### Tickets from notifications MUST NOT be created when

- Either feature flag is false
- No matching rule
- No org mapping (notification marked 'unroutable' instead)
- Dedupe window active
- Within quiet hours for matched rule

------------------------------------------------------------------------

## 14) Mutation Rules

### External notification records

- Immutable after creation; no updates, no deletes
- Exception: `routing_status` transitions: pending → routed, pending → unroutable
- These are the only allowed mutations and are forward-only

### External asset records

- Updated on device snapshot refresh (read-side mirror, not operational truth)
- `archived_at` set if device disappears from Pulseway; never hard-deleted

### Tickets created from notifications

- All mutations governed by PET ticket lifecycle (existing TicketStatus transitions)
- No external system may mutate ticket state
- `intake_source` field is immutable after creation

### Org mappings

- Editable by admin
- Old mappings archived, never deleted
- Changes do not retroactively re-route previously ingested notifications

### Ticket rules

- Editable by admin
- Changes apply to future notifications only
- Existing tickets are not modified when rules change

------------------------------------------------------------------------

## 15) Prohibited Behaviours (must NOT happen)

- MUST NOT create PET tickets without an explicit matching rule
- MUST NOT create PET tickets when feature flags are disabled
- MUST NOT create records by rendering UI (no read-side side effects)
- MUST NOT mutate PET ticket state from Pulseway data (no auto-close, no auto-resolve)
- MUST NOT create duplicate tickets for the same notification within the dedupe window
- MUST NOT create tickets for unroutable notifications (no mapping = no ticket)
- MUST NOT store Pulseway credentials in plaintext
- MUST NOT log authentication secrets or include them in error messages
- MUST NOT auto-create PET customers/sites from Pulseway org data
- MUST NOT delete or update external notification records (append-only, with routing_status exception)
- MUST NOT bypass domain permission checks from integration endpoints
- MUST NOT call Pulseway automation endpoints (C) in A/B scope
- MUST NOT rely on WP-Cron for polling (system cron required)
- MUST NOT skip persisting a notification even if ticket creation fails

------------------------------------------------------------------------

## 16) Stress-Test Scenarios

1. **Master feature flag off**
   - Given `pet_pulseway_enabled` = false
   - When system cron fires / webhook received
   - Then no polling occurs, no notifications ingested, no tickets created, no side effects

2. **Ticket creation flag off, monitoring on**
   - Given `pet_pulseway_enabled` = true, `pet_pulseway_ticket_creation_enabled` = false
   - When notifications are ingested
   - Then notifications are persisted; no tickets are created

3. **Duplicate notification (idempotency)**
   - Given a notification with dedupe_key X already exists
   - When same notification arrives again (webhook retry or poll overlap)
   - Then INSERT is a no-op; exactly one record exists; no duplicate ticket

4. **Duplicate ticket prevention (dedupe window)**
   - Given a ticket exists for dedupe_key X created 10 minutes ago
   - And rule dedupe_window_minutes = 60
   - When new notification with same dedupe_key arrives
   - Then no new ticket; existing ticket_id returned

5. **No org mapping (unroutable)**
   - Given a notification for Pulseway org/site with no PET mapping
   - When notification is ingested
   - Then notification persisted with routing_status = 'unroutable'; no ticket created; no data lost

6. **Mapping created after unroutable notifications exist**
   - Given unroutable notifications exist for a Pulseway org
   - When admin creates org mapping
   - Then existing unroutable notifications are NOT retroactively ticketed
   - Future notifications for that org ARE routable

7. **Rate limit hit (429)**
   - Given Pulseway returns 429
   - When poll cycle is running
   - Then current batch stops; backoff applied; next attempt after backoff; no data loss

8. **Circuit breaker (6 consecutive failures)**
   - Given consecutive_failures >= 6
   - When next poll cycle evaluates this integration
   - Then polling is skipped; admin notified; manual reset required

9. **Quiet hours**
   - Given a rule with quiet_hours_start=22:00, quiet_hours_end=06:00
   - When notification matches at 23:00
   - Then no ticket created; notification remains 'pending'; re-evaluated next cycle after 06:00

10. **Ticket creation failure (DB error)**
    - Given notification is persisted but CreateTicketCommand fails
    - Then notification remains routing_status = 'pending'; retried next cycle; no data lost

11. **Multiple integrations**
    - Given two active Pulseway integrations (org A and org B)
    - When poll cycle runs
    - Then each integration polled independently; credentials isolated; no cross-contamination

12. **Permission gating**
    - Given a user without admin/manager scope
    - When accessing monitoring dashboard or integration config
    - Then 403 or hidden; no data leakage

------------------------------------------------------------------------

## 17) Captured for Later (C + D)

### C) Bi-directional Automation Control

Pulseway REST API v3 exposes automation endpoints (Run Workflow / Task / Script, view executions). Future PET commands:
- `RequestExternalRemediationAction`
- `ExternalRemediationRequested` / `ExternalRemediationCompleted` events
- Hard governance: no auto-run on page load, explicit user acknowledgement, idempotency per action_request_id, immutable execution output storage

### D) Full RMM ↔ PET Orchestration

- Two-way status reconciliation (without overwriting PET truth)
- Policy compliance → PET advisory outputs
- Patch compliance import
- PSA linkage (separate auth flow, separate API surface, 1500 req/hr/endpoint limit)

------------------------------------------------------------------------

## 18) Open Items (to confirm during implementation)

1. **Webhook availability**: Confirm whether the target Pulseway plan supports outbound notification webhooks. If not, polling remains the sole transport.
2. **Notification ID stability**: Confirm whether Pulseway notification IDs are stable and globally unique per tenant, or whether hash-based dedupe_key is required.
3. **Webhook signature verification**: Confirm exact header name and hashing algorithm for webhook secret validation (if webhooks available).
4. **Device status fields**: Confirm which device fields best represent status/last-seen and whether those are included in notification payloads or require a follow-up GET.
5. **Pulseway API versioning**: The v3 API endpoint naming has known inconsistencies (some tenants use `/systems` vs `/devices`). Confirm the correct endpoints for the target tenant.
6. **Encryption approach**: Confirm whether to use WordPress `wp_options` with application-level encryption or a dedicated secrets storage mechanism for API credentials.

------------------------------------------------------------------------

## Acceptance Gate

Implementation must not start until:
- This contract is approved
- Pulseway tenant access is confirmed and API connectivity verified
- Open items 1–5 are resolved or have documented fallback strategies
- Feature flag behaviour and idempotency are explicitly test-planned
