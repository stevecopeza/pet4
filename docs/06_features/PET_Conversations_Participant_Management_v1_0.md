# PET Conversation Participant Management & Smart Seeding (v1.0)

**Date:** 2026-02-25
**Scope:** Participant management, smart seeding, and @mention automation for Conversations.

---

## 1. Overview

Conversations in PET are private by default, visible only to **Participants**. This document details how participants are managed, including explicit API actions, smart seeding rules, and automation via @mentions.

---

## 2. Participant Types

The system supports three distinct participant types:

| Type | Description | Usage |
| :--- | :--- | :--- |
| **`user`** | Internal system users (Employees). | Standard participants. |
| **`contact`** | External Customer Contacts. | For Quote/Ticket discussions involving the customer. |
| **`team`** | Groups of users (e.g., "Sales", "Support"). | Allows notifying/adding entire functional groups. |

---

## 3. Participant Management API

### Add Participant
**POST** `/pet/v1/conversations/{uuid}/participants`

Adds a new participant to the conversation.

**Payload:**
```json
{
  "participant_type": "user|contact|team",
  "participant_id": 123
}
```

### Remove Participant
**DELETE** `/pet/v1/conversations/{uuid}/participants`

Removes a participant from the conversation.

**Payload:**
```json
{
  "participant_type": "user|contact|team",
  "participant_id": 123
}
```

### Constraints
1.  **Last Internal Coverage**: You cannot remove the last internal `user` participant from a conversation. This ensures no conversation is left "orphaned" without internal oversight. Attempting to do so returns a `400 Bad Request`.

---

## 4. Smart Seeding (Automation)

To reduce manual effort, the system automatically adds relevant participants during specific lifecycle events.

### Quote Conversation Seeding
When a conversation is created for a **Quote** (`context_type = 'quote'`):
1.  The system identifies the **Customer** associated with the Quote.
2.  It retrieves all **Contacts** linked to that Customer.
3.  These Contacts are automatically added as `contact` participants to the conversation.

**Benefit**: Ensures that when a quote is discussed, the relevant customer points of contact are immediately included without manual addition.

---

## 5. @Mention Automation

The system parses message bodies for mentions and automatically adds referenced entities as participants.

### Supported Formats
*   **`@user`**: Adds the mentioned internal user.
*   **`@team`**: Adds the mentioned team (and potentially notifies members).
*   **`@contact`**: Adds the mentioned customer contact.

### Behavior
1.  User posts a message: *"Hey @Sarah, can you check this?"*
2.  System parses `@Sarah` (User ID: 45).
3.  System checks if User 45 is already a participant.
4.  If not, User 45 is **automatically added** to the conversation.
5.  A `ParticipantAdded` event is recorded.

This ensures that anyone looped into the discussion explicitly via mention is granted access to the conversation context.

---

## 6. Security & Access Control

*   **Visibility**: Only listed participants (and potentially admins/owners depending on context rules) can view the conversation.
*   **Modification**: Only participants can post messages or add/remove other participants.
*   **Context Access**: Adding a participant *grants* them access to the conversation, but does not necessarily grant access to the underlying Context (e.g., the Quote itself) if they didn't already have it. However, for Quotes, the seeded Contacts are already associated with the Customer.
