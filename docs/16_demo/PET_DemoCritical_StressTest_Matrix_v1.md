# PET Demo-Critical Areas --- Stress-Test Execution Matrix v1.0

Date: 2026-02-26 Target location: docs/ToBeMoved/

## Purpose

Map every integration-level stress scenario to concrete test
implementation targets. No feature is considered complete unless every
row below has a corresponding automated test.

------------------------------------------------------------------------

# 1. Escalation & Risk

  ------------------------------------------------------------------------------------------------------------------------------
  Scenario       Test Type     Test File                       Setup                    Assertion    Idempotency / Concurrency
                                                                                                     Angle
  -------------- ------------- ------------------------------- ------------------------ ------------ ---------------------------
  Feature flag   Integration   EscalationFeatureFlagTest.php   Disable                  No           Ensure event listener exits
  off prevents                                                 pet_escalation_enabled   escalation   early
  creation                                                                              row created  

  Duplicate SLA  Integration   EscalationIdempotencyTest.php   Fire TicketBreachedEvent One OPEN     Unique open_dedupe_key
  breach event                                                 twice                    escalation   enforced

  Cooldown       Integration   EscalationCooldownTest.php      Trigger breach within    No second    SELECT ... FOR UPDATE +
  enforcement                                                  cooldown                 escalation   unique

  ACK/RESOLVE    Integration   EscalationTransitionTest.php    OPEN escalation          Transition   Illegal transitions
  transitions                                                                           rows         rejected
                                                                                        appended     

  Permission     Integration   EscalationPermissionTest.php    Agent outside scope      403 response No data leakage
  gating                                                                                             

  Parent         Integration   EscalationIsolationTest.php     Modify ticket fields     Escalation   No accidental side effects
  mutation                                                                              unchanged    
  independence                                                                                       
  ------------------------------------------------------------------------------------------------------------------------------

------------------------------------------------------------------------

# 2. Support Helpdesk

  ---------------------------------------------------------------------------------------------------------------------
  Scenario       Test Type     Test File                           Setup                  Assertion    Invariant Angle
  -------------- ------------- ----------------------------------- ---------------------- ------------ ----------------
  Feature flag   Integration   HelpdeskFeatureFlagTest.php         Disable                Endpoint     No data mutation
  off hides                                                        pet_helpdesk_enabled   blocked      
  overview                                                                                             

  Overview uses  Integration   HelpdeskOverviewDataTest.php        Seed tickets           Lists        No hardcoded
  real data                                                                               populated    arrays

  Assignment     Unit +        TicketAssignmentInvariantTest.php   Assign team then       Exactly one  Atomic mutation
  invariant      Integration                                       employee               assignee     
  enforced                                                                                             

  Illegal pull   Integration   HelpdeskIllegalPullTest.php         Employee-assigned      409 returned No mutation
  rejected                                                         ticket                              

  Manager        Integration   HelpdeskManagerAssignTest.php       Manager assigns        Event        Feed updated
  reassignment                                                                            dispatched   

  SLA timers     Integration   HelpdeskSlaReadOnlyTest.php         Ticket with SLA        No SLA       Read-only
  read-only                                                                               mutation     surface
  ---------------------------------------------------------------------------------------------------------------------

------------------------------------------------------------------------

# 3. Advisory Layer

  --------------------------------------------------------------------------------------------------------------------------
  Scenario       Test Type     Test File                          Setup                  Assertion        Immutability Angle
  -------------- ------------- ---------------------------------- ---------------------- ---------------- ------------------
  Feature flag   Integration   AdvisoryFeatureFlagTest.php        Disable                Endpoint blocked No signal creation
  off blocks                                                      pet_advisory_enabled                    
  advisory                                                                                                

  Manual         Integration   AdvisoryManualGenerationTest.php   Manager generates      One report       No auto-generation
  generation                                                      report                 inserted         
  explicit                                                                                                

  Regeneration   Integration   AdvisoryVersioningTest.php         Generate twice same    Version          No overwrite
  creates new                                                     period                 increments       
  version                                                                                                 

  No operational Integration   AdvisoryIsolationTest.php          Generate report        No               Strict derivation
  mutation                                                                               ticket/project   
                                                                                         changes          

  Permission     Integration   AdvisoryPermissionTest.php         Agent without scope    403              No data leakage
  gating                                                                                                  
  --------------------------------------------------------------------------------------------------------------------------

------------------------------------------------------------------------

# 4. People Resilience

  ---------------------------------------------------------------------------------------------------------------------------------
  Scenario      Test Type     Test File                             Setup                           Assertion    Idempotency Angle
  ------------- ------------- ------------------------------------- ------------------------------- ------------ ------------------
  Feature flag  Integration   ResilienceFeatureFlagTest.php         Disable                         Endpoints    No analysis
  off blocks                                                        pet_people_resilience_enabled   blocked      
  resilience                                                                                                     

  Requirement   Integration   ResilienceRequirementUniqueTest.php   Duplicate create attempt        409          Unique constraint
  uniqueness                                                                                                     

  Manual        Integration   ResilienceManualRunTest.php           Run analysis                    Analysis run No auto-run
  analysis                                                                                          inserted     
  explicit                                                                                                       

  Idempotent    Integration   ResilienceSignalIdempotencyTest.php   Retry same run_id               No duplicate run_id constraint
  signals per                                                                                       signals      
  run                                                                                                            

  Escalation    Integration   ResilienceEscalationFlagTest.php      CRITICAL SPOF                   Escalation   Feature flag
  coupling                                                                                          only if flag enforcement
  controlled                                                                                        true         
  ---------------------------------------------------------------------------------------------------------------------------------

------------------------------------------------------------------------

# Completion Rule

Before marking subsystem complete: - All rows must have passing
automated tests. - Concurrency/idempotency tests must simulate retry
behaviour. - Feature flag off scenarios must demonstrate zero side
effects.
