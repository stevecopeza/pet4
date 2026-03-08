# API Contract -- Conversations

## 1. Conversation Lifecycle

### POST /conversations

Create a new conversation anchored to a context.

**Payload:**
```json
{
  "context_type": "quote|project|ticket",
  "context_id": "123",
  "subject": "Discussion about X",
  "subject_key": "quote:123" // Optional unique key
}
```

**Response:**
`201 Created` with UUID.

### GET /conversations

Retrieve a conversation by UUID or Context.

**Query Parameters:**
*   `uuid`: (Optional) The conversation UUID.
*   `context_type`: (Optional) e.g., "quote".
*   `context_id`: (Optional) e.g., "123".

**Response:**
`200 OK` with full conversation details (timeline, participants, decisions).

### POST /conversations/{uuid}/resolve

Resolve a conversation.

### POST /conversations/{uuid}/reopen

Reopen a resolved conversation.

---

## 2. Messages & Timeline

### POST /conversations/{uuid}/messages

Post a new message to the conversation.

**Payload:**
```json
{
  "content": "Hello world",
  "attachments": [] // Optional
}
```

**Response:**
`201 Created` with Message ID.

### POST /conversations/{uuid}/messages/{id}/reactions

Add a reaction to a message.

**Payload:**
```json
{
  "reaction": "👍"
}
```

### DELETE /conversations/{uuid}/messages/{id}/reactions/{type}

Remove a reaction from a message.

---

## 3. Participant Management

### POST /conversations/{uuid}/participants/add

Add a participant to the conversation.

**Payload:**
```json
{
  "participant_type": "user|contact|team",
  "participant_id": 123
}
```

**Response:**
`201 Created`

### POST /conversations/{uuid}/participants/remove

Remove a participant from the conversation.

**Payload:**
```json
{
  "participant_type": "user|contact|team",
  "participant_id": 123
}
```

**Response:**
`200 OK`

**Constraints:**
*   Cannot remove the last internal `user` participant ("Last Internal Coverage").

---

## 4. Decisions

### POST /conversations/{uuid}/decisions

Request a formal decision (approval).

**Payload:**
```json
{
  "decision_type": "quote_approval",
  "payload": { ... }
}
```

### POST /decisions/{uuid}/respond

Respond to a decision request.

**Payload:**
```json
{
  "response": "approved|rejected",
  "comment": "Looks good"
}
```

---

## 5. Security

*   **Access Control**: Access is granted to explicit **Participants** OR users with access to the underlying **Context** (if the domain rules allow).
*   **Visibility**: Timeline events are filtered based on visibility rules.
