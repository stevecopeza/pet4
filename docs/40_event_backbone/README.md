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

### Integration Behavior
- Push (PET→QB): queue export ⇒ outbox row ⇒ send ⇒ mapping created ⇒ events appended (sent/failed).
- Pull (QB→PET): run record ⇒ shadow upserts ⇒ events appended (invoice/payment upserted).
- No inbound QB mutation of PET operational truth; shadows are read-only.

### Testing Requirements
- Event append monotonicity, event_uuid uniqueness, outbox retry scheduling, shadow upsert idempotency.
