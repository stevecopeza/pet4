# Skills Domain Specification

## Entity: Skill

### Mandatory Fields

-   skill_name
-   capability_id (Links to Capability Framework)
-   skill_scale_definition (Reference to Global Proficiency Scale)
-   status (active | archived)

### Structured Fields

-   skill_description
-   observable_behaviours
-   assessment_method
-   target_proficiency_per_role

## Rating Model

-   Self Rating
-   Manager Rating
-   Delta stored explicitly
-   Ratings are evented and historical

## PersonSkill (Instance)

### Fields

-   person_id
-   skill_id
-   self_rating
-   manager_rating
-   effective_date
-   review_cycle_id
-   evidence_id (optional)

### Rules

-   Self and Manager ratings stored independently
-   Delta is derived, not stored
-   Ratings locked per ReviewCycle
