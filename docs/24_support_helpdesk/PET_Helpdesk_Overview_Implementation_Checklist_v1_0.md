# PET Helpdesk Overview Implementation Checklist v1.0

Follow in order.

1) Docs
- Add docs to plugins/pet/docs/ (3 files)

2) Shortcode wiring
- Extend existing shortcode registrar pattern (same as the current 4 shortcodes)

3) Handler
- Parse attributes + defaults
- Auth + capability check
- Build DTOs from existing read model only
- Sort/group for lanes and KPIs

4) Templates
- Implement DOM structure per UI Contract (manager + wallboard)
- Degrade gracefully if SLA fields absent

5) Assets
- CSS: tokens + components + wallboard overrides
- JS: optional refresh (full page refresh OK for v1)
- Enqueue only when shortcode exists on page

6) Admin listing page
- Add [pet_helpdesk] entry + examples + copy button

7) Tests
- Unit: attribute defaults
- Integration: logged-in renders headings
- Security: anonymous does not output ticket refs
