## Governance (Approvals) & People (Leave/Capacity)

### Approvals Engine (Append-Only Decisions)
- pet_approval_requests: uuid, request_type, subject_type/id, status (pending|approved|rejected|cancelled), requested_by/at, decided_by/at, decision_reason, request_payload_json immutable.
- pet_approval_steps (optional): multi-step routing with approver_type/reference, status, decided_at, decision_reason.
- Rules: no hard deletes; decisions emit domain events; edits occur only via status transitions.

### Leave & Capacity Realism
- pet_leave_types: name, paid_flag.
- pet_leave_requests: uuid, employee_id, leave_type_id, dates, status (draft|submitted|approved|rejected|cancelled), submitted/approved timestamps, notes.
- pet_capacity_overrides: employee_id, effective_date, capacity_pct, reason; append-only overrides.
- Derived capacity = calendar windows + holidays + approved leave + latest override.

#### Leave State Machine
- States: draft → submitted → approved|rejected|cancelled
- Allowed:
  - draft → submitted
  - submitted → approved
  - submitted → rejected
  - approved → cancelled (compensating record)
  - rejected → cancelled
  - draft → cancelled
- Illegal transitions: hard error
- Required fields:
  - submit: start_date, end_date, leave_type_id
  - approve: approved_by_employee_id, approved_at, decision_reason (optional)
  - reject: approved_by_employee_id, approved_at, decision_reason (required)
- Events: LeaveSubmitted, LeaveApproved, LeaveRejected, LeaveCancelled (append-only)

### Commands & UI
- RequestApproval, Approve/Reject/CancelRequest; Leave submit/approve/reject/cancel; SetCapacityOverride.
- UI: approvals queue/detail/history; leave requests (my/team), calendar overlay.
- Decisions create domain events; no direct table edits via UI.

#### Capacity Override Semantics
- Precedence (highest wins): CapacityOverride → Approved Leave → Holiday → Calendar Working Window
- EffectiveCapacity(date):
  - base_hours = working_window_hours
  - if holiday → base_hours = 0
  - if approved_leave overlaps → base_hours = 0
  - if override → base_hours = working_window_hours * (capacity_pct / 100)
  - overrides do not stack
  
#### Utilization API Output
```
{
  "employee_id": 3,
  "date": "YYYY-MM-DD",
  "effective_capacity_hours": 6.8,
  "scheduled_hours": 5.0,
  "utilization_pct": 73.5
}
```

### Tests
- State machine guards (illegal transitions blocked), append-only discipline, audit visibility of payload snapshots.
