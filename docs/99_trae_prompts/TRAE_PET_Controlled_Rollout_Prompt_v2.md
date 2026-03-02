clear

You are executing PET Controlled Rollout Phase for:

-   SLA Automation Hardening
-   Work Orchestration Hardening

You MUST implement feature-flag governed activation.

Do NOT redesign. Do NOT modify documentation. Additive implementation
only.

==================================================== PHASE 1 --- FEATURE
FLAGS ====================================================

Implement config-backed feature flags:

-   pet_sla_scheduler_enabled (default false)
-   pet_work_projection_enabled (default false)
-   pet_queue_visibility_enabled (default false)
-   pet_priority_engine_enabled (default false)

Flags must:

-   Persist in DB config
-   Be runtime-checked
-   Not default to true

==================================================== PHASE 2 --- SLA
HARDENING (FLAG-GATED)
====================================================

Implement hardened SlaAutomationService per:

-   05_sla_automation_memo.md
-   SLA Automation Hardening Addendum

Scheduler must execute ONLY if: pet_sla_scheduler_enabled == true

Ensure:

-   SELECT ... FOR UPDATE
-   Idempotent transitions
-   Batch evaluation
-   Unit + integration tests

==================================================== PHASE 3 --- WORK
PROJECTION (FLAG-GATED)
====================================================

TicketCreatedEvent listener must execute ONLY if:
pet_work_projection_enabled == true

Enforce:

-   UNIQUE(source_type, source_id, context_version)
-   Idempotent projection
-   Concurrency-safe

==================================================== PHASE 4 ---
PRIORITY ENGINE (FLAG-GATED)
====================================================

PriorityScoringService must apply ONLY if: pet_priority_engine_enabled
== true

Ensure:

-   Deterministic scoring
-   Stable tie-break

==================================================== PHASE 5 --- QUEUE
EXPOSURE (FLAG-GATED)
====================================================

Queue endpoints/UI must execute ONLY if: pet_queue_visibility_enabled ==
true

====================================================

If ambiguity arises:

-   Halt
-   Cite doc
-   Request bounded clarification

Execution order mandatory.
