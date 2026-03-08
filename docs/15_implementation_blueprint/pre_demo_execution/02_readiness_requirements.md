# PET Pre-Demo Readiness Requirements v1.0

## 1. Purpose
This document defines mandatory engineering work that must be completed before the Demo Dataset Engine implementation begins. The Demo Engine depends on domain integrity, event completeness, and automation loops. Without these, the demo will misrepresent system capability.

## 2. SLA Automation Engine – Mandatory Completion
- Implement scheduled SLA evaluation loop (cron, queue worker, or scheduler).
- Periodically recalculate SLA clocks for active tickets.
- Dispatch TicketWarningEvent when warning threshold reached.
- Dispatch TicketBreachedEvent when breach occurs.
- Trigger escalation workflow (operational + governance escalation).
- Ensure SLA recalculation respects BusinessTimeCalculator rules.

**Exit Criteria**: A ticket configured to breach within 30 minutes must automatically generate warning and breach events without manual intervention.

## 3. Quote Domain Readiness Gates – Mandatory Completion
- Implement Quote.validate() method.
- Enforce catalog type rules (Product vs Service).
- Ensure services include rate fields; products must not include service-only fields.
- Require WBS (milestones/tasks) when Implementation section present.
- Validate payment plan resolves to canonical schedule before acceptance.
- Block Quote acceptance if validation fails.

**Exit Criteria**: Invalid quote structures cannot transition to Accepted state. Acceptance automatically triggers valid Project creation.

## 4. Event Registry Audit – Mandatory Verification
- Confirm QuoteAccepted event dispatch.
- Confirm ProjectCreated event dispatch.
- Confirm TicketCreated event dispatch.
- Confirm TicketWarning event dispatch.
- Confirm TicketBreached event dispatch.
- Confirm EscalationTriggered event dispatch.
- Confirm MilestoneCompleted event dispatch.
- Confirm ChangeOrderApproved event dispatch.
- Ensure all events update projections and feed correctly.

**Exit Criteria**: Demonstrable event-to-projection flow verified in a controlled test scenario.

## 5. Deferred Until After Demo Engine
- Advisory Layer implementation.
- External Integration adapters (QuickBooks, Twilio, etc.).

## 6. Conclusion
The Demo Engine is a system-wide stress harness. Domain validation, SLA automation, and event completeness are prerequisites. Only once these are complete should Demo Dataset implementation begin.
