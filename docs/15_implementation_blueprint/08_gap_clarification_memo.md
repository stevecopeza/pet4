# PET Commercial Engine v1.0 -- Developer Gap Clarification Memo

Status: Clarification Required Before Implementation Audience:
Development Team

  ---------
  Purpose
  ---------

This memo clarifies gaps identified in the initial implementation
feedback and aligns execution with the approved PET Commercial Engine
v1.0 Developer Execution Specification.

No development should proceed until these items are acknowledged.

  -----------------------------------------------
  1\. Quote Header Fields (Title & Description)
  -----------------------------------------------

The fields `title` and `description` are currently missing from the
pet_quotes table.

Required actions include:

-   Add columns to pet_quotes table
-   Update Domain Quote entity constructor
-   Update CreateQuote command
-   Update CloneQuote logic
-   Enforce immutability after Accepted state
-   Include in readiness validation

This is a Domain-layer change, not just a database change.

  ------------------------------------------------
  2\. Catalog Distinction (Products vs Services)
  ------------------------------------------------

> **UPDATE:** The `type` column approach described below has been superseded.
> The catalog is now **products only** (`pet_catalog_products`). Labour/service
> economics are modelled via **Role** (`base_internal_rate`), **ServiceType**
> (classification), and **RateCard** (sell pricing). See
> `07_commercial/07_Products_Roles_ServiceTypes_and_RateCards_v2.md`.

~~Adding a `type` column (product | service) is acceptable ONLY if domain
constraints are enforced.~~

The refactored model enforces:

- CatalogProduct: no rate fields, no role references — products only
- Role: carries `base_internal_rate` (internal cost); no sell rate
- RateCard: maps (role, service_type, optional contract) → sell_rate with date validity
- ServiceType: classification only, no pricing data

The Service Catalog entity (`ServiceCatalogItem`) no longer exists. Rate
integrity is enforced via the RateCard resolution algorithm.

  ------------------------------------------------
  3\. Section-Based Builder (Replace Line Items)
  ------------------------------------------------

The UI must implement an Add Section model:

Options: - Once-Off Product - Implementation Plan (WBS) - Recurring
Service - Commercial Adjustment

Mandatory UI capabilities: - Drag-and-drop section ordering - Live
margin calculation (sell, cost, margin %) - Readiness indicator -
Payment plan status indicator - Version delta tracking - Department
snapshot storage on tasks

A simple modal replacement is insufficient.

  --------------------------------------------------------------
  4\. Additional Required Components Not Previously Identified
  --------------------------------------------------------------

A. WBS Template Engine - Save WBS as template - Version templates -
Publish / deprecate - Deep clone on load - Store template_id +
template_version snapshot on Quote

B. Forecast Integration On QuoteApproved: - Extract WBS hours - Weight
by Opportunity probability - Insert ForecastCapacity records - Replace
on version supersession - Remove on rejection/expiry

C. Readiness Gate Before approval: - At least one component required -
Payment plan must be generated - Margin must be \>= 0 - Required fields
complete

Must be enforced in Domain layer.

D. CostAdjustment Entity - Sales cannot override internal cost -
Management override via explicit CostAdjustment entity - No silent
mutation of rate snapshots

E. Version Delta Summary - Auto-generate structured delta summary on new
Quote version - Persist for version history display

  -----------------------------------------
  5\. Procurement & Activation Separation
  -----------------------------------------

Lifecycle:

QuoteApproved → ProcurementForecast (non-executable) ContractActive →
ProcurementIntent (executable)

No procurement execution on mere acceptance.

  -------------------------------
  6\. Non-Negotiable Invariants
  -------------------------------

-   Accepted Quote immutable
-   Contract pricing immutable unless amended
-   Baseline changes require explicit re-baseline
-   Internal cost never silently modified
-   Templates deep-cloned and versioned
-   Forecast distinct from committed capacity

  -----------------------------------
  7\. Required Implementation Order
  -----------------------------------

1.  Migrations
2.  Domain entities & invariants
3.  Application command handlers
4.  Forecast engine
5.  Acceptance & baseline logic
6.  UI builder
7.  Dashboards
8.  Stress validation

No UI-first implementation.

  -------------
  End of Memo
  -------------
