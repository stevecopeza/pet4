# PET – Schema Management UI Overview

## Purpose
Defines how administrators manage **malleable schemas and fields** in PET through the UI, without violating immutability or historical truth.

This document translates architectural rules into **explicit UI behaviour contracts**.

---

## Scope
Schema management applies to entities that support malleable fields, including (but not limited to):
- Customers
- Sites
- Contacts
- Leads
- Projects
- Tickets
- Knowledgebase Articles

---

## Core Principles
- Schemas are **versioned**
- Only one schema version is **Active** at a time
- Historical schemas are **read-only**
- Changes are **drafted**, then **explicitly published**
- Publishing is **irreversible**

---

## UI Location

WordPress Admin:
```
PET → Settings → Schemas & Malleable Fields
```

---

## Roles
- Only authorised administrators may manage schemas
- No project, sales, or support role may alter schemas

---

**Authority**: Normative

