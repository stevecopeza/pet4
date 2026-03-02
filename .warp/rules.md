# PET Plugin — Project Rules

## WordPress Environment
- **Site URL**: https://pet4.cope.zone/
- **WP Admin**: https://pet4.cope.zone/wp-admin/
- **REST API base**: https://pet4.cope.zone/wp-json/pet/v1/
- **Admin user**: admin
- **Admin password**: stc54

## Seed & Purge (REST endpoints — no auth required)
- **Seed**: `POST https://pet4.cope.zone/wp-json/pet/v1/system/seed_full`
- **Purge**: `POST https://pet4.cope.zone/wp-json/pet/v1/system/purge` (body: `{"seed_run_id":"<id>"}`)

## Build & Test
- **Frontend build**: `npm run build` (tsc + vite)
- **PHP lint**: `php -l <file>`
- **Unit tests**: `php vendor/bin/phpunit --configuration phpunit.xml --testsuite Unit`
- **WP CLI**: `wp --path=/Users/stevecope/Sites/pet4`

## Conventions
- Always discuss and document before coding
- Plans use `create_plan` tool; never code without approval
- Co-author line on commits: `Co-Authored-By: Oz <oz-agent@warp.dev>`
- Documentation lives in `docs/`; update docs when adding features
- Use `-k` flag with curl for HTTPS calls (self-signed cert)
