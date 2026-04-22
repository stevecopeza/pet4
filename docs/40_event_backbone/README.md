## Event Backbone & Outbox (Immutable, Idempotent)

### Event Store (Append-Only)
- pet_domain_event_stream: event_uuid UNIQUE, occurred_at/recorded_at, aggregate_type/id/version, event_type, event_schema_version, actor, correlation/causation, payload_json, metadata_json.
- Rules: insert-only; no UPDATE/DELETE; aggregate_version monotonic.
- Indexes: UNIQUE(event_uuid); (aggregate_type, aggregate_id, aggregate_version); occurred_at; event_type.

### Projection Offsets
- pet_projection_offsets: projection_name UNIQUE, last_event_id, updated_at.
- Used by projection workers to process events exactly once in order.

### Outbox
- pet_outbox: event_id FK, destination (quickbooks|email|webhook), status (pending|sent|failed|dead), attempt_count, next_attempt_at, last_error, timestamps.
- Transactional write with event append; dispatcher retries, idempotent send keyed by event_uuid/export uuid.
- **Unique constraint:** `UNIQUE KEY uq_outbox_event_dest (event_id, destination)` — prevents duplicate rows for the same event+destination. Added Sprint 47.

### Delivery Guarantee
**PET outbox delivers at-least-once.** This is an explicit design decision, not an oversight.

What this means:
- The dispatcher retries on failure; a successful external call that crashes before `markSent()` can result in the same event being dispatched again on the next cron run.
- The unique constraint prevents duplicate *rows*, but not duplicate *external calls* across retries.

**Consumers must tolerate duplicates.** Every external destination (QuickBooks, webhooks, etc.) must handle receiving the same event more than once without side effects. The recommended mechanism is the `event_id` field, which is stable across retries — send it as an idempotency key in the outbound request, or use it for consumer-side deduplication.

At-most-once delivery is not achievable without a two-phase commit or a distributed transaction coordinator, neither of which fits the WordPress/WP-CLI execution model.

### Integration Behavior
- Push (PET→QB): queue export ⇒ outbox row ⇒ send ⇒ mapping created ⇒ events appended (sent/failed).
- Pull (QB→PET): run record ⇒ shadow upserts ⇒ events appended (invoice/payment upserted).
- No inbound QB mutation of PET operational truth; shadows are read-only.

### Testing Requirements
- Event append monotonicity, event_uuid uniqueness, outbox retry scheduling, shadow upsert idempotency.
