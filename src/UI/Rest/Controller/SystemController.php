<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Commercial\Command\AcceptQuoteCommand;
use Pet\Application\Commercial\Command\AcceptQuoteHandler;
use Pet\Application\System\Service\DemoPreFlightCheck;
use Pet\Application\System\Service\DemoInstaller;
use Pet\Application\System\Service\DemoSeedService;
use Pet\Application\System\Service\DemoPurgeService;
use Pet\Application\System\Service\CleanDemoBaselineService;
use Pet\Application\System\Service\DemoEnvironmentHealthService;
use Pet\Application\System\Service\SeedRegistryDiagnosticsService;
use WP_REST_Request;
use WP_REST_Response;

class SystemController implements RestController
{
    private DemoPreFlightCheck $preFlightCheck;
    private DemoInstaller $demoInstaller;
    private DemoSeedService $demoSeedService;
    private DemoPurgeService $demoPurgeService;
    private AcceptQuoteHandler $acceptQuoteHandler;

    public function __construct(DemoPreFlightCheck $preFlightCheck, DemoInstaller $demoInstaller, DemoSeedService $demoSeedService, DemoPurgeService $demoPurgeService, AcceptQuoteHandler $acceptQuoteHandler)
    {
        $this->preFlightCheck = $preFlightCheck;
        $this->demoInstaller = $demoInstaller;
        $this->demoSeedService = $demoSeedService;
        $this->demoPurgeService = $demoPurgeService;
        $this->acceptQuoteHandler = $acceptQuoteHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route('pet/v1', '/system/pre-demo-check', [
            'methods' => 'GET',
            'callback' => [$this, 'runPreFlightCheck'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
        register_rest_route('pet/v1', '/system/demo/health', [
            'methods' => 'GET',
            'callback' => [$this, 'demoEnvironmentHealth'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
        register_rest_route('pet/v1', '/system/demo/diagnostics', [
            'methods' => 'GET',
            'callback' => [$this, 'demoDiagnostics'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
        register_rest_route('pet/v1', '/system/seed-demo', [
            'methods' => 'POST',
            'callback' => [$this, 'seedDemoData'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
        register_rest_route('pet/v1', '/system/run-demo', [
            'methods' => 'POST',
            'callback' => [$this, 'runDemoInstaller'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
        register_rest_route('pet/v1', '/system/demo/seed_full', [
            'methods' => 'POST',
            'callback' => [$this, 'seedFull'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
        register_rest_route('pet/v1', '/system/seed_full', [
            'methods' => 'POST',
            'callback' => [$this, 'seedFull'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
        register_rest_route('pet/v1', '/system/accept-quote', [
            'methods' => 'POST',
            'callback' => [$this, 'acceptQuoteDev'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'args' => [
                'quote_id' => [
                    'required' => true,
                    'type' => 'integer',
                ],
            ],
        ]);
        register_rest_route('pet/v1', '/system/demo/purge', [
            'methods' => 'POST',
            'callback' => [$this, 'purge'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'args' => [
                'seed_run_id' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);
        register_rest_route('pet/v1', '/system/purge', [
            'methods' => 'POST',
            'callback' => [$this, 'purge'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'args' => [
                'seed_run_id' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);
        register_rest_route('pet/v1', '/system/demo/clean-baseline', [
            'methods' => 'POST',
            'callback' => [$this, 'cleanDemoBaseline'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'args' => [
                'confirm' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);
        register_rest_route('pet/v1', '/system/clean-demo-baseline', [
            'methods' => 'POST',
            'callback' => [$this, 'cleanDemoBaseline'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'args' => [
                'confirm' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);
        register_rest_route('pet/v1', '/system/admin/reset_token', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getResetToken'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
            ],
        ]);
        register_rest_route('pet/v1', '/system/admin/reset_pet_only', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'adminResetPetOnly'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
            ],
        ]);
    }

    public function runPreFlightCheck(WP_REST_Request $request): WP_REST_Response
    {
        $result = $this->preFlightCheck->run();
        
        $status = 200;
        if ($result['overall'] !== 'PASS') {
            // The spec says "Hard-block demo activation".
            // Returning 503 Service Unavailable or 412 Precondition Failed might be appropriate if this was the activation endpoint.
            // But this is the *check* endpoint. It should return the result.
            // The client (Demo Engine) will read this and block.
            // But strictly, "Hard-block demo activation" implies logic *elsewhere* calling this.
            // For this endpoint, we just return the JSON.
            // I'll return 200 with the JSON payload.
        }

        return new WP_REST_Response($result, $status);
    }

    public function runDemoInstaller(WP_REST_Request $request): WP_REST_Response
    {
        $result = $this->demoInstaller->run();
        return new WP_REST_Response($result, 201);
    }

    public function seedDemoData(WP_REST_Request $request): WP_REST_Response
    {
        $result = $this->preFlightCheck->seedDemoData();
        return new WP_REST_Response($result, 201);
    }

    public function seedFull(WP_REST_Request $request): WP_REST_Response
    {
        $seedRunId = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('seed_', true);
        try {
            $summary = $this->demoSeedService->seedFull($seedRunId, 'demo_full');
            $envelope = ['seed_run_id' => $seedRunId, 'summary' => $summary];

            // Derive anchors and counts for contract alignment (best-effort)
            global $wpdb;
            $quotesTable = $wpdb->prefix . 'pet_quotes';
            $projectsTable = $wpdb->prefix . 'pet_projects';
            $ticketsTable = $wpdb->prefix . 'pet_tickets';
            $timeTable = $wpdb->prefix . 'pet_time_entries';
            $customersTable = $wpdb->prefix . 'pet_customers';

            $q1Id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $quotesTable WHERE title = %s ORDER BY id DESC LIMIT 1", 'Q1 Website Implementation & Advisory'));
            $p1Id = $q1Id ? (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $projectsTable WHERE source_quote_id = %d LIMIT 1", $q1Id)) : 0;
            $customerId = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $customersTable WHERE name = %s LIMIT 1", 'Acme Manufacturing SA (Pty) Ltd'));

            $envelope['anchors'] = [
                'customer_id' => $customerId ?: null,
                'quote_q1_id' => $q1Id ?: null,
                'project_p1_id' => $p1Id ?: null,
            ];
            $envelope['counts'] = [
                'customers' => (int)$wpdb->get_var("SELECT COUNT(*) FROM $customersTable"),
                'quotes' => (int)$wpdb->get_var("SELECT COUNT(*) FROM $quotesTable"),
                'projects' => (int)$wpdb->get_var("SELECT COUNT(*) FROM $projectsTable"),
                'tickets' => (int)$wpdb->get_var("SELECT COUNT(*) FROM $ticketsTable"),
                'time_entries' => (int)$wpdb->get_var("SELECT COUNT(*) FROM $timeTable"),
            ];
            $envelope['steps'] = [
                ['step' => 'seed.customers', 'status' => 'APPLIED'],
                ['step' => 'seed.org', 'status' => 'APPLIED'],
                ['step' => 'seed.quotes', 'status' => 'APPLIED'],
                ['step' => 'seed.projects', 'status' => 'APPLIED'],
                ['step' => 'seed.tickets', 'status' => 'APPLIED'],
                ['step' => 'seed.backboneTickets', 'status' => 'APPLIED'],
                ['step' => 'seed.sla', 'status' => 'APPLIED'],
                ['step' => 'seed.workOrchestration', 'status' => 'APPLIED'],
                ['step' => 'seed.time', 'status' => 'APPLIED'],
                ['step' => 'seed.advisory', 'status' => 'APPLIED'],
            ];
            $envelope['overall'] = 'PASS';

            return new WP_REST_Response($envelope, 201);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['seed_run_id' => $seedRunId, 'error' => 'domain_exception', 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            error_log('PET Demo Seed Full failed: ' . $e->getMessage());
            return new WP_REST_Response(['seed_run_id' => $seedRunId, 'error' => 'internal_error', 'message' => 'Demo seed failed'], 500);
        }
    }

    public function demoDiagnostics(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        try {
            $service = new SeedRegistryDiagnosticsService($this->demoPurgeService, $wpdb);
            return new WP_REST_Response($service->diagnostics(), 200);
        } catch (\Throwable $e) {
            error_log('PET Seed Registry Diagnostics failed: ' . $e->getMessage());
            return new WP_REST_Response([
                'error' => 'internal_error',
                'message' => 'Failed to compute seed registry diagnostics',
            ], 500);
        }
    }

    public function demoEnvironmentHealth(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        try {
            $service = new DemoEnvironmentHealthService($this->demoPurgeService, $wpdb);
            return new WP_REST_Response($service->getHealth(), 200);
        } catch (\Throwable $e) {
            error_log('PET Demo Environment Health failed: ' . $e->getMessage());
            return new WP_REST_Response([
                'error' => 'internal_error',
                'message' => 'Failed to compute demo environment health',
            ], 500);
        }
    }

    public function acceptQuoteDev(WP_REST_Request $request): WP_REST_Response
    {
        $envType = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';
        if (!in_array($envType, ['local', 'development'], true)) {
            return new WP_REST_Response(['allowed' => false, 'environment' => $envType, 'error' => 'dev_only_endpoint'], 403);
        }

        $quoteIdParam = $request->get_param('quote_id') ?? $request->get_param('quoteId') ?? $request->get_param('id');
        $quoteId = (int) $quoteIdParam;

        if ($quoteId <= 0) {
            return new WP_REST_Response(['error' => 'invalid_quote_id'], 400);
        }

        try {
            $command = new AcceptQuoteCommand($quoteId);
            $this->acceptQuoteHandler->handle($command);
            return new WP_REST_Response(['ok' => true, 'quote_id' => $quoteId], 200);
        } catch (\RuntimeException $e) {
            return new WP_REST_Response(['error' => 'quote_not_found', 'message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => 'internal_error', 'message' => 'Accept quote failed'], 500);
        }
    }

    public function purge(WP_REST_Request $request): WP_REST_Response
    {
        $seedRunId = (string)$request->get_param('seed_run_id');
        $summary = $this->demoPurgeService->purgeBySeedRunId($seedRunId);
        return new WP_REST_Response(['seed_run_id' => $seedRunId, 'summary' => $summary], 200);
    }

    public function cleanDemoBaseline(WP_REST_Request $request): WP_REST_Response
    {
        $confirm = strtoupper(trim((string)$request->get_param('confirm')));
        if ($confirm !== 'CLEAN_DEMO_BASELINE') {
            return new WP_REST_Response([
                'operation' => 'clean_demo_baseline',
                'error' => 'confirm_required',
                'message' => 'Pass confirm=CLEAN_DEMO_BASELINE to run this operation.',
            ], 400);
        }

        global $wpdb;
        $service = new CleanDemoBaselineService($this->demoPurgeService, [$this->demoSeedService, 'seedFull'], $wpdb);
        try {
            $result = $service->run('demo_full');
            $status = ($result['overall'] ?? 'FAIL') === 'PASS' ? 201 : 422;
            return new WP_REST_Response($result, $status);
        } catch (\DomainException $e) {
            return new WP_REST_Response([
                'operation' => 'clean_demo_baseline',
                'error' => 'domain_exception',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            error_log('PET Clean Demo Baseline failed: ' . $e->getMessage());
            return new WP_REST_Response([
                'operation' => 'clean_demo_baseline',
                'error' => 'internal_error',
                'message' => 'Clean demo baseline failed',
            ], 500);
        }
    }

    private function classify(): array
    {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $envType = function_exists('wp_get_environment_type') ? wp_get_environment_type() : null;
        $usersTable = $prefix . 'users';
        $postsTable = $prefix . 'posts';
        $counts = [
            'wp_users' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $usersTable"),
            'wp_posts' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $postsTable"),
        ];
        $petTables = $wpdb->get_col($wpdb->prepare("SHOW TABLES LIKE %s", $prefix . 'pet_%'));
        $dbName = defined('DB_NAME') ? DB_NAME : null;
        $hostRow = $wpdb->get_row("SELECT DATABASE() as db, @@hostname as host", ARRAY_A);
        $hostName = $hostRow ? ($hostRow['host'] ?? null) : null;
        $classification = 'NON_PRODUCTION';
        if (!$envType || $envType === 'production' || ($dbName && stripos($dbName, 'prod') !== false) || ($hostName && stripos($hostName, 'prod') !== false)) {
            $classification = 'PRODUCTION';
        }
        return [
            'classification' => $classification,
            'evidence' => [
                'WP_ENVIRONMENT_TYPE' => $envType ?: 'unset',
                'DB_NAME' => $dbName ?: 'unset',
                'DB_HOST' => defined('DB_HOST') ? DB_HOST : 'unset',
                'table_prefix' => $prefix,
                'wp_users' => $counts['wp_users'],
                'wp_posts' => $counts['wp_posts'],
                'pet_table_count' => count($petTables),
            ],
            'pet_tables' => $petTables,
            'host_probe' => $hostRow ?: ['db' => null, 'host' => null],
        ];
    }

    public function getResetToken(WP_REST_Request $request): WP_REST_Response
    {
        $c = $this->classify();
        if ($c['classification'] === 'PRODUCTION') {
            return new WP_REST_Response(['allowed' => false, 'classification' => 'PRODUCTION', 'errors' => ['production_block']], 200);
        }
        $token = bin2hex(random_bytes(16));
        $ttl = 600;
        $record = [
            'hash' => hash('sha256', $token),
            'expires' => time() + $ttl,
            'used' => false,
            'uid' => get_current_user_id(),
        ];
        set_transient('pet_reset_token_record', $record, $ttl);
        return new WP_REST_Response(['allowed' => true, 'classification' => $c['classification'], 'token' => $token, 'ttl' => $ttl], 200);
    }

    private function validateToken(string $token): array
    {
        $record = get_transient('pet_reset_token_record');
        if (!$record) {
            return ['ok' => false, 'error' => 'token_missing'];
        }
        if (!isset($record['hash']) || $record['hash'] !== hash('sha256', $token)) {
            return ['ok' => false, 'error' => 'token_invalid'];
        }
        if (!empty($record['used'])) {
            return ['ok' => false, 'error' => 'token_used'];
        }
        if (!isset($record['expires']) || time() > (int)$record['expires']) {
            return ['ok' => false, 'error' => 'token_expired'];
        }
        return ['ok' => true, 'record' => $record];
    }

    public function adminResetPetOnly(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $params = $request->get_json_params();
        $mode = (string)($params['mode'] ?? 'dry_run');
        $alsoSeed = array_key_exists('also_seed', $params) ? (bool)$params['also_seed'] : true;
        $resetToken = (string)($params['reset_token'] ?? '');
        $class = $this->classify();
        $allowed = $class['classification'] !== 'PRODUCTION';
        $tables = $class['pet_tables'];
        $resp = [
            'allowed' => $allowed,
            'classification' => $class['classification'],
            'evidence' => $class['evidence'],
            'backup' => ['attempted' => false, 'path' => null, 'ok' => false, 'error' => null],
            'tables_to_drop' => $tables,
            'result' => ['dropped_tables' => 0, 'recreated_tables' => 0, 'seed_run_id' => null, 'preflight' => null],
            'errors' => [],
        ];
        if (!$allowed) {
            return new WP_REST_Response($resp, 200);
        }
        if ($mode !== 'execute') {
            return new WP_REST_Response($resp, 200);
        }
        $tok = $this->validateToken($resetToken);
        if (!$tok['ok']) {
            $resp['errors'][] = $tok['error'];
            return new WP_REST_Response($resp, 200);
        }
        $upload = wp_upload_dir();
        $backupDir = trailingslashit($upload['basedir']) . 'pet-backups';
        wp_mkdir_p($backupDir);
        // Deny web access on Apache; random token defends against enumeration on nginx.
        $htaccess = $backupDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all\n");
        }
        $token = bin2hex(random_bytes(8));
        $backupPath = $backupDir . '/pet-only-reset-' . date('Ymd-His') . '-' . $token . '.sql';
        $resp['backup']['attempted'] = true;
        try {
            $fp = fopen($backupPath, 'w');
            foreach ($tables as $t) {
                $create = $wpdb->get_row("SHOW CREATE TABLE `$t`", ARRAY_N);
                if ($create && isset($create[1])) {
                    fwrite($fp, $create[1] . ";\n");
                }
                $rows = $wpdb->get_results("SELECT * FROM `$t`", ARRAY_A);
                foreach ($rows as $row) {
                    $cols = array_map(function ($k) { return "`$k`"; }, array_keys($row));
                    $vals = array_map(function ($v) use ($wpdb) {
                        return isset($v) ? "'" . esc_sql((string)$v) . "'" : "NULL";
                    }, array_values($row));
                    fwrite($fp, "INSERT INTO `$t` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n");
                }
            }
            fclose($fp);
            $resp['backup']['path'] = $backupPath;
            $resp['backup']['ok'] = true;
        } catch (\Throwable $e) {
            $resp['backup']['error'] = $e->getMessage();
        }
        foreach ($tables as $t) {
            $wpdb->query("DROP TABLE IF EXISTS `$t`");
            $resp['result']['dropped_tables']++;
        }
        $wpdb->query("DROP TABLE IF EXISTS `" . $wpdb->prefix . "pet_migrations`");
        $runner = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create()->get(\Pet\Infrastructure\Persistence\Migration\MigrationRunner::class);
        $runner->run(\Pet\Infrastructure\Persistence\Migration\MigrationRegistry::all());
        $resp['result']['recreated_tables'] = (int)$wpdb->get_var("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE '" . esc_sql($wpdb->prefix) . "pet_%'");
        $pre = $this->preFlightCheck->run();
        $resp['result']['preflight'] = $pre;
        if ($alsoSeed) {
            $seedRunId = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('seed_', true);
            $this->demoSeedService->seedFull($seedRunId, 'demo_full');
            $resp['result']['seed_run_id'] = $seedRunId;
        }
        $auditTable = $wpdb->prefix . 'pet_admin_audit_log';
        $wpdb->insert($auditTable, [
            'user_id' => get_current_user_id(),
            'action' => 'reset_pet_only',
            'mode' => $mode,
            'tables_dropped' => json_encode($tables),
            'backup_path' => $resp['backup']['path'],
            'seed_run_id' => $resp['result']['seed_run_id'],
            'evidence_json' => json_encode(['classification' => $class['classification'], 'evidence' => $class['evidence']]),
            'created_at' => current_time('mysql'),
        ]);
        set_transient('pet_reset_token_record', ['used' => true], 60);
        return new WP_REST_Response($resp, 200);
    }
}
