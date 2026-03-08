# PET – Email Integration

## Purpose
Defines how PET uses email as a communication channel without letting it alter domain truth.

---

## Triggering Events

- QuoteSent
- TicketUpdated
- SLAWarningIssued

---

## Rules

- Emails are notifications, not commands
- Replies do not change state automatically

---

## Captured Data

- Timestamp
- Sender / recipient
- Subject
- Message reference

---

## Failure Handling

- Delivery failure generates an event
- No retries beyond configured limits

---

**Authority**: Normative

