# PET Payment Schedule — How To Proceed From Current State (v1.2)

**Location:** `docs/08_quotes/PET_Payment_Schedule_How_To_Proceed_From_Current_State_v1_2.md`

## 1. Current symptom
- Payment Schedule appears automatically at the top of a new quote.
- It is not configurable (appears as a static table with “Full Payment / On acceptance / Unpaid”).

## 2. Likely root causes (ranked)
A) UI renders a “Payment Schedule” section unconditionally (even when no block exists).  
B) Backend GET quote response always includes a default schedule projection (not persisted).  
C) Quote creation handler auto-creates a schedule record/item.  
D) Demo/template seeding logic is unintentionally applied to normal quote creation.  

## 3. Triage steps (evidence-first; no code changes)

### 3.1 Confirm whether it is persisted
- Create a new quote
- Identify quote_id + quote_version
- Query DB for any schedule rows for that quote/version
  - If rows exist immediately: likely C or D
  - If no rows exist: likely A or B

### 3.2 Confirm whether backend injects schedule on GET
- In browser Network tab, capture GET quote response
- Search JSON for payment schedule representation
  - If present without any persisted record: likely B
  - If absent but UI renders anyway: likely A

### 3.3 Confirm UI code path
- Search UI code for a component rendering PaymentSchedule without checking block existence
- Identify whether schedule component receives data from blocks list or from separate projection fields

## 4. Fix approach (choose based on evidence)

### Case A: UI unconditional render
Fix:
- Gate rendering by “block exists in quote blocks list”
- Ensure “Add block” affordance exists

Tests:
- UI assertion: schedule section absent when blocks do not include PaymentSchedule

### Case B: Backend projection injection
Fix:
- Remove synthetic default schedule from quote read model
- Only include schedule projection if persisted schedule block exists

Tests:
- API GET quote response contains no schedule when none exists

### Case C/D: Auto-creation at quote create
Fix:
- Remove default insertion logic
- Only create schedule via explicit command endpoint/command (draft only)
- Existing environments:
  - Do not delete accepted history
  - If unwanted schedules exist on drafts, allow removing (draft only) or leave as-is (safe default)

Tests:
- DB assertion: schedule tables have no rows after quote creation

## 5. Guardrails to add immediately (regardless of root cause)
Implement Integration Contract v1.2 + Prohibited Behaviours v1.2 as automated tests.

## 6. Definition of done
- New draft quote shows no Payment Schedule by default.
- “Add Block” includes Payment Schedule.
- Adding Payment Schedule creates an editable/configurable block (draft only).
- Accepted quotes show schedule read-only (immutable).
- GET quote does not include schedule unless it exists.
