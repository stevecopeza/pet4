# PET – People Management

## Overview
The People module (accessed via **Staff > People**) manages the internal users (Employees) of the PET system. It links WordPress Users to PET Employee profiles and extends them with domain-specific data.

## Capabilities

### 1. List View
- Displays all employees with key metadata:
  - ID
  - Avatar
  - Name
  - Email
  - Status (Active/Archived)
- **Features**:
  - Pagination (standard).
  - Bulk Selection.
  - Bulk Archive.

### 2. Add/Edit Employee
- **Core Fields**:
  - First Name
  - Last Name
  - Email (Work)
  - WP User Association (Dropdown of unassigned users).
  - Manager (Dropdown of active employees).
  - Teams (Multi-select for team membership).
  - Hire Date
  - Status
- **Malleable Fields**:
  - Dynamically rendered based on the `employee` Schema.
  - Supports text, number, boolean, date, etc.
- **Validation**:
  - Email uniqueness.
  - WP User uniqueness.

### 3. Archival Policy
- Employees are **Archived** (soft delete), not hard deleted, to ensure historical records (Time Entries, Quotes) remain valid.
- Archived employees:
  - Cannot log in / access PET.
  - Do not appear in "Active" assignment lists.
  - Can be restored.

## Technical Implementation
- **API**: `/pet/v1/employees` (GET, POST)
- **API**: `/pet/v1/employees/{id}` (PUT, DELETE/Archive)
- **Frontend**: `Employees.tsx`, `EmployeeForm.tsx` (Unified Add/Edit component).

**Authority**: Informational
