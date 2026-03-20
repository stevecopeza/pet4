# PET Admin UI Modernization Foundation v1

## Purpose

Define a consistent, system-wide UI foundation for PET to eliminate
legacy CRUD patterns and unify interaction quality.

## Problem Statement

PET currently has two UI paradigms: - Modern, rich UX (dashboards,
support operational, advisory) - Legacy CRUD (tables, alerts, inline
styles)

This creates inconsistency, user friction, and slows future development.

## Objectives

-   Establish a unified design system
-   Replace blocking browser interactions (alert/confirm)
-   Standardize loading, empty, and error states
-   Normalize navigation and layout patterns
-   Enable safe, testable UI evolution

------------------------------------------------------------------------

## 1. Design System Foundations

### Core primitives

-   PageShell
-   Card / Panel
-   DataTable (enhanced)
-   Tabs
-   Form layout system
-   Modal / Dialog
-   Toast / Notification
-   StatusBadge
-   ActionBar

### Rules

-   No inline styles in feature components
-   No WP-specific styling (e.g. widefat) in modern surfaces
-   All UI composed from primitives

------------------------------------------------------------------------

## 2. Interaction Model

### Replace

-   alert() → Toast system
-   confirm() → ConfirmationDialog

### Requirements

-   Non-blocking interactions
-   Clear success/failure feedback
-   Partial failure handling for batch actions

------------------------------------------------------------------------

## 3. State Handling Standards

Every screen must support: - Loading (skeletons/spinners) - Empty states
(guided messaging) - Error states (retry + context)

No raw: - "Loading..." - inline red text errors

------------------------------------------------------------------------

## 4. Navigation & Layout

### Standardization

-   Single page shell
-   Consistent header + actions
-   Unified tab system
-   Deep-link support where applicable

------------------------------------------------------------------------

## 5. Form UX Standards

-   Inline validation
-   Clear field guidance
-   Consistent spacing/density
-   Explicit destructive action handling
-   No reliance on browser defaults

------------------------------------------------------------------------

## 6. Accessibility Baseline

-   Keyboard navigable
-   Proper focus management (dialogs)
-   Avoid color-only signals
-   Semantic components

------------------------------------------------------------------------

## 7. Testing Strategy

### Required coverage

-   Critical flows per module
-   Dialog interactions
-   Error handling
-   State transitions

------------------------------------------------------------------------

## 8. Modernization Order

### Phase 1 --- Foundation

-   Build primitives
-   Implement dialog/toast system

### Phase 2 --- Core modules

-   Customers
-   Projects
-   Time Entries
-   Employees
-   Settings

### Phase 3 --- Secondary modules

-   Approvals
-   Conversations
-   Activity
-   Knowledge
-   Escalations

### Phase 4 --- Coverage expansion

------------------------------------------------------------------------

## Non-negotiable rules

-   No business logic in UI
-   API remains authoritative
-   No regression of existing workflows
-   Changes must be additive and controlled

------------------------------------------------------------------------

## Outcome

PET transitions from mixed UI paradigms to a consistent, modern
operational interface aligned with its backend maturity.
