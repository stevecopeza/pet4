# PET Command Surface — API Contract v1.0

Base Path
- /wp-json/pet/v1

Auth & Permissions
- Requires WordPress REST auth via X-WP-Nonce header
- Permission policy: current_user_can('manage_options')

Announcements
- GET /announcements
  - Returns announcements relevant to the current user (audienceScope: global | department | role).
  - Response: Array of objects
    - id: string (UUID)
    - title: string
    - body: string
    - priorityLevel: "low" | "normal" | "high" | "critical"
    - pinned: boolean
    - acknowledgementRequired: boolean
    - gpsRequired: boolean
    - acknowledgementDeadline: string | null (ISO 8601)
    - audienceScope: "global" | "department" | "role"
    - audienceReferenceId: string | null
    - authorUserId: string (WordPress user id)
    - expiresAt: string | null (ISO 8601)
    - createdAt: string (ISO 8601)

- POST /announcements
  - Creates a new announcement.
  - Headers: X-WP-Nonce
  - Body (JSON):
    - title: string
    - body: string
    - priorityLevel?: "low" | "normal" | "high" | "critical" (default "normal")
    - pinned?: boolean (default false)
    - acknowledgementRequired?: boolean (default false)
    - gpsRequired?: boolean (default false)
    - acknowledgementDeadline?: string (ISO 8601)
    - audienceScope?: "global" | "department" | "role" (default "global")
    - audienceReferenceId?: string (team id when department; role id when role)
    - expiresAt?: string (ISO 8601)
  - Responses:
    - 201: Returns the created announcement (fields as above)
    - 400: Validation or payload error
    - 401: Unauthorized or missing nonce/permissions

- POST /announcements/{id}/ack
  - Acknowledges the specified announcement.
  - Headers: X-WP-Nonce
  - Body (JSON):
    - deviceInfo?: string
    - gpsLat?: number
    - gpsLng?: number
  - Responses:
    - 201: { id, announcementId, userId, acknowledgedAt }
    - 200: { message: "Already acknowledged" } (idempotent dedup)
    - 400: Validation or payload error
    - 401: Unauthorized

Feed Events
- GET /feed
  - Returns feed events relevant to the current user.
  - Response: Array of objects
    - id: string (UUID)
    - eventType: string
    - sourceEngine: string
    - sourceEntityId: string
    - classification: "critical" | "operational" | "informational" | "strategic"
    - title: string
    - summary: string
    - metadata: object
    - audienceScope: "global" | "department" | "role" | "user"
    - audienceReferenceId: string | null
    - pinned: boolean
    - expiresAt: string | null (ISO 8601)
    - createdAt: string (ISO 8601)

- POST /feed/{id}/react
  - Creates or returns an existing reaction for a feed event (idempotent).
  - Headers: X-WP-Nonce
  - Body (JSON):
    - reactionType: "acknowledged" | "win" | "concern" | "suggestion"
  - Responses:
    - 201: { id, feedEventId, userId, reactionType, createdAt }
    - 200: Existing reaction returned (idempotent dedup)
    - 400: Validation or payload error
    - 401: Unauthorized

Notes
- A dedicated "GET /announcements/pending" endpoint is not currently implemented; clients can filter acknowledgementRequired=true from GET /announcements.
