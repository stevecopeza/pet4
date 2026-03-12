# Documentation Status and Supersession Rules

## Status
PROPOSED AUTHORITATIVE FOUNDATION DOCUMENT

## Purpose
This document standardises how PET documents declare status, version, and supersession.
Its purpose is to reduce ambiguity when older and newer documents coexist.

## Required status header
All substantial PET documents should contain a header section near the top with at least:

- `Status`
- `Purpose`

Recommended additional metadata where appropriate:
- `Version`
- `Scope`
- `Supersedes`
- `Superseded By`

## Allowed status values

### AUTHORITATIVE
The document is intended to govern the topic within its authority layer.

### PROPOSED
The document is drafted for discussion and is not yet binding.
Implementation must not rely on it unless explicitly approved and adopted.

### DERIVED
The document operationalises or summarises higher authority.
It is useful, but not the source of truth.

### STAGING
The document is in a temporary holding location and is not yet part of the main authoritative tree.

### SUPERSEDED
The document remains in the repository for history/reference, but must no longer be used as the active source.

### TRANSITIONAL
The document exists to support migration between old and new models and must declare its limited scope clearly.

## Supersession rule
A document is only superseded when one of the following is true:

1. it explicitly says `Superseded By: <path>`
2. the newer replacement explicitly says `Supersedes: <path>`
3. a higher-authority governance document explicitly records the supersession

Newer filename alone does not supersede older authority.

## Versioning rule
Version increments should mean one of the following:
- corrected authority
- additive clarification
- explicit scope tightening
- substantive change in defined behaviour

Cosmetic edits alone should not create misleading semantic version jumps.

## Replacement rule
When replacing a document, prefer one of these patterns:

### Pattern A — direct replacement
- add new complete document
- mark old one superseded or remove it deliberately

### Pattern B — additive clarification
- leave core doc intact
- add a narrowly scoped clarification document
- make the relation explicit

### Pattern C — governance override
- add a higher-order governance document clarifying interpretation
- use this sparingly and only when broad correction is required

## Filename discipline
Filenames should help a reader determine:
- what the document is about
- whether it is likely authoritative or derived
- whether versioning matters

Avoid having many unrelated documents all use broad labels like:
- `master`
- `baseline`
- `full spec`
- `readme`

unless their scope is very clear from path placement.

## Repository hygiene rule
The following should not be treated as meaningful authority signals:
- `.DS_Store`
- `__MACOSX`
- exported PDFs when editable markdown authority also exists
- screenshots/mock images without supporting spec text

## Implementation rule
Prompts, execution checklists, and migration instructions should explicitly identify the authoritative documents they derive from.
They must not silently become substitute authority.
