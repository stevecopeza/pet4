# PET – Environment & Configuration Principles

## Purpose
Defines how PET handles configuration safely.

## Rules
- Secrets never stored in code
- Secrets never stored in migrations
- Environment-specific config is explicit

## Storage
- Secrets: environment variables
- Settings: wp_options (namespaced)

**Authority**: Normative

