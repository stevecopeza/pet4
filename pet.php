<?php
/**
 * Plugin Name: PET (Plan. Execute. Track)
 * Description: Domain-driven project estimation and management tool.
 * Version: 1.0.2
 * Author: Steve Cope
 * Text Domain: pet
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// Autoload Dependencies
require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Plugin
add_action('plugins_loaded', function () {
    try {
        $container = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
        
        // Run Migrations
        /** @var \Pet\Infrastructure\Persistence\Migration\MigrationRunner $runner */
        $runner = $container->get(\Pet\Infrastructure\Persistence\Migration\MigrationRunner::class);
        $runner->run(\Pet\Infrastructure\Persistence\Migration\MigrationManifest::getAll());

        $featureFlagService = $container->get(\Pet\Application\System\Service\FeatureFlagService::class);

        $uiRegistry = new \Pet\UI\Admin\AdminPageRegistry(
            __DIR__,
            plugin_dir_url(__FILE__),
            $featureFlagService
        );
        $uiRegistry->register();

        $shortcodeRegistrar = new \Pet\UI\Shortcode\ShortcodeRegistrar();
        $shortcodeRegistrar->register();

        $standaloneDashboard = new \Pet\UI\Standalone\StandaloneDashboardPage(
            __DIR__,
            plugin_dir_url(__FILE__),
            $featureFlagService,
            $container->get(\Pet\Domain\Dashboard\Service\DashboardAccessPolicy::class)
        );
        $standaloneDashboard->register();

        $standaloneStaffTimeCapture = new \Pet\UI\Standalone\StandaloneStaffTimeCapturePage(
            __DIR__,
            plugin_dir_url(__FILE__),
            $featureFlagService,
            $container->get(\Pet\Application\Identity\Service\StaffEmployeeResolver::class)
        );
        $standaloneStaffTimeCapture->register();

        // Register REST API
        $apiRegistry = new \Pet\UI\Rest\ApiRegistry($container);
        $apiRegistry->register();
        
        // Register Event Listeners
        /** @var \Pet\Domain\Event\EventBus $eventBus */
        $eventBus = $container->get(\Pet\Domain\Event\EventBus::class);
        
        $ticketCreatedListener = $container->get(\Pet\Application\Activity\Listener\TicketCreatedListener::class);
        $eventBus->subscribe(\Pet\Domain\Support\Event\TicketCreated::class, $ticketCreatedListener);
        
        $quoteAcceptedListener = $container->get(\Pet\Application\Commercial\Listener\QuoteAcceptedListener::class);
        $eventBus->subscribe(\Pet\Domain\Commercial\Event\QuoteAccepted::class, $quoteAcceptedListener);

        $createProjectFromQuoteListener = $container->get(\Pet\Application\Delivery\Listener\CreateProjectFromQuoteListener::class);
        $eventBus->subscribe(\Pet\Domain\Commercial\Event\QuoteAccepted::class, $createProjectFromQuoteListener);

        $createForecastFromQuoteListener = $container->get(\Pet\Application\Commercial\Listener\CreateForecastFromQuoteListener::class);
        $eventBus->subscribe(\Pet\Domain\Commercial\Event\QuoteAccepted::class, $createForecastFromQuoteListener);

        // Feed Projection Listener
        $feedProjectionListener = $container->get(\Pet\Application\Projection\Listener\FeedProjectionListener::class);
        $eventBus->subscribe(\Pet\Domain\Commercial\Event\QuoteAccepted::class, [$feedProjectionListener, 'onQuoteAccepted']);
        $eventBus->subscribe(\Pet\Domain\Delivery\Event\ProjectCreated::class, [$feedProjectionListener, 'onProjectCreated']);
        $eventBus->subscribe(\Pet\Domain\Support\Event\TicketCreated::class, [$feedProjectionListener, 'onTicketCreated']);
        $eventBus->subscribe(\Pet\Domain\Support\Event\TicketWarningEvent::class, [$feedProjectionListener, 'onTicketWarning']);
        $eventBus->subscribe(\Pet\Domain\Support\Event\TicketBreachedEvent::class, [$feedProjectionListener, 'onTicketBreached']);
        // SLA-side escalation feed entry removed — the richer Escalation domain event handles this (see below)
        $eventBus->subscribe(\Pet\Domain\Delivery\Event\MilestoneCompletedEvent::class, [$feedProjectionListener, 'onMilestoneCompleted']);

        // Feed Projection – Escalation Domain events (gated by feature flag)
        if ($featureFlagService->isEscalationEngineEnabled()) {
            $eventBus->subscribe(\Pet\Domain\Escalation\Event\EscalationTriggeredEvent::class, [$feedProjectionListener, 'onDomainEscalationTriggered']);
            $eventBus->subscribe(\Pet\Domain\Escalation\Event\EscalationAcknowledgedEvent::class, [$feedProjectionListener, 'onEscalationAcknowledged']);
            $eventBus->subscribe(\Pet\Domain\Escalation\Event\EscalationResolvedEvent::class, [$feedProjectionListener, 'onEscalationResolved']);
        }

        // Escalation Domain Bridge – creates Escalation aggregates from SLA breach events
        $slaEscalationBridge = $container->get(\Pet\Application\Escalation\Listener\SlaEscalationBridgeListener::class);
        $eventBus->subscribe(\Pet\Domain\Support\Event\EscalationTriggeredEvent::class, $slaEscalationBridge);
        $eventBus->subscribe(\Pet\Domain\Commercial\Event\ChangeOrderApprovedEvent::class, [$feedProjectionListener, 'onChangeOrderApproved']);

        // Work Item Projector Listener
        $workItemProjector = $container->get(\Pet\Application\Work\Projection\WorkItemProjector::class);
        $eventBus->subscribe(\Pet\Domain\Support\Event\TicketCreated::class, [$workItemProjector, 'onTicketCreated']);
        $eventBus->subscribe(\Pet\Domain\Support\Event\TicketAssigned::class, [$workItemProjector, 'onTicketAssigned']);

        // Register Cron Handlers (all inside plugins_loaded, reusing singleton container)
        add_action('pet_outbox_dispatch_event', function () use ($container) {
            try {
                $container->get(\Pet\Application\Integration\Cron\OutboxDispatchJob::class)->run();
            } catch (\Throwable $e) {
                error_log('PET Outbox Dispatch Cron Failed: ' . $e->getMessage());
            }
        });

        add_action('pet_sla_automation_event', function () use ($container) {
            try {
                $container->get(\Pet\Application\Support\Cron\SlaAutomationJob::class)->run();
            } catch (\Throwable $e) {
                error_log('PET SLA Automation Cron Failed: ' . $e->getMessage());
            }
        });

        add_action('pet_work_item_priority_update', function () use ($container) {
            try {
                $container->get(\Pet\Application\Work\Cron\WorkItemPriorityUpdateJob::class)->run();
            } catch (\Throwable $e) {
                error_log('PET Work Item Priority Update Cron Failed: ' . $e->getMessage());
            }
        });

        add_action('pet_advisory_generation_event', function () use ($container) {
            try {
                $container->get(\Pet\Application\Advisory\Cron\AdvisoryGenerationJob::class)->run();
            } catch (\Throwable $e) {
                error_log('PET Advisory Generation Cron Failed: ' . $e->getMessage());
            }
        });
    } catch (\Exception $e) {
        error_log('PET Plugin Bootstrap Error: ' . $e->getMessage());
    }
});

// Register Cron Schedules
add_filter('cron_schedules', function ($schedules) {
    $schedules['pet_five_minutes'] = [
        'interval' => 300,
        'display' => __('Every 5 Minutes', 'pet')
    ];
    return $schedules;
});

// Schedule Cron Event on Activation
register_activation_hook(__FILE__, function () {
    // Flush rewrite rules so /pet-dashboards/ and /portal work immediately
    flush_rewrite_rules();

    // Register portal capabilities on existing roles.
    // These are per-user caps — assigned individually to staff WP users.
    // We add them to the 'administrator' role so admins automatically pass portal gates.
    $adminRole = get_role('administrator');
    if ($adminRole) {
        $adminRole->add_cap('pet_sales');
        $adminRole->add_cap('pet_hr');
        $adminRole->add_cap('pet_manager');
    }
    // Note: pet_sales, pet_hr, pet_manager are also granted individually to
    // non-admin staff users via EmployeeController when portal_role is set.

    if (!wp_next_scheduled('pet_sla_automation_event')) {
        wp_schedule_event(time(), 'pet_five_minutes', 'pet_sla_automation_event');
    }
    if (!wp_next_scheduled('pet_work_item_priority_update')) {
        wp_schedule_event(time(), 'pet_five_minutes', 'pet_work_item_priority_update');
    }
    if (!wp_next_scheduled('pet_advisory_generation_event')) {
        wp_schedule_event(time(), 'pet_five_minutes', 'pet_advisory_generation_event');
    }
    if (!wp_next_scheduled('pet_outbox_dispatch_event')) {
        wp_schedule_event(time(), 'pet_five_minutes', 'pet_outbox_dispatch_event');
    }
});

// Clear Cron Event on Deactivation
register_deactivation_hook(__FILE__, function () {
    $timestamp = wp_next_scheduled('pet_sla_automation_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'pet_sla_automation_event');
    }
    $timestamp = wp_next_scheduled('pet_work_item_priority_update');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'pet_work_item_priority_update');
    }
    $timestamp = wp_next_scheduled('pet_advisory_generation_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'pet_advisory_generation_event');
    }
    $timestamp = wp_next_scheduled('pet_outbox_dispatch_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'pet_outbox_dispatch_event');
    }
});

if (\defined('WP_CLI') && \constant('WP_CLI')) {
    \call_user_func('WP_CLI::add_command', 'pet migrate', function () {
        try {
            $env = \getenv('PET_ENV') ?: \getenv('WP_ENV') ?: '';
            $env = \strtolower((string) $env);
            $allowed = ['local', 'development', 'dev'];
            if (!\in_array($env, $allowed, true)) {
                \call_user_func('WP_CLI::error', 'PET migrations are restricted to local/dev environments (PET_ENV or WP_ENV).');
            }

            $container = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
            /** @var \Pet\Infrastructure\Persistence\Migration\MigrationRunner $runner */
            $runner = $container->get(\Pet\Infrastructure\Persistence\Migration\MigrationRunner::class);
            $runner->run(\Pet\Infrastructure\Persistence\Migration\MigrationManifest::getAll());
            \call_user_func('WP_CLI::success', 'PET migrations executed');
        } catch (\Throwable $e) {
            \call_user_func('WP_CLI::error', 'PET migration failed: ' . $e->getMessage());
        }
    });

    \call_user_func('WP_CLI::add_command', 'pet seed', function () {
        try {
            // Force a current user so get_current_user_id() returns a valid ID
            if (!\get_current_user_id()) {
                $adminUsers = \get_users(['role' => 'administrator', 'number' => 1]);
                if ($adminUsers) {
                    \wp_set_current_user($adminUsers[0]->ID);
                }
            }
            $container = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
            $seeder = $container->get(\Pet\Application\System\Service\DemoSeedService::class);
            $seedRunId = \function_exists('wp_generate_uuid4') ? \wp_generate_uuid4() : \uniqid('seed_', true);
            \call_user_func('WP_CLI::log', 'Seeding demo data (run: ' . $seedRunId . ')...');
            $summary = $seeder->seedFull($seedRunId, 'demo_full');
            // Store the seed_run_id for purge
            \update_option('pet_last_seed_run_id', $seedRunId);
            \call_user_func('WP_CLI::log', \json_encode($summary, JSON_PRETTY_PRINT));
            \call_user_func('WP_CLI::success', 'Demo seed complete (run: ' . $seedRunId . ')');
        } catch (\Throwable $e) {
            \call_user_func('WP_CLI::error', 'Demo seed failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    });

    \call_user_func('WP_CLI::add_command', 'pet purge', function ($args) {
        try {
            $seedRunId = $args[0] ?? \get_option('pet_last_seed_run_id', '');
            if (!$seedRunId) {
                \call_user_func('WP_CLI::error', 'No seed_run_id provided and no previous seed found. Usage: wp pet purge [seed_run_id]');
                return;
            }
            global $wpdb;
            $container = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
            $purger = $container->get(\Pet\Application\System\Service\DemoPurgeService::class);
            \call_user_func('WP_CLI::log', 'Purging seed run: ' . $seedRunId . '...');
            $summary = $purger->purgeBySeedRunId($seedRunId);
            \call_user_func('WP_CLI::log', \json_encode($summary, JSON_PRETTY_PRINT));
            \call_user_func('WP_CLI::success', 'Purge complete for seed run: ' . $seedRunId);
        } catch (\Throwable $e) {
            \call_user_func('WP_CLI::error', 'Purge failed: ' . $e->getMessage());
        }
    });

    \call_user_func('WP_CLI::add_command', 'pet performance:run', function () {
        try {
            if (!\get_current_user_id()) {
                $adminUsers = \get_users(['role' => 'administrator', 'number' => 1]);
                if ($adminUsers) {
                    \wp_set_current_user($adminUsers[0]->ID);
                }
            }
            $container = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
            /** @var \Pet\Application\Performance\Service\PerformanceRunService $runService */
            $runService = $container->get(\Pet\Application\Performance\Service\PerformanceRunService::class);
            /** @var \Pet\Application\Performance\Port\PerformanceResultStore $resultStore */
            $resultStore = $container->get(\Pet\Application\Performance\Port\PerformanceResultStore::class);

            $snapshot = $runService->runBenchmark();
            $runId = $snapshot->runId();
            $metrics = $runId > 0 ? $resultStore->findByRunId($runId) : [];
            $workloadContractKeys = [
                'dashboard',
                'advisory.signals',
                'advisory.signals_work_item',
                'advisory.reports_list',
                'advisory.reports_latest',
                'advisory.reports_get',
                'advisory.reports_generate',
                'ticket.list',
            ];

            $probe = [];
            $workload = [];
            foreach ($workloadContractKeys as $workloadKey) {
                $workload[$workloadKey] = [
                    'query_count' => 0,
                    'execution_time_ms' => 0.0,
                ];
            }
            $workloadOther = [];
            $recommendations = [];
            $errors = [];

            foreach ($metrics as $metric) {
                $metricKey = (string) ($metric['metric_key'] ?? '');
                $metricValue = $metric['metric_value'] ?? null;
                $context = isset($metric['context']) && \is_array($metric['context']) ? $metric['context'] : null;
                if ($metricKey === '') {
                    continue;
                }

                if (\str_starts_with($metricKey, 'workload.')) {
                    if (\preg_match('/^workload\.(.+)\.(query_count|execution_time_ms)$/', $metricKey, $matches) === 1) {
                        $workloadKey = (string) ($matches[1] ?? '');
                        $field = (string) ($matches[2] ?? '');
                        if ($workloadKey !== '' && $field !== '') {
                            $isContractKey = \in_array($workloadKey, $workloadContractKeys, true);
                            if ($isContractKey) {
                                if ($field === 'query_count') {
                                    $workload[$workloadKey]['query_count'] += \is_numeric($metricValue) ? (int) $metricValue : 0;
                                } else {
                                    $workload[$workloadKey]['execution_time_ms'] += \is_numeric($metricValue) ? (float) $metricValue : 0.0;
                                }
                            } else {
                                if (!isset($workloadOther[$workloadKey])) {
                                    $workloadOther[$workloadKey] = [
                                        'query_count' => 0,
                                        'execution_time_ms' => 0.0,
                                    ];
                                }
                                if ($field === 'query_count') {
                                    $workloadOther[$workloadKey]['query_count'] += \is_numeric($metricValue) ? (int) $metricValue : 0;
                                } else {
                                    $workloadOther[$workloadKey]['execution_time_ms'] += \is_numeric($metricValue) ? (float) $metricValue : 0.0;
                                }
                            }
                        }
                    }
                    continue;
                }

                if (\str_starts_with($metricKey, 'recommendation.')) {
                    $recommendation = $context['recommendation'] ?? null;
                    if (\is_array($recommendation)) {
                        $recommendations[] = $recommendation;
                    } else {
                        $recommendations[] = [
                            'issue_key' => \substr($metricKey, \strlen('recommendation.')),
                            'severity' => \is_string($metricValue) ? $metricValue : null,
                        ];
                    }
                    continue;
                }

                if (\str_starts_with($metricKey, 'error.')) {
                    $errors[] = [
                        'metric_key' => $metricKey,
                        'message' => \is_string($metricValue) ? $metricValue : \wp_json_encode($metricValue),
                        'context' => $context,
                    ];
                    continue;
                }

                $probe[$metricKey] = [
                    'value' => $metricValue,
                    'context' => $context,
                ];
            }

            $payload = [
                'run' => [
                    'id' => $snapshot->runId(),
                    'run_type' => $snapshot->runType(),
                    'status' => $snapshot->status(),
                    'started_at' => $snapshot->startedAt()->format('c'),
                    'completed_at' => $snapshot->completedAt()?->format('c'),
                    'duration_ms' => $snapshot->durationMs(),
                ],
                'metrics' => [
                    'probe' => $probe,
                    'workload' => $workload,
                    'workload_other' => $workloadOther,
                    'recommendations' => $recommendations,
                    'errors' => $errors,
                ],
                'counts' => [
                    'probe' => \count($probe),
                    'recommendations' => \count($recommendations),
                    'errors' => \count($errors),
                ],
                'snapshot' => $snapshot->toArray(),
            ];

            \call_user_func('WP_CLI::log', \json_encode($payload, JSON_PRETTY_PRINT));
            \call_user_func('WP_CLI::success', 'Performance benchmark run complete');
        } catch (\Throwable $e) {
            \call_user_func('WP_CLI::error', 'Performance benchmark run failed: ' . $e->getMessage());
        }
    });

    \call_user_func('WP_CLI::add_command', 'pet pulseway:poll', function () {
        try {
            $container = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();

            // Phase A: Ingest notifications
            $ingestionService = $container->get(\Pet\Application\Integration\Pulseway\Service\NotificationIngestionService::class);
            $ingestionResults = $ingestionService->pollAll();
            \call_user_func('WP_CLI::log', 'Ingestion: ' . \json_encode($ingestionResults, JSON_PRETTY_PRINT));

            // Phase B: Process pending notifications into tickets (if enabled)
            $ticketService = $container->get(\Pet\Application\Integration\Pulseway\Service\PulsewayTicketCreationService::class);
            $featureFlags = $container->get(\Pet\Application\System\Service\FeatureFlagService::class);
            if ($featureFlags->isPulsewayTicketCreationEnabled()) {
                $repo = $container->get(\Pet\Infrastructure\Persistence\Repository\Pulseway\SqlPulsewayIntegrationRepository::class);
                $integrations = $repo->findActiveIntegrations();
                $ticketResults = [];
                foreach ($integrations as $integration) {
                    $ticketResults[(int)$integration['id']] = $ticketService->processPendingNotifications((int)$integration['id']);
                }
                \call_user_func('WP_CLI::log', 'Ticket creation: ' . \json_encode($ticketResults, JSON_PRETTY_PRINT));
            } else {
                \call_user_func('WP_CLI::log', 'Ticket creation: disabled (pet_pulseway_ticket_creation_enabled = false)');
            }

            \call_user_func('WP_CLI::success', 'Pulseway poll cycle complete');
        } catch (\Throwable $e) {
            \call_user_func('WP_CLI::error', 'Pulseway poll failed: ' . $e->getMessage());
        }
    });

    \call_user_func('WP_CLI::add_command', 'pet pulseway:sync-devices', function () {
        try {
            $container = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
            $service = $container->get(\Pet\Application\Integration\Pulseway\Service\DeviceSnapshotService::class);
            $results = $service->syncAll();
            \call_user_func('WP_CLI::log', \json_encode($results, JSON_PRETTY_PRINT));
            \call_user_func('WP_CLI::success', 'Pulseway device sync complete');
        } catch (\Throwable $e) {
            \call_user_func('WP_CLI::error', 'Pulseway device sync failed: ' . $e->getMessage());
        }
    });

    \call_user_func('WP_CLI::add_command', 'pet reset', function () {
        try {
            // Force a current user
            if (!\get_current_user_id()) {
                $adminUsers = \get_users(['role' => 'administrator', 'number' => 1]);
                if ($adminUsers) {
                    \wp_set_current_user($adminUsers[0]->ID);
                }
            }
            global $wpdb;
            $prefix = $wpdb->prefix;
            $petTables = $wpdb->get_col($wpdb->prepare("SHOW TABLES LIKE %s", $prefix . 'pet_%'));
            \call_user_func('WP_CLI::log', 'Dropping ' . \count($petTables) . ' PET tables...');
            $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');
            foreach ($petTables as $t) {
                $wpdb->query("DROP TABLE IF EXISTS `$t`");
            }
            $wpdb->query("DROP TABLE IF EXISTS `{$prefix}pet_migrations`");
            $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');
            \call_user_func('WP_CLI::log', 'Re-running migrations...');
            $container = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
            $runner = $container->get(\Pet\Infrastructure\Persistence\Migration\MigrationRunner::class);
            $runner->run(\Pet\Infrastructure\Persistence\Migration\MigrationManifest::getAll());
            \call_user_func('WP_CLI::log', 'Seeding fresh demo data...');
            $seeder = $container->get(\Pet\Application\System\Service\DemoSeedService::class);
            $seedRunId = \function_exists('wp_generate_uuid4') ? \wp_generate_uuid4() : \uniqid('seed_', true);
            $summary = $seeder->seedFull($seedRunId, 'demo_full');
            \update_option('pet_last_seed_run_id', $seedRunId);
            \call_user_func('WP_CLI::log', \json_encode($summary, JSON_PRETTY_PRINT));
            \call_user_func('WP_CLI::success', 'Full reset + seed complete (run: ' . $seedRunId . ')');
        } catch (\Throwable $e) {
            \call_user_func('WP_CLI::error', 'Reset failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    });

    /**
     * Import customers from a CSV file.
     *
     * ## OPTIONS
     *
     * <file>
     * : Path to the CSV file.
     *
     * [--dry-run]
     * : Preview what would be imported without writing to the database.
     *
     * [--update]
     * : Update existing customers (matched by email) instead of skipping.
     *
     * ## EXAMPLES
     *
     *     wp pet import:customers customers.csv
     *     wp pet import:customers customers.csv --dry-run
     *
     * Required CSV columns: name, email
     * Optional columns: legal_name, status (default: active)
     *
     * @synopsis <file> [--dry-run] [--update]
     */
    \call_user_func('WP_CLI::add_command', 'pet import:customers', function ($args, $assocArgs) {
        $file    = $args[0] ?? '';
        $dryRun  = isset($assocArgs['dry-run']);
        $update  = isset($assocArgs['update']);

        if (!$file || !\file_exists($file)) {
            \call_user_func('WP_CLI::error', "File not found: $file");
            return;
        }

        global $wpdb;

        $handle = \fopen($file, 'r');
        if (!$handle) {
            \call_user_func('WP_CLI::error', "Cannot open file: $file");
            return;
        }

        // Read header row
        $headers = \fgetcsv($handle);
        if (!$headers) {
            \call_user_func('WP_CLI::error', 'CSV is empty or unreadable.');
            \fclose($handle);
            return;
        }
        $headers = \array_map('trim', $headers);

        $required = ['name', 'email'];
        foreach ($required as $col) {
            if (!\in_array($col, $headers, true)) {
                \call_user_func('WP_CLI::error', "Required column missing: $col. Found: " . \implode(', ', $headers));
                \fclose($handle);
                return;
            }
        }

        $container = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
        $customerRepo = $container->get(\Pet\Domain\Identity\Repository\CustomerRepository::class);
        $createHandler = $container->get(\Pet\Application\Identity\Command\CreateCustomerHandler::class);

        $customersTable = $wpdb->prefix . 'pet_customers';
        $row = 1;
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors  = 0;

        while (($data = \fgetcsv($handle)) !== false) {
            $row++;
            $record = \array_combine($headers, $data);
            if (!$record) { $skipped++; continue; }

            $name       = \trim($record['name'] ?? '');
            $email      = \trim(\strtolower($record['email'] ?? ''));
            $legalName  = \trim($record['legal_name'] ?? '') ?: null;
            $status     = \trim($record['status'] ?? '') ?: 'active';

            if (!$name || !$email) {
                \call_user_func('WP_CLI::warning', "Row $row: name and email required — skipped.");
                $skipped++;
                continue;
            }

            // Check for existing customer by email
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$customersTable} WHERE contact_email = %s LIMIT 1",
                $email
            ));

            if ($existing && !$update) {
                \call_user_func('WP_CLI::log', "Row $row: Customer '$email' already exists — skipped.");
                $skipped++;
                continue;
            }

            if ($existing && $update) {
                if (!$dryRun) {
                    $wpdb->update(
                        $customersTable,
                        ['name' => $name, 'legal_name' => $legalName, 'status' => $status, 'updated_at' => \current_time('mysql')],
                        ['id' => (int) $existing->id]
                    );
                }
                \call_user_func('WP_CLI::log', "Row $row: Updated '$name' <$email>" . ($dryRun ? ' [DRY-RUN]' : ''));
                $updated++;
                continue;
            }

            // Create new
            if (!$dryRun) {
                try {
                    $command = new \Pet\Application\Identity\Command\CreateCustomerCommand($name, $email, $legalName, $status);
                    $createHandler->handle($command);
                    \call_user_func('WP_CLI::log', "Row $row: Created '$name' <$email>");
                    $created++;
                } catch (\Throwable $e) {
                    \call_user_func('WP_CLI::warning', "Row $row: Error for '$name': " . $e->getMessage());
                    $errors++;
                }
            } else {
                \call_user_func('WP_CLI::log', "Row $row: Would create '$name' <$email> [DRY-RUN]");
                $created++;
            }
        }

        \fclose($handle);

        $label = $dryRun ? ' (DRY-RUN — no changes made)' : '';
        \call_user_func('WP_CLI::success', "Import complete{$label}: $created created, $updated updated, $skipped skipped, $errors errors. ($row rows read)");
    });

    /**
     * Import catalog items (products/services) from a CSV file.
     *
     * ## OPTIONS
     *
     * <file>
     * : Path to the CSV file.
     *
     * [--dry-run]
     * : Preview what would be imported without writing to the database.
     *
     * [--update]
     * : Update existing items (matched by SKU or name) instead of skipping.
     *
     * ## EXAMPLES
     *
     *     wp pet import:products products.csv
     *     wp pet import:products products.csv --dry-run
     *
     * Required CSV columns: name, type, unit_price
     * Optional columns: unit_cost, sku, description, category
     * type must be 'service' or 'product'
     *
     * @synopsis <file> [--dry-run] [--update]
     */
    \call_user_func('WP_CLI::add_command', 'pet import:products', function ($args, $assocArgs) {
        $file   = $args[0] ?? '';
        $dryRun = isset($assocArgs['dry-run']);
        $update = isset($assocArgs['update']);

        if (!$file || !\file_exists($file)) {
            \call_user_func('WP_CLI::error', "File not found: $file");
            return;
        }

        global $wpdb;

        $handle = \fopen($file, 'r');
        if (!$handle) {
            \call_user_func('WP_CLI::error', "Cannot open file: $file");
            return;
        }

        $headers = \fgetcsv($handle);
        if (!$headers) {
            \call_user_func('WP_CLI::error', 'CSV is empty or unreadable.');
            \fclose($handle);
            return;
        }
        $headers = \array_map('trim', $headers);

        $required = ['name', 'type', 'unit_price'];
        foreach ($required as $col) {
            if (!\in_array($col, $headers, true)) {
                \call_user_func('WP_CLI::error', "Required column missing: $col. Found: " . \implode(', ', $headers));
                \fclose($handle);
                return;
            }
        }

        $catalogTable   = $wpdb->prefix . 'pet_catalog_items';
        $container      = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
        $catalogRepo    = $container->get(\Pet\Domain\Commercial\Repository\CatalogItemRepository::class);
        $createHandler  = $container->get(\Pet\Application\Commercial\Command\CreateCatalogItemHandler::class);

        $row     = 1;
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors  = 0;

        while (($data = \fgetcsv($handle)) !== false) {
            $row++;
            $record = \array_combine($headers, $data);
            if (!$record) { $skipped++; continue; }

            $name        = \trim($record['name'] ?? '');
            $type        = \strtolower(\trim($record['type'] ?? 'service'));
            $unitPrice   = (float) ($record['unit_price'] ?? 0);
            $unitCost    = (float) ($record['unit_cost'] ?? 0);
            $sku         = \trim($record['sku'] ?? '') ?: null;
            $description = \trim($record['description'] ?? '') ?: null;
            $category    = \trim($record['category'] ?? '') ?: null;

            if (!$name) {
                \call_user_func('WP_CLI::warning', "Row $row: name is required — skipped.");
                $skipped++;
                continue;
            }
            if (!\in_array($type, ['service', 'product'], true)) {
                \call_user_func('WP_CLI::warning', "Row $row: invalid type '$type' (must be 'service' or 'product') — skipped.");
                $skipped++;
                continue;
            }

            // Check for existing item by SKU (if provided) or by name
            $existing = null;
            if ($sku) {
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM {$catalogTable} WHERE sku = %s LIMIT 1",
                    $sku
                ));
            }
            if (!$existing) {
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM {$catalogTable} WHERE name = %s AND type = %s LIMIT 1",
                    $name, $type
                ));
            }

            if ($existing && !$update) {
                \call_user_func('WP_CLI::log', "Row $row: '$name' already exists — skipped.");
                $skipped++;
                continue;
            }

            if ($existing && $update) {
                if (!$dryRun) {
                    $wpdb->update(
                        $catalogTable,
                        ['name' => $name, 'type' => $type, 'unit_price' => $unitPrice, 'unit_cost' => $unitCost, 'sku' => $sku, 'description' => $description, 'category' => $category, 'updated_at' => \current_time('mysql')],
                        ['id' => (int) $existing->id]
                    );
                }
                \call_user_func('WP_CLI::log', "Row $row: Updated '$name'" . ($dryRun ? ' [DRY-RUN]' : ''));
                $updated++;
                continue;
            }

            // Create new
            if (!$dryRun) {
                try {
                    $command = new \Pet\Application\Commercial\Command\CreateCatalogItemCommand(
                        $name, $unitPrice, $unitCost, $sku, $description, $category, $type, []
                    );
                    $createHandler->handle($command);
                    \call_user_func('WP_CLI::log', "Row $row: Created '$name' [$type] @ $unitPrice");
                    $created++;
                } catch (\Throwable $e) {
                    \call_user_func('WP_CLI::warning', "Row $row: Error for '$name': " . $e->getMessage());
                    $errors++;
                }
            } else {
                \call_user_func('WP_CLI::log', "Row $row: Would create '$name' [$type] @ $unitPrice [DRY-RUN]");
                $created++;
            }
        }

        \fclose($handle);

        $label = $dryRun ? ' (DRY-RUN — no changes made)' : '';
        \call_user_func('WP_CLI::success', "Import complete{$label}: $created created, $updated updated, $skipped skipped, $errors errors. ($row rows read)");
    });
}
