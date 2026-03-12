# PET Migration Determinism Specification v1.0

## Problem

Web activation and WP-CLI use different migration lists.

## Requirement

Extract a single MigrationRegistry::all() used by both paths.

## Verification

Migration arrays must be byte-identical.
