# PET Pre-Demo Engineering Execution Spec v1.0

## 1. Overview
This specification details the technical execution plan for meeting Pre-Demo Readiness Requirements.

## 2. SLA Automation Loop
### 2.1 Architecture
- Use WordPress Cron (wp_schedule_event) or separate worker? -> **WP Cron** (MVP).
- Service: `SlaAutomationService`.
- Logic:
  - Query all tickets in `Open` or `In Progress` state.
  - For each ticket:
    - Load SlaSnapshot.
    - Calculate `time_remaining`.
    - If `time_remaining < warning_threshold` AND `!warning_sent`, dispatch `TicketWarningEvent`.
    - If `time_remaining <= 0` AND `!breach_sent`, dispatch `TicketBreachedEvent`.
    - Trigger `EscalationService` on breach.

### 2.2 Integration Test Plan
- Create dummy ticket with 1-hour SLA.
- Mock `SlaClockService` or manipulate time.
- Run `SlaAutomationService::process()`.
- Assert `TicketWarningEvent` dispatched.
- Advance time.
- Run `SlaAutomationService::process()`.
- Assert `TicketBreachedEvent` dispatched.
- Assert `EscalationTriggeredEvent` dispatched.

**Exit Criteria**: Ticket breaches automatically without manual trigger.

## 3. Quote Domain Readiness Gates (Mandatory)
### 3.1 Required Fields on Quote:
- id, customer_id, status, title, description, version, total_value, created_at

### 3.2 Required Quote Sections:
- Product Section (product_sku, quantity, unit_price)
- Implementation Section (milestones, tasks, role_id, hours, rate)
- Recurring Service Section (sla_id, monthly_fee)

### 3.3 Required Validation Rules (Quote.validate()):
- Product lines must NOT contain service fields.
- Service lines MUST contain rate and role reference.
- Implementation section MUST contain at least 1 milestone and task.
- Payment plan must resolve to canonical schedule entries.
- Quote cannot transition to Accepted if validation fails.

### 3.4 Required Events:
- QuoteAcceptedEvent
- ProjectCreatedEvent

### 3.5 API Endpoints:
- POST /quotes
- POST /quotes/{id}/validate
- POST /quotes/{id}/accept

**Exit Criteria**: Invalid quotes cannot be accepted.

## 4. Event Registry Audit
### 4.1 Confirm Implemented Events:
- QuoteAcceptedEvent
- ProjectCreatedEvent
- MilestoneCompletedEvent
- TicketCreatedEvent
- TicketWarningEvent
- TicketBreachedEvent
- EscalationTriggeredEvent
- ChangeOrderApprovedEvent

### 4.2 Confirm Projection Handlers Exist For:
- WorkItemProjection
- FeedProjection
- CapacityProjection

### 4.3 API Visibility:
- GET /events/recent (admin only)

**Exit Criteria**: Event → Projection flow verified in integration test.

## 5. System Self-Diagnostics (Pre-Demo)
Implement `DemoPreFlightCheck` service:
- Validate SLA automation loop operational.
- Validate Quote.validate() enforced.
- Validate required events dispatched.
- Validate projection handlers active.
- Validate no fatal integrity exceptions.

**Admin API**:
- GET /system/pre-demo-check

Pre-demo check must return structured PASS/FAIL per rule.

## 6. Conclusion
Completion of this specification ensures domain integrity, automation reliability, and event consistency before Demo Dataset work begins. Only once these gates are satisfied should demo engine implementation resume.
