# Complete Field Definitions -- Commercial Layer

## Leads

id (BIGINT, PK, auto-increment)
customer_id (BIGINT, FK, required)
subject (varchar 255, required)
description (text, nullable)
source (varchar 100, nullable) — e.g. email, phone, website, referral
status (enum: new, qualified, converted, disqualified)
assigned_to (BIGINT, nullable, FK → employees)
estimated_value (decimal 14,2, nullable)
converted_at (datetime, nullable) — set when lead transitions to converted
created_at (datetime)
updated_at (datetime)

## Quotes

id (BIGINT, PK, auto-increment)
customer_id (BIGINT, FK, required)
lead_id (BIGINT, FK → leads, nullable) — set when created via lead conversion
title (varchar 255, required)
description (text, required)
currency (char 3, required)
valid_from (date)
valid_until (date)
version_number (int)
supersedes_quote_id (BIGINT, nullable)
status (enum: draft, sent, accepted, rejected)
total_sell_value (decimal 14,2)
total_internal_cost (decimal 14,2)
total_margin (decimal 14,2)
created_by (BIGINT)
created_at (datetime)
updated_at (datetime)

## Quote Components

id (UUID) quote_id (UUID) component_type (enum: catalog, implementation,
recurring, adjustment) sort_order (int) sell_value (decimal 14,2)
internal_cost (decimal 14,2)

## Implementation Tasks

id (UUID) milestone_id (UUID) title (varchar 255) duration_hours
(decimal 8,2) role_catalog_item_id (UUID) department_snapshot (varchar
255) base_rate_snapshot (decimal 12,2) sell_rate_snapshot (decimal 12,2)
internal_cost_snapshot (decimal 14,2) sell_value_snapshot (decimal 14,2)
