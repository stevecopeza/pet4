# PET Payment Schedule — Prohibited Behaviours (v1.2)

**Repo target path:** `plugins/pet/docs/08_quotes/PET_Payment_Schedule_Prohibited_Behaviours_v1_2.md`

## Purpose
Explicit “must NOT happen” list to prevent integration drift and accidental defaults.

---

## P0 (Never allowed)

1) **Auto-create on quote creation**
- Creating a draft quote must never create a schedule block or items.

2) **Unconditional UI rendering**
- Quote screen must never render the schedule section unless the block exists in the current quote version.

3) **Synthetic projection injection**
- Backend must not invent a “Full Payment on acceptance” schedule row in read models unless persisted as a block.

4) **Mutation after acceptance**
- No edit, delete, or replace of schedule block/items on accepted quotes.
- No “silent fix” of rounding on accepted quotes.

5) **Implicit schedule normalization**
- Read handlers must not “repair” missing schedule by creating defaults.
- Only explicit commands may create/replace schedule blocks.

---

## P1 (Allowed only with explicit, audited decision later)
(Not part of v1.2; do not implement without a signed-off spec update.)

- Automatic invoice creation in QuickBooks
- Manual payment satisfaction toggles
- Partial payment allocations
- Multiple bases (labor/material/block totals)
- Multiple schedules per quote version

---

## Required guardrail tests
- New quote has no schedule block in payload
- UI does not render schedule when absent
- No insert into schedule tables occurs on quote create
- Accepted quote: schedule mutation endpoints return hard error
