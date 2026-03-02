# PET Command Surface -- Data Model & Fields v1.0

## Core Entity: FeedEvent (Projection Only)

-   id (UUID, PK)
-   event_type (enum: SLAWarning, SLABreach, EscalationTriggered,
    ProjectMilestone, ChangeOrderApproved, Announcement, etc.)
-   source_engine (enum: SLA, Project, Commercial, Ticket, Capacity,
    Advisory)
-   source_entity_id (UUID)
-   classification (enum: critical, operational, informational,
    strategic)
-   title (varchar 255)
-   summary (text)
-   metadata_json (json)
-   audience_scope (enum: global, department, role, user)
-   audience_reference_id (UUID nullable)
-   pinned_flag (boolean)
-   expires_at (datetime nullable)
-   created_at (datetime)

Indexes: - classification - audience_scope - created_at

## Announcement Entity

-   id (UUID, PK)
-   title (varchar 255)
-   body (text)
-   priority_level (enum: low, normal, high, critical)
-   pinned_flag (boolean)
-   acknowledgement_required (boolean)
-   gps_required (boolean)
-   acknowledgement_deadline (datetime nullable)
-   audience_scope (enum: global, department, role)
-   audience_reference_id (UUID nullable)
-   author_user_id (UUID)
-   created_at (datetime)
-   expires_at (datetime nullable)

## AnnouncementAcknowledgement

-   id (UUID)
-   announcement_id (UUID)
-   user_id (UUID)
-   acknowledged_at (datetime)
-   device_info (varchar 255 nullable)
-   gps_lat (decimal nullable)
-   gps_lng (decimal nullable)

## FeedReaction

-   id (UUID)
-   feed_event_id (UUID)
-   user_id (UUID)
-   reaction_type (enum: acknowledged, concern, suggestion, win)
-   created_at (datetime)

Constraint: - One reaction per user per feed_event.
