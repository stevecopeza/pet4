# PET – WhatsApp (Twilio) Integration

## Purpose
Defines PET’s use of WhatsApp via Twilio for customer communication.

---

## Triggering Events

- QuoteSent
- TicketResponseRequired

---

## Rules

- Messages are outbound notifications
- Inbound messages are captured as interactions
- No automatic state change from inbound content

---

## Captured Data

- Timestamp
- Phone number
- Message body
- Delivery status

---

## Failure Handling

- Failures generate events
- Retries are rate-limited

---

**Authority**: Normative

