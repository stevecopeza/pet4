STATUS: PARTIALLY IMPLEMENTED
SCOPE: Work Orchestration, Queues, and Assignment
VERSION: v1.1

# Work Orchestration, Queues, and Assignment (v1)

## Current asset (audit)

- `wp_pet_work_items` represent queueable work.
- `source_type` supports `ticket` and `project_task`.
- `wp_pet_department_queues` supports pull/assign tracking.

This is valuable and should become the unified operational queue.

## Required alignment to Ticket backbone

### Source normalization
End state:
- WorkItems for human work are primarily `source_type='ticket'`.
- `project_task` sources are legacy/compat until fully migrated.

### Department/Team ownership
Ticket carries owning department/team.
WorkItem is projected from Ticket and references:
- department_id
- required_role_id
- SLA snapshot/due dates (when applicable)

### Assignment modes
Ticket stores:
- preferred assignee
- actual assignee
- assignment mode

WorkItem mirrors assignment for scheduling and queue views.

## Queue mechanics (department-owned)

- Department owns the queue.
- Tickets enter queue upon creation or when status becomes active.
- Team members may pull if allowed.
- Department head may allocate.

## Manager visibility

Managers view:
- queue inventory
- assignment distribution
- SLA risk (derived from ticket due dates and SLA clock)

## No business logic in UI

UI actions call commands; domain enforces:
- who may assign
- who may pick up
- what transitions are legal

## Implementation Status

### Implemented
- Ticket entity: `assignToTeam(queueId)`, `assignToEmployee(employeeUserId)`, `pull(requestingUserId)`
- `TicketAssigned` domain event with `previousOwnerUserId`, `previousQueueId`, `newQueueId` fields
- REST API: `POST /tickets/{id}/assign/team`, `/assign/employee`, `/pull`
- Assignment syncs to WorkItem projection (`assigned_user_id`, `department_id`)
- Frontend: employee dropdown, queue dropdown, "Pull to me" button in ticket detail sidebar
- WorkItem entity: `source_type` supports `ticket`, `project_task`, `escalation`, `admin`

### Not yet implemented
- Permission gating (who may assign/pick up) — currently any authenticated user
- Department-owned queue pull restrictions
- Manager allocation mode enforcement
