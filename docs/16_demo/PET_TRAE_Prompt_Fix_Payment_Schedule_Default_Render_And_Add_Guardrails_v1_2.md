# TRAE Prompt — Fix Payment Schedule Default Render + Add Guardrails (v1.2)

**Repo target path:** `plugins/pet/docs/16_demo/PET_TRAE_Prompt_Fix_Payment_Schedule_Default_Render_And_Add_Guardrails_v1_2.md`

```text
clear

You are troubleshooting and fixing PET Quotes behavior:
Payment Schedule is showing by default on new quotes and is not configurable.

NON-NEGOTIABLES:
- Accepted quotes immutable
- Additive corrections only
- Domain rules in Domain layer
- Event-backed architecture
- Forward-only migrations
- Backward compatibility mandatory
- No refactors beyond what is necessary to implement the fix

THIS TASK SCOPE:
(1) Stop default display/injection/auto-create.
(2) Ensure schedule appears only when explicitly added as a Quote Block.
(3) Add guardrail tests to prevent regression.

AUTHORITATIVE CONTRACTS:
- plugins/pet/docs/08_quotes/PET_Payment_Schedule_Integration_Contract_v1_2.md
- plugins/pet/docs/08_quotes/PET_Payment_Schedule_Prohibited_Behaviours_v1_2.md
- Existing Payment Schedule spec v1.1 remains valid for fields/invariants, but this task is primarily integration guardrails.

WORK ORDER (STRICT):

STEP 1 — Evidence and classification (NO CODE CHANGES)
1) Create a new quote draft.
2) Determine which is true:
   A) UI renders schedule unconditionally
   B) Backend GET injects default schedule in payload
   C) Quote creation auto-persists schedule
   D) Demo/template seeding creates it
3) Capture evidence:
   - Screenshot UI (schedule visible)
   - Network GET quote JSON fragment (where schedule appears)
   - DB check: schedule rows exist or not
   - Code search hits (file+line+snippet)
4) Record findings in a short report (in PR description or local note).

STEP 2 — Minimal fix based on classification
Case A:
- Gate UI rendering strictly by presence of PaymentSchedule block in quote blocks list.
- If absent, do not render schedule section (no empty frame).

Case B:
- Remove synthetic default schedule from quote read model/projection.
- Only include schedule block projection if persisted schedule exists.

Case C/D:
- Remove default insertion logic from quote creation/initialization paths.
- Ensure schedule block is created only via explicit Add Block endpoint/command (draft only).

STEP 3 — Guardrail tests (REQUIRED)
Add tests enforcing Prohibited Behaviours:
1) New quote: GET payload contains no schedule when none exists.
2) New quote: DB contains no schedule rows after creation.
3) UI: does not render schedule when absent.
4) Accepted quote: mutation endpoints (if present) hard-fail.

STEP 4 — Verify
- New draft quote: schedule not visible.
- Add Block -> Payment Schedule: schedule visible and configurable (draft).
- Reload: persists only if added.

OUTPUT:
- Minimal code changes to fix the bug and add tests.
- Do not modify unrelated areas.
- Do not modify docs.
```
*** End Patch***  ?>">
