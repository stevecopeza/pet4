# PET Command Surface -- Cross-Engine Integration v1.0

## Event Projection Model

All engines emit domain events.

Projection Service maps domain events → FeedEvent.

Example Mappings:

SLAWarning → classification=critical SLABreach → classification=critical
EscalationTriggered → classification=operational
ProjectMilestoneAchieved → classification=informational
ChangeOrderApproved → classification=operational

No module writes directly to FeedEvent table.

## Audience Resolution

Scopes:
- FeedEvent supports audienceScope: global | department | role | user
- Announcement supports audienceScope: global | department | role

Resolution for current user:
- department → user must be a member of the referenced team (employee.teamIds)
- role → user must have an active assignment to the referenced role (AssignmentRepository)
- user → user id must match audienceReferenceId
- global → visible to all authorized users

Guidance:
- SLA events → assigned user and relevant department(s)
- Project milestones → project team (mapped into department/role/user where applicable)
- Commercial wins → global
- Advisory signals → management roles

## Acknowledgement Escalation

If acknowledgement_required and deadline exceeded: - Emit
AnnouncementUnacknowledged event - Notify direct manager - Escalate
upward after configurable interval

## Retention Policy

-   Operational events retained 90 days
-   Strategic announcements retained indefinitely
-   SLA breach events archived after 180 days

## Idempotency & Deduplication

- Announcements acknowledgements are idempotent: a repeat ack returns 200 with a message indicating prior acknowledgement.
- Feed reactions are idempotent per (feed_event_id, user_id): a repeat react returns 200 with the existing reaction payload.
