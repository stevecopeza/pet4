# Performance Benchmark Surface
STATUS: IMPLEMENTED
OWNING LAYER: UI / REST / Application
SURFACE: Admin page `pet-performance`

## Purpose
`Performance Benchmark` provides an admin-only, read-focused benchmark surface for PET runtime diagnostics. It captures probe metrics, simulated workload metrics, recommendations, and probe errors in a persisted benchmark run.

This is an operational benchmark surface, not a business KPI dashboard.

## Access and Permissions
- Page location: `PET → Performance`
- REST permission: `manage_options`
- UI component: `src/UI/Admin/components/Performance.tsx`
- REST controller: `src/UI/Rest/Controller/PerformanceController.php`

## Endpoints
- `GET /pet/v1/performance/latest`
  - returns the latest meaningful benchmark payload
  - prefers latest `completed` or `completed_with_errors`
  - falls back to latest `failed` run when no completed run exists
- `POST /pet/v1/performance/run`
  - triggers a benchmark run
  - persists run and metric outputs
  - returns payload plus run snapshot

## Benchmark Run Lifecycle
Run status model:
- `pending`
- `running`
- `completed`
- `completed_with_errors`
- `failed`
- `blocked_by_cooldown`

Execution safety controls:
- cooldown window enforced between benchmark runs
- lock guard to prevent concurrent benchmark execution
- workload metric collection active only while run state is active

Implementation: `src/Application/Performance/Service/PerformanceRunService.php`, `src/Infrastructure/Performance/WpBenchmarkRunStateStore.php`.

## UI Contract
The page renders:
- run summary and status
- probe metric groups (environment, php, database, cache, network)
- PET workload contract rows
- recommendation table
- probe errors table

Primary action:
- `Run Benchmark` button (POST run endpoint)

Read behavior:
- no data mutation controls for individual metrics
- non-contract workload keys are captured in API response as `workload_other` but not promoted to first-class rows in UI

## Workload Contract Keys
Contract keys promoted by REST/UI:
- `dashboard`
- `advisory.signals`
- `advisory.signals_work_item`
- `advisory.reports_list`
- `advisory.reports_latest`
- `advisory.reports_get`
- `advisory.reports_generate`
- `ticket.list`

## Persistence Model
Tables:
- `wp_pet_performance_runs`
- `wp_pet_performance_results`

Migration:
- `src/Infrastructure/Persistence/Migration/Definition/CreatePerformanceBenchmarkTables.php`
- registered in `src/Infrastructure/Persistence/Migration/MigrationRegistry.php`

Stores:
- run store: `src/Infrastructure/Persistence/Repository/SqlPerformanceRunStore.php`
- result store: `src/Infrastructure/Persistence/Repository/SqlPerformanceResultStore.php`

## Invariants
- benchmark data is append-only by run
- latest view is selection policy driven; it does not rewrite historical runs
- UI does not recalculate or backfill probe metrics
- recommendation and error metrics are represented explicitly, not hidden

## Related Documentation
- Dashboard principles: `10_dashboards/01_dashboard_and_kpi_views.md`
- Dashboard composition and manager surfaces: `10_dashboards/PET_Dashboard_Composition_And_Manager_Summary_Surfaces_v1.md`
- UI navigation contract: `13_ui_structure/01_wp_menu_and_navigation_structure.md`
- Screen contracts: `13_ui_structure/02_screen_level_contracts.md`

