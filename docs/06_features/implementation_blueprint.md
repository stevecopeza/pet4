# Implementation Blueprint

Milestone → Task structure.

Each Task includes: - title - description - duration_estimate -
role_id (FK → pet_roles) - base_internal_rate (snapshot from Role) -
sell_rate (snapshot from RateCard) - internal_cost_snapshot

Internal cost ceiling derived at sale.
