# PET – API Contract Principles

## Purpose
Defines non-negotiable rules for all PET APIs to prevent drift and ambiguity.

## Core Rules
- API is schema-first; OpenAPI is authoritative
- No endpoint exists unless documented
- No UI may invent payloads
- DTOs are versioned and immutable

## Error Semantics
- Hard errors by default
- Domain errors map 1:1 to HTTP responses

**Authority**: Normative

