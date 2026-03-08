# PET – Schema List Screen

## Purpose
Defines the behaviour of the **Schema List Screen**, where admins view and manage schema versions.

---

## Screen Description

The Schema List Screen displays:
- Entity type (Customer, Lead, etc.)
- Schema version number
- Status: Draft / Active / Historical
- Created date
- Created by

---

## Allowed Actions

### Active Schema
- View (read-only)
- Clone to Draft

### Draft Schema
- Edit
- Delete draft
- Publish

### Historical Schema
- View only

---

## Prohibited Actions
- Editing Active schemas
- Editing Historical schemas
- Deleting schemas with historical data

---

## Visual Indicators
- Active schema clearly highlighted
- Historical schemas visually muted

---

**Authority**: Normative

