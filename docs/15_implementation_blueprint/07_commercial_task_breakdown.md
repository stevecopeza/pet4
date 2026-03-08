# PET Commercial Engine v1 -- Developer Task Breakdown

Status: Execution Plan Scope: Quote → Approval → Contract → Baseline →
Forecast → Delivery Transition Audience: Developers, Technical Leads

  ---------------------------------------------------------------------
  PHASE 0 --- Documentation Lock
  ---------------------------------------------------------------------
  \- Freeze Commercial Engine v1.0 spec - Confirm no open architectural
  questions - Tag as commercial-v1-baseline - No code before this

  ---------------------------------------------------------------------

## PHASE 1 --- Database & Migrations (Forward-Only)

Create migrations for: - opportunities - quotes - quote_components -
quote_milestones - quote_tasks - recurring_services - contracts -
baselines - variance_orders - cost_adjustments - forecast_capacity -
procurement_forecast - procurement_intent - payment_schedule -
quote_activity

Requirements: - UUID primary keys - Explicit foreign keys - Decimal
precision (money = 14,2) - Proper indexing (status, opportunity_id,
role_id, department_id) - No down migrations

  ------------------------------------------------
  PHASE 2 --- Domain Layer (Pure Business Logic)
  ------------------------------------------------

Entities: - Opportunity - Quote - QuoteComponent -
ImplementationMilestone - ImplementationTask - RecurringService -
Contract - Baseline - VarianceOrder - CostAdjustment - ForecastCapacity

Responsibilities: - Enforce invariants - Emit domain events - Be
persistence-agnostic

State Machines: Quote: draft → pending_approval → approved → sent →
accepted → rejected → superseded

Hard block illegal transitions.

Invariants: - Accepted Quote immutable - Approval required on margin
breach - No internal cost mutation - Forecast only from approved
quotes - Baseline locked unless re-baselined

Domain Events: - QuoteCreated - QuoteApproved - QuoteAccepted -
QuoteRejected - ContractCreated - ContractActivated - BaselineCreated -
VarianceOrderCreated - ChangeOrderCreated - ForecastCapacityCreated -
CostAdjustmentCreated

  --------------------------------------------------
  PHASE 3 --- Application Layer (Command Handlers)
  --------------------------------------------------

Commands: - CreateQuote - CloneQuote - AddQuoteComponent -
AddMilestone - AddTask - GeneratePaymentPlan - SubmitQuote -
ApproveQuote - SendQuote - AcceptQuote - RejectQuote -
ActivateContract - CreateVarianceOrder - CreateChangeOrder -
RebaselineProject

Rules: - Transaction boundary per command - Validate state before
action - Persist via repository - Dispatch events

  -----------------------------
  PHASE 4 --- Forecast Engine
  -----------------------------

On QuoteApproved: - Extract WBS hours - Group by role & department -
Apply probability weighting - Insert forecast records

On QuoteSuperseded / Rejected / Expired: - Replace or remove forecast
records

Threshold Engine: - Warning at 85% - Escalation at 100% - Event-driven
notifications

  ----------------------------------------
  PHASE 5 --- Contract & Baseline Engine
  ----------------------------------------

AcceptQuote (Atomic): 1. Create Contract 2. Snapshot commercial totals
3. Snapshot SLA 4. Generate canonical payment schedule 5. Create
Baseline v1 6. Emit events 7. Create ProcurementForecast

ActivateContract: - Convert Forecast → Committed - Convert
ProcurementForecast → ProcurementIntent

  ----------------------------------------------
  PHASE 6 --- UI Layer (Section-Based Builder)
  ----------------------------------------------

Header: - Customer - Title - Description - Currency - Validity

Section Builder: - Add Product - Add Implementation - Add Recurring
Service - Add Adjustment - Drag-and-drop reorder - Live margin display

Implementation Builder: - Milestone → Task hierarchy - Role dropdown -
Hours input - Live cost, sell, margin feedback

Footer: - Total sell - Total cost - Margin % - Payment plan status -
Readiness indicator

Version History Panel: - Version number - Status - Change summary

  ------------------------------------
  PHASE 7 --- Reporting & Dashboards
  ------------------------------------

-   Forecast dashboard (role/month aggregation)
-   Margin exception report
-   Procurement forecast dashboard

  ------------------------------------
  PHASE 8 --- Stress Test Validation
  ------------------------------------

Validate: - Version supersession - Margin override approval - Forecast
replacement logic - ChangeOrder flow - VarianceOrder flow - Contract
activation boundary - Procurement conversion

  -----------------------
  PHASE 9 --- Hardening
  -----------------------

-   Concurrency control
-   Idempotency for Accept & Activate
-   Audit trail validation
-   Performance index validation
-   Decimal rounding consistency

  ------------------------------------
  PHASE 10 --- Capacity Module Hooks
  ------------------------------------

Prepare integration points for: - Calendar module - Leave tracking -
Staff allocation engine - Capacity smoothing logic

Do not embed scheduling logic inside commercial layer.

------------------------------------------------------------------------

END OF TASK BREAKDOWN
