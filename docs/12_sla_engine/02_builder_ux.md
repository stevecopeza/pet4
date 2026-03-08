# SLA Builder UX Contract

Audience: Commercial Management

## Builder Model

Structured Sections:

1.  Coverage
2.  SLA Mode (Single-tier or Tiered)
3.  Response Targets (single-tier) or Tier Configuration (tiered)
4.  Resolution Targets (single-tier) or per-tier resolution (tiered)
5.  Operating Calendar (single-tier) or per-tier calendar (tiered)
6.  Escalation Matrix (per tier for tiered SLAs)
7.  Tier Transition Cap % (tiered SLAs only, default 80)
8.  Exclusions
9.  Service Credits
10. Reporting Obligations

### Tier Builder (tiered SLAs)

For each tier the builder presents:
- Priority (evaluation order)
- Calendar selection (from published calendars)
- Response target (minutes)
- Resolution target (minutes)
- Escalation rules (threshold %, action, role)

Add/remove tiers dynamically. Minimum one tier.

Validation at publish: all tier calendars must provide complete time
coverage. Overlapping calendars rejected.

See docs_27_sla_engine_08_tiered_sla_spec.md for full specification.

## Output

-   Structured JSON stored (including tiers[] for tiered SLAs)
-   Customer-readable formatted output auto-generated
-   No freeform document editing

## Permissions

-   Draft editing: Commercial Management only
-   Sales: Select published SLA only
-   Support: Manual tier override (with audit trail)
