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

        $uiRegistry = new \Pet\UI\Admin\AdminPageRegistry(
            __DIR__,
            plugin_dir_url(__FILE__)
        );
        $uiRegistry->register();

        $shortcodeRegistrar = new \Pet\UI\Shortcode\ShortcodeRegistrar();
        $shortcodeRegistrar->register();

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
        $eventBus->subscribe(\Pet\Domain\Support\Event\EscalationTriggeredEvent::class, [$feedProjectionListener, 'onEscalationTriggered']);
        $eventBus->subscribe(\Pet\Domain\Delivery\Event\MilestoneCompletedEvent::class, [$feedProjectionListener, 'onMilestoneCompleted']);
        $eventBus->subscribe(\Pet\Domain\Commercial\Event\ChangeOrderApprovedEvent::class, [$feedProjectionListener, 'onChangeOrderApproved']);

        // Work Item Projector Listener
        $workItemProjector = $container->get(\Pet\Application\Work\Projection\WorkItemProjector::class);
        $eventBus->subscribe(\Pet\Domain\Support\Event\TicketCreated::class, [$workItemProjector, 'onTicketCreated']);
        $eventBus->subscribe(\Pet\Domain\Support\Event\TicketAssigned::class, [$workItemProjector, 'onTicketAssigned']);
        $eventBus->subscribe(\Pet\Domain\Delivery\Event\ProjectTaskCreated::class, [$workItemProjector, 'onProjectTaskCreated']);

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
}
