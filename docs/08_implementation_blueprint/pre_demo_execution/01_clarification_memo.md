# PET Deferred Components Clarification Memo v1.0

## 1. Purpose
This memo formally clarifies which identified gaps are intentionally deferred, why they are deferred, and what preconditions must exist before they are prioritised. The objective is to prevent scope drift while protecting architectural integrity.

## 2. Advisory Layer (Deferred)
**Status**: Intentionally deferred.
**Reason**: Advisory is a derived analytics layer dependent on stable operational truth (Quotes, Projects, SLAs, Capacity, Events). Implementing it before operational automation and validation stabilise would result in misleading advisory outputs.
**Preconditions for activation**:
- SLA Automation fully operational
- Event registry complete for commercial and support domains
- Projection integrity verified
- Demo engine implemented and validated (used as stress harness)

## 3. External Integrations (Deferred)
**Status**: Intentionally deferred.
**Reason**: Integration adapters (QuickBooks, Email, Twilio, ERP connectors) must sit on stable domain events and invariant-safe workflows. Implementing them prior to SLA automation and quote validation increases the risk of propagating invalid state externally.
**Preconditions for activation**:
- Quote readiness gates enforced
- SLA automation loop operational
- Integration suppression guard in place
- Event dispatch coverage audited

## 4. Event Registry Completeness (Partial Audit Required)
**Status**: Must audit but not fully expand.
**Reason**: Only demo-critical events must be implemented before Demo Engine work begins. Full event coverage across all potential future domains is not required at this stage.
**Required now**:
- QuoteAcceptedEvent
- ProjectCreatedEvent
- TicketWarningEvent
- TicketBreachedEvent
- EscalationTriggeredEvent
- MilestoneCompletedEvent
- ChangeOrderApprovedEvent

Remaining events may be deferred until advisory and integration layers mature.

## 5. Strategic Sequencing Rationale
- Operational integrity precedes external propagation.
- Automation loops precede simulation (Demo Engine).
- Simulation precedes advisory interpretation.
- Stable events precede external integrations.
- Diagnostics precede expansion.

## 6. Conclusion
The deferred components are not deprioritised; they are sequenced. Completion of Pre-Demo Readiness gates ensures that subsequent Advisory and Integration layers are built on stable, validated operational truth.
