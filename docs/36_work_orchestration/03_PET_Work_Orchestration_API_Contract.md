# PET Unified Work Orchestration -- API Contract v1.0

GET /work/my Returns unified work list for logged-in user.

Response: \[ { "work_item_id": UUID, "source_type": "ticket", "title":
string, "priority_score": decimal, "sla_time_remaining_minutes": int,
"scheduled_start": datetime, "capacity_allocation_percent": decimal,
"status": "active" }\]

GET /work/department/{id} Returns department queue + SLA risk summary.

POST /work/{id}/assign Assign work item to user.

POST /work/{id}/start Marks work as active.

POST /work/{id}/pause Pauses SLA clock if ticket.

POST /work/{id}/override-priority Requires manager role.

Validation Rules: - Cannot assign completed item. - SLA clock pause only
valid for ticket source. - Override requires justification field.
