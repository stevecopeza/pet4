# PET – Local Development Environment Variables

**Authority**: Normative
Date: 2026-04-22

---

## Required for WP-CLI commands: `PET_ENV` or `WP_ENV`

Two WP-CLI commands enforce an environment guard before executing:

- `wp pet migrate`
- `wp pet seed`

Both commands check `PET_ENV` (first) then `WP_ENV` (fallback). The value must be one
of `local`, `development`, or `dev` (case-insensitive). If neither variable is set, or
if the value is anything else (e.g. `production`, `staging`, or empty string), the
command exits immediately with an error:

```
Error: PET seed is restricted to local/dev environments (set PET_ENV or WP_ENV).
```

**This is intentional.** Running `seed` or `migrate` against production without explicit
environment configuration is treated as a safety failure, not a missing feature.

---

## How to set the environment variable

### Option 1 — wp-config.php (recommended for most local setups)

```php
// In wp-config.php, before the /* That's all, stop editing! */ line:
define( 'PET_ENV', 'local' );
```

Both commands read `getenv()`, and WordPress does not export `define()`d constants as
environment variables by default. Use the shell approach (Option 2) if `define()` does
not work with your local stack.

### Option 2 — Shell export (always works)

```bash
export PET_ENV=local
wp pet migrate
wp pet seed
```

Or inline:

```bash
PET_ENV=local wp pet migrate
PET_ENV=local wp pet seed
```

### Option 3 — `.env` file (Laravel Valet / Lando / DDEV)

Most local environment managers expose a `.env` file that is loaded before WP-CLI runs.
Add:

```
PET_ENV=local
```

---

## What `getenv()` actually reads

The guard uses PHP `getenv()`, which reads the **process environment**, not
WordPress constants. The precedence is:

1. `PET_ENV` (checked first, PET-specific)
2. `WP_ENV` (fallback, common in Bedrock/Roots setups)

If your local stack sets `WP_ENV=development` already (e.g. Bedrock), no additional
configuration is needed.

---

## Why `wp pet migrate` is also guarded

`wp pet migrate` creates or alters tables. On a shared hosting environment or a staging
instance without `PET_ENV` set, a developer accidentally running `wp pet migrate` is
recoverable — but it was a surface for mistakes. The guard ensures both migrate and seed
are explicit-only operations.

---

## What happens on CI/CD

If your CI pipeline runs `wp pet migrate` as part of a test suite setup, the environment
variable **must** be set in the CI environment:

```yaml
# GitHub Actions example
env:
  PET_ENV: local
```

Failing to set this in CI will cause `wp pet migrate` (and `wp pet seed` if used) to
abort. This is the desired behaviour — it surfaces the missing configuration early.

---

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| `Error: PET seed is restricted to local/dev environments` | Neither `PET_ENV` nor `WP_ENV` is set in the current shell | Run `export PET_ENV=local` or use inline form |
| Same error even after setting the variable | Using `define('PET_ENV', ...)` in `wp-config.php` — `define()` is not exported to `getenv()` | Use `$_SERVER['PET_ENV']` or shell `export` instead |
| CI pipeline fails on migrate | CI does not set `PET_ENV` | Add `PET_ENV: local` to CI environment config |
| Works interactively but not in cron | Cron jobs don't inherit shell variables | Set `PET_ENV` explicitly in the cron command string |

---

## Related commands that are NOT guarded

The following WP-CLI commands have no environment guard and can run on any environment:

- `wp pet purge` — still requires manual invocation; no guard needed (destructive intent is explicit)
- `wp pet reset` — no guard; operator intent is explicit
- `wp pet pulseway:poll`, `wp pet pulseway:sync-devices` — integration syncs; safe on any environment
- `wp pet performance:run` — read-only benchmark; safe anywhere

If any of these were ever to perform destructive writes in production, they would need
the same guard. Apply the pattern from `pet.php` (the `$env` check near `wp pet seed`).
