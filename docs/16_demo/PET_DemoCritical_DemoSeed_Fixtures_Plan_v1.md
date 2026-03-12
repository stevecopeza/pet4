# PET Demo-Critical Areas --- Demo Seed & Fixtures Plan v1.0

Date: 2026-02-26

## Purpose

Define minimal demo data required to convincingly showcase: - Helpdesk
activity - SLA warnings/breaches - Escalations and acknowledgements -
Advisory QBR snapshot outputs - People resilience SPOFs

This plan assumes DemoSeedService exists and can be extended safely.

------------------------------------------------------------------------

## 1. Required Demo Entities

### Customers (4 implemented)

-   1 VIP customer (RPM Resources)
-   3 regular customers (Acme Manufacturing, Nexus Startup Labs, Government Digital Services)

### Teams / Departments

-   Executive
-   Delivery Team
-   Support Team

### Employees (8 implemented)

-   1 executive (Steve Admin)
-   1 manager (Mia Manager)
-   2 implementation consultants (Liam Lead Tech, Ava Consultant)
-   1 support tech (Noah Support)
-   1 finance coordinator (Zoe Finance)
-   1 DevOps (Ethan DevOps)
-   1 analyst (Isabella Analyst)

### Skills / Certifications

-   1 scarce skill (e.g., "SYSPRO Integration")
-   1 common skill (e.g., "L1 Support")

### Capability Requirements

-   Support Team requires "L1 Support" minimum_people=2
-   Delivery Team requires "SYSPRO Integration" minimum_people=2
    (intentionally violated -\> SPOF)

------------------------------------------------------------------------

## 2. Tickets (13 implemented)

Create at least 13 tickets with variety: - 5 new/open (recent created) -
3 resolved (recent resolved) - 2 at warning threshold - 2 breached (one
VIP) - 1 additional for customer variety

Each ticket should have: - priority diversity - customer association -
team assignment (some unclaimed, some claimed)

------------------------------------------------------------------------

## 3. SLA Clock State Fixtures

For warning/breached tickets: - Set warning_at and breach_at relative to
now - Ensure one VIP ticket is breached to trigger a CRITICAL escalation

------------------------------------------------------------------------

## 4. Escalation Rules Fixtures

Create 3 rules: 1. On SLA_BREACH for VIP customer -\> severity CRITICAL
-\> target Support Manager -\> cooldown 240m 2. On SLA_BREACH for
non-VIP -\> severity HIGH -\> target L2 team -\> cooldown 240m 3. On
ADVISORY_SIGNAL type=SPOF_CRITICAL -\> severity HIGH -\> target Delivery
Manager -\> cooldown 1440m

------------------------------------------------------------------------

## 5. Advisory Fixtures

Ensure advisory signals exist for: - SLA risk cluster (multiple
warnings) - Capacity bottleneck (if implemented in generator) - SPOF
signal (from resilience analysis)

Then generate: - QBR Snapshot for last 90 days

------------------------------------------------------------------------

## 6. Demo Narrative (what must be observable)

1.  Helpdesk overview shows real recent created/resolved tickets
2.  SLA timers show warning and breach states
3.  Escalation list shows open escalations, including CRITICAL VIP
    breach
4.  A manager acknowledges an escalation (transition timeline visible)
5.  Resilience dashboard shows SPOF for scarce skill
6.  Advisory dashboard shows signals and a generated QBR snapshot
    referencing above

------------------------------------------------------------------------

## Acceptance Criteria

-   Demo data is deterministic and repeatable
-   No duplicates created on repeated seeding (idempotent seed run id)
-   All four subsystems have visible, linked surfaces
