# PET Go-Live Sprint — Options A, B, C
## Implementation Plans
**Date:** 2026-04-21  
**Context:** Staff / Products / Services / Sales / Quote go-live preparation

---

## Status Summary

| Portal Area | State |
|-------------|-------|
| Employees | ✅ Done — 6-tab full-page detail, WP user provisioning |
| Customers | ✅ Done — full-page detail, overview + contacts tabs |
| Leads | ✅ Done — full-page detail, create/edit/convert/delete |
| Quotes | ✅ Done — full-page detail, submit/approve/reject, PDF, builder link |
| Catalog (Service Types + Products) | ✅ Done — both entity types, full CRUD |
| Quote Builder | ✅ Done — all block types, pricing panel, submit flow |
| Approvals | ✅ Done — manager approval queue |

---

## Option A — CatalogProduct Delivery Artefacts on Quote Acceptance ✅ DONE 2026-04-21

### Problem
`AcceptQuoteHandler::createTicketsFromQuote()` only provisions tickets for
`ImplementationComponent` and `OnceOffServiceComponent` blocks. A quote containing
`CatalogComponent` blocks (product or service SKUs) is accepted silently — no delivery
ticket is created, no work is queued. This is the documented ~10% delivery gap.

### Scope
- **Backend only** — no frontend changes required.
- One new private method in `AcceptQuoteHandler`.
- No new commands/handlers needed — reuses `CreateProjectTicketCommand`.
- No new migrations — ticket schema already supports these artefacts.

### Domain Model

A `CatalogComponent` contains one or more `QuoteCatalogItem` objects:

```
CatalogComponent
  └─ QuoteCatalogItem[]
       ├─ type: 'product' | 'service'
       ├─ sku: string
       ├─ roleId: ?int          (service items only)
       ├─ quantity: float
       ├─ unitSellPrice: float
       └─ unitInternalCost: float
```

### Ticket Provisioning Rules

| Condition | Result |
|-----------|--------|
| 0 items | Skip — no tickets |
| 1 item | One direct delivery ticket |
| N > 1 items | One rollup ticket (`is_rollup=true`) + N child tickets |

**Per item:**
- `subject`: `"Fulfil: {qty}× {sku}"` (for products) / `"Deliver service: {qty}× {sku}"` (for services)
- `description`: `"Catalog item – SKU: {sku}, Quantity: {qty}, Unit price: {unitSellPrice}"`
- `soldMinutes`: 0 (catalog items are not time-tracked at provisioning)
- `soldValueCents`: `(int)($item->unitSellPrice() * $item->quantity() * 100)`
- `estimatedMinutes`: 0
- `requiredRoleId`: `$item->roleId()` for services, `null` for products
- `sourceType`: `'quote_component'`
- `sourceComponentId`: `$component->id()` (same for all items; idempotency key is `(projectId, sourceComponentId, parentTicketId)`)
- `lifecycle_owner`: `'project'`

**Rollup ticket:**
- `subject`: `"Catalog fulfilment ({N} items)"`
- `isRollup`: true
- `soldMinutes`: 0, `soldValueCents`: sum of all items

### Code Changes

**File:** `src/Application/Commercial/Command/AcceptQuoteHandler.php`

**Change 1 — `createTicketsFromQuote()` (line ~110):**
Add `CatalogComponent` to the guard that triggers project creation, and
call `provisionCatalogComponentTickets()` in the dispatch loop.

```php
// Before (guard only checks two types):
if (!$quote->hasImplementationComponents() && !$quote->hasOnceOffServiceComponents()) {
    return;
}

// After (also checks for catalog components):
if (!$quote->hasImplementationComponents()
    && !$quote->hasOnceOffServiceComponents()
    && !$this->hasCatalogComponents($quote)) {
    return;
}
```

**Change 2 — add dispatch in the component loop:**
```php
foreach ($quote->components() as $component) {
    if ($component instanceof ImplementationComponent) {
        $this->provisionImplementationComponentTickets(...);
    } elseif ($component instanceof OnceOffServiceComponent) {
        $this->provisionOnceOffServiceComponentTickets(...);
    } elseif ($component instanceof CatalogComponent) {
        $this->provisionCatalogComponentTickets($component, $quote, $projectId);
    }
}
```

**Change 3 — new method:**
```php
private function provisionCatalogComponentTickets(
    CatalogComponent $component,
    Quote $quote,
    int $projectId
): void {
    $items = $component->items();
    if (empty($items)) {
        return;
    }

    $customerId = (int)$quote->customerId();
    $quoteId    = (int)$quote->id();
    $compId     = (int)$component->id();

    $parentTicketId = null;
    if (count($items) > 1) {
        $totalValueCents = (int)array_sum(
            array_map(fn($i) => $i->unitSellPrice() * $i->quantity() * 100, $items)
        );
        $parentTicketId = $this->createProjectTicketHandler->handle(
            new CreateProjectTicketCommand(
                $customerId, $projectId, $quoteId,
                sprintf('Catalog fulfilment (%d items)', count($items)),
                'Rollup ticket for catalogue component delivery.',
                0, $totalValueCents, 0,
                null, null, null, null,
                'quote_component', $compId,
                null, true
            )
        );
        if ($parentTicketId <= 0) {
            throw new \RuntimeException(
                'Quote acceptance failed: unable to create catalog fulfilment rollup.'
            );
        }
    }

    foreach ($items as $item) {
        $type        = $item->type();
        $sku         = $item->sku();
        $qty         = $item->quantity();
        $valueCents  = (int)($item->unitSellPrice() * $qty * 100);
        $subject     = $type === 'service'
            ? sprintf('Deliver service: %s× %s', $qty, $sku)
            : sprintf('Fulfil: %s× %s', $qty, $sku);
        $description = sprintf(
            'Catalog item – SKU: %s, Quantity: %s, Unit price: %s',
            $sku, $qty, number_format($item->unitSellPrice(), 2)
        );

        $ticketId = $this->createProjectTicketHandler->handle(
            new CreateProjectTicketCommand(
                $customerId, $projectId, $quoteId,
                $subject, $description,
                0, $valueCents, 0,
                null,
                $type === 'service' ? $item->roleId() : null,
                null, null,
                'quote_component', $compId,
                $parentTicketId, false
            )
        );
        if ($ticketId <= 0) {
            throw new \RuntimeException(
                sprintf('Quote acceptance failed: unable to create ticket for SKU %s.', $sku)
            );
        }
    }
}
```

**Change 4 — helper method:**
```php
private function hasCatalogComponents(Quote $quote): bool
{
    foreach ($quote->components() as $c) {
        if ($c instanceof CatalogComponent) {
            return true;
        }
    }
    return false;
}
```

### Testing
- Add a PHPUnit test: `AcceptQuoteHandlerCatalogTest` covering:
  - Single item → one ticket, no rollup
  - Multiple items → rollup + N children
  - Empty component → no tickets created
  - Mixed quote (impl + catalog) → both provisioned
- Verify idempotency: accepting twice should not double-create tickets

### Files Changed
```
src/Application/Commercial/Command/AcceptQuoteHandler.php   (modified)
tests/Unit/Application/Commercial/AcceptQuoteHandlerCatalogTest.php  (new)
```

### Done Criteria
- [x] Quote with only catalog products accepted → delivery tickets created
- [x] Quote with only catalog services accepted → delivery tickets with requiredRoleId
- [x] Mixed quote (impl + catalog) → both ticket types created
- [x] Empty catalog component → no crash, no ticket
- [x] All unit tests pass (374 tests, up from 366 — 8 new)
- [x] `composer analyse` still exits 0

### Files Changed
```
src/Application/Commercial/Command/AcceptQuoteHandler.php              (modified)
tests/Unit/Application/Commercial/Command/AcceptQuoteHandlerCatalogTest.php  (new — 8 tests)
```

---

## Option B — Staff Mobile Experience (Phase 3 + 4) ✅ DONE 2026-04-21

### Overview
Two standalone WordPress pages for staff use from mobile:
- **`[pet_log_time]`** — Log time against tickets (Phase 3)
- **`[pet_my_approvals]`** — Approve/reject quotes from phone (Phase 4)

Both are React SPAs mounted by PHP shortcodes, using a new `staff` Vite entry point.

### Architecture

```
Vite entry:  src/UI/Staff/main.tsx
             └─ App.tsx  (mount point: #pet-staff-app)
                  ├─ TimeCapturePage   (for [pet_log_time])
                  └─ StaffApprovalsPage (for [pet_my_approvals])

PHP:  src/UI/Shortcode/StaffShortcodes.php
      ├─ renderLogTime()     → enqueues 'staff' bundle, renders <div id="pet-staff-app" data-view="time">
      └─ renderMyApprovals() → enqueues 'staff' bundle, renders <div id="pet-staff-app" data-view="approvals">
```

The `main.tsx` reads `dataset.view` from the mount div to decide which component to render.

### Phase 3 — `[pet_log_time]` Time Capture

**REST API used:**
- `GET  /pet/v1/staff/time-capture/context` — current employee + ticket suggestions
- `GET  /pet/v1/staff/time-capture/entries` — today's entries for current user
- `POST /pet/v1/staff/time-capture/entries` — create new entry

**UI Design (mobile-first):**

```
┌─────────────────────────────────┐
│  Log Time                  Today│
│  Mon 21 Apr                     │
├─────────────────────────────────┤
│  Ticket ▼                       │  ← searchable select (ticket suggestions)
│  Start  [08:30]   End  [10:00]  │  ← time pickers
│  Notes  [................................] │
│  [💾 Save]                     │
├─────────────────────────────────┤
│  Today's entries                │
│  ┌──────────────────────────┐  │
│  │ TKT-042  08:30–10:00  1h30│  │
│  │ TKT-007  11:00–12:30  1h30│  │
│  └──────────────────────────┘  │
│  Total: 3h00                    │
└─────────────────────────────────┘
```

**State:**
```typescript
interface TimeEntry {
  id: number;
  ticketId: number;
  ticketRef: string;
  start: string;    // ISO8601
  end: string;
  duration: number; // minutes
  description: string;
}

interface TimeCaptureState {
  tickets: TicketSuggestion[];  // from context endpoint
  entries: TimeEntry[];         // today's entries
  form: { ticketId: number|null; start: string; end: string; description: string; };
  saving: boolean;
  error: string|null;
}
```

**Key behaviour:**
- Default start = now rounded to nearest 15 min
- Default end = now
- Duration calculated live from start/end diff
- On save: POST → reload entries → clear form
- "Start now" shortcut button sets start = current time

### Phase 4 — `[pet_my_approvals]` Staff Approvals

**REST API used:**
- `GET  /pet/v1/quotes?state=pending_approval` — pending quotes
- `POST /pet/v1/quotes/{id}/approve` — approve quote
- `POST /pet/v1/quotes/{id}/reject` — reject quote (body: `{ note: string }`)

**UI Design (mobile-first):**

```
┌─────────────────────────────────┐
│  Approvals                  (3) │
├─────────────────────────────────┤
│  ┌──────────────────────────┐  │
│  │ Q-0042  RPM Resources    │  │
│  │ Website Rebuild  £14,500 │  │
│  │ Submitted: 20 Apr        │  │
│  │  [✓ Approve] [✗ Reject]  │  │
│  └──────────────────────────┘  │
│  ┌──────────────────────────┐  │
│  │ Q-0043  Acme Mfg         │  │
│  │ Security Audit   £8,200  │  │
│  │  [✓ Approve] [✗ Reject]  │  │
│  └──────────────────────────┘  │
└─────────────────────────────────┘

[Reject modal]
┌─────────────────────────────────┐
│  Reject Q-0042                  │
│  Reason: [___________________]  │
│  [Cancel]         [Confirm Reject] │
└─────────────────────────────────┘
```

**Key behaviour:**
- Approve: single tap → confirm → POST approve → remove from list
- Reject: tap → modal for reason note → POST reject → remove from list
- Empty state: "No quotes awaiting your approval"
- Badge count shown in header

### Vite Config Change
```typescript
// vite.config.ts — add staff entry:
input: {
  admin:  path.resolve(__dirname, 'src/UI/Admin/main.tsx'),
  portal: path.resolve(__dirname, 'src/UI/Portal/main.tsx'),
  staff:  path.resolve(__dirname, 'src/UI/Staff/main.tsx'),   // NEW
}
```

### PHP Registration
```php
// src/UI/Shortcode/ShortcodeRegistrar.php — add two new shortcodes:
add_shortcode('pet_log_time',     [$this, 'renderLogTime']);
add_shortcode('pet_my_approvals', [$this, 'renderMyApprovals']);

public function renderLogTime(): string {
    if (!is_user_logged_in()) return '';
    wp_enqueue_script('pet-staff', PLUGIN_URL . 'dist/staff.js', [], VERSION, true);
    wp_enqueue_style('pet-staff',  PLUGIN_URL . 'dist/staff.css', [], VERSION);
    wp_localize_script('pet-staff', 'petStaffConfig', [
        'nonce'  => wp_create_nonce('wp_rest'),
        'apiUrl' => rest_url('pet/v1/'),
        'userId' => get_current_user_id(),
    ]);
    return '<div id="pet-staff-app" data-view="time"></div>';
}

public function renderMyApprovals(): string {
    if (!is_user_logged_in()) return '';
    wp_enqueue_script('pet-staff', PLUGIN_URL . 'dist/staff.js', [], VERSION, true);
    wp_enqueue_style('pet-staff',  PLUGIN_URL . 'dist/staff.css', [], VERSION);
    wp_localize_script('pet-staff', 'petStaffConfig', [
        'nonce'  => wp_create_nonce('wp_rest'),
        'apiUrl' => rest_url('pet/v1/'),
        'userId' => get_current_user_id(),
    ]);
    return '<div id="pet-staff-app" data-view="approvals"></div>';
}
```

### Files to Create/Change
```
src/UI/Staff/main.tsx                  (new — entry point)
src/UI/Staff/App.tsx                   (new — view router)
src/UI/Staff/pages/TimeCapturePage.tsx (new)
src/UI/Staff/pages/StaffApprovalsPage.tsx (new)
src/UI/Staff/components/TicketSelect.tsx  (new)
src/UI/Staff/staff.css                 (new — mobile styles)
src/UI/Shortcode/ShortcodeRegistrar.php   (modified — add 2 shortcodes)
vite.config.ts                         (modified — add staff entry)
```

### Done Criteria — Phase 3
- [x] `[pet_log_time]` shortcode registered → renders React SPA with data-view="time"
- [x] Ticket selector populated from `/staff/time-capture/context` endpoint
- [x] Create time entry → POST to `/staff/time-capture/entries` + reload
- [x] Today's total minutes calculated and displayed
- [x] Mobile-first CSS at 375px viewport (max-width: 520px centered)

### Done Criteria — Phase 4
- [x] `[pet_my_approvals]` upgraded to React SPA (replaced 180-line PHP renderer)
- [x] Pending quotes loaded from `GET /quotes` filtered client-side to `pending_approval`
- [x] Approve → `POST /quotes/{id}/approve` → card removed from list
- [x] Reject with note → `POST /quotes/{id}/reject-approval` → card removed
- [x] Empty state renders when no pending quotes
- [x] Urgency colour-coding on cards (green/amber/red by days pending)
- [x] Works on 375px viewport

### Files Changed
```
src/UI/Staff/main.tsx                          (new — Vite entry point)
src/UI/Staff/App.tsx                           (new — view router)
src/UI/Staff/pages/TimeCapturePage.tsx         (new — Phase 3)
src/UI/Staff/pages/StaffApprovalsPage.tsx      (new — Phase 4)
src/UI/Staff/staff.css                         (new — mobile styles)
src/UI/Shortcode/ShortcodeRegistrar.php        (modified — renderLogTime new, renderMyApprovals upgraded, enqueueStaffAssets helper)
vite.config.ts                                 (modified — staff entry added)
dist/assets/staff-*.js + dist/assets/staff-*.css  (built)
```

---

## Option C — Admin UI Completeness ✅ DONE 2026-04-21

### C1 — WorkItems Item-Level Actions ✅ DONE 2026-04-21

**Current state:** Queue view works. Drill-through to ticket works. Pull (assign to self)
and Return-to-queue work. Missing: reassign to specific agent, close ticket, escalate.

**Required REST endpoints (confirm exist or add):**
- `POST /pet/v1/tickets/{id}/reassign` — body: `{ employeeUserId: string }` ✅ pre-existing
- `POST /pet/v1/tickets/{id}/close` — body: `{ resolution?: string }` ✅ added
- `POST /pet/v1/tickets/{id}/escalate` — out of scope; not implemented

**UI additions to `WorkItems.tsx`:**
Each row in the data table gets a `⋯` KebabMenu overflow menu:
```
[Pull to me] [Return] [···]
                       ├─ Pull to me
                       ├─ Return to Queue
                       ├─ ─────────────
                       ├─ Reassign…   → employee picker modal (lazy-loads GET /employees)
                       ├─ Resolve…    → confirm dialog with optional resolution note (danger; disabled if terminal)
                       ├─ ─────────────
                       └─ View
```

**Implementation notes:**
- `POST /tickets/{id}/close` reuses `UpdateTicketHandler` — fetches current ticket, sets `status='resolved'`
- `Employee` type added: `{ id, wpUserId, firstName, lastName, status }`
- `ActiveModal` union type: `{ type: 'close' | 'reassign', item: QueueItem }`
- `ensureEmployees()` lazy-loads employees on first modal open
- `isTerminal()` helper disables Resolve for already-closed tickets

**Files:**
```
src/UI/Admin/components/WorkItems.tsx          (modified — CloseModal, ReassignModal, KebabMenu actions)
src/UI/Rest/Controller/TicketController.php    (modified — POST /tickets/{id}/close endpoint added)
```

### C2 — Advisory Report Generation Button ✅ DONE (pre-existing)

**Current state:** Button already present in `Advisory.tsx` at line 307 — `onClick={generateReport}`,
disabled when `!customerId`. No code change required.

### Done Criteria
- [x] WorkItems: reassign action available on each row (Reassign… → modal → POST /tickets/{id}/reassign)
- [x] WorkItems: close/resolve action available on each row (Resolve… → modal → POST /tickets/{id}/close)
- [x] POST /tickets/{id}/close endpoint added to TicketController
- [x] Advisory: Generate Report button visible and functional (pre-existing)
- [x] TypeScript build passes (tsc --noEmit, 162 modules)
- [x] PHPStan exits 0 (501 files analysed)
- [x] All 374 unit tests pass (856 assertions)

### Files Changed
```
src/UI/Admin/components/WorkItems.tsx          (modified — item-level actions, CloseModal, ReassignModal)
src/UI/Rest/Controller/TicketController.php    (modified — POST /tickets/{id}/close)
```

---

## Implementation Order for Today

```
1. Option A  — AcceptQuoteHandler catalog provisioning  (~3–4 h, backend)
   └─ PHPUnit tests
   └─ composer analyse passes

2. Option B3 — [pet_log_time] shortcode + TimeCapturePage  (~3 h, frontend)
   └─ Vite staff entry, PHP shortcode registration

3. Option B4 — [pet_my_approvals] shortcode + StaffApprovalsPage  (~2 h, frontend)

4. Option C2 — Advisory generate button  (~30 min, trivial)

5. Option C1 — WorkItems actions  (~2 h, if time permits)
```
