# PET Unified Work Orchestration -- Data Model v1.0

Core Entity: WorkItem (Read Model / Aggregation Projection)

Fields: - id (UUID) - source_type (enum: project_task, ticket,
escalation, admin) - source_id (UUID) - assigned_user_id (UUID
nullable) - department_id (UUID) - sla_snapshot_id (UUID nullable) -
sla_time_remaining_minutes (int nullable) - priority_score (decimal) -
scheduled_start_utc (datetime nullable) - scheduled_due_utc (datetime
nullable) - capacity_allocation_percent (decimal) - status (enum:
active, waiting, completed) - escalation_level (int nullable) -
created_at (datetime) - updated_at (datetime)

DepartmentQueue Entity: - id (UUID) - department_id (UUID) -
work_item_id (UUID) - assigned_user_id (nullable) - entered_queue_at
(datetime) - picked_up_at (datetime nullable)

OperationalEscalationRole: - team_id (UUID) -
operational_escalation_user_id (UUID)

Invariants: - SLA clock never stored directly; computed from snapshot +
timestamps. - WorkItem is projection only; source entity remains
authoritative.
