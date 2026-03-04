<?php

declare(strict_types=1);

namespace Pet\Application\System\Service;

use Pet\Domain\Commercial\Event\QuoteAccepted;
use Pet\Domain\Event\EventBus;

final class DemoSeedService
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function seedFull(string $seedRunId, string $seedProfile = 'demo_full'): array
    {
        $summary = [];
        $now = new \DateTimeImmutable();
        $recentDate = $now->format('Y-m-d H:i:s');

        $summary['feature_flags'] = $this->seedFeatureFlags($seedRunId);
        $summary['employees'] = $this->seedEmployees($seedRunId, $seedProfile, $recentDate);
        $summary['customers_sites_contacts'] = $this->seedCustomersSitesContacts($seedRunId, $seedProfile, $recentDate);
        $summary['teams'] = $this->seedTeams($seedRunId, $seedProfile, $recentDate);
        $summary['calendar'] = $this->seedCalendar($seedRunId, $seedProfile, $recentDate);
        $summary['capability'] = $this->seedCapability($seedRunId, $seedProfile, $recentDate);
        $summary['leave'] = $this->seedLeave($seedRunId, $seedProfile, $recentDate);
        $summary['catalog'] = $this->seedCatalog($seedRunId, $seedProfile, $recentDate);
        $summary['commercial'] = $this->seedCommercial($seedRunId, $seedProfile, $recentDate);
        $summary['leads'] = $this->seedLeads($seedRunId, $seedProfile, $recentDate);
        $summary['delivery'] = $this->seedDelivery($seedRunId, $seedProfile, $recentDate);
        $summary['support'] = $this->seedSupport($seedRunId, $seedProfile, $recentDate);
        $summary['backbone_tickets'] = $this->seedBackboneTickets($seedRunId, $seedProfile, $recentDate);
        $summary['work'] = $this->seedWorkOrchestration($seedRunId, $seedProfile, $recentDate);
        $summary['time'] = $this->seedTimeEntries($seedRunId, $seedProfile, $recentDate);
        $summary['knowledge'] = $this->seedKnowledge($seedRunId, $seedProfile, $recentDate);
        $summary['feed'] = $this->seedFeed($seedRunId, $seedProfile, $recentDate);
        $summary['conversations'] = $this->seedConversations($seedRunId, $seedProfile, $recentDate);
        $summary['project_tasks'] = $this->seedProjectTasks($seedRunId, $seedProfile, $recentDate);
        $summary['project_enrichment'] = $this->seedProjectEnrichment($seedRunId, $seedProfile, $recentDate);
        $summary['billing'] = $this->seedBilling($seedRunId, $seedProfile, $recentDate);
        $summary['event_backbone'] = $this->seedEventBackboneExpectations($seedRunId, $seedProfile, $recentDate);
        $summary['pulseway'] = $this->seedPulseway($seedRunId, $seedProfile, $recentDate);
        $summary['health_history'] = $this->seedHealthHistory($seedRunId, $seedProfile, $recentDate);

        // Registry entries for tables without JSON metadata
        $this->registerSeedRunEntities($seedRunId, $recentDate);

        return $summary;
    }

    private function jsonMeta(string $seedRunId, string $seedProfile, string $seededAt): string
    {
        return json_encode([
            'seed_run_id' => $seedRunId,
            'seed_profile' => $seedProfile,
            'seeded_at' => $seededAt,
            'touched_at' => null,
            'touched_by_employee_id' => null,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function registryAdd(string $seedRunId, string $table, string $rowId): void
    {
        $registry = $this->wpdb->prefix . 'pet_demo_seed_registry';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$registry'") !== $registry) {
            return;
        }
        $this->wpdb->insert($registry, [
            'seed_run_id' => $seedRunId,
            'table_name' => $table,
            'row_id' => $rowId,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    private function registerSeedRunEntities(string $seedRunId, string $seededAt): void
    {
        // Employees
        $emp = $this->wpdb->prefix . 'pet_employees';
        $empIds = $this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $emp WHERE created_at = %s", [$seededAt]));
        foreach ($empIds as $id) $this->registryAdd($seedRunId, $emp, (string)$id);

        // Customers, Sites, Contacts
        $cust = $this->wpdb->prefix . 'pet_customers';
        $site = $this->wpdb->prefix . 'pet_sites';
        $cont = $this->wpdb->prefix . 'pet_contacts';
        foreach ($this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $cust WHERE created_at = %s", [$seededAt])) as $id) $this->registryAdd($seedRunId, $cust, (string)$id);
        foreach ($this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $site WHERE created_at = %s", [$seededAt])) as $id) $this->registryAdd($seedRunId, $site, (string)$id);
        foreach ($this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $cont WHERE created_at = %s", [$seededAt])) as $id) $this->registryAdd($seedRunId, $cont, (string)$id);

        // Teams
        $teams = $this->wpdb->prefix . 'pet_teams';
        foreach ($this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $teams WHERE created_at = %s", [$seededAt])) as $id) $this->registryAdd($seedRunId, $teams, (string)$id);

        // Calendar
        $cal = $this->wpdb->prefix . 'pet_calendars';
        $calId = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $cal WHERE name = %s", 'Default Calendar'));
        if ($calId) $this->registryAdd($seedRunId, $cal, (string)$calId);

        // Quotes and components
        $quotes = $this->wpdb->prefix . 'pet_quotes';
        $qc = $this->wpdb->prefix . 'pet_quote_components';
        $qm = $this->wpdb->prefix . 'pet_quote_milestones';
        $qci = $this->wpdb->prefix . 'pet_quote_catalog_items';
        $qrs = $this->wpdb->prefix . 'pet_quote_recurring_services';
        $quoteIds = $this->wpdb->get_col("SELECT id FROM $quotes");
        foreach ($quoteIds as $qid) {
            $this->registryAdd($seedRunId, $quotes, (string)$qid);
            foreach ($this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $qc WHERE quote_id = %d", [(int)$qid])) as $id) $this->registryAdd($seedRunId, $qc, (string)$id);
            $compIds = $this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $qc WHERE quote_id = %d", [(int)$qid]));
            foreach ($compIds as $cid) {
                foreach ($this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $qm WHERE component_id = %d", [(int)$cid])) as $id) $this->registryAdd($seedRunId, $qm, (string)$id);
                foreach ($this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $qci WHERE component_id = %d", [(int)$cid])) as $id) $this->registryAdd($seedRunId, $qci, (string)$id);
                foreach ($this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $qrs WHERE component_id = %d", [(int)$cid])) as $id) $this->registryAdd($seedRunId, $qrs, (string)$id);
            }
        }

        // Contract, baseline, baseline components
        $contracts = $this->wpdb->prefix . 'pet_contracts';
        $baselines = $this->wpdb->prefix . 'pet_baselines';
        $blc = $this->wpdb->prefix . 'pet_baseline_components';
        foreach ($quoteIds as $qid) {
            $contractId = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $contracts WHERE quote_id = %d", [(int)$qid]));
            if ($contractId) {
                $this->registryAdd($seedRunId, $contracts, (string)$contractId);
                $baselineId = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $baselines WHERE contract_id = %d", [$contractId]));
                if ($baselineId) {
                    $this->registryAdd($seedRunId, $baselines, (string)$baselineId);
                    foreach ($this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $blc WHERE baseline_id = %d", [$baselineId])) as $id) $this->registryAdd($seedRunId, $blc, (string)$id);
                }
            }
        }

        $projects = $this->wpdb->prefix . 'pet_projects';
        foreach ($quoteIds as $qid) {
            $projectId = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $projects WHERE source_quote_id = %d", [(int)$qid]));
            if ($projectId) {
                $this->registryAdd($seedRunId, $projects, (string)$projectId);
            }
        }

        // SLA snapshot, tickets, clock state
        $tickets = $this->wpdb->prefix . 'pet_tickets';
        $clocks = $this->wpdb->prefix . 'pet_sla_clock_state';
        $tIds = $this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $tickets WHERE opened_at = %s", [$seededAt]));
        foreach ($tIds as $tid) {
            $this->registryAdd($seedRunId, $tickets, (string)$tid);
            foreach ($this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $clocks WHERE ticket_id = %d", [(int)$tid])) as $id) $this->registryAdd($seedRunId, $clocks, (string)$id);
        }

        // Work items and department queues
        $work = $this->wpdb->prefix . 'pet_work_items';
        $queues = $this->wpdb->prefix . 'pet_department_queues';
        foreach ($this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $work WHERE created_at = %s", [$seededAt])) as $id) $this->registryAdd($seedRunId, $work, (string)$id);
        foreach ($this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $queues WHERE entered_queue_at = %s", [$seededAt])) as $id) $this->registryAdd($seedRunId, $queues, (string)$id);

        // Billing exports & items
        $exports = $this->wpdb->prefix . 'pet_billing_exports';
        $items = $this->wpdb->prefix . 'pet_billing_export_items';
        foreach ($this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $exports WHERE created_at = %s", [$seededAt])) as $id) {
            $this->registryAdd($seedRunId, $exports, (string)$id);
            foreach ($this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $items WHERE export_id = %d", [(int)$id])) as $iid) $this->registryAdd($seedRunId, $items, (string)$iid);
        }

        // External mappings and integration runs
        $maps = $this->wpdb->prefix . 'pet_external_mappings';
        foreach ($this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $maps WHERE `system` = %s AND entity_type = %s", ['quickbooks', 'billing_export'])) as $id) $this->registryAdd($seedRunId, $maps, (string)$id);
        $runs = $this->wpdb->prefix . 'pet_integration_runs';
        foreach ($this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $runs WHERE created_at = %s", [$seededAt])) as $id) $this->registryAdd($seedRunId, $runs, (string)$id);

        // QB snapshots
        $qbInv = $this->wpdb->prefix . 'pet_qb_invoices';
        foreach ($this->wpdb->get_col("SELECT id FROM $qbInv ORDER BY id DESC LIMIT 3") as $id) $this->registryAdd($seedRunId, $qbInv, (string)$id);
        $qbPay = $this->wpdb->prefix . 'pet_qb_payments';
        foreach ($this->wpdb->get_col("SELECT id FROM $qbPay ORDER BY id DESC LIMIT 2") as $id) $this->registryAdd($seedRunId, $qbPay, (string)$id);

        // Feed and knowledge
        $feed = $this->wpdb->prefix . 'pet_feed_events';
        foreach ($this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $feed WHERE created_at = %s", [$seededAt])) as $id) $this->registryAdd($seedRunId, $feed, (string)$id);
        $ann = $this->wpdb->prefix . 'pet_announcements';
        foreach ($this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $ann WHERE created_at = %s", [$seededAt])) as $id) $this->registryAdd($seedRunId, $ann, (string)$id);
        $articles = $this->wpdb->prefix . 'pet_articles';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$articles'") === $articles) {
            foreach ($this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $articles WHERE created_at = %s", [$seededAt])) as $id) $this->registryAdd($seedRunId, $articles, (string)$id);
        }
    }

    private function seedFeatureFlags(string $seedRunId): array
    {
        $table = $this->wpdb->prefix . 'pet_settings';
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $flags = [
            'pet_helpdesk_enabled' => 'Enable the helpdesk / support ticket system',
            'pet_helpdesk_shortcode_enabled' => 'Enable helpdesk shortcodes for front-end ticket portal',
            'pet_sla_scheduler_enabled' => 'Enable the SLA clock and automation scheduler',
            'pet_work_projection_enabled' => 'Enable work item projection and priority engine input',
            'pet_queue_visibility_enabled' => 'Enable department queue visibility endpoints',
            'pet_priority_engine_enabled' => 'Enable automatic priority scoring on work items',
            'pet_escalation_engine_enabled' => 'Enable automatic escalation when SLA thresholds are breached',
            'pet_advisory_enabled' => 'Enable advisory signals on work items',
            'pet_advisory_reports_enabled' => 'Enable advisory report generation',
            'pet_resilience_indicators_enabled' => 'Enable resilience / utilization indicators',
        ];
        $count = 0;
        foreach ($flags as $key => $desc) {
            $existing = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT setting_key FROM $table WHERE setting_key = %s", $key
            ));
            if (!$existing) {
                $this->wpdb->insert($table, [
                    'setting_key' => $key,
                    'setting_value' => '1',
                    'setting_type' => 'boolean',
                    'description' => $desc,
                    'updated_at' => $now,
                ]);
                $count++;
            }
        }
        return ['enabled' => $count, 'total_flags' => count($flags)];
    }

    private function seedEmployees(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        $t = $this->wpdb->prefix . 'pet_employees';
        $existing = (int)$this->wpdb->get_var("SELECT COUNT(*) FROM $t");
        if ($existing >= 8) return ['count' => $existing, 'skipped' => true];
        $rows = [
            ['first_name' => 'Steve', 'last_name' => 'Admin', 'email' => 'steve@example.com'],
            ['first_name' => 'Mia', 'last_name' => 'Manager', 'email' => 'mia@example.com'],
            ['first_name' => 'Liam', 'last_name' => 'Lead Tech', 'email' => 'liam@example.com'],
            ['first_name' => 'Ava', 'last_name' => 'Consultant', 'email' => 'ava@example.com'],
            ['first_name' => 'Noah', 'last_name' => 'Support', 'email' => 'noah@example.com'],
            ['first_name' => 'Zoe', 'last_name' => 'Finance', 'email' => 'zoe@example.com'],
            ['first_name' => 'Ethan', 'last_name' => 'DevOps', 'email' => 'ethan@example.com'],
            ['first_name' => 'Isabella', 'last_name' => 'Analyst', 'email' => 'isabella@example.com'],
        ];
        $isFirst = true;
        foreach ($rows as $r) {
            // Map the first employee (Steve Admin) to the current WP admin user
            $wpUserId = 0;
            if ($isFirst && function_exists('get_current_user_id')) {
                $adminId = get_current_user_id();
                if ($adminId <= 0) {
                    // CLI or REST context — use WP user 1 (site admin)
                    $adminId = 1;
                }
                $wpUserId = $adminId;
                $isFirst = false;
            } elseif (function_exists('email_exists') && function_exists('wp_insert_user')) {
                $isFirst = false;
                $existingUserId = email_exists($r['email']);
                if ($existingUserId) {
                    $wpUserId = (int)$existingUserId;
                } else {
                    $login = strtolower($r['first_name']) . '.' . strtolower(str_replace(' ', '', $r['last_name']));
                    // Ensure login is unique
                    if (username_exists($login)) {
                        $login .= '_' . wp_rand(100, 999);
                    }
                    $userId = wp_insert_user([
                        'user_login'   => $login,
                        'user_email'   => $r['email'],
                        'display_name' => $r['first_name'] . ' ' . $r['last_name'],
                        'first_name'   => $r['first_name'],
                        'last_name'    => $r['last_name'],
                        'user_pass'    => wp_generate_password(16, true, true),
                        'role'         => 'editor',
                    ]);
                    if (!is_wp_error($userId)) {
                        $wpUserId = (int)$userId;
                    }
                }
            }
            $this->wpdb->insert($t, [
                'wp_user_id' => $wpUserId,
                'first_name' => $r['first_name'],
                'last_name' => $r['last_name'],
                'email' => $r['email'],
                'created_at' => $seededAt,
                'archived_at' => null,
            ]);
        }
        return ['count' => (int)$this->wpdb->get_var("SELECT COUNT(*) FROM $t"), 'skipped' => false];
    }

    private function seedCustomersSitesContacts(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        $c = $this->wpdb->prefix . 'pet_customers';
        $s = $this->wpdb->prefix . 'pet_sites';
        $p = $this->wpdb->prefix . 'pet_contacts';
        $this->wpdb->insert($c, ['name' => 'RPM Resources (Pty) Ltd', 'contact_email' => 'info@rpm.example', 'malleable_data' => json_encode(['logo_url' => 'https://ui-avatars.com/api/?name=RPM&background=1a56db&color=fff&size=64&bold=true&length=3', 'brand_color' => '#1a56db']), 'created_at' => $seededAt]);
        $rpmId = (int)$this->wpdb->insert_id;
        $this->registryAdd($seedRunId, $c, (string)$rpmId);
        $this->wpdb->insert($c, ['name' => 'Acme Manufacturing SA (Pty) Ltd', 'contact_email' => 'info@acme.example', 'malleable_data' => json_encode(['logo_url' => 'https://ui-avatars.com/api/?name=AM&background=dc3545&color=fff&size=64&bold=true', 'brand_color' => '#dc3545']), 'created_at' => $seededAt]);
        $acmeId = (int)$this->wpdb->insert_id;
        $this->registryAdd($seedRunId, $c, (string)$acmeId);
        $this->wpdb->insert($s, ['customer_id' => $rpmId, 'name' => 'RPM Cape Town', 'created_at' => $seededAt]);
        $this->registryAdd($seedRunId, $s, (string)$this->wpdb->insert_id);
        $this->wpdb->insert($s, ['customer_id' => $rpmId, 'name' => 'RPM Johannesburg', 'created_at' => $seededAt]);
        $this->registryAdd($seedRunId, $s, (string)$this->wpdb->insert_id);
        $this->wpdb->insert($s, ['customer_id' => $acmeId, 'name' => 'Acme Stellenbosch', 'created_at' => $seededAt]);
        $this->registryAdd($seedRunId, $s, (string)$this->wpdb->insert_id);
        $this->wpdb->insert($p, ['customer_id' => $rpmId, 'first_name' => 'Priya', 'last_name' => 'Patel', 'email' => 'priya@rpm.example', 'created_at' => $seededAt]);
        $this->registryAdd($seedRunId, $p, (string)$this->wpdb->insert_id);
        $this->wpdb->insert($p, ['customer_id' => $rpmId, 'first_name' => 'John', 'last_name' => 'Mokoena', 'email' => 'john@rpm.example', 'created_at' => $seededAt]);
        $this->registryAdd($seedRunId, $p, (string)$this->wpdb->insert_id);
        $this->wpdb->insert($p, ['customer_id' => $acmeId, 'first_name' => 'Sarah', 'last_name' => 'Jacobs', 'email' => 'sarah@acme.example', 'created_at' => $seededAt]);
        $this->registryAdd($seedRunId, $p, (string)$this->wpdb->insert_id);
        $this->wpdb->insert($p, ['customer_id' => $acmeId, 'first_name' => 'David', 'last_name' => 'Naidoo', 'email' => 'david@acme.example', 'created_at' => $seededAt]);
        $this->registryAdd($seedRunId, $p, (string)$this->wpdb->insert_id);

        // Nexus Startup Labs
        $this->wpdb->insert($c, ['name' => 'Nexus Startup Labs', 'contact_email' => 'hello@nexuslabs.example', 'malleable_data' => json_encode(['logo_url' => 'https://ui-avatars.com/api/?name=NL&background=28a745&color=fff&size=64&bold=true', 'brand_color' => '#28a745']), 'created_at' => $seededAt]);
        $nexusId = (int)$this->wpdb->insert_id;
        $this->registryAdd($seedRunId, $c, (string)$nexusId);
        $this->wpdb->insert($s, ['customer_id' => $nexusId, 'name' => 'Nexus Cape Town', 'created_at' => $seededAt]);
        $this->registryAdd($seedRunId, $s, (string)$this->wpdb->insert_id);
        $this->wpdb->insert($p, ['customer_id' => $nexusId, 'first_name' => 'Tariq', 'last_name' => 'Hendricks', 'email' => 'tariq@nexuslabs.example', 'created_at' => $seededAt]);
        $this->registryAdd($seedRunId, $p, (string)$this->wpdb->insert_id);
        $this->wpdb->insert($p, ['customer_id' => $nexusId, 'first_name' => 'Lisa', 'last_name' => 'van Wyk', 'email' => 'lisa@nexuslabs.example', 'created_at' => $seededAt]);
        $this->registryAdd($seedRunId, $p, (string)$this->wpdb->insert_id);

        // Government Digital Services
        $this->wpdb->insert($c, ['name' => 'Government Digital Services', 'contact_email' => 'procurement@govdigital.example', 'malleable_data' => json_encode(['logo_url' => 'https://ui-avatars.com/api/?name=GDS&background=6f42c1&color=fff&size=64&bold=true&length=3', 'brand_color' => '#6f42c1']), 'created_at' => $seededAt]);
        $govId = (int)$this->wpdb->insert_id;
        $this->registryAdd($seedRunId, $c, (string)$govId);
        $this->wpdb->insert($s, ['customer_id' => $govId, 'name' => 'GDS Pretoria HQ', 'created_at' => $seededAt]);
        $this->registryAdd($seedRunId, $s, (string)$this->wpdb->insert_id);
        $this->wpdb->insert($s, ['customer_id' => $govId, 'name' => 'GDS Regional Office', 'created_at' => $seededAt]);
        $this->registryAdd($seedRunId, $s, (string)$this->wpdb->insert_id);
        $this->wpdb->insert($p, ['customer_id' => $govId, 'first_name' => 'Thabo', 'last_name' => 'Dlamini', 'email' => 'thabo@govdigital.example', 'created_at' => $seededAt]);
        $this->registryAdd($seedRunId, $p, (string)$this->wpdb->insert_id);

        return ['customers' => 4, 'sites' => 6, 'contacts' => 7];
    }

    private function seedTeams(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        $t = $this->wpdb->prefix . 'pet_teams';
        $this->wpdb->insert($t, ['name' => 'Executive', 'created_at' => $seededAt]);
        $this->registryAdd($seedRunId, $t, (string)$this->wpdb->insert_id);
        $this->wpdb->insert($t, ['name' => 'Delivery', 'created_at' => $seededAt]);
        $this->registryAdd($seedRunId, $t, (string)$this->wpdb->insert_id);
        $this->wpdb->insert($t, ['name' => 'Support', 'created_at' => $seededAt]);
        $this->registryAdd($seedRunId, $t, (string)$this->wpdb->insert_id);
        return ['teams' => 3];
    }

    private function seedCalendar(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        $t = $this->wpdb->prefix . 'pet_calendars';
        $columns = $this->wpdb->get_col("SHOW COLUMNS FROM $t");
        $cols = is_array($columns) ? array_flip($columns) : [];
        $hasUuid = isset($cols['uuid']);
        $hasIsDefault = isset($cols['is_default']);
        $hasConfig = isset($cols['config_json']);

        // Reuse existing default calendar if present
        if ($hasIsDefault) {
            $existingId = (int)$this->wpdb->get_var("SELECT id FROM $t WHERE is_default = 1 LIMIT 1");
            if ($existingId) {
                return ['calendars' => (int)$this->wpdb->get_var("SELECT COUNT(*) FROM $t"), 'reused_default' => $existingId];
            }
        }

        $data = [
            'name' => 'Default Calendar',
            'timezone' => 'Africa/Johannesburg',
        ];
        if ($hasUuid) {
            $uuid = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
            $data['uuid'] = $uuid;
        }
        if ($hasIsDefault) {
            $data['is_default'] = 1;
        }
        if ($hasConfig) {
            $data['config_json'] = json_encode([
                'workdays' => ['Mon','Tue','Wed','Thu','Fri'],
                'work_hours' => ['08:30-17:00'],
                'after_hours' => ['17:00-20:00'],
                'multipliers' => ['after_hours' => 1.5],
                'holidays' => ['01-01','04-27'],
                'seed_run_id' => $seedRunId,
                'seed_profile' => $seedProfile,
                'seeded_at' => $seededAt,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $this->wpdb->insert($t, $data);

        // --- Additional calendars for tiered SLA demo ---
        $c = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
        /** @var \Pet\Infrastructure\Persistence\Repository\SqlCalendarRepository $calRepo */
        $calRepo = $c->get(\Pet\Domain\Calendar\Repository\CalendarRepository::class);

        // Out of Hours: Mon-Fri 17:00-22:00 + Sat-Sun 08:00-18:00
        $oohWindows = [];
        foreach (['monday','tuesday','wednesday','thursday','friday'] as $day) {
            $oohWindows[] = new \Pet\Domain\Calendar\Entity\WorkingWindow($day, '17:00', '22:00', 'overtime', 1.5);
        }
        $oohWindows[] = new \Pet\Domain\Calendar\Entity\WorkingWindow('saturday', '08:00', '18:00', 'overtime', 1.5);
        $oohWindows[] = new \Pet\Domain\Calendar\Entity\WorkingWindow('sunday', '08:00', '18:00', 'overtime', 2.0);
        $oohCal = new \Pet\Domain\Calendar\Entity\Calendar(
            'Out of Hours',
            'Africa/Johannesburg',
            $oohWindows,
            [],
            false
        );
        $calRepo->save($oohCal);

        // 24/7 Coverage: all days 00:00-23:59
        $allDayCal = new \Pet\Domain\Calendar\Entity\Calendar(
            '24/7 Coverage',
            'Africa/Johannesburg',
            [], // empty windows — is24x7 flag handles it
            [],
            false,
            null,
            null,
            true // is24x7
        );
        $calRepo->save($allDayCal);

        return ['calendars' => (int)$this->wpdb->get_var("SELECT COUNT(*) FROM $t")];
    }

    private function seedCapability(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        $c = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
        /** @var \Pet\Infrastructure\Persistence\Repository\SqlCapabilityRepository $capRepo */
        $capRepo = $c->get(\Pet\Infrastructure\Persistence\Repository\SqlCapabilityRepository::class);
        /** @var \Pet\Infrastructure\Persistence\Repository\SqlProficiencyLevelRepository $profRepo */
        $profRepo = $c->get(\Pet\Infrastructure\Persistence\Repository\SqlProficiencyLevelRepository::class);
        /** @var \Pet\Application\Work\Command\CreateSkillHandler $createSkill */
        $createSkill = $c->get(\Pet\Application\Work\Command\CreateSkillHandler::class);
        /** @var \Pet\Application\Work\Command\CreateRoleHandler $createRole */
        $createRole = $c->get(\Pet\Application\Work\Command\CreateRoleHandler::class);
        /** @var \Pet\Application\Work\Command\PublishRoleHandler $publishRole */
        $publishRole = $c->get(\Pet\Application\Work\Command\PublishRoleHandler::class);
        /** @var \Pet\Application\Work\Command\CreateKpiDefinitionHandler $createKpi */
        $createKpi = $c->get(\Pet\Application\Work\Command\CreateKpiDefinitionHandler::class);
        /** @var \Pet\Application\Work\Command\AssignKpiToRoleHandler $assignKpiToRole */
        $assignKpiToRole = $c->get(\Pet\Application\Work\Command\AssignKpiToRoleHandler::class);
        /** @var \Pet\Application\Work\Command\AssignRoleToPersonHandler $assignRoleToPerson */
        $assignRoleToPerson = $c->get(\Pet\Application\Work\Command\AssignRoleToPersonHandler::class);
        /** @var \Pet\Application\Work\Command\RateEmployeeSkillHandler $rateSkill */
        $rateSkill = $c->get(\Pet\Application\Work\Command\RateEmployeeSkillHandler::class);
        /** @var \Pet\Application\Work\Command\CreateCertificationHandler $createCert */
        $createCert = $c->get(\Pet\Application\Work\Command\CreateCertificationHandler::class);
        /** @var \Pet\Application\Work\Command\AssignCertificationToPersonHandler $assignCert */
        $assignCert = $c->get(\Pet\Application\Work\Command\AssignCertificationToPersonHandler::class);

        // Proficiency levels
        $levels = [
            [1, 'Novice', 'Basic awareness'],
            [2, 'Intermediate', 'Working knowledge'],
            [3, 'Proficient', 'Independent contributor'],
            [4, 'Advanced', 'Expert practitioner'],
            [5, 'Master', 'Recognized authority'],
        ];
        foreach ($levels as [$num, $name, $def]) {
            $profRepo->save(new \Pet\Domain\Work\Entity\ProficiencyLevel($num, $name, $def));
        }

        // Capabilities
        $capabilities = [
            ['Web Delivery', 'Delivery of web projects'],
            ['Support Operations', 'Operational support and incident handling'],
            ['Governance & Advisory', 'Governance reviews and advisory services'],
        ];
        foreach ($capabilities as [$name, $desc]) {
            $capRepo->save(new \Pet\Domain\Work\Entity\Capability($name, $desc));
        }
        // Resolve capability IDs
        $capTable = $this->wpdb->prefix . 'pet_capabilities';
        $capIds = [
            'Web Delivery' => (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $capTable WHERE name=%s", 'Web Delivery')),
            'Support Operations' => (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $capTable WHERE name=%s", 'Support Operations')),
            'Governance & Advisory' => (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $capTable WHERE name=%s", 'Governance & Advisory')),
        ];

        // Skills
        $skills = [
            ['React Frontend', $capIds['Web Delivery'], 'Modern React UI'],
            ['PHP Backend', $capIds['Web Delivery'], 'API and services'],
            ['WordPress Admin', $capIds['Support Operations'], 'Site administration'],
            ['SLA Design', $capIds['Governance & Advisory'], 'Service level design'],
            ['Incident Handling', $capIds['Support Operations'], 'Triage and resolution'],
            ['Architecture Review', $capIds['Governance & Advisory'], 'Architecture assessments'],
        ];
        foreach ($skills as [$name, $capId, $desc]) {
            $createSkill->handle(new \Pet\Application\Work\Command\CreateSkillCommand($name, (int)$capId, $desc));
        }
        // Resolve skill IDs
        $skillsTable = $this->wpdb->prefix . 'pet_skills';
        $skillId = function (string $name): int {
            $t = $this->wpdb->prefix . 'pet_skills';
            return (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $t WHERE name=%s", $name));
        };

        // Roles with required skills
        $roles = [
            [
                'name' => 'Consultant',
                'level' => 'Senior',
                'desc' => 'Advisory and delivery consultant',
                'criteria' => 'Delivers advisory outcomes with fidelity',
                'skills' => [
                    $skillId('Architecture Review') => ['min_proficiency_level' => 4, 'importance_weight' => 3],
                    $skillId('PHP Backend') => ['min_proficiency_level' => 3, 'importance_weight' => 2],
                ],
            ],
            [
                'name' => 'Support Technician',
                'level' => 'Mid',
                'desc' => 'Frontline support operations',
                'criteria' => 'Resolves incidents within SLA',
                'skills' => [
                    $skillId('Incident Handling') => ['min_proficiency_level' => 3, 'importance_weight' => 3],
                    $skillId('WordPress Admin') => ['min_proficiency_level' => 2, 'importance_weight' => 2],
                ],
            ],
            [
                'name' => 'Project Manager',
                'level' => 'Senior',
                'desc' => 'Manages project delivery',
                'criteria' => 'Meets scope, schedule, budget',
                'skills' => [
                    $skillId('SLA Design') => ['min_proficiency_level' => 3, 'importance_weight' => 2],
                    $skillId('React Frontend') => ['min_proficiency_level' => 2, 'importance_weight' => 1],
                ],
            ],
        ];
        $roleIds = [];
        foreach ($roles as $r) {
            $roleId = $createRole->handle(new \Pet\Application\Work\Command\CreateRoleCommand(
                $r['name'],
                $r['level'],
                $r['desc'],
                $r['criteria'],
                $r['skills']
            ));
            $roleIds[$r['name']] = (int)$roleId;
            $publishRole->handle(new \Pet\Application\Work\Command\PublishRoleCommand((int)$roleId));
        }

        // KPI Definitions
        $kpis = [
            ['Utilization', 'Billable hours ratio', 'monthly', '%'],
            ['Tickets Resolved', 'Count of resolved tickets', 'monthly', 'count'],
            ['SLA Compliance', 'Percentage of tickets within SLA', 'monthly', '%'],
        ];
        foreach ($kpis as [$name, $desc, $freq, $unit]) {
            $createKpi->handle(new \Pet\Application\Work\Command\CreateKpiDefinitionCommand($name, $desc, $freq, $unit));
        }
        $kpiTable = $this->wpdb->prefix . 'pet_kpi_definitions';
        $kpiId = function (string $name): int {
            $t = $this->wpdb->prefix . 'pet_kpi_definitions';
            return (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $t WHERE name=%s", $name));
        };
        // Assign KPIs to roles
        $assignKpiToRole->handle(new \Pet\Application\Work\Command\AssignKpiToRoleCommand($roleIds['Consultant'], $kpiId('Utilization'), 40, 75.0, 'monthly'));
        $assignKpiToRole->handle(new \Pet\Application\Work\Command\AssignKpiToRoleCommand($roleIds['Consultant'], $kpiId('SLA Compliance'), 20, 90.0, 'monthly'));
        $assignKpiToRole->handle(new \Pet\Application\Work\Command\AssignKpiToRoleCommand($roleIds['Support Technician'], $kpiId('Tickets Resolved'), 60, 60.0, 'monthly'));
        $assignKpiToRole->handle(new \Pet\Application\Work\Command\AssignKpiToRoleCommand($roleIds['Support Technician'], $kpiId('SLA Compliance'), 40, 85.0, 'monthly'));
        $assignKpiToRole->handle(new \Pet\Application\Work\Command\AssignKpiToRoleCommand($roleIds['Project Manager'], $kpiId('Utilization'), 30, 50.0, 'monthly'));

        // Assign roles to people
        $empTable = $this->wpdb->prefix . 'pet_employees';
        $miaId = (int)$this->wpdb->get_var("SELECT id FROM $empTable WHERE first_name='Mia' LIMIT 1");
        $liamId = (int)$this->wpdb->get_var("SELECT id FROM $empTable WHERE first_name='Liam' LIMIT 1");
        $avaId = (int)$this->wpdb->get_var("SELECT id FROM $empTable WHERE first_name='Ava' LIMIT 1");
        if ($miaId && $roleIds['Project Manager']) {
            $assignRoleToPerson->handle(new \Pet\Application\Work\Command\AssignRoleToPersonCommand($miaId, $roleIds['Project Manager'], $seededAt, 100));
        }
        if ($liamId && $roleIds['Support Technician']) {
            $assignRoleToPerson->handle(new \Pet\Application\Work\Command\AssignRoleToPersonCommand($liamId, $roleIds['Support Technician'], $seededAt, 100));
        }
        if ($avaId && $roleIds['Consultant']) {
            $assignRoleToPerson->handle(new \Pet\Application\Work\Command\AssignRoleToPersonCommand($avaId, $roleIds['Consultant'], $seededAt, 100));
        }

        // Rate employee skills
        $rateSkill->handle(new \Pet\Application\Work\Command\RateEmployeeSkillCommand($liamId, $skillId('Incident Handling'), 3, 4, $seededAt));
        $rateSkill->handle(new \Pet\Application\Work\Command\RateEmployeeSkillCommand($miaId, $skillId('SLA Design'), 4, 4, $seededAt));
        $rateSkill->handle(new \Pet\Application\Work\Command\RateEmployeeSkillCommand($avaId, $skillId('Architecture Review'), 4, 5, $seededAt));

        // Certifications
        $createCert->handle(new \Pet\Application\Work\Command\CreateCertificationCommand('ITIL Foundation', 'Axelos', 0));
        $createCert->handle(new \Pet\Application\Work\Command\CreateCertificationCommand('AWS Solutions Architect', 'AWS', 36));
        $certTable = $this->wpdb->prefix . 'pet_certifications';
        $itilId = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $certTable WHERE name=%s", 'ITIL Foundation'));
        $awsId = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $certTable WHERE name=%s", 'AWS Solutions Architect'));
        if ($liamId && $itilId) {
            $assignCert->handle(new \Pet\Application\Work\Command\AssignCertificationToPersonCommand($liamId, $itilId, substr($seededAt, 0, 10), null, null));
        }
        if ($avaId && $awsId) {
            $assignCert->handle(new \Pet\Application\Work\Command\AssignCertificationToPersonCommand($avaId, $awsId, substr($seededAt, 0, 10), null, null));
        }

        // Generate Person KPIs for current month
        /** @var \Pet\Application\Work\Command\GeneratePersonKpisHandler $generatePersonKpis */
        $generatePersonKpis = $c->get(\Pet\Application\Work\Command\GeneratePersonKpisHandler::class);
        $periodStart = (new \DateTimeImmutable('first day of this month'))->format('Y-m-d');
        $periodEnd = (new \DateTimeImmutable('last day of this month'))->format('Y-m-d');
        if ($liamId && $roleIds['Support Technician']) {
            $generatePersonKpis->handle(new \Pet\Application\Work\Command\GeneratePersonKpisCommand($liamId, $roleIds['Support Technician'], $periodStart, $periodEnd));
        }
        if ($miaId && $roleIds['Project Manager']) {
            $generatePersonKpis->handle(new \Pet\Application\Work\Command\GeneratePersonKpisCommand($miaId, $roleIds['Project Manager'], $periodStart, $periodEnd));
        }
        if ($avaId && $roleIds['Consultant']) {
            $generatePersonKpis->handle(new \Pet\Application\Work\Command\GeneratePersonKpisCommand($avaId, $roleIds['Consultant'], $periodStart, $periodEnd));
        }

        $capsCount = (int)$this->wpdb->get_var("SELECT COUNT(*) FROM {$this->wpdb->prefix}pet_capabilities");
        $skillsCount = (int)$this->wpdb->get_var("SELECT COUNT(*) FROM {$this->wpdb->prefix}pet_skills");
        $rolesCount = (int)$this->wpdb->get_var("SELECT COUNT(*) FROM {$this->wpdb->prefix}pet_roles");
        $kpisCount = (int)$this->wpdb->get_var("SELECT COUNT(*) FROM {$this->wpdb->prefix}pet_kpi_definitions");
        return ['capabilities' => $capsCount, 'skills' => $skillsCount, 'roles' => $rolesCount, 'kpi_definitions' => $kpisCount];
    }

    private function seedLeave(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        $types = $this->wpdb->prefix . 'pet_leave_types';
        $req = $this->wpdb->prefix . 'pet_leave_requests';
        $sqlUpsert = "INSERT INTO $types (name, paid_flag) VALUES (%s, %d) ON DUPLICATE KEY UPDATE paid_flag = VALUES(paid_flag)";
        $this->wpdb->query($this->wpdb->prepare($sqlUpsert, 'Annual Leave', 1));
        $this->wpdb->query($this->wpdb->prepare($sqlUpsert, 'Sick Leave', 1));
        $annualId = (int)$this->wpdb->get_var("SELECT id FROM $types WHERE name = 'Annual Leave' LIMIT 1");
        $avaId = (int)$this->wpdb->get_var("SELECT id FROM {$this->wpdb->prefix}pet_employees WHERE first_name='Ava' LIMIT 1");
        $miaId = (int)$this->wpdb->get_var("SELECT id FROM {$this->wpdb->prefix}pet_employees WHERE first_name='Mia' LIMIT 1");
        $liamId = (int)$this->wpdb->get_var("SELECT id FROM {$this->wpdb->prefix}pet_employees WHERE first_name='Liam' LIMIT 1");
        $noahId = (int)$this->wpdb->get_var("SELECT id FROM {$this->wpdb->prefix}pet_employees WHERE first_name='Noah' LIMIT 1");
        $this->wpdb->insert($req, [
            'uuid' => $this->uuid(),
            'employee_id' => $avaId,
            'leave_type_id' => $annualId,
            'start_date' => '2026-02-24',
            'end_date' => '2026-02-28',
            'status' => 'approved',
            'submitted_at' => $seededAt,
            'decided_by_employee_id' => $miaId,
            'decided_at' => $seededAt,
            'decision_reason' => 'Approved per policy',
            'notes' => '',
            'created_at' => $seededAt,
            'updated_at' => $seededAt,
        ]);
        $this->wpdb->insert($req, [
            'uuid' => $this->uuid(),
            'employee_id' => $liamId,
            'leave_type_id' => $annualId,
            'start_date' => '2026-03-10',
            'end_date' => '2026-03-14',
            'status' => 'submitted',
            'submitted_at' => $seededAt,
            'decided_by_employee_id' => null,
            'decided_at' => null,
            'decision_reason' => null,
            'notes' => '',
            'created_at' => $seededAt,
            'updated_at' => $seededAt,
        ]);
        $this->wpdb->insert($req, [
            'uuid' => $this->uuid(),
            'employee_id' => $noahId,
            'leave_type_id' => $annualId,
            'start_date' => '2026-02-20',
            'end_date' => '2026-02-21',
            'status' => 'rejected',
            'submitted_at' => $seededAt,
            'decided_by_employee_id' => $miaId,
            'decided_at' => $seededAt,
            'decision_reason' => 'Operational constraints',
            'notes' => '',
            'created_at' => $seededAt,
            'updated_at' => $seededAt,
        ]);
        return ['types' => 2, 'requests' => 3];
    }

    private function seedCatalog(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        $c = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
        /** @var \Pet\Infrastructure\Persistence\Repository\SqlCatalogItemRepository $repo */
        $repo = $c->get(\Pet\Infrastructure\Persistence\Repository\SqlCatalogItemRepository::class);
        $items = [
            new \Pet\Domain\Commercial\Entity\CatalogItem('Consulting Hour', 180.0, 110.0, 'service', 'SERV-001', 'General consulting', 'Services', []),
            new \Pet\Domain\Commercial\Entity\CatalogItem('Support Hour', 150.0, 90.0, 'service', 'SERV-002', 'Operational support', 'Services', []),
            new \Pet\Domain\Commercial\Entity\CatalogItem('Onsite Training Day', 1200.0, 700.0, 'service', 'SERV-003', 'Onsite training', 'Training', []),
            new \Pet\Domain\Commercial\Entity\CatalogItem('Website Hosting', 50.0, 20.0, 'service', 'SERV-004', 'Monthly hosting', 'Hosting', []),
            new \Pet\Domain\Commercial\Entity\CatalogItem('Theme License', 75.0, 40.0, 'product', 'PROD-100', 'Premium theme license', 'Licenses', []),
            new \Pet\Domain\Commercial\Entity\CatalogItem('Security Plugin', 90.0, 30.0, 'product', 'PROD-200', 'Security plugin license', 'Licenses', []),
            new \Pet\Domain\Commercial\Entity\CatalogItem('Governance Review', 200.0, 120.0, 'service', 'ADVIS-001', 'Governance review session', 'Advisory', []),
            new \Pet\Domain\Commercial\Entity\CatalogItem('SLA Design Session', 220.0, 140.0, 'service', 'ADVIS-002', 'SLA design workshop', 'Advisory', []),
            new \Pet\Domain\Commercial\Entity\CatalogItem('DevOps Hour', 195.0, 125.0, 'service', 'SERV-005', 'DevOps engineering and automation', 'Services', []),
            new \Pet\Domain\Commercial\Entity\CatalogItem('BA Consulting Hour', 170.0, 105.0, 'service', 'SERV-006', 'Business analysis and requirements', 'Services', []),
            new \Pet\Domain\Commercial\Entity\CatalogItem('Emergency Support Hour', 280.0, 150.0, 'service', 'SERV-007', 'After-hours emergency support (1.5x)', 'Services', []),
            new \Pet\Domain\Commercial\Entity\CatalogItem('Managed Backup Service', 120.0, 45.0, 'service', 'RECUR-001', 'Monthly managed backup and DR', 'Managed Services', []),
            new \Pet\Domain\Commercial\Entity\CatalogItem('SSL Certificate', 25.0, 10.0, 'product', 'PROD-300', 'Annual wildcard SSL certificate', 'Licenses', []),
            new \Pet\Domain\Commercial\Entity\CatalogItem('Cloud Migration Assessment', 2500.0, 1400.0, 'service', 'SERV-008', 'Full cloud readiness assessment', 'Advisory', []),
        ];
        foreach ($items as $it) {
            $repo->save($it);
        }
        $count = (int)$this->wpdb->get_var("SELECT COUNT(*) FROM {$this->wpdb->prefix}pet_catalog_items");
        return ['catalog_items' => $count];
    }

    private function seedCommercial(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        $c = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
        /** @var \Pet\Application\Commercial\Command\CreateQuoteHandler $createQuote */
        $createQuote = $c->get(\Pet\Application\Commercial\Command\CreateQuoteHandler::class);
        /** @var \Pet\Application\Commercial\Command\AddComponentHandler $addComponent */
        $addComponent = $c->get(\Pet\Application\Commercial\Command\AddComponentHandler::class);
        /** @var \Pet\Application\Commercial\Command\SendQuoteHandler $sendQuote */
        $sendQuote = $c->get(\Pet\Application\Commercial\Command\SendQuoteHandler::class);
        /** @var \Pet\Application\Commercial\Command\AcceptQuoteHandler $acceptQuote */
        $acceptQuote = $c->get(\Pet\Application\Commercial\Command\AcceptQuoteHandler::class);
        /** @var \Pet\Application\Commercial\Command\SetPaymentScheduleHandler $setPayment */
        $setPayment = $c->get(\Pet\Application\Commercial\Command\SetPaymentScheduleHandler::class);
        /** @var \Pet\Domain\Commercial\Repository\QuoteRepository $quoteRepo */
        $quoteRepo = $c->get(\Pet\Domain\Commercial\Repository\QuoteRepository::class);

        $rpmId = (int)$this->wpdb->get_var("SELECT id FROM {$this->wpdb->prefix}pet_customers WHERE name = 'RPM Resources (Pty) Ltd' LIMIT 1");
        $acmeId = (int)$this->wpdb->get_var("SELECT id FROM {$this->wpdb->prefix}pet_customers WHERE name = 'Acme Manufacturing SA (Pty) Ltd' LIMIT 1");
        $quotesTable = $this->wpdb->prefix . 'pet_quotes';

        // Q1: Composite (Implementation + Catalog), accepted (complex implementation: 4 milestones, 10 tasks)
        $q1ExistingId = (int)$this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM $quotesTable WHERE title = %s ORDER BY id DESC LIMIT 1",
            'Q1 Website Implementation & Advisory'
        ));
        $q1Id = null;
        $q1New = false;
        if ($q1ExistingId > 0) {
            $componentsTable = $this->wpdb->prefix . 'pet_quote_components';
            $existingTypes = $this->wpdb->get_col($this->wpdb->prepare(
                "SELECT type FROM $componentsTable WHERE quote_id = %d",
                $q1ExistingId
            ));
            $hasImplementation = in_array('implementation', $existingTypes, true);
            $hasCatalog = in_array('catalog', $existingTypes, true);
            if ($hasImplementation && $hasCatalog) {
                $q1Id = $q1ExistingId;
            }
        }
        if ($q1Id === null) {
            $q1Id = $createQuote->handle(new \Pet\Application\Commercial\Command\CreateQuoteCommand(
                $rpmId,
                'Q1 Website Implementation & Advisory',
                'Composite structure per demo pack',
                'USD'
            ));
            $q1New = true;
            $this->registryAdd($seedRunId, $quotesTable, (string)$q1Id);
        }
        if ($q1New) {
            $addComponent->handle(new \Pet\Application\Commercial\Command\AddComponentCommand($q1Id, 'implementation', [
                'section' => 'Delivery',
                'description' => 'Implementation Work',
                'milestones' => [
                    [
                        'description' => 'Discovery',
                        'tasks' => [
                            ['description' => 'Kickoff Workshop', 'duration_hours' => 6, 'complexity' => 2, 'sell_rate' => 150.0, 'internal_cost' => 100.0],
                            ['description' => 'Requirements Elicitation', 'duration_hours' => 12, 'complexity' => 3, 'sell_rate' => 150.0, 'internal_cost' => 100.0],
                        ]
                    ],
                    [
                        'description' => 'Build',
                        'tasks' => [
                            ['description' => 'Theme Setup', 'duration_hours' => 10, 'complexity' => 2, 'sell_rate' => 150.0, 'internal_cost' => 100.0],
                            ['description' => 'Custom Components', 'duration_hours' => 20, 'complexity' => 4, 'sell_rate' => 180.0, 'internal_cost' => 120.0],
                        ]
                    ],
                    [
                        'description' => 'Launch Preparation',
                        'tasks' => [
                            ['description' => 'UAT Support', 'duration_hours' => 8, 'complexity' => 3, 'sell_rate' => 160.0, 'internal_cost' => 110.0],
                            ['description' => 'Go-Live Checklist', 'duration_hours' => 6, 'complexity' => 2, 'sell_rate' => 160.0, 'internal_cost' => 110.0],
                            ['description' => 'Cutover Planning', 'duration_hours' => 4, 'complexity' => 2, 'sell_rate' => 160.0, 'internal_cost' => 110.0],
                        ]
                    ],
                    [
                        'description' => 'Post Go-Live Support',
                        'tasks' => [
                            ['description' => 'Hypercare Support', 'duration_hours' => 6, 'complexity' => 2, 'sell_rate' => 150.0, 'internal_cost' => 100.0],
                            ['description' => 'Stabilization Review', 'duration_hours' => 4, 'complexity' => 2, 'sell_rate' => 150.0, 'internal_cost' => 100.0],
                            ['description' => 'Handover Workshop', 'duration_hours' => 6, 'complexity' => 3, 'sell_rate' => 170.0, 'internal_cost' => 120.0],
                        ]
                    ],
                ]
            ]));
        }
        $catTable = $this->wpdb->prefix . 'pet_catalog_items';
        $govId = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $catTable WHERE sku = %s LIMIT 1", 'ADVIS-001'));
        $slaId = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $catTable WHERE sku = %s LIMIT 1", 'ADVIS-002'));
        $rolesTable = $this->wpdb->prefix . 'pet_roles';
        $consultRoleId = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $rolesTable WHERE name=%s LIMIT 1", 'Consultant'));
        if ($consultRoleId <= 0) {
            $consultRoleId = (int)$this->wpdb->get_var("SELECT id FROM $rolesTable ORDER BY id ASC LIMIT 1");
        }
        if ($q1New) {
            $addComponent->handle(new \Pet\Application\Commercial\Command\AddComponentCommand($q1Id, 'catalog', [
                'section' => 'Advisory',
                'description' => 'Advisory Pack',
                'items' => [
                    ['description' => 'Governance Review', 'quantity' => 4, 'unit_sell_price' => 200.0, 'unit_internal_cost' => 120.0, 'catalog_item_id' => $govId, 'sku' => 'ADVIS-001', 'type' => 'service', 'role_id' => $consultRoleId],
                    ['description' => 'SLA Design Session', 'quantity' => 3, 'unit_sell_price' => 220.0, 'unit_internal_cost' => 140.0, 'catalog_item_id' => $slaId, 'sku' => 'ADVIS-002', 'type' => 'service', 'role_id' => $consultRoleId],
                ]
            ]));
        }
        $q1 = $quoteRepo->findById($q1Id);
        if ($q1 && !$q1->state()->isTerminal()) {
            $q1Total = $q1->totalValue();
            $first = round($q1Total * 0.5, 2);
            $second = round($q1Total - $first, 2);
            $setPayment->handle(new \Pet\Application\Commercial\Command\SetPaymentScheduleCommand($q1Id, [
                ['title' => 'Deposit 50%', 'amount' => $first, 'dueDate' => null],
                ['title' => 'Final 50%', 'amount' => $second, 'dueDate' => null],
            ]));
        }
        $acceptedAt = $this->wpdb->get_var($this->wpdb->prepare("SELECT accepted_at FROM $quotesTable WHERE id = %d", $q1Id));
            
            if (!$acceptedAt) {
                $sendQuote->handle(new \Pet\Application\Commercial\Command\SendQuoteCommand($q1Id));
                $acceptQuote->handle(new \Pet\Application\Commercial\Command\AcceptQuoteCommand($q1Id));
            } else {
                fwrite(STDERR, "DemoSeedService: Q1 already accepted.\n");
            }
        $contractsTable = $this->wpdb->prefix . 'pet_contracts';
        $contractId = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $contractsTable WHERE quote_id = %d ORDER BY id DESC LIMIT 1", $q1Id));
        if ($acceptedAt && $contractId <= 0) {
            $eventBus = $c->get(EventBus::class);
            $quoteEntity = $quoteRepo->findById($q1Id);
            if ($quoteEntity) {
                $eventBus->dispatch(new QuoteAccepted($quoteEntity));
            }
            $contractId = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $contractsTable WHERE quote_id = %d ORDER BY id DESC LIMIT 1", $q1Id));
        }
        if ($contractId > 0) {
            $baselineRepo = $c->get(\Pet\Domain\Commercial\Repository\BaselineRepository::class);
            $existingBaseline = $baselineRepo->findByContractId($contractId);
            if ($existingBaseline === null) {
                $quoteEntity = $quoteRepo->findById($q1Id);
                if ($quoteEntity && !empty($quoteEntity->components())) {
                    $baseline = new \Pet\Domain\Commercial\Entity\Baseline(
                        $contractId,
                        $quoteEntity->totalValue(),
                        $quoteEntity->totalInternalCost(),
                        $quoteEntity->components()
                    );
                    $baselineRepo->save($baseline);
                }
            }
        }
        $projCheck = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM {$this->wpdb->prefix}pet_projects WHERE source_quote_id = %d LIMIT 1", $q1Id));
        if ($projCheck <= 0) {
            /** @var \Pet\Application\Delivery\Command\CreateProjectHandler $createProject */
            $createProject = $c->get(\Pet\Application\Delivery\Command\CreateProjectHandler::class);
            $q1 = $quoteRepo->findById($q1Id);
            $soldValue = $q1 ? $q1->totalValue() : 0.0;
            $createProject->handle(new \Pet\Application\Delivery\Command\CreateProjectCommand(
                $rpmId,
                'RPM Website Implementation & Advisory',
                0.0,
                $q1Id,
                $soldValue,
                null,
                null,
                []
            ));
        }
        $projectId = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM {$this->wpdb->prefix}pet_projects WHERE source_quote_id = %d ORDER BY id DESC LIMIT 1", $q1Id));
        if ($projectId > 0) {
            $this->registryAdd($seedRunId, $this->wpdb->prefix . 'pet_projects', (string)$projectId);
        }

        // Q2: Milestone-only (Implementation), draft (simple implementation: 2 milestones, 3 tasks)
        $q2Existing = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $quotesTable WHERE title = %s ORDER BY id DESC LIMIT 1", 'Q2 ERP Migration Plan'));
        $q2New = $q2Existing <= 0;
        $q2Id = $q2Existing > 0 ? $q2Existing : $createQuote->handle(new \Pet\Application\Commercial\Command\CreateQuoteCommand(
            $acmeId,
            'Q2 ERP Migration Plan',
            'Milestone-only implementation',
            'USD'
        ));
        if ($q2New) {
            $this->registryAdd($seedRunId, $quotesTable, (string)$q2Id);
            $addComponent->handle(new \Pet\Application\Commercial\Command\AddComponentCommand($q2Id, 'implementation', [
                'section' => 'Delivery',
                'description' => 'Migration Plan',
                'milestones' => [
                    [
                        'description' => 'Assessment',
                        'tasks' => [
                            ['description' => 'System Audit', 'duration_hours' => 8, 'complexity' => 3, 'sell_rate' => 160.0, 'internal_cost' => 110.0],
                            ['description' => 'Risk Analysis', 'duration_hours' => 6, 'complexity' => 2, 'sell_rate' => 160.0, 'internal_cost' => 110.0],
                        ]
                    ],
                    [
                        'description' => 'Cutover Readiness',
                        'tasks' => [
                            ['description' => 'Cutover Playbook', 'duration_hours' => 4, 'complexity' => 2, 'sell_rate' => 160.0, 'internal_cost' => 110.0],
                        ]
                    ],
                ]
            ]));
        }

        // Q3: Recurring-only, draft
        $q3Existing = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $quotesTable WHERE title = %s ORDER BY id DESC LIMIT 1", 'Q3 Managed Support'));
        $q3New = $q3Existing <= 0;
        $q3Id = $q3Existing > 0 ? $q3Existing : $createQuote->handle(new \Pet\Application\Commercial\Command\CreateQuoteCommand(
            $rpmId,
            'Q3 Managed Support',
            'Recurring support services',
            'USD'
        ));
        if ($q3New) {
            $this->registryAdd($seedRunId, $quotesTable, (string)$q3Id);
            $addComponent->handle(new \Pet\Application\Commercial\Command\AddComponentCommand($q3Id, 'recurring', [
                'section' => 'Support',
                'description' => 'Managed Support SLA',
                'service_name' => 'Managed Support',
                'cadence' => 'monthly',
                'term_months' => 12,
                'renewal_model' => 'auto_renew',
                'sell_price_per_period' => 1500.0,
                'internal_cost_per_period' => 900.0,
                'sla_snapshot' => ['name' => 'Standard', 'response_minutes' => 240, 'resolution_minutes' => 1440]
            ]));
        }

        // Q4: Catalog-only, accepted
        $q4Existing = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $quotesTable WHERE title = %s ORDER BY id DESC LIMIT 1", 'Q4 Catalog Services'));
        $q4New = $q4Existing <= 0;
        $q4Id = $q4Existing > 0 ? $q4Existing : $createQuote->handle(new \Pet\Application\Commercial\Command\CreateQuoteCommand(
            $acmeId,
            'Q4 Catalog Services',
            'Catalog-only quote',
            'USD'
        ));
        if ($q4New) {
            $this->registryAdd($seedRunId, $quotesTable, (string)$q4Id);
        }
        $trainId = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $catTable WHERE sku = %s LIMIT 1", 'SERV-003'));
        $consultId = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $catTable WHERE sku = %s LIMIT 1", 'SERV-001'));
        if ($consultRoleId <= 0) {
            $rolesTable = $this->wpdb->prefix . 'pet_roles';
            $consultRoleId = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $rolesTable WHERE name=%s LIMIT 1", 'Consultant'));
            if ($consultRoleId <= 0) {
                $consultRoleId = (int)$this->wpdb->get_var("SELECT id FROM $rolesTable ORDER BY id ASC LIMIT 1");
            }
        }
        if ($q4New) {
            $addComponent->handle(new \Pet\Application\Commercial\Command\AddComponentCommand($q4Id, 'catalog', [
                'section' => 'Services',
                'description' => 'Standard Service Pack',
                'items' => [
                    ['description' => 'Onsite Training', 'quantity' => 2, 'unit_sell_price' => 500.0, 'unit_internal_cost' => 300.0, 'catalog_item_id' => $trainId, 'sku' => 'SERV-003', 'type' => 'service', 'role_id' => $consultRoleId],
                    ['description' => 'Remote Consulting', 'quantity' => 6, 'unit_sell_price' => 180.0, 'unit_internal_cost' => 100.0, 'catalog_item_id' => $consultId, 'sku' => 'SERV-001', 'type' => 'service', 'role_id' => $consultRoleId],
                ]
            ]));
        }
        $q4 = $quoteRepo->findById($q4Id);
        if ($q4 && !$q4->state()->isTerminal()) {
            $q4Total = $q4->totalValue();
            $first = round($q4Total * 0.5, 2);
            $second = round($q4Total - $first, 2);
            $setPayment->handle(new \Pet\Application\Commercial\Command\SetPaymentScheduleCommand($q4Id, [
                ['title' => 'Deposit 50%', 'amount' => $first, 'dueDate' => null],
                ['title' => 'Final 50%', 'amount' => $second, 'dueDate' => null],
            ]));
        }
        $accepted4 = $this->wpdb->get_var($this->wpdb->prepare("SELECT accepted_at FROM $quotesTable WHERE id = %d", $q4Id));
        if (!$accepted4) {
            $sendQuote->handle(new \Pet\Application\Commercial\Command\SendQuoteCommand($q4Id));
            $acceptQuote->handle(new \Pet\Application\Commercial\Command\AcceptQuoteCommand($q4Id));
        }
        // Project for Q4 (Acme Catalog Services)
        $projQ4Check = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM {$this->wpdb->prefix}pet_projects WHERE source_quote_id = %d LIMIT 1", $q4Id));
        if ($projQ4Check <= 0) {
            $createProject = $c->get(\Pet\Application\Delivery\Command\CreateProjectHandler::class);
            $q4e = $quoteRepo->findById($q4Id);
            $createProject->handle(new \Pet\Application\Delivery\Command\CreateProjectCommand(
                $acmeId, 'Acme Catalog Services Delivery', 0.0, $q4Id, $q4e ? $q4e->totalValue() : 0.0, null, null, []
            ));
        }

        // Resolve new customer IDs
        $nexusId = (int)$this->wpdb->get_var("SELECT id FROM {$this->wpdb->prefix}pet_customers WHERE name = 'Nexus Startup Labs' LIMIT 1");
        $govCustId = (int)$this->wpdb->get_var("SELECT id FROM {$this->wpdb->prefix}pet_customers WHERE name = 'Government Digital Services' LIMIT 1");
        $supportRoleId = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $rolesTable WHERE name=%s LIMIT 1", 'Support Technician'));
        if ($supportRoleId <= 0) $supportRoleId = $consultRoleId;

        // Resolve new catalog item IDs
        $devopsId = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $catTable WHERE sku = %s LIMIT 1", 'SERV-005'));
        $baId = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $catTable WHERE sku = %s LIMIT 1", 'SERV-006'));
        $backupId = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $catTable WHERE sku = %s LIMIT 1", 'RECUR-001'));
        $sslId = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $catTable WHERE sku = %s LIMIT 1", 'PROD-300'));
        $cloudAssessId = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $catTable WHERE sku = %s LIMIT 1", 'SERV-008'));
        $emergId = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $catTable WHERE sku = %s LIMIT 1", 'SERV-007'));
        $supportHrId = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $catTable WHERE sku = %s LIMIT 1", 'SERV-002'));

        // Q5: Nexus Cloud Migration (Implementation + Recurring) — accepted → project
        $q5Existing = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $quotesTable WHERE title = %s ORDER BY id DESC LIMIT 1", 'Q5 Cloud Migration & Managed Services'));
        $q5New = $q5Existing <= 0;
        $q5Id = $q5Existing > 0 ? $q5Existing : $createQuote->handle(new \Pet\Application\Commercial\Command\CreateQuoteCommand(
            $nexusId, 'Q5 Cloud Migration & Managed Services', 'Implementation with ongoing managed services for cloud-first startup', 'USD'
        ));
        if ($q5New) {
            $this->registryAdd($seedRunId, $quotesTable, (string)$q5Id);
            $addComponent->handle(new \Pet\Application\Commercial\Command\AddComponentCommand($q5Id, 'implementation', [
                'section' => 'Delivery',
                'description' => 'Cloud Migration',
                'milestones' => [
                    [
                        'description' => 'Assessment & Planning',
                        'tasks' => [
                            ['description' => 'Cloud Readiness Assessment', 'duration_hours' => 16, 'complexity' => 3, 'sell_rate' => 195.0, 'internal_cost' => 125.0],
                            ['description' => 'Architecture Design', 'duration_hours' => 12, 'complexity' => 4, 'sell_rate' => 195.0, 'internal_cost' => 125.0],
                            ['description' => 'Migration Runbook', 'duration_hours' => 8, 'complexity' => 2, 'sell_rate' => 170.0, 'internal_cost' => 105.0],
                        ]
                    ],
                    [
                        'description' => 'Migration Execution',
                        'tasks' => [
                            ['description' => 'Infrastructure Provisioning', 'duration_hours' => 20, 'complexity' => 4, 'sell_rate' => 195.0, 'internal_cost' => 125.0],
                            ['description' => 'Data Migration', 'duration_hours' => 16, 'complexity' => 4, 'sell_rate' => 195.0, 'internal_cost' => 125.0],
                            ['description' => 'Application Deployment', 'duration_hours' => 12, 'complexity' => 3, 'sell_rate' => 195.0, 'internal_cost' => 125.0],
                        ]
                    ],
                    [
                        'description' => 'Validation & Handover',
                        'tasks' => [
                            ['description' => 'Performance Testing', 'duration_hours' => 8, 'complexity' => 3, 'sell_rate' => 195.0, 'internal_cost' => 125.0],
                            ['description' => 'Security Audit', 'duration_hours' => 6, 'complexity' => 3, 'sell_rate' => 200.0, 'internal_cost' => 130.0],
                            ['description' => 'Handover & Training', 'duration_hours' => 8, 'complexity' => 2, 'sell_rate' => 170.0, 'internal_cost' => 105.0],
                        ]
                    ],
                ]
            ]));
            $addComponent->handle(new \Pet\Application\Commercial\Command\AddComponentCommand($q5Id, 'recurring', [
                'section' => 'Managed Services',
                'description' => 'Ongoing Cloud Management',
                'service_name' => 'Managed Cloud & Backup',
                'cadence' => 'monthly',
                'term_months' => 24,
                'renewal_model' => 'auto_renew',
                'sell_price_per_period' => 2200.0,
                'internal_cost_per_period' => 1100.0,
                'sla_snapshot' => ['name' => 'Premium', 'response_minutes' => 60, 'resolution_minutes' => 480]
            ]));
        }
        $q5e = $quoteRepo->findById($q5Id);
        if ($q5e && !$q5e->state()->isTerminal()) {
            $q5Total = $q5e->totalValue();
            $setPayment->handle(new \Pet\Application\Commercial\Command\SetPaymentScheduleCommand($q5Id, [
                ['title' => 'Upfront 30%', 'amount' => round($q5Total * 0.3, 2), 'dueDate' => null],
                ['title' => 'Mid-project 40%', 'amount' => round($q5Total * 0.4, 2), 'dueDate' => null],
                ['title' => 'Completion 30%', 'amount' => round($q5Total * 0.3, 2), 'dueDate' => null],
            ]));
        }
        $accepted5 = $this->wpdb->get_var($this->wpdb->prepare("SELECT accepted_at FROM $quotesTable WHERE id = %d", $q5Id));
        if (!$accepted5) {
            $sendQuote->handle(new \Pet\Application\Commercial\Command\SendQuoteCommand($q5Id));
            $acceptQuote->handle(new \Pet\Application\Commercial\Command\AcceptQuoteCommand($q5Id));
        }
        $projQ5Check = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM {$this->wpdb->prefix}pet_projects WHERE source_quote_id = %d LIMIT 1", $q5Id));
        if ($projQ5Check <= 0) {
            $createProject = $c->get(\Pet\Application\Delivery\Command\CreateProjectHandler::class);
            $q5e = $quoteRepo->findById($q5Id);
            $createProject->handle(new \Pet\Application\Delivery\Command\CreateProjectCommand(
                $nexusId, 'Nexus Cloud Migration & Managed Services', 0.0, $q5Id, $q5e ? $q5e->totalValue() : 0.0, null, null, []
            ));
        }

        // Q6: Government IT Assessment (Catalog only) — draft
        $q6Existing = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $quotesTable WHERE title = %s ORDER BY id DESC LIMIT 1", 'Q6 IT Infrastructure Assessment'));
        $q6New = $q6Existing <= 0;
        $q6Id = $q6Existing > 0 ? $q6Existing : $createQuote->handle(new \Pet\Application\Commercial\Command\CreateQuoteCommand(
            $govCustId, 'Q6 IT Infrastructure Assessment', 'Government procurement — catalog-based assessment and advisory package', 'USD'
        ));
        if ($q6New) {
            $this->registryAdd($seedRunId, $quotesTable, (string)$q6Id);
            $addComponent->handle(new \Pet\Application\Commercial\Command\AddComponentCommand($q6Id, 'catalog', [
                'section' => 'Advisory',
                'description' => 'Assessment Package',
                'items' => [
                    ['description' => 'Cloud Migration Assessment', 'quantity' => 1, 'unit_sell_price' => 2500.0, 'unit_internal_cost' => 1400.0, 'catalog_item_id' => $cloudAssessId, 'sku' => 'SERV-008', 'type' => 'service', 'role_id' => $consultRoleId],
                    ['description' => 'Governance Review Sessions', 'quantity' => 6, 'unit_sell_price' => 200.0, 'unit_internal_cost' => 120.0, 'catalog_item_id' => $govId, 'sku' => 'ADVIS-001', 'type' => 'service', 'role_id' => $consultRoleId],
                    ['description' => 'BA Consulting', 'quantity' => 10, 'unit_sell_price' => 170.0, 'unit_internal_cost' => 105.0, 'catalog_item_id' => $baId, 'sku' => 'SERV-006', 'type' => 'service', 'role_id' => $consultRoleId],
                    ['description' => 'SSL Certificates', 'quantity' => 4, 'unit_sell_price' => 25.0, 'unit_internal_cost' => 10.0, 'catalog_item_id' => $sslId, 'sku' => 'PROD-300', 'type' => 'product', 'role_id' => null],
                ]
            ]));
        }

        // Q7: RPM Annual Support Renewal (Recurring + Catalog) — sent
        $q7Existing = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $quotesTable WHERE title = %s ORDER BY id DESC LIMIT 1", 'Q7 Annual Support Renewal'));
        $q7New = $q7Existing <= 0;
        $q7Id = $q7Existing > 0 ? $q7Existing : $createQuote->handle(new \Pet\Application\Commercial\Command\CreateQuoteCommand(
            $rpmId, 'Q7 Annual Support Renewal', 'Annual renewal for support retainer with supplementary catalog items', 'USD'
        ));
        if ($q7New) {
            $this->registryAdd($seedRunId, $quotesTable, (string)$q7Id);
            $addComponent->handle(new \Pet\Application\Commercial\Command\AddComponentCommand($q7Id, 'recurring', [
                'section' => 'Support',
                'description' => 'Premium Support Retainer',
                'service_name' => 'Premium Support',
                'cadence' => 'monthly',
                'term_months' => 12,
                'renewal_model' => 'manual_review',
                'sell_price_per_period' => 3200.0,
                'internal_cost_per_period' => 1800.0,
                'sla_snapshot' => ['name' => 'Premium', 'response_minutes' => 60, 'resolution_minutes' => 480]
            ]));
            $addComponent->handle(new \Pet\Application\Commercial\Command\AddComponentCommand($q7Id, 'catalog', [
                'section' => 'Supplementary',
                'description' => 'Add-on Services',
                'items' => [
                    ['description' => 'Emergency Support Hours', 'quantity' => 10, 'unit_sell_price' => 280.0, 'unit_internal_cost' => 150.0, 'catalog_item_id' => $emergId, 'sku' => 'SERV-007', 'type' => 'service', 'role_id' => $supportRoleId],
                    ['description' => 'Managed Backup', 'quantity' => 12, 'unit_sell_price' => 120.0, 'unit_internal_cost' => 45.0, 'catalog_item_id' => $backupId, 'sku' => 'RECUR-001', 'type' => 'service', 'role_id' => $supportRoleId],
                    ['description' => 'Support Hours', 'quantity' => 20, 'unit_sell_price' => 150.0, 'unit_internal_cost' => 90.0, 'catalog_item_id' => $supportHrId, 'sku' => 'SERV-002', 'type' => 'service', 'role_id' => $supportRoleId],
                ]
            ]));
        }
        $q7e = $quoteRepo->findById($q7Id);
        if ($q7e && !$q7e->state()->isTerminal()) {
            $q7Total = $q7e->totalValue();
            $setPayment->handle(new \Pet\Application\Commercial\Command\SetPaymentScheduleCommand($q7Id, [
                ['title' => 'Monthly in arrears', 'amount' => round($q7Total, 2), 'dueDate' => null],
            ]));
        }
        $q7e = $quoteRepo->findById($q7Id);
        if ($q7e && $q7e->state()->toString() === 'draft') {
            $sendQuote->handle(new \Pet\Application\Commercial\Command\SendQuoteCommand($q7Id));
        }

        $totalQuotes = (int)$this->wpdb->get_var("SELECT COUNT(*) FROM $quotesTable");
        return ['quotes' => $totalQuotes];
    }

    private function seedLeads(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        $wpdb = $this->wpdb;
        $leadsTable = $wpdb->prefix . 'pet_leads';
        $quotesTable = $wpdb->prefix . 'pet_quotes';

        // Skip if leads already exist
        $existingCount = (int)$wpdb->get_var("SELECT COUNT(*) FROM $leadsTable");
        if ($existingCount > 0) {
            return ['leads' => $existingCount, 'skipped' => true];
        }

        $rpmId = (int)$wpdb->get_var("SELECT id FROM {$wpdb->prefix}pet_customers WHERE name = 'RPM Resources (Pty) Ltd' LIMIT 1");
        $acmeId = (int)$wpdb->get_var("SELECT id FROM {$wpdb->prefix}pet_customers WHERE name = 'Acme Manufacturing SA (Pty) Ltd' LIMIT 1");
        $nexusId = (int)$wpdb->get_var("SELECT id FROM {$wpdb->prefix}pet_customers WHERE name = 'Nexus Startup Labs' LIMIT 1");
        $govId = (int)$wpdb->get_var("SELECT id FROM {$wpdb->prefix}pet_customers WHERE name = 'Government Digital Services' LIMIT 1");

        $q1Id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $quotesTable WHERE title = %s ORDER BY id DESC LIMIT 1", 'Q1 Website Implementation & Advisory'));
        $q4Id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $quotesTable WHERE title = %s ORDER BY id DESC LIMIT 1", 'Q4 Catalog Services'));

        $now = new \DateTimeImmutable();

        $leads = [
            // L1: Converted → linked to Q1 (RPM)
            [
                'customer_id' => $rpmId,
                'subject' => 'Website Modernisation Enquiry',
                'description' => 'RPM interested in full website rebuild with advisory retainer',
                'status' => 'converted',
                'source' => 'referral',
                'estimated_value' => 15000.00,
                'created_at' => $now->modify('-30 days')->format('Y-m-d H:i:s'),
                'converted_at' => $now->modify('-25 days')->format('Y-m-d H:i:s'),
            ],
            // L2: Converted → linked to Q4 (Acme)
            [
                'customer_id' => $acmeId,
                'subject' => 'Training & Consulting Package',
                'description' => 'Acme needs onsite training and remote consulting hours',
                'status' => 'converted',
                'source' => 'inbound',
                'estimated_value' => 2000.00,
                'created_at' => $now->modify('-20 days')->format('Y-m-d H:i:s'),
                'converted_at' => $now->modify('-15 days')->format('Y-m-d H:i:s'),
            ],
            // L3: Qualified (Nexus) — active, not yet converted
            [
                'customer_id' => $nexusId,
                'subject' => 'Kubernetes Migration Assessment',
                'description' => 'Nexus wants to migrate from Docker Compose to Kubernetes',
                'status' => 'qualified',
                'source' => 'outbound',
                'estimated_value' => 8500.00,
                'created_at' => $now->modify('-10 days')->format('Y-m-d H:i:s'),
                'converted_at' => null,
            ],
            // L4: New (Government) — fresh inbound
            [
                'customer_id' => $govId,
                'subject' => 'Security Audit RFP',
                'description' => 'Government department enquiry about security audit services',
                'status' => 'new',
                'source' => 'tender',
                'estimated_value' => 12000.00,
                'created_at' => $now->modify('-3 days')->format('Y-m-d H:i:s'),
                'converted_at' => null,
            ],
            // L5: New (RPM) — another enquiry, stale (>7d for attention card)
            [
                'customer_id' => $rpmId,
                'subject' => 'Managed Backup Add-on',
                'description' => 'RPM enquired about managed backup services',
                'status' => 'new',
                'source' => 'email',
                'estimated_value' => 1500.00,
                'created_at' => $now->modify('-12 days')->format('Y-m-d H:i:s'),
                'converted_at' => null,
            ],
            // L6: Disqualified (Acme) — closed
            [
                'customer_id' => $acmeId,
                'subject' => 'Budget Enquiry - On Hold',
                'description' => 'Acme enquired but budget was not approved for this quarter',
                'status' => 'disqualified',
                'source' => 'inbound',
                'estimated_value' => 3000.00,
                'created_at' => $now->modify('-40 days')->format('Y-m-d H:i:s'),
                'converted_at' => null,
            ],
        ];

        $insertedCount = 0;
        $leadIds = [];
        foreach ($leads as $lead) {
            $wpdb->insert($leadsTable, [
                'customer_id' => $lead['customer_id'],
                'subject' => $lead['subject'],
                'description' => $lead['description'],
                'status' => $lead['status'],
                'source' => $lead['source'],
                'estimated_value' => $lead['estimated_value'],
                'malleable_schema_version' => 1,
                'malleable_data' => $this->jsonMeta($seedRunId, $seedProfile, $seededAt),
                'created_at' => $lead['created_at'],
                'updated_at' => $lead['converted_at'] ?? $lead['created_at'],
                'converted_at' => $lead['converted_at'],
            ]);
            $leadId = (int)$wpdb->insert_id;
            $leadIds[] = $leadId;
            $this->registryAdd($seedRunId, $leadsTable, (string)$leadId);
            $insertedCount++;
        }

        // Link L1 → Q1 and L2 → Q4
        if ($q1Id > 0 && isset($leadIds[0])) {
            $wpdb->update($quotesTable, ['lead_id' => $leadIds[0]], ['id' => $q1Id]);
        }
        if ($q4Id > 0 && isset($leadIds[1])) {
            $wpdb->update($quotesTable, ['lead_id' => $leadIds[1]], ['id' => $q4Id]);
        }

        return ['leads' => $insertedCount];
    }

    private function seedDelivery(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        $wpdb = $this->wpdb;
        $projectsTable = $wpdb->prefix . 'pet_projects';
        $projectsCount = (int)$wpdb->get_var("SELECT COUNT(*) FROM $projectsTable WHERE source_quote_id IS NOT NULL");
        return ['projects' => $projectsCount];
    }

    private function seedSupport(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        $c = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
        /** @var \Pet\Infrastructure\Persistence\Repository\SqlSlaRepository $slaRepo */
        $slaRepo = $c->get(\Pet\Infrastructure\Persistence\Repository\SqlSlaRepository::class);
        /** @var \Pet\Domain\Calendar\Repository\CalendarRepository $calRepo */
        $calRepo = $c->get(\Pet\Domain\Calendar\Repository\CalendarRepository::class);
        $createTicket = $c->get(\Pet\Application\Support\Command\CreateTicketHandler::class);
        $wpdb = $this->wpdb;
        $projectsTable = $wpdb->prefix . 'pet_projects';
        $ticketsTable = $wpdb->prefix . 'pet_tickets';
        $clockTable = $wpdb->prefix . 'pet_sla_clock_state';
        // --- Customer IDs ---
        $rpmCustId = (int)$wpdb->get_var("SELECT id FROM {$wpdb->prefix}pet_customers WHERE name = 'RPM Resources (Pty) Ltd' LIMIT 1");
        $acmeCustId = (int)$wpdb->get_var("SELECT id FROM {$wpdb->prefix}pet_customers WHERE name = 'Acme Manufacturing SA (Pty) Ltd' LIMIT 1");
        $nexusCustId = (int)$wpdb->get_var("SELECT id FROM {$wpdb->prefix}pet_customers WHERE name = 'Nexus Startup Labs' LIMIT 1");

        // --- Per-customer project lookup ---
        $rpmProject = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $projectsTable WHERE customer_id = %d AND source_quote_id IS NOT NULL ORDER BY id ASC LIMIT 1",
            $rpmCustId
        ));
        if (!$rpmProject) {
            return ['tickets' => 0, 'sla' => 'missing_project'];
        }
        $acmeProject = $acmeCustId ? $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $projectsTable WHERE customer_id = %d AND source_quote_id IS NOT NULL ORDER BY id ASC LIMIT 1",
            $acmeCustId
        )) : null;
        $nexusProject = $nexusCustId ? $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $projectsTable WHERE customer_id = %d AND source_quote_id IS NOT NULL ORDER BY id ASC LIMIT 1",
            $nexusCustId
        )) : null;

        $calendar = $calRepo->findDefault();
        if (!$calendar) {
            $calendar = new \Pet\Domain\Calendar\Entity\Calendar('Default', 'UTC', [], [], true);
        }

        // --- 4 SLA Definitions (3 published + 1 draft) ---
        $premium = new \Pet\Domain\Sla\Entity\SlaDefinition('Premium', $calendar, 60, 480, [
            new \Pet\Domain\Sla\Entity\EscalationRule(50, 'warn'),
            new \Pet\Domain\Sla\Entity\EscalationRule(75, 'escalate'),
            new \Pet\Domain\Sla\Entity\EscalationRule(100, 'breach'),
        ], 'published', 1);
        $slaRepo->save($premium);

        $standard = new \Pet\Domain\Sla\Entity\SlaDefinition('Standard', $calendar, 240, 1440, [
            new \Pet\Domain\Sla\Entity\EscalationRule(75, 'warn'),
            new \Pet\Domain\Sla\Entity\EscalationRule(100, 'breach'),
        ], 'published', 1);
        $slaRepo->save($standard);

        $essential = new \Pet\Domain\Sla\Entity\SlaDefinition('Essential', $calendar, 480, 2880, [
            new \Pet\Domain\Sla\Entity\EscalationRule(80, 'warn'),
            new \Pet\Domain\Sla\Entity\EscalationRule(100, 'breach'),
        ], 'published', 1);
        $slaRepo->save($essential);

        $compliance = new \Pet\Domain\Sla\Entity\SlaDefinition('Compliance', $calendar, 120, 720, [
            new \Pet\Domain\Sla\Entity\EscalationRule(75, 'warn'),
            new \Pet\Domain\Sla\Entity\EscalationRule(100, 'breach'),
        ], 'draft', 1);
        $slaRepo->save($compliance);

        // --- Tiered SLA Definitions ---
        $calTable = $wpdb->prefix . 'pet_calendars';
        $oohCalId = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $calTable WHERE name = %s LIMIT 1", 'Out of Hours'));
        $allDayCalId = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $calTable WHERE name = %s LIMIT 1", '24/7 Coverage'));
        $defaultCalId = $calendar->id() ?? 0;

        // 5. Premium Tiered (2-tier: Office Hours + Out of Hours) — published
        if ($oohCalId) {
            $premiumTiered = new \Pet\Domain\Sla\Entity\SlaDefinition(
                'Premium Tiered',
                null, null, null, [],
                'published', 1, null, null,
                [
                    new \Pet\Domain\Sla\Entity\SlaTier(1, 'Office Hours', $defaultCalId, 30, 240, [
                        new \Pet\Domain\Sla\Entity\EscalationRule(50, 'warn'),
                        new \Pet\Domain\Sla\Entity\EscalationRule(75, 'escalate'),
                        new \Pet\Domain\Sla\Entity\EscalationRule(100, 'breach'),
                    ]),
                    new \Pet\Domain\Sla\Entity\SlaTier(2, 'Out of Hours', $oohCalId, 120, 960, [
                        new \Pet\Domain\Sla\Entity\EscalationRule(75, 'warn'),
                        new \Pet\Domain\Sla\Entity\EscalationRule(100, 'breach'),
                    ]),
                ],
                80
            );
            $slaRepo->save($premiumTiered);
        }

        // 6. Enterprise 24/7 (3-tier: Office Hours + After Hours + 24/7 Fallback) — published
        if ($oohCalId && $allDayCalId) {
            $enterprise247 = new \Pet\Domain\Sla\Entity\SlaDefinition(
                'Enterprise 24/7',
                null, null, null, [],
                'published', 1, null, null,
                [
                    new \Pet\Domain\Sla\Entity\SlaTier(1, 'Business Hours', $defaultCalId, 15, 120, [
                        new \Pet\Domain\Sla\Entity\EscalationRule(50, 'warn'),
                        new \Pet\Domain\Sla\Entity\EscalationRule(75, 'escalate'),
                        new \Pet\Domain\Sla\Entity\EscalationRule(100, 'breach'),
                    ]),
                    new \Pet\Domain\Sla\Entity\SlaTier(2, 'After Hours', $oohCalId, 60, 480, [
                        new \Pet\Domain\Sla\Entity\EscalationRule(75, 'warn'),
                        new \Pet\Domain\Sla\Entity\EscalationRule(100, 'breach'),
                    ]),
                    new \Pet\Domain\Sla\Entity\SlaTier(3, '24/7 Fallback', $allDayCalId, 30, 240, [
                        new \Pet\Domain\Sla\Entity\EscalationRule(80, 'warn'),
                        new \Pet\Domain\Sla\Entity\EscalationRule(100, 'breach'),
                    ]),
                ],
                75
            );
            $slaRepo->save($enterprise247);
        }

        // --- Per-project snapshots ---
        $premiumSnap = $premium->createSnapshot((int)$rpmProject->id);
        $premiumSnapId = $slaRepo->saveSnapshot($premiumSnap);

        $standardSnap = null;
        $standardSnapId = null;
        if ($acmeProject) {
            $standardSnap = $standard->createSnapshot((int)$acmeProject->id);
            $standardSnapId = $slaRepo->saveSnapshot($standardSnap);
        }

        $essentialSnap = null;
        $essentialSnapId = null;
        if ($nexusProject) {
            $essentialSnap = $essential->createSnapshot((int)$nexusProject->id);
            $essentialSnapId = $slaRepo->saveSnapshot($essentialSnap);
        }

        // RPM tickets
        $rpmSubjects = [
            ['Login issue', 'critical'],
            ['Email not syncing', 'high'],
            ['Server alert', 'high'],
            ['VPN access', 'medium'],
            ['Printer offline', 'low'],
            ['New user setup', 'medium'],
            ['Policy question', 'low'],
        ];
        foreach ($rpmSubjects as [$s, $pri]) {
            $createTicket->handle(new \Pet\Application\Support\Command\CreateTicketCommand(
                $rpmCustId, null, null, $s, 'Auto-generated demo ticket for RPM Resources', $pri, []
            ));
        }

        // Acme tickets
        if ($acmeCustId) {
            $acmeSubjects = [
                ['ERP module crashing on reports', 'critical'],
                ['Slow network at Stellenbosch site', 'medium'],
                ['License renewal query', 'low'],
            ];
            foreach ($acmeSubjects as [$s, $pri]) {
                $createTicket->handle(new \Pet\Application\Support\Command\CreateTicketCommand(
                    $acmeCustId, null, null, $s, 'Auto-generated demo ticket for Acme Manufacturing', $pri, []
                ));
            }
        }

        // Nexus tickets
        if ($nexusCustId) {
            $nexusSubjects = [
                ['AWS console access issue', 'high'],
                ['CI/CD pipeline failure', 'critical'],
                ['DNS propagation delay', 'medium'],
            ];
            foreach ($nexusSubjects as [$s, $pri]) {
                $createTicket->handle(new \Pet\Application\Support\Command\CreateTicketCommand(
                    $nexusCustId, null, null, $s, 'Auto-generated demo ticket for Nexus Labs', $pri, []
                ));
            }
        }

        // --- Per-customer SLA binding ---
        $now = new \DateTimeImmutable();
        $customerSlaMap = [
            $rpmCustId => ['snapshot_id' => $premiumSnapId, 'response' => 60, 'resolution' => 480, 'version' => $premiumSnap->slaVersionAtBinding()],
        ];
        if ($acmeCustId && $standardSnapId && $standardSnap) {
            $customerSlaMap[$acmeCustId] = ['snapshot_id' => $standardSnapId, 'response' => 240, 'resolution' => 1440, 'version' => $standardSnap->slaVersionAtBinding()];
        }
        if ($nexusCustId && $essentialSnapId && $essentialSnap) {
            $customerSlaMap[$nexusCustId] = ['snapshot_id' => $essentialSnapId, 'response' => 480, 'resolution' => 2880, 'version' => $essentialSnap->slaVersionAtBinding()];
        }

        $recentTickets = $wpdb->get_results(
            "SELECT id, customer_id FROM $ticketsTable ORDER BY id DESC LIMIT 13"
        );

        $rpmTicketIds = [];
        $acmeTicketIds = [];
        $nexusTicketIds = [];

        foreach ($recentTickets as $row) {
            $custId = (int)$row->customer_id;
            $slaInfo = $customerSlaMap[$custId] ?? $customerSlaMap[$rpmCustId];

            $responseDue = $now->modify('+' . $slaInfo['response'] . ' minutes')->format('Y-m-d H:i:s');
            $resolutionDue = $now->modify('+' . $slaInfo['resolution'] . ' minutes')->format('Y-m-d H:i:s');

            $wpdb->update($ticketsTable, [
                'sla_snapshot_id' => $slaInfo['snapshot_id'],
                'response_due_at' => $responseDue,
                'resolution_due_at' => $resolutionDue,
                'status' => 'open',
                'opened_at' => $seededAt
            ], ['id' => (int)$row->id]);
            $wpdb->insert($clockTable, [
                'ticket_id' => (int)$row->id,
                'last_event_dispatched' => 'none',
                'last_evaluated_at' => null,
                'sla_version_id' => $slaInfo['version'],
                'paused_flag' => 0,
                'escalation_stage' => 0
            ]);
            $this->registryAdd($seedRunId, $ticketsTable, (string)$row->id);
            $this->registryAdd($seedRunId, $clockTable, (string)$this->wpdb->insert_id);

            if ($custId === $rpmCustId) $rpmTicketIds[] = (int)$row->id;
            elseif ($custId === $acmeCustId) $acmeTicketIds[] = (int)$row->id;
            elseif ($custId === $nexusCustId) $nexusTicketIds[] = (int)$row->id;
        }

        $ticketIds = array_map(fn($r) => (int)$r->id, $recentTickets);
        if (!empty($ticketIds)) {
            // --- Status variety across RPM tickets ---
            $criticalId = $rpmTicketIds[0] ?? null;
            $closedId = $rpmTicketIds[1] ?? null;
            $pendingId = $rpmTicketIds[2] ?? null;
            $resolvedId = $rpmTicketIds[3] ?? null;

            if ($criticalId) {
                $wpdb->update($ticketsTable, ['priority' => 'critical', 'status' => 'open'], ['id' => $criticalId]);
            }

            // Closed + resolved ticket (completed concept)
            if ($closedId) {
                $wpdb->update($ticketsTable, [
                    'status' => 'closed',
                    'priority' => 'high',
                    'resolved_at' => (new \DateTimeImmutable('+1 day'))->format('Y-m-d H:i:s'),
                ], ['id' => $closedId]);
            }

            // Pending ticket (waiting concept)
            if ($pendingId) {
                $wpdb->update($ticketsTable, [
                    'status' => 'pending',
                    'priority' => 'medium',
                ], ['id' => $pendingId]);
            }

            // Resolved but not closed ticket
            if ($resolvedId) {
                $wpdb->update($ticketsTable, [
                    'status' => 'resolved',
                    'priority' => 'low',
                    'resolved_at' => (new \DateTimeImmutable('+2 days'))->format('Y-m-d H:i:s'),
                ], ['id' => $resolvedId]);
            }

            // --- Tier-aware SLA clock states ---
            // Premium (tight 60/480 SLA): 1 breached, 1 near-breach
            if (isset($rpmTicketIds[4])) {
                $wpdb->update($clockTable, ['breach_at' => $now->format('Y-m-d H:i:s')], ['ticket_id' => $rpmTicketIds[4]]);
            }
            if (isset($rpmTicketIds[5])) {
                $wpdb->update($clockTable, ['escalation_stage' => 1], ['ticket_id' => $rpmTicketIds[5]]);
            }
            // Standard (mid 240/1440 SLA): 1 paused (customer waiting)
            if (isset($acmeTicketIds[0])) {
                $wpdb->update($clockTable, ['paused_flag' => 1], ['ticket_id' => $acmeTicketIds[0]]);
            }
            // Essential (generous 480/2880 SLA): all healthy — no clock tweaks

            // --- Ticket assignments: realistic mix of assigned / queued / unassigned ---
            // Queue IDs use canonical string names matching frontend QUEUES constant
            $empTable = $wpdb->prefix . 'pet_employees';
            $liamId = (string)$wpdb->get_var("SELECT id FROM $empTable WHERE first_name='Liam' LIMIT 1");
            $noahId = (string)$wpdb->get_var("SELECT id FROM $empTable WHERE first_name='Noah' LIMIT 1");
            $ethanId = (string)$wpdb->get_var("SELECT id FROM $empTable WHERE first_name='Ethan' LIMIT 1");
            $avaId = (string)$wpdb->get_var("SELECT id FROM $empTable WHERE first_name='Ava' LIMIT 1");

            // Assign to employee + queue (most common: agent owns it within a queue)
            foreach (array_slice($ticketIds, 0, 3) as $i => $tid) {
                $owner = [$liamId, $noahId, $ethanId][$i % 3];
                $wpdb->update($ticketsTable, [
                    'owner_user_id' => $owner,
                    'queue_id' => 'support',
                ], ['id' => $tid]);
            }
            // Queue-only (in queue, unowned — waiting to be pulled)
            foreach (array_slice($ticketIds, 3, 3) as $tid) {
                $wpdb->update($ticketsTable, [
                    'owner_user_id' => null,
                    'queue_id' => 'support',
                ], ['id' => $tid]);
            }
            // Assigned to Ava (consultant doing specialist investigation)
            if (isset($ticketIds[6])) {
                $wpdb->update($ticketsTable, [
                    'owner_user_id' => $avaId,
                    'queue_id' => 'support',
                ], ['id' => $ticketIds[6]]);
            }
            // Remaining tickets (~6) left fully unassigned — demo shows "no assignment" state
        }
        $ticketsCount = (int)$wpdb->get_var("SELECT COUNT(*) FROM $ticketsTable");
        $slaDefCount = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pet_sla_definitions");
        return ['tickets' => $ticketsCount, 'sla_definitions' => $slaDefCount, 'sla_snapshots' => 3, 'sla' => 'ok'];
    }

    /**
     * C4: Seed project and internal tickets using backbone fields.
     * Creates WBS parent/child structure for project tickets and internal admin/R&D tickets.
     */
    private function seedBackboneTickets(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        $wpdb = $this->wpdb;
        $ticketsTable = $wpdb->prefix . 'pet_tickets';
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        // Get a project to attach tickets to
        $project = $wpdb->get_row("SELECT id, customer_id FROM {$wpdb->prefix}pet_projects ORDER BY id ASC LIMIT 1");
        if (!$project) {
            return ['project_tickets' => 0, 'internal_tickets' => 0];
        }
        $projectId = (int)$project->id;
        $customerId = (int)$project->customer_id;

        // --- Project tickets with WBS (parent → children) ---
        // Parent (rollup) ticket
        $wpdb->insert($ticketsTable, [
            'customer_id' => $customerId,
            'subject' => 'Website Redesign — Full Delivery',
            'description' => 'Rollup ticket for the full website redesign project scope.',
            'status' => 'in_progress',
            'priority' => 'high',
            'primary_container' => 'project',
            'lifecycle_owner' => 'project',
            'project_id' => $projectId,
            'ticket_kind' => 'deliverable',
            'billing_context_type' => 'project',
            'is_billable_default' => 1,
            'is_rollup' => 1,
            'estimated_minutes' => 2400,
            'sold_minutes' => 2400,
            'remaining_minutes' => 1200,
            'created_at' => $now,
            'opened_at' => $now,
        ]);
        $parentId = (int)$wpdb->insert_id;
        $this->registryAdd($seedRunId, $ticketsTable, (string)$parentId);

        // Update root_ticket_id to self
        $wpdb->update($ticketsTable, ['root_ticket_id' => $parentId], ['id' => $parentId]);

        // Child leaf tickets
        $children = [
            ['Discovery & Requirements', 'planned', 480, 480, 480, 'task'],
            ['UI/UX Design', 'in_progress', 600, 600, 300, 'task'],
            ['Frontend Development', 'planned', 720, 720, 720, 'task'],
            ['Backend Integration', 'planned', 360, 360, 360, 'task'],
            ['QA & Testing', 'planned', 240, 240, 240, 'task'],
        ];
        $childIds = [];
        foreach ($children as [$subject, $status, $est, $sold, $remaining, $kind]) {
            $wpdb->insert($ticketsTable, [
                'customer_id' => $customerId,
                'subject' => $subject,
                'description' => "Project work package: $subject",
                'status' => $status,
                'priority' => 'medium',
                'primary_container' => 'project',
                'lifecycle_owner' => 'project',
                'project_id' => $projectId,
                'parent_ticket_id' => $parentId,
                'root_ticket_id' => $parentId,
                'ticket_kind' => $kind,
                'billing_context_type' => 'project',
                'is_billable_default' => 1,
                'is_rollup' => 0,
                'estimated_minutes' => $est,
                'sold_minutes' => $sold,
                'remaining_minutes' => $remaining,
                'created_at' => $now,
                'opened_at' => $now,
            ]);
            $childIds[] = (int)$wpdb->insert_id;
            $this->registryAdd($seedRunId, $ticketsTable, (string)$wpdb->insert_id);
        }

        // --- Internal tickets (no customer, no billing) ---
        $internalTickets = [
            ['Update internal wiki documentation', 'in_progress', 'admin'],
            ['Quarterly security audit prep', 'planned', 'compliance'],
            ['R&D: Evaluate new monitoring stack', 'in_progress', 'research'],
            ['Office network switch upgrade', 'done', 'infrastructure'],
        ];
        $internalCount = 0;
        foreach ($internalTickets as [$subject, $status, $kind]) {
            $wpdb->insert($ticketsTable, [
                'customer_id' => $customerId, // internal still needs a customer_id (NOT NULL)
                'subject' => $subject,
                'description' => "Internal task: $subject",
                'status' => $status,
                'priority' => 'low',
                'primary_container' => 'internal',
                'lifecycle_owner' => 'internal',
                'ticket_kind' => $kind,
                'billing_context_type' => 'internal',
                'is_billable_default' => 0,
                'is_rollup' => 0,
                'created_at' => $now,
                'opened_at' => $now,
            ]);
            $this->registryAdd($seedRunId, $ticketsTable, (string)$wpdb->insert_id);
            $internalCount++;
        }

        // --- Assign backbone tickets ---
        // Queue IDs use canonical string names matching frontend QUEUES constant
        $empTable = $wpdb->prefix . 'pet_employees';
        $miaId = (string)$wpdb->get_var("SELECT id FROM $empTable WHERE first_name='Mia' LIMIT 1");
        $liamId = (string)$wpdb->get_var("SELECT id FROM $empTable WHERE first_name='Liam' LIMIT 1");
        $ethanId = (string)$wpdb->get_var("SELECT id FROM $empTable WHERE first_name='Ethan' LIMIT 1");
        $steveId = (string)$wpdb->get_var("SELECT id FROM $empTable WHERE first_name='Steve' LIMIT 1");

        // Project parent — owned by Mia (PM) in Projects queue
        $wpdb->update($ticketsTable, ['owner_user_id' => $miaId, 'queue_id' => 'projects'], ['id' => $parentId]);
        // Project children — mix of assigned and queue-only
        $projectAssignees = [$liamId, $ethanId, null, $liamId, null];
        foreach ($childIds as $ci => $childId) {
            $wpdb->update($ticketsTable, [
                'owner_user_id' => $projectAssignees[$ci] ?? null,
                'queue_id' => 'projects',
            ], ['id' => $childId]);
        }
        // Internal tickets — assigned to Steve (admin) in Internal queue
        $internalIds = $wpdb->get_col("SELECT id FROM $ticketsTable WHERE primary_container = 'internal' AND created_at = '$now' ORDER BY id ASC");
        foreach ($internalIds as $ii => $iid) {
            $owner = ($ii % 2 === 0) ? $steveId : null; // half assigned, half queue-only
            $wpdb->update($ticketsTable, [
                'owner_user_id' => $owner,
                'queue_id' => 'internal',
            ], ['id' => (int)$iid]);
        }

        // --- Seed ticket_links: cross-context references ---
        $linksTable = $wpdb->prefix . 'pet_ticket_links';
        if ($wpdb->get_var("SHOW TABLES LIKE '$linksTable'") === $linksTable) {
            // Link project parent to the project entity
            $wpdb->insert($linksTable, [
                'ticket_id' => $parentId,
                'link_type' => 'project',
                'linked_id' => (string)$projectId,
                'created_at' => $now,
            ]);
            $this->registryAdd($seedRunId, $linksTable, (string)$wpdb->insert_id);
            // Link a support ticket to the project (helpdesk assisting project)
            $supportTicketForLink = (int)$wpdb->get_var("SELECT id FROM $ticketsTable WHERE primary_container = 'support' ORDER BY id ASC LIMIT 1");
            if ($supportTicketForLink) {
                $wpdb->insert($linksTable, [
                    'ticket_id' => $supportTicketForLink,
                    'link_type' => 'project',
                    'linked_id' => (string)$projectId,
                    'created_at' => $now,
                ]);
                $this->registryAdd($seedRunId, $linksTable, (string)$wpdb->insert_id);
            }
            // Link a child ticket to the customer
            if (!empty($childIds)) {
                $wpdb->insert($linksTable, [
                    'ticket_id' => $childIds[0],
                    'link_type' => 'customer',
                    'linked_id' => (string)$customerId,
                    'created_at' => $now,
                ]);
                $this->registryAdd($seedRunId, $linksTable, (string)$wpdb->insert_id);
            }
        }

        return [
            'project_tickets' => 1 + count($childIds), // parent + children
            'internal_tickets' => $internalCount,
            'ticket_links' => ($wpdb->get_var("SHOW TABLES LIKE '$linksTable'") === $linksTable)
                ? (int)$wpdb->get_var("SELECT COUNT(*) FROM $linksTable")
                : 0,
        ];
    }

    private function seedWorkOrchestration(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        $c = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
        /** @var \Pet\Infrastructure\Persistence\Repository\SqlWorkItemRepository $workRepo */
        $workRepo = $c->get(\Pet\Infrastructure\Persistence\Repository\SqlWorkItemRepository::class);
        /** @var \Pet\Infrastructure\Persistence\Repository\SqlDepartmentQueueRepository $queueRepo */
        $queueRepo = $c->get(\Pet\Infrastructure\Persistence\Repository\SqlDepartmentQueueRepository::class);
        $wpdb = $this->wpdb;

        // Get the current WP user ID for assignment.
        // In CLI context (wp eval / WP-CLI) get_current_user_id() returns 0,
        // so fall back to the wp_user_id of the first seeded employee (Steve Admin).
        $currentUserId = get_current_user_id();
        if ($currentUserId <= 0) {
            $empTable = $wpdb->prefix . 'pet_employees';
            $currentUserId = (int)$wpdb->get_var("SELECT wp_user_id FROM $empTable ORDER BY id ASC LIMIT 1");
        }
        $currentUserId = (string)$currentUserId;

        // Create work items for ALL open tickets (not just 3)
        $ticketsTable = $wpdb->prefix . 'pet_tickets';
        $tickets = $wpdb->get_results(
            "SELECT id, status, priority, primary_container FROM $ticketsTable WHERE status NOT IN ('closed','resolved') ORDER BY id DESC"
        );
        $now = new \DateTimeImmutable();
        $wiTable = $wpdb->prefix . 'pet_work_items';
        $dqTable = $wpdb->prefix . 'pet_department_queues';

        // SLA time remaining scenarios for demo variety
        $slaScenarios = [-45, -12, 15, 30, 55, 120, 180, 240, 360, null];
        $idx = 0;

        // Map primary_container to department for work items
        // Uses canonical queue names matching frontend QUEUES constant
        $containerToDept = [
            'support' => 'support',
            'project' => 'projects',
            'internal' => 'internal',
        ];

        foreach ($tickets as $t) {
            $container = isset($t->primary_container) ? $t->primary_container : 'support';
            $dept = $containerToDept[$container] ?? 'support';

            // Check if work item already exists for this ticket (may be auto-created by WorkItemProjector)
            $existingWi = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $wiTable WHERE source_type = 'ticket' AND source_id = %s LIMIT 1",
                (string)$t->id
            ));

            if ($existingWi) {
                $id = $existingWi;
                // Update department if it was defaulted to 'support' by an earlier projector
                $wpdb->update($wiTable, ['department_id' => $dept], ['id' => $id]);
            } else {
                $id = $this->uuid();
                $priority = match ($t->priority) {
                    'critical' => 95.0,
                    'high' => 80.0,
                    'medium' => 60.0,
                    default => 40.0,
                };
                $item = \Pet\Domain\Work\Entity\WorkItem::create($id, 'ticket', (string)$t->id, $dept, $priority, 'active', $now);
                $workRepo->save($item);
                $queueRepo->save(\Pet\Domain\Work\Entity\DepartmentQueue::enter($this->uuid(), $dept, $id));
                $this->registryAdd($seedRunId, $wiTable, $id);
                $this->registryAdd($seedRunId, $dqTable, $id);
            }

            // Assign most tickets to current user so Support persona view is populated
            $slaRemaining = $slaScenarios[$idx % count($slaScenarios)];
            $assignedTo = ($idx < 5) ? $currentUserId : (($idx < 6) ? null : $currentUserId);

            $updateData = [];
            if ($assignedTo !== null && $assignedTo !== '' && $assignedTo !== '0') {
                $updateData['assigned_user_id'] = $assignedTo;
            }
            if ($slaRemaining !== null) {
                $updateData['sla_time_remaining_minutes'] = $slaRemaining;
            }
            if (!empty($updateData)) {
                $wpdb->update($wiTable, $updateData, ['id' => $id]);
            }

            $idx++;
        }

        // Set escalation on the first item
        $oneItem = $wpdb->get_row("SELECT id FROM $wiTable ORDER BY created_at DESC LIMIT 1");
        if ($oneItem) {
            $wpdb->update($wiTable, ['escalation_level' => 1], ['id' => $oneItem->id]);
        }
        $items = (int)$wpdb->get_var("SELECT COUNT(*) FROM $wiTable");
        $queues = (int)$wpdb->get_var("SELECT COUNT(*) FROM $dqTable");
        return ['work_items' => $items, 'queues' => $queues];
    }

    private function seedTimeEntries(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        $wpdb = $this->wpdb;
        $employeesTable = $wpdb->prefix . 'pet_employees';
        $ticketsTable = $wpdb->prefix . 'pet_tickets';
        $entriesTable = $wpdb->prefix . 'pet_time_entries';

        $allEmps = $wpdb->get_results("SELECT id, first_name, last_name FROM $employeesTable ORDER BY id ASC");
        $allTickets = $wpdb->get_results("SELECT id, customer_id, primary_container FROM $ticketsTable ORDER BY id ASC");

        if (empty($allEmps) || empty($allTickets)) {
            return ['time_entries' => 0];
        }

        // Employee → billing rate mapping (from catalog)
        $empRates = [];
        foreach ($allEmps as $emp) {
            $name = $emp->first_name . ' ' . $emp->last_name;
            $empRates[(int)$emp->id] = match (true) {
                str_contains($name, 'DevOps') => 195.0,
                str_contains($name, 'Support') => 150.0,
                str_contains($name, 'Finance') || str_contains($name, 'Analyst') => 170.0,
                default => 180.0, // Consulting rate
            };
        }
        $empNames = [];
        foreach ($allEmps as $emp) {
            $empNames[(int)$emp->id] = $emp->first_name . ' ' . $emp->last_name;
        }
        $empIds = array_keys($empRates);

        // Group tickets by customer for distribution
        $ticketsByCustomer = [];
        foreach ($allTickets as $t) {
            $ticketsByCustomer[(int)$t->customer_id][] = (int)$t->id;
        }
        $allTicketIds = array_map(fn($t) => (int)$t->id, $allTickets);

        // Descriptions — mixed project and support work
        $descriptions = [
            'Initial triage and issue classification',
            'Remote session — reproduced issue on client environment',
            'Investigated root cause in application logs',
            'Applied hotfix patch and verified resolution',
            'Updated firewall rules per customer security policy',
            'Configured monitoring alerts for recurring issue',
            'Drafted knowledge base article for common resolution',
            'Escalation review with senior engineer',
            'Performed database health check and index optimisation',
            'Restored backup and verified data integrity',
            'Client call — walked through configuration changes',
            'Reviewed SLA compliance and updated status notes',
            'Deployed scheduled maintenance window changes',
            'Tested regression after platform update',
            'Coordinated with vendor on third-party integration issue',
            'Sprint planning and backlog refinement',
            'Code review and merge — feature branch',
            'Architecture design session — microservices split',
            'Infrastructure provisioning — staging environment',
            'CI/CD pipeline configuration and testing',
            'Requirements workshop with stakeholders',
            'Data migration script development and testing',
            'Security audit — reviewed access logs and permissions',
            'Performance benchmarking and optimisation',
            'User acceptance testing facilitation',
            'Documentation update — API reference',
            'Quote preparation and technical scoping',
            'Customer onboarding environment setup',
            'Training material preparation',
            'Post-incident review and documentation',
            'Capacity planning and resource forecasting',
            'Governance review session — quarterly',
            'SLA design workshop with customer',
            'Cloud migration assessment and planning',
            'Network connectivity troubleshooting',
            'SSL certificate renewal and verification',
        ];

        // Duration options in minutes (realistic work chunks)
        $durations = [30, 45, 60, 60, 90, 90, 120, 120, 150, 180, 180, 240];

        // Build entries spanning 3 months back + current month
        // Month offsets: -3 (Dec/lighter), -2 (Jan/growing), -1 (Feb/steady), 0 (Mar/current busiest)
        $now = new \DateTimeImmutable();
        $monthConfigs = [
            ['offset' => -3, 'entries_per_emp' => 3, 'status' => 'locked'],      // 3 months ago — all locked
            ['offset' => -2, 'entries_per_emp' => 4, 'status' => 'locked'],      // 2 months ago — all locked
            ['offset' => -1, 'entries_per_emp' => 5, 'status' => 'submitted'],   // last month — all submitted
            ['offset' =>  0, 'entries_per_emp' => 6, 'status' => 'mixed'],       // current — mixed statuses
        ];

        $entryIdx = 0;
        $allInsertedIds = [];

        foreach ($monthConfigs as $mc) {
            $monthStart = $now->modify("first day of {$mc['offset']} month")->setTime(8, 0);
            $workDays = [];
            // Build array of workdays (Mon-Fri) in this month
            $day = $monthStart;
            $monthEnd = $now->modify("last day of {$mc['offset']} month")->setTime(23, 59);
            // For current month, stop at today
            if ($mc['offset'] === 0) {
                $monthEnd = $now;
            }
            while ($day <= $monthEnd) {
                $dow = (int)$day->format('N');
                if ($dow <= 5) { // Mon-Fri
                    $workDays[] = $day;
                }
                $day = $day->modify('+1 day');
            }
            if (empty($workDays)) continue;

            foreach ($empIds as $empId) {
                $rate = $empRates[$empId];
                $entriesForThisEmp = $mc['entries_per_emp'];
                // Add variance: some employees busier than others
                $nameKey = $empNames[$empId] ?? '';
                if (str_contains($nameKey, 'Lead Tech') || str_contains($nameKey, 'Consultant')) {
                    $entriesForThisEmp += 2; // busiest staff
                } elseif (str_contains($nameKey, 'Finance')) {
                    $entriesForThisEmp = max(1, $entriesForThisEmp - 1); // lighter
                }

                for ($e = 0; $e < $entriesForThisEmp; $e++) {
                    $workDay = $workDays[($entryIdx + $e) % count($workDays)];
                    $hourSlot = 8 + (($e * 2) % 8); // 08, 10, 12, 14, 08, 10...
                    $start = $workDay->setTime($hourSlot, ($e % 2) * 30); // :00 or :30
                    $dur = $durations[$entryIdx % count($durations)];
                    $end = $start->modify("+{$dur} minutes");
                    $desc = $descriptions[$entryIdx % count($descriptions)];
                    $billable = ($entryIdx % 10 < 7); // ~70% billable

                    // Distribute across customers/tickets
                    $ticketId = $allTicketIds[$entryIdx % count($allTicketIds)];

                    // Determine status based on month
                    if ($mc['status'] === 'mixed') {
                        $status = match (true) {
                            $e < (int)ceil($entriesForThisEmp * 0.4) => 'submitted',
                            $e < (int)ceil($entriesForThisEmp * 0.7) => 'draft',
                            default => 'locked',
                        };
                    } else {
                        $status = $mc['status'];
                    }

                    $malleable = json_encode([
                        'seed_run_id' => $seedRunId,
                        'billing_rate' => $rate,
                        'department' => $this->resolveTicketDepartment($ticketId, $allTickets),
                    ]);

                    $wpdb->insert($entriesTable, [
                        'employee_id' => $empId,
                        'ticket_id' => $ticketId,
                        'start_time' => $start->format('Y-m-d H:i:s'),
                        'end_time' => $end->format('Y-m-d H:i:s'),
                        'duration_minutes' => $dur,
                        'is_billable' => $billable ? 1 : 0,
                        'description' => $desc,
                        'status' => $status,
                        'malleable_data' => $malleable,
                        'created_at' => $start->format('Y-m-d H:i:s'),
                    ]);
                    $insertId = (int)$wpdb->insert_id;
                    if ($insertId) {
                        $allInsertedIds[] = $insertId;
                        $this->registryAdd($seedRunId, $entriesTable, (string)$insertId);
                    }
                    $entryIdx++;
                }
            }
        }

        // Seed correction entries (B2 feature demo)
        $correctionSource = $wpdb->get_row(
            "SELECT id, employee_id, ticket_id, start_time, end_time, duration_minutes, is_billable, description FROM $entriesTable WHERE status = 'submitted' ORDER BY id ASC LIMIT 1"
        );
        $correctionCount = 0;
        if ($correctionSource) {
            $srcId = (int)$correctionSource->id;
            $srcStart = $correctionSource->start_time;
            $srcEnd = $correctionSource->end_time;
            $srcDuration = (int)$correctionSource->duration_minutes;

            $wpdb->insert($entriesTable, [
                'employee_id' => (int)$correctionSource->employee_id,
                'ticket_id' => (int)$correctionSource->ticket_id,
                'start_time' => $srcStart,
                'end_time' => $srcEnd,
                'duration_minutes' => -$srcDuration,
                'is_billable' => (int)$correctionSource->is_billable,
                'description' => 'REVERSAL: ' . $correctionSource->description,
                'status' => 'submitted',
                'corrects_entry_id' => $srcId,
                'malleable_data' => json_encode(['seed_run_id' => $seedRunId, 'correction_type' => 'reversal']),
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
            $this->registryAdd($seedRunId, $entriesTable, (string)(int)$wpdb->insert_id);
            $correctionCount++;

            $correctedEnd = (new \DateTimeImmutable($srcEnd))->modify('-30 minutes')->format('Y-m-d H:i:s');
            $correctedDuration = max(0, $srcDuration - 30);
            $wpdb->insert($entriesTable, [
                'employee_id' => (int)$correctionSource->employee_id,
                'ticket_id' => (int)$correctionSource->ticket_id,
                'start_time' => $srcStart,
                'end_time' => $correctedEnd,
                'duration_minutes' => $correctedDuration,
                'is_billable' => (int)$correctionSource->is_billable ? 0 : 1,
                'description' => 'CORRECTION: ' . $correctionSource->description . ' (adjusted duration & billing)',
                'status' => 'submitted',
                'corrects_entry_id' => $srcId,
                'malleable_data' => json_encode(['seed_run_id' => $seedRunId, 'correction_type' => 'correction']),
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
            $this->registryAdd($seedRunId, $entriesTable, (string)(int)$wpdb->insert_id);
            $correctionCount++;
        }

        $count = (int)$wpdb->get_var("SELECT COUNT(*) FROM $entriesTable");
        return ['time_entries' => $count, 'corrections' => $correctionCount];
    }

    /**
     * Resolve a ticket's department from its primary_container field.
     */
    private function resolveTicketDepartment(int $ticketId, array $allTickets): string
    {
        foreach ($allTickets as $t) {
            if ((int)$t->id === $ticketId) {
                return match ($t->primary_container ?? 'support') {
                    'project' => 'projects',
                    'internal' => 'internal',
                    default => 'support',
                };
            }
        }
        return 'support';
    }

    private function seedKnowledge(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        $c = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
        /** @var \Pet\Application\Knowledge\Command\CreateArticleHandler $createArticle */
        $createArticle = $c->get(\Pet\Application\Knowledge\Command\CreateArticleHandler::class);
        $articles = [
            ['Getting Started with PET', 'Welcome to PET platform', 'general'],
            ['SLA Design Principles', 'Core principles for SLA design', 'support'],
            ['Quote Components Explained', 'Implementation vs Catalog vs Recurring', 'commercial'],
            ['Capability Model Overview', 'Levels, skills, roles, KPIs', 'work'],
            ['Time Entry Workflow', 'Draft, submit, approve, lock', 'time'],
            ['Billing & QuickBooks', 'Exports, invoices, payments', 'finance'],
        ];
        foreach ($articles as [$title, $content, $category]) {
            $createArticle->handle(new \Pet\Application\Knowledge\Command\CreateArticleCommand($title, $content, $category, 'published', [
                'seed_run_id' => $seedRunId,
                'seed_profile' => $seedProfile,
                'seeded_at' => $seededAt,
            ]));
        }
        $count = (int)$this->wpdb->get_var("SELECT COUNT(*) FROM {$this->wpdb->prefix}pet_articles");
        return ['articles' => $count];
    }

    private function seedFeed(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        $c = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
        /** @var \Pet\Infrastructure\Persistence\Repository\SqlFeedEventRepository $feedRepo */
        $feedRepo = $c->get(\Pet\Infrastructure\Persistence\Repository\SqlFeedEventRepository::class);
        /** @var \Pet\Infrastructure\Persistence\Repository\SqlAnnouncementRepository $annRepo */
        $annRepo = $c->get(\Pet\Infrastructure\Persistence\Repository\SqlAnnouncementRepository::class);
        $wpdb = $this->wpdb;

        // Resolve real entity IDs for rich feed events
        $rpmId = (string)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}pet_customers WHERE name=%s", 'RPM Resources (Pty) Ltd'));
        $acmeId = (string)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}pet_customers WHERE name=%s", 'Acme Manufacturing SA (Pty) Ltd'));
        $nexusId = (string)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}pet_customers WHERE name=%s", 'Nexus Startup Labs'));
        $q1Id = (string)$wpdb->get_var("SELECT id FROM {$wpdb->prefix}pet_quotes ORDER BY id ASC LIMIT 1");
        $projIds = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}pet_projects WHERE source_quote_id IS NOT NULL ORDER BY id ASC LIMIT 3");
        $projId = (string)($projIds[0] ?? 0);
        $projAcmeId = (string)($projIds[1] ?? 0);
        $projNexusId = (string)($projIds[2] ?? 0);
        $ticketIds = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}pet_tickets WHERE status NOT IN ('closed','resolved') ORDER BY id ASC LIMIT 5");

        // Announcements
        $a1Id = $this->uuid();
        $a1 = \Pet\Domain\Feed\Entity\Announcement::create(
            $a1Id,
            'Welcome to PET Demo',
            'This environment is seeded for demonstration.',
            'normal',
            true,
            false,
            false,
            null,
            'global',
            null,
            'admin',
            null
        );
        $a2Id = $this->uuid();
        $a2 = \Pet\Domain\Feed\Entity\Announcement::create(
            $a2Id,
            'Policy Update',
            'SLA compliance tracking enabled.',
            'high',
            true,
            true,
            false,
            (new \DateTimeImmutable('tomorrow')),
            'department',
            'support',
            'admin',
            null
        );
        $annRepo->save($a1);
        $this->registryAdd($seedRunId, $this->wpdb->prefix . 'pet_announcements', $a1Id);
        $annRepo->save($a2);
        $this->registryAdd($seedRunId, $this->wpdb->prefix . 'pet_announcements', $a2Id);

        // --- Build avatar and logo lookup maps for rich feed events ---
        $empTable = $wpdb->prefix . 'pet_employees';
        $empRows = $wpdb->get_results("SELECT id, wp_user_id, first_name, last_name FROM $empTable ORDER BY id ASC");
        $actorAvatars = []; // 'First Last' => avatar URL
        $actorIds = [];     // 'First Last' => employee id string
        $actorColors = [
            'Steve Admin' => '1a56db', 'Mia Manager' => '6f42c1', 'Liam Lead Tech' => '0d6efd',
            'Ava Consultant' => 'e83e8c', 'Noah Support' => '17a2b8', 'Zoe Finance' => '28a745',
            'Ethan DevOps' => 'fd7e14', 'Isabella Analyst' => '20c997',
        ];
        foreach ($empRows as $emp) {
            $fullName = $emp->first_name . ' ' . $emp->last_name;
            $color = $actorColors[$fullName] ?? '6c757d';
            $nameParam = urlencode($emp->first_name . ' ' . $emp->last_name);
            $actorAvatars[$fullName] = "https://ui-avatars.com/api/?name={$nameParam}&background={$color}&color=fff&size=64&bold=true";
            $actorIds[$fullName] = (string)$emp->id;
        }
        // External contacts (not employees)
        $actorAvatars['Priya Patel'] = 'https://ui-avatars.com/api/?name=Priya+Patel&background=845ec2&color=fff&size=64&bold=true';
        $actorAvatars['System'] = 'https://ui-avatars.com/api/?name=SYS&background=adb5bd&color=fff&size=64&bold=true';

        // Company logo URLs — deterministic brand colors
        $companyLogos = [
            'RPM Resources' => 'https://ui-avatars.com/api/?name=RPM&background=1a56db&color=fff&size=64&bold=true&length=3',
            'Acme Manufacturing' => 'https://ui-avatars.com/api/?name=AM&background=dc3545&color=fff&size=64&bold=true',
            'Nexus Startup Labs' => 'https://ui-avatars.com/api/?name=NL&background=28a745&color=fff&size=64&bold=true',
            'Government Digital Services' => 'https://ui-avatars.com/api/?name=GDS&background=6f42c1&color=fff&size=64&bold=true&length=3',
        ];
        $companyBrandColors = [
            'RPM Resources' => '#1a56db',
            'Acme Manufacturing' => '#dc3545',
            'Nexus Startup Labs' => '#28a745',
            'Government Digital Services' => '#6f42c1',
        ];

        // Rich feed events with real entity references and customer context
        $events = [
            // Commercial events
            ['commercial', 'quote', 'quote_sent', 'operational', 'Quote Sent to RPM Resources', 'Q1 Website Implementation & Advisory sent for approval', 'global', null, ['quote_id' => $q1Id, 'customer_id' => $rpmId, 'customer_name' => 'RPM Resources', 'actor_name' => 'Steve Admin', 'total_value' => 15400]],
            ['commercial', 'quote', 'quote_accepted', 'strategic', 'Quote Accepted — RPM Resources', 'RPM accepted Q1 Website Implementation & Advisory ($15,400)', 'global', null, ['quote_id' => $q1Id, 'customer_id' => $rpmId, 'customer_name' => 'RPM Resources', 'actor_name' => 'Priya Patel']],
            ['commercial', 'contract', 'contract_created', 'strategic', 'Contract Created — RPM Resources', 'Contract auto-generated from accepted quote Q1', 'global', null, ['quote_id' => $q1Id, 'customer_id' => $rpmId, 'customer_name' => 'RPM Resources', 'actor_name' => 'System']],
            ['commercial', 'quote', 'quote_sent', 'operational', 'Quote Sent to Acme Manufacturing', 'Q4 Catalog Services sent for review', 'global', null, ['customer_id' => $acmeId, 'customer_name' => 'Acme Manufacturing', 'actor_name' => 'Steve Admin']],
            ['commercial', 'quote', 'quote_accepted', 'strategic', 'Quote Accepted — Acme Manufacturing', 'Acme accepted Q4 Catalog Services', 'global', null, ['customer_id' => $acmeId, 'customer_name' => 'Acme Manufacturing', 'actor_name' => 'Mia Manager']],
            // Delivery events
            ['delivery', 'project', 'project_created', 'operational', 'Project Kicked Off — RPM Resources', 'Project created from accepted quote Q1', 'department', 'delivery', ['project_id' => $projId, 'customer_id' => $rpmId, 'customer_name' => 'RPM Resources', 'actor_name' => 'Mia Manager']],
            ['delivery', 'task', 'task_completed', 'informational', 'Task Completed: Kickoff Workshop', 'Discovery milestone progressing — RPM project', 'department', 'delivery', ['project_id' => $projId, 'customer_name' => 'RPM Resources', 'actor_name' => 'Liam Lead Tech']],
            ['delivery', 'task', 'task_completed', 'informational', 'Task Completed: Requirements Elicitation', 'Discovery milestone complete — moving to Build', 'department', 'delivery', ['project_id' => $projId, 'customer_name' => 'RPM Resources', 'actor_name' => 'Ava Consultant']],
            ['delivery', 'milestone', 'milestone_completed', 'strategic', 'Milestone Completed: Discovery', 'RPM project Discovery phase delivered on time', 'global', null, ['project_id' => $projId, 'customer_name' => 'RPM Resources', 'actor_name' => 'Mia Manager']],
            // Additional delivery events for richer PM view
            ['delivery', 'task', 'task_completed', 'informational', 'Task Completed: Theme Setup', 'Build phase progressing — RPM custom theme configured', 'department', 'delivery', ['project_id' => $projId, 'customer_name' => 'RPM Resources', 'actor_name' => 'Liam Lead Tech']],
            ['delivery', 'task', 'task_completed', 'informational', 'Task Completed: Custom Components', 'RPM custom component library delivered for review', 'department', 'delivery', ['project_id' => $projId, 'customer_name' => 'RPM Resources', 'actor_name' => 'Liam Lead Tech']],
            ['delivery', 'task', 'task_completed', 'informational', 'Task Completed: Onsite Training Day 1', 'Acme staff training day 1 delivered successfully', 'department', 'delivery', ['project_id' => $projAcmeId, 'customer_name' => 'Acme Manufacturing', 'actor_name' => 'Ava Consultant']],
            ['delivery', 'task', 'task_completed', 'informational', 'Task Completed: Onsite Training Day 2', 'Acme staff training day 2 completed', 'department', 'delivery', ['project_id' => $projAcmeId, 'customer_name' => 'Acme Manufacturing', 'actor_name' => 'Ava Consultant']],
            ['delivery', 'task', 'task_completed', 'informational', 'Task Completed: Remote Consulting Session 1', 'Acme follow-up consulting session delivered', 'department', 'delivery', ['project_id' => $projAcmeId, 'customer_name' => 'Acme Manufacturing', 'actor_name' => 'Ava Consultant']],
            ['delivery', 'project', 'project_status_changed', 'critical', 'Project At Risk: Acme Catalog Services', 'Deadline passed — project 4 days overdue with 38h logged against 35h budget', 'global', null, ['project_id' => $projAcmeId, 'customer_id' => $acmeId, 'customer_name' => 'Acme Manufacturing', 'actor_name' => 'System', 'tags' => ['Overdue', 'Over Budget']]],
            ['delivery', 'task', 'task_started', 'informational', 'Task Started: Cloud Readiness Assessment', 'Nexus cloud migration assessment underway', 'department', 'delivery', ['project_id' => $projNexusId, 'customer_id' => $nexusId, 'customer_name' => 'Nexus Startup Labs', 'actor_name' => 'Ethan DevOps']],
            ['time', 'time_entry', 'time_entry_logged', 'operational', 'Time Logged: RPM Website', 'Liam logged 6h against RPM Website — Custom Components', 'department', 'delivery', ['project_id' => $projId, 'customer_name' => 'RPM Resources', 'actor_name' => 'Liam Lead Tech']],
            ['time', 'time_entry', 'time_entry_logged', 'operational', 'Time Logged: Acme Catalog', 'Ava logged 8h against Acme Catalog — Remote Consulting', 'department', 'delivery', ['project_id' => $projAcmeId, 'customer_name' => 'Acme Manufacturing', 'actor_name' => 'Ava Consultant']],
            // Support events
            ['support', 'ticket', 'ticket_created', 'operational', 'Ticket: Login Issue', 'New support ticket from RPM Resources', 'department', 'support', ['ticket_id' => $ticketIds[0] ?? 0, 'customer_id' => $rpmId, 'customer_name' => 'RPM Resources', 'actor_name' => 'Noah Support']],
            ['support', 'ticket', 'ticket_assigned', 'operational', 'Ticket Assigned to Support', 'Login Issue assigned via priority queue', 'department', 'support', ['ticket_id' => $ticketIds[0] ?? 0, 'customer_name' => 'RPM Resources', 'actor_name' => 'System', 'sla' => ['clock_state' => 'active', 'seconds_remaining' => 7200, 'kind' => 'response']]],
            ['support', 'ticket', 'ticket_status_changed', 'operational', 'Ticket In Progress: Email Not Syncing', 'Support team investigating email sync issue', 'department', 'support', ['ticket_id' => $ticketIds[1] ?? 0, 'customer_name' => 'RPM Resources', 'actor_name' => 'Noah Support', 'sla' => ['clock_state' => 'active', 'seconds_remaining' => 3300, 'kind' => 'resolution']]],
            ['support', 'ticket', 'sla_warning', 'critical', 'SLA Warning: Server Alert', 'Ticket approaching SLA breach — 15 minutes remaining', 'department', 'support', ['ticket_id' => $ticketIds[2] ?? 0, 'customer_name' => 'RPM Resources', 'actor_name' => 'System', 'sla' => ['clock_state' => 'active', 'seconds_remaining' => 900, 'kind' => 'resolution'], 'tags' => ['SLA Risk']]],
            ['support', 'ticket', 'sla_breached', 'critical', 'SLA Breached: VPN Access', 'Resolution SLA breached — escalation triggered', 'global', null, ['ticket_id' => $ticketIds[3] ?? 0, 'customer_name' => 'RPM Resources', 'actor_name' => 'System', 'sla' => ['clock_state' => 'breached', 'seconds_remaining' => -2700, 'kind' => 'resolution'], 'tags' => ['SLA Breach']]],
            ['support', 'ticket', 'ticket_resolved', 'informational', 'Ticket Resolved: Printer Offline', 'Issue resolved within SLA — printer driver updated', 'department', 'support', ['ticket_id' => $ticketIds[4] ?? 0, 'customer_name' => 'RPM Resources', 'actor_name' => 'Noah Support']],
            // Escalation
            ['support', 'escalation', 'escalation_triggered', 'critical', 'Escalation: VPN Access', 'SLA breach triggered automatic escalation to manager', 'global', null, ['customer_name' => 'RPM Resources', 'actor_name' => 'System', 'tags' => ['Escalation']]],
            // Work events
            ['work', 'work_item', 'work_item_queued', 'informational', 'Work Item Queued', 'Support ticket entered priority queue', 'department', 'support', ['actor_name' => 'System']],
            // Time events
            ['time', 'time_entry', 'time_entry_approved', 'operational', 'Time Entries Approved', 'Mia approved 8 submitted time entries', 'department', 'delivery', ['actor_name' => 'Mia Manager', 'entries_count' => 8]],
            // Identity
            ['identity', 'employee', 'employee_onboarded', 'informational', 'New Team Member: Zoe Finance', 'Zoe joined the team as Finance specialist', 'global', null, ['actor_name' => 'Zoe Finance']],
            // Advisory
            ['advisory', 'report', 'advisory_published', 'strategic', 'Advisory Report: Q1 Governance Review', 'Governance advisory completed for RPM Resources', 'global', null, ['customer_name' => 'RPM Resources', 'actor_name' => 'Ava Consultant']],
            // New customer events
            ['commercial', 'quote', 'quote_sent', 'operational', 'Quote Sent to Nexus Startup Labs', 'Q5 Cloud Migration & Managed Services sent for approval', 'global', null, ['customer_name' => 'Nexus Startup Labs', 'actor_name' => 'Steve Admin']],
            ['commercial', 'quote', 'quote_accepted', 'strategic', 'Quote Accepted — Nexus Startup Labs', 'Nexus accepted Q5 Cloud Migration & Managed Services', 'global', null, ['customer_name' => 'Nexus Startup Labs', 'actor_name' => 'Mia Manager']],
            ['delivery', 'project', 'project_created', 'operational', 'Project Kicked Off — Nexus Labs', 'Cloud migration project created for Nexus Startup Labs', 'department', 'delivery', ['customer_name' => 'Nexus Startup Labs', 'actor_name' => 'Ethan DevOps']],
            ['delivery', 'project', 'project_created', 'operational', 'Project Kicked Off — Acme Manufacturing', 'Catalog services delivery started for Acme', 'department', 'delivery', ['customer_name' => 'Acme Manufacturing', 'actor_name' => 'Mia Manager']],
            ['commercial', 'quote', 'quote_drafted', 'informational', 'New Quote: Government IT Assessment', 'Q6 IT Infrastructure Assessment created for Government Digital Services', 'global', null, ['customer_name' => 'Government Digital Services', 'actor_name' => 'Isabella Analyst']],
            // Lead events
            ['commercial', 'lead', 'lead_created', 'informational', 'New Lead: Security Audit RFP', 'Government Digital Services submitted an enquiry for security audit', 'global', null, ['customer_name' => 'Government Digital Services', 'actor_name' => 'Isabella Analyst']],
            ['commercial', 'lead', 'lead_created', 'informational', 'New Lead: Kubernetes Migration', 'Nexus Startup Labs interested in Kubernetes migration assessment', 'global', null, ['customer_name' => 'Nexus Startup Labs', 'actor_name' => 'Ethan DevOps']],
            ['commercial', 'lead', 'lead_converted', 'strategic', 'Lead Converted: Website Modernisation', 'RPM Resources lead converted to Quote Q1', 'global', null, ['customer_name' => 'RPM Resources', 'actor_name' => 'Steve Admin']],
            ['commercial', 'lead', 'lead_converted', 'strategic', 'Lead Converted: Training Package', 'Acme Manufacturing lead converted to Quote Q4', 'global', null, ['customer_name' => 'Acme Manufacturing', 'actor_name' => 'Mia Manager']],
            ['commercial', 'quote', 'quote_sent', 'operational', 'Quote Sent to RPM Resources', 'Q7 Annual Support Renewal sent for review', 'global', null, ['customer_name' => 'RPM Resources', 'actor_name' => 'Mia Manager']],
            ['support', 'ticket', 'ticket_created', 'operational', 'Ticket: ERP Module Crashing', 'Critical ticket from Acme Manufacturing — ERP reports failing', 'department', 'support', ['customer_name' => 'Acme Manufacturing', 'actor_name' => 'Noah Support']],
            ['support', 'ticket', 'ticket_created', 'operational', 'Ticket: CI/CD Pipeline Failure', 'Critical ticket from Nexus Labs — staging deployment blocked', 'department', 'support', ['customer_name' => 'Nexus Startup Labs', 'actor_name' => 'Liam Lead Tech']],
            ['identity', 'employee', 'employee_onboarded', 'informational', 'New Team Member: Ethan DevOps', 'Ethan joined the team as DevOps Engineer', 'global', null, ['actor_name' => 'Ethan DevOps']],
            ['identity', 'employee', 'employee_onboarded', 'informational', 'New Team Member: Isabella Analyst', 'Isabella joined as Business Analyst', 'global', null, ['actor_name' => 'Isabella Analyst']],
        ];

        // Enrich each event with avatar URL, logo URL, actor_id, actor_type
        foreach ($events as [$engine, $entity, $etype, $class, $title, $summaryText, $aud, $audRef, $meta]) {
            $eid = $this->uuid();

            // Resolve actor avatar and ID from name
            $actorName = $meta['actor_name'] ?? null;
            if ($actorName && isset($actorAvatars[$actorName])) {
                $meta['actor_avatar_url'] = $actorAvatars[$actorName];
                $meta['actor_type'] = ($actorName === 'System') ? 'system' : 'employee';
                if (isset($actorIds[$actorName])) {
                    $meta['actor_id'] = $actorIds[$actorName];
                }
            }

            // Resolve company logo from customer name
            $custName = $meta['customer_name'] ?? null;
            if ($custName) {
                foreach ($companyLogos as $namePrefix => $logoUrl) {
                    if (str_starts_with($custName, $namePrefix) || $custName === $namePrefix) {
                        $meta['company_logo_url'] = $logoUrl;
                        $meta['company_brand_color'] = $companyBrandColors[$namePrefix] ?? null;
                        break;
                    }
                }
            }

            $metadata = array_merge($meta, [
                'seed_run_id' => $seedRunId,
                'seed_profile' => $seedProfile,
                'seeded_at' => $seededAt,
            ]);
            $feedRepo->save(\Pet\Domain\Feed\Entity\FeedEvent::create(
                $eid,
                $etype,
                $engine,
                $entity,
                $class,
                $title,
                $summaryText,
                $metadata,
                $aud,
                $audRef,
                false,
                null
            ));
            $this->registryAdd($seedRunId, $this->wpdb->prefix . 'pet_feed_events', $eid);
        }
        $eventsCount = (int)$this->wpdb->get_var("SELECT COUNT(*) FROM {$this->wpdb->prefix}pet_feed_events");
        $annCount = (int)$this->wpdb->get_var("SELECT COUNT(*) FROM {$this->wpdb->prefix}pet_announcements");
        return ['events' => $eventsCount, 'announcements' => $annCount];
    }

    private function seedConversations(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        $c = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
        $createConversation = $c->get(\Pet\Application\Conversation\Command\CreateConversationHandler::class);
        $postMessage = $c->get(\Pet\Application\Conversation\Command\PostMessageHandler::class);
        $wpdb = $this->wpdb;

        $currentUserId = get_current_user_id();
        $empTable = $wpdb->prefix . 'pet_employees';
        $miaId = (int)$wpdb->get_var("SELECT wp_user_id FROM $empTable WHERE first_name='Mia' LIMIT 1") ?: $currentUserId;
        $avaId = (int)$wpdb->get_var("SELECT wp_user_id FROM $empTable WHERE first_name='Ava' LIMIT 1") ?: $currentUserId;
        $noahId = (int)$wpdb->get_var("SELECT wp_user_id FROM $empTable WHERE first_name='Noah' LIMIT 1") ?: $currentUserId;

        $created = 0;

        // Conversations on quotes (all 7)
        $ethanId = (int)$wpdb->get_var("SELECT wp_user_id FROM $empTable WHERE first_name='Ethan' LIMIT 1") ?: $currentUserId;
        $isabellaId = (int)$wpdb->get_var("SELECT wp_user_id FROM $empTable WHERE first_name='Isabella' LIMIT 1") ?: $currentUserId;
        $liamId = (int)$wpdb->get_var("SELECT wp_user_id FROM $empTable WHERE first_name='Liam' LIMIT 1") ?: $currentUserId;
        $zoeId = (int)$wpdb->get_var("SELECT wp_user_id FROM $empTable WHERE first_name='Zoe' LIMIT 1") ?: $currentUserId;
        $quotes = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}pet_quotes ORDER BY id ASC LIMIT 7");
        $quoteMessages = [
            [
                ['actor' => $currentUserId, 'body' => 'I\'ve structured this as Implementation + Advisory to cover both delivery and governance. The 4 milestones follow our standard engagement model.'],
                ['actor' => $miaId, 'body' => 'Looks good. The payment schedule split 50/50 works for RPM. Can we confirm the advisory pack pricing with Ava?'],
                ['actor' => $avaId, 'body' => 'Advisory rates confirmed. 4x Governance Review + 3x SLA Design is the right mix for their maturity level.'],
                ['actor' => $currentUserId, 'body' => 'Perfect. Sending to the client for approval.'],
            ],
            [
                ['actor' => $currentUserId, 'body' => 'This is scoped as assessment-only for now. Acme wants to understand the migration risk before committing.'],
                ['actor' => $miaId, 'body' => 'Understood. Keep it in draft until the steering committee meets next Thursday.'],
            ],
            [
                ['actor' => $miaId, 'body' => 'Recurring support component looks right. The 12-month auto-renew gives RPM the continuity they asked for.'],
                ['actor' => $currentUserId, 'body' => 'Agreed. Response time at 4 hours and resolution at 24 hours matches their SLA tier.'],
            ],
            [
                ['actor' => $avaId, 'body' => 'Catalog items priced per standard rate card. Training days discounted to $500 as agreed.'],
                ['actor' => $currentUserId, 'body' => 'Good. Acme confirmed the 2 onsite training + 6 remote consulting split.'],
                ['actor' => $miaId, 'body' => 'Approved. Send it through.'],
            ],
            [
                ['actor' => $ethanId, 'body' => 'Cloud readiness assessment will take about 2 weeks. I\'ve scoped the architecture design to cover multi-AZ deployment.'],
                ['actor' => $currentUserId, 'body' => 'The 24-month managed services term is important — Nexus wants long-term stability.'],
                ['actor' => $miaId, 'body' => 'Payment schedule is 30/40/30. This gives us cash flow coverage for the infrastructure provisioning phase.'],
                ['actor' => $ethanId, 'body' => 'Confirmed. The recurring component at R2,200/month covers backup, monitoring, and 1h emergency response SLA.'],
            ],
            [
                ['actor' => $isabellaId, 'body' => 'Government procurement requires detailed line items. I\'ve broken out the assessment, governance sessions, and BA hours separately.'],
                ['actor' => $avaId, 'body' => 'The 6 governance reviews align with their quarterly audit cycle. SSL certs are a standard add-on for their compliance requirements.'],
                ['actor' => $currentUserId, 'body' => 'Still in draft — waiting for the RFQ response deadline next month.'],
            ],
            [
                ['actor' => $miaId, 'body' => 'RPM\'s annual renewal is due. I\'ve upgraded them to Premium tier with 60-minute response SLA.'],
                ['actor' => $noahId, 'body' => 'The 10 emergency support hours are essential — they used 8 last year. Managed backup covers both sites.'],
                ['actor' => $currentUserId, 'body' => 'Sent to Priya for approval. She\'s reviewing with their CFO this week.'],
            ],
        ];

        foreach ($quotes as $qi => $q) {
            try {
                $convUuid = $createConversation->handle(new \Pet\Application\Conversation\Command\CreateConversationCommand(
                    'quote',
                    (string)$q->id,
                    'Discussion: ' . $q->title,
                    'quote-' . $q->id . '-general',
                    $currentUserId
                ));
                $messages = $quoteMessages[$qi] ?? [];
                foreach ($messages as $msg) {
                    $postMessage->handle(new \Pet\Application\Conversation\Command\PostMessageCommand(
                        $convUuid,
                        $msg['body'],
                        [],
                        [],
                        $msg['actor']
                    ));
                }
                $created++;
            } catch (\Throwable $e) {
                // Skip if conversation already exists
            }
        }

        // Conversations on tickets — all non-closed tickets get a discussion thread
        $tickets = $wpdb->get_results("SELECT id, subject FROM {$wpdb->prefix}pet_tickets WHERE status NOT IN ('closed') ORDER BY id ASC");
        $ticketMessages = [
            // 1. Login issue (RPM)
            [
                ['actor' => $noahId, 'body' => 'User reports intermittent login failures since this morning. Clearing cache didn\'t help.'],
                ['actor' => $currentUserId, 'body' => 'Check if their AD password expired. We saw similar issues last month with the sync delay.'],
                ['actor' => $noahId, 'body' => 'Confirmed — password sync was delayed. Forced a reset and user can log in now. Monitoring.'],
            ],
            // 2. Email not syncing (RPM)
            [
                ['actor' => $noahId, 'body' => 'Email sync stopped for 3 users on the Cape Town site. Exchange Online connector shows healthy.'],
                ['actor' => $miaId, 'body' => 'This might be related to the firewall change yesterday. Can you check the mail flow rules?'],
            ],
            // 3. Server alert (RPM)
            [
                ['actor' => $currentUserId, 'body' => 'Nagios alert: CPU at 92% on web-prod-02 for the last 30 minutes.'],
                ['actor' => $noahId, 'body' => 'Investigating. Looks like a runaway cron job. Restarting the service now.'],
                ['actor' => $noahId, 'body' => 'Resolved. The backup job was running during peak hours. Rescheduled to 02:00.'],
            ],
            // 4. VPN access (RPM)
            [
                ['actor' => $avaId, 'body' => 'New contractor needs VPN access for the RPM project. They start Monday.'],
                ['actor' => $currentUserId, 'body' => 'Ticket escalated — SLA is tight on this one. @Noah can you provision today?'],
            ],
            // 5. Printer offline (RPM)
            [
                ['actor' => $noahId, 'body' => 'Printer on floor 2 showing offline. Already checked the network cable and power cycled.'],
                ['actor' => $liamId, 'body' => 'Tried replacing the toner and resetting the print spooler. Still no luck — might be a hardware fault.'],
                ['actor' => $noahId, 'body' => 'Swapped in the spare unit from storeroom. Old one tagged for vendor repair.'],
            ],
            // 6. New user setup (RPM)
            [
                ['actor' => $noahId, 'body' => 'New hire starting next week — need full onboarding: AD account, email, VPN token, laptop provisioning.'],
                ['actor' => $currentUserId, 'body' => 'Laptop is ready. AD account created. Just need VPN token from the Sophos portal.'],
                ['actor' => $noahId, 'body' => 'VPN provisioned. Welcome pack email scheduled for Monday 08:00.'],
            ],
            // 7. Policy question (RPM)
            [
                ['actor' => $avaId, 'body' => 'Client asked about our data retention policy for backup archives. Need the latest version.'],
                ['actor' => $miaId, 'body' => 'The updated policy is in SharePoint under Governance > Policies. Version 3.2 was approved last quarter.'],
            ],
            // 8. ERP module crashing (Acme)
            [
                ['actor' => $noahId, 'body' => 'Acme reports ERP module crashing when generating monthly reports. Affects their Stellenbosch site.'],
                ['actor' => $ethanId, 'body' => 'Looks like a memory issue on the report server. I\'ll increase the allocation and check the query optimization.'],
                ['actor' => $noahId, 'body' => 'Temporary fix applied — increased memory to 16GB. Long-term fix needs a DB index on the reports table.'],
            ],
            // 9. Slow network at Stellenbosch (Acme)
            [
                ['actor' => $noahId, 'body' => 'Acme Stellenbosch site reporting 300ms+ latency on internal apps. Started after the MPLS link maintenance window.'],
                ['actor' => $ethanId, 'body' => 'Ran a traceroute — traffic is routing via the backup link instead of primary. Looks like the failback didn\'t trigger.'],
                ['actor' => $noahId, 'body' => 'Forced failback to primary link. Latency back to 12ms. Adding monitoring alert for MPLS path changes.'],
            ],
            // 10. License renewal query (Acme)
            [
                ['actor' => $isabellaId, 'body' => 'Acme\'s Microsoft 365 E3 licenses expire end of month. 45 seats need renewal.'],
                ['actor' => $zoeId, 'body' => 'PO received from Acme finance. I\'ll process the renewal through our CSP portal today.'],
                ['actor' => $isabellaId, 'body' => 'Renewal confirmed. New expiry date is 12 months out. Sent confirmation to Sarah Jacobs at Acme.'],
            ],
            // 11. AWS console access (Nexus)
            [
                ['actor' => $ethanId, 'body' => 'Nexus dev team can\'t access the AWS console. IAM login page returns 403 Forbidden.'],
                ['actor' => $currentUserId, 'body' => 'Checked CloudTrail — the IAM policy was updated by their automation pipeline. Reverting to last known good.'],
                ['actor' => $ethanId, 'body' => 'Access restored. Added a policy version lock so their CI/CD can\'t overwrite IAM permissions again.'],
            ],
            // 12. CI/CD pipeline failure (Nexus)
            [
                ['actor' => $ethanId, 'body' => 'Nexus CI/CD pipeline failed on the staging deployment. Build logs show a Docker image pull timeout.'],
                ['actor' => $currentUserId, 'body' => 'Check the ECR credentials — they might have rotated. This happened before with their IAM policy.'],
            ],
            // 13. DNS propagation delay (Nexus)
            [
                ['actor' => $noahId, 'body' => 'Nexus DNS change propagating slowly. Some users still hitting the old IP.'],
                ['actor' => $ethanId, 'body' => 'TTL was set to 86400. Lowering to 300 for the migration period. Should resolve within 5 minutes.'],
            ],
        ];

        // Generic fallback conversations for tickets beyond the explicit list
        $fallbackConversations = [
            [
                ['body' => 'Picked this up — reviewing the details now. Will update once I have a diagnosis.'],
                ['body' => 'Initial assessment complete. Looks straightforward — working on the fix.'],
            ],
            [
                ['body' => 'Reproduced the issue in our test environment. Isolating the root cause.'],
                ['body' => 'Found it — configuration drift after the last maintenance window. Applying corrective config now.'],
                ['body' => 'Fix deployed and verified. Monitoring for recurrence over the next 24 hours.'],
            ],
            [
                ['body' => 'Customer reported this via email. I\'ve gathered the logs and screenshots they attached.'],
                ['body' => 'Good — escalating to engineering. The logs show an intermittent timeout pattern that needs deeper investigation.'],
            ],
            [
                ['body' => 'This is a known issue from the last release. Workaround documented in KB-2047.'],
                ['body' => 'Applied the workaround for the customer. Permanent fix is scheduled for the next patch cycle.'],
                ['body' => 'Customer confirmed the workaround resolved their immediate issue. Keeping the ticket open for tracking.'],
            ],
            [
                ['body' => 'Ran diagnostics — all infrastructure checks pass. Suspect this is application-level.'],
                ['body' => 'Confirmed. The application config had a stale connection string. Updated and tested — working now.'],
            ],
            [
                ['body' => 'Scheduled a remote session with the customer for 14:00 to investigate live.'],
                ['body' => 'Remote session complete. Identified a permissions issue on their end. Fixed during the call.'],
                ['body' => 'Customer happy with the resolution. Closing after 24h observation period.'],
            ],
            [
                ['body' => 'Third occurrence this month. Raising this with the team — we need a permanent resolution.'],
                ['body' => 'Agreed. I\'ve added this to the next sprint backlog. In the meantime, automated restart script is in place.'],
            ],
            [
                ['body' => 'Initial triage complete. This requires vendor involvement — opening a case with the supplier.'],
                ['body' => 'Vendor confirmed the issue. Patch expected within 48 hours. Customer notified of timeline.'],
            ],
            [
                ['body' => 'Checked the monitoring dashboards — no anomalies in the last 6 hours. Might be intermittent.'],
                ['body' => 'Set up enhanced logging to capture the next occurrence. Customer asked to report immediately if it recurs.'],
                ['body' => 'Captured the event — it\'s a race condition in the queue processor. Deploying the fix shortly.'],
            ],
            [
                ['body' => 'Customer\'s security team flagged this. Reviewing access logs and audit trail now.'],
                ['body' => 'No breach detected. The alert was triggered by a legitimate bulk import process. Updated the alert threshold.'],
            ],
        ];

        // Actor rotation pool for fallback conversations
        $actorPool = [$noahId, $currentUserId, $miaId, $ethanId, $avaId, $liamId, $isabellaId, $zoeId];

        foreach ($tickets as $ti => $t) {
            try {
                $convUuid = $createConversation->handle(new \Pet\Application\Conversation\Command\CreateConversationCommand(
                    'ticket',
                    (string)$t->id,
                    'Ticket #' . $t->id . ': ' . $t->subject,
                    'ticket-' . $t->id . '-general',
                    $currentUserId
                ));

                if ($ti < count($ticketMessages)) {
                    // Use explicit conversation
                    $msgs = $ticketMessages[$ti];
                } else {
                    // Use fallback with rotating actors
                    $fbIdx = ($ti - count($ticketMessages)) % count($fallbackConversations);
                    $fbTemplate = $fallbackConversations[$fbIdx];
                    $msgs = [];
                    foreach ($fbTemplate as $mi => $msg) {
                        $actorIdx = ($ti + $mi) % count($actorPool);
                        $msgs[] = ['actor' => $actorPool[$actorIdx], 'body' => $msg['body']];
                    }
                }

                foreach ($msgs as $msg) {
                    $postMessage->handle(new \Pet\Application\Conversation\Command\PostMessageCommand(
                        $convUuid,
                        $msg['body'],
                        [],
                        [],
                        $msg['actor']
                    ));
                }
                $created++;
            } catch (\Throwable $e) {
                // Skip on error
            }
        }

        // Conversations on projects
        $projects = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}pet_projects ORDER BY id ASC LIMIT 3");
        $projectMessages = [
            [
                ['actor' => $miaId, 'body' => 'Discovery phase complete. Kickoff and requirements both signed off. Moving to Build milestone.'],
                ['actor' => $currentUserId, 'body' => 'Theme setup is underway. Liam estimates 2 days for custom components after that.'],
                ['actor' => $avaId, 'body' => 'Advisory sessions running in parallel. First governance review scheduled for next Tuesday.'],
            ],
            [
                ['actor' => $currentUserId, 'body' => 'Acme project kicked off. Training schedule confirmed with Sarah Jacobs.'],
                ['actor' => $miaId, 'body' => 'Consulting hours are tracking well. 2 of 6 sessions complete.'],
            ],
            [
                ['actor' => $ethanId, 'body' => 'Cloud migration project just kicked off. Assessment phase starts Monday.'],
                ['actor' => $currentUserId, 'body' => 'Nexus team is eager to start. Tariq confirmed their AWS account access is ready.'],
            ],
        ];
        foreach ($projects as $pi => $p) {
            try {
                $convUuid = $createConversation->handle(new \Pet\Application\Conversation\Command\CreateConversationCommand(
                    'project', (string)$p->id, 'Project: ' . $p->name, 'project-' . $p->id . '-general', $currentUserId
                ));
                $messages = $projectMessages[$pi] ?? [];
                foreach ($messages as $msg) {
                    $postMessage->handle(new \Pet\Application\Conversation\Command\PostMessageCommand(
                        $convUuid, $msg['body'], [], [], $msg['actor']
                    ));
                }
                $created++;
            } catch (\Throwable $e) {
                // Skip on error
            }
        }

        return ['conversations' => $created];
    }

    private function seedProjectTasks(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        $c = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
        /** @var \Pet\Application\Delivery\Command\AddTaskHandler $addTask */
        $addTask = $c->get(\Pet\Application\Delivery\Command\AddTaskHandler::class);
        $wpdb = $this->wpdb;

        $projects = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}pet_projects WHERE source_quote_id IS NOT NULL ORDER BY id ASC LIMIT 3");
        if (empty($projects)) {
            return ['tasks' => 0];
        }

        // Task definitions per project (completion is handled in seedProjectEnrichment)
        $projectTasks = [
            // Project 1: RPM Website (Discovery complete, Build in progress)
            [
                ['Kickoff Workshop', 6.0],
                ['Requirements Elicitation', 12.0],
                ['Theme Setup', 10.0],
                ['Custom Components', 20.0],
                ['UAT Support', 8.0],
                ['Go-Live Checklist', 6.0],
                ['Cutover Planning', 4.0],
                ['Hypercare Support', 6.0],
                ['Stabilization Review', 4.0],
                ['Handover Workshop', 6.0],
            ],
            // Project 2: Acme Catalog Services
            [
                ['Training Schedule Setup', 4.0],
                ['Onsite Training Day 1', 8.0],
                ['Onsite Training Day 2', 8.0],
                ['Remote Consulting Session 1', 3.0],
                ['Remote Consulting Session 2', 3.0],
                ['Remote Consulting Session 3', 3.0],
                ['Progress Review', 2.0],
                ['Final Report', 4.0],
            ],
            // Project 3: Nexus Cloud Migration
            [
                ['Cloud Readiness Assessment', 16.0],
                ['Architecture Design', 12.0],
                ['Migration Runbook', 8.0],
                ['Infrastructure Provisioning', 20.0],
                ['Data Migration', 16.0],
                ['Application Deployment', 12.0],
                ['Performance Testing', 8.0],
                ['Security Audit', 6.0],
                ['Handover & Training', 8.0],
            ],
        ];

        $totalCount = 0;
        foreach ($projects as $pi => $project) {
            $projectId = (int)$project->id;
            $existingTasks = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}pet_tasks WHERE project_id = %d", $projectId
            ));
            if ($existingTasks > 0) continue;

            $tasks = $projectTasks[$pi] ?? [];
            foreach ($tasks as [$name, $hours]) {
                try {
                    $addTask->handle(new \Pet\Application\Delivery\Command\AddTaskCommand($projectId, $name, $hours));
                    $totalCount++;
                } catch (\Throwable $e) {
                    // Skip on error
                }
            }
        }

        return ['tasks' => $totalCount];
    }

    private function seedProjectEnrichment(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        $wpdb = $this->wpdb;
        $projTable = $wpdb->prefix . 'pet_projects';
        $taskTable = $wpdb->prefix . 'pet_tasks';
        $now = new \DateTimeImmutable();

        // Fetch all seeded projects (created from quotes) in order
        $projects = $wpdb->get_results(
            "SELECT id, name FROM $projTable WHERE source_quote_id IS NOT NULL ORDER BY id ASC LIMIT 3"
        );
        if (empty($projects)) {
            return ['enriched' => 0];
        }

        // Project enrichment definitions: [state, start_offset, end_offset, sold_hours, hours_used, pm, health]
        // Offsets are in days relative to now (negative = past)
        $enrichments = [
            // RPM Website: active, 6 weeks in, 2 weeks to go — healthy mid-delivery
            [
                'state' => 'active',
                'start_days' => -42,
                'end_days' => 14,
                'sold_hours' => 82.0,
                'hours_used' => 24.0,
                'pm' => 'Mia Manager',
                'health' => 'on_track',
                'complete_tasks' => ['Kickoff Workshop', 'Requirements Elicitation', 'Theme Setup', 'Custom Components'],
            ],
            // Acme Catalog: active, started 3 weeks ago, deadline was 4 days ago — OVERDUE + OVER BUDGET
            [
                'state' => 'active',
                'start_days' => -21,
                'end_days' => -4,
                'sold_hours' => 35.0,
                'hours_used' => 38.0,
                'pm' => 'Ava Consultant',
                'health' => 'at_risk',
                'complete_tasks' => ['Training Schedule Setup', 'Onsite Training Day 1', 'Onsite Training Day 2', 'Remote Consulting Session 1', 'Remote Consulting Session 2'],
            ],
            // Nexus Cloud: active, 1 week in, 8 weeks to go — early stage
            [
                'state' => 'active',
                'start_days' => -7,
                'end_days' => 56,
                'sold_hours' => 106.0,
                'hours_used' => 8.0,
                'pm' => 'Ethan DevOps',
                'health' => 'on_track',
                'complete_tasks' => [],
            ],
        ];

        $enrichedCount = 0;
        foreach ($projects as $pi => $project) {
            $def = $enrichments[$pi] ?? null;
            if (!$def) continue;

            $projectId = (int)$project->id;
            $startDate = $now->modify($def['start_days'] . ' days')->format('Y-m-d');
            $endDate = $now->modify($def['end_days'] . ' days')->format('Y-m-d');

            $malleableData = json_encode([
                'pm' => $def['pm'],
                'health' => $def['health'],
                'hours_used' => $def['hours_used'],
            ], JSON_UNESCAPED_SLASHES);

            // Use explicit prepared SQL for reliable direct writes
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE $projTable SET state = %s, start_date = %s, end_date = %s, sold_hours = %f, malleable_data = %s, updated_at = %s WHERE id = %d",
                $def['state'],
                $startDate,
                $endDate,
                $def['sold_hours'],
                $malleableData,
                $seededAt,
                $projectId
            ));

            if ($result === false) {
                error_log("PET seedProjectEnrichment: UPDATE failed for project $projectId — " . $wpdb->last_error);
            }

            // Complete specified tasks via explicit SQL
            foreach ($def['complete_tasks'] as $taskName) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE $taskTable SET is_completed = 1 WHERE project_id = %d AND name = %s",
                    $projectId,
                    $taskName
                ));
            }

            $enrichedCount++;
        }

        return ['enriched' => $enrichedCount];
    }

    private function seedBilling(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        $wpdb = $this->wpdb;
        $c = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
        /** @var \Pet\Application\Finance\Command\CreateBillingExportHandler $createExport */
        $createExport = $c->get(\Pet\Application\Finance\Command\CreateBillingExportHandler::class);
        /** @var \Pet\Application\Finance\Command\AddBillingExportItemHandler $addItem */
        $addItem = $c->get(\Pet\Application\Finance\Command\AddBillingExportItemHandler::class);
        /** @var \Pet\Application\Finance\Command\QueueBillingExportForQuickBooksHandler $queueExport */
        $queueExport = $c->get(\Pet\Application\Finance\Command\QueueBillingExportForQuickBooksHandler::class);
        /** @var \Pet\Infrastructure\Persistence\Repository\SqlQbInvoiceRepository $qbInvoices */
        $qbInvoices = $c->get(\Pet\Infrastructure\Persistence\Repository\SqlQbInvoiceRepository::class);
        /** @var \Pet\Infrastructure\Persistence\Repository\SqlQbPaymentRepository $qbPayments */
        $qbPayments = $c->get(\Pet\Infrastructure\Persistence\Repository\SqlQbPaymentRepository::class);
        /** @var \Pet\Infrastructure\Persistence\Repository\SqlExternalMappingRepository $mappings */
        $mappings = $c->get(\Pet\Infrastructure\Persistence\Repository\SqlExternalMappingRepository::class);
        $customerId = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}pet_customers WHERE name = %s LIMIT 1", 'RPM Resources (Pty) Ltd'));

        $qbInvTable = $wpdb->prefix . 'pet_qb_invoices';
        $qbPayTable = $wpdb->prefix . 'pet_qb_payments';
        $wpdb->query("DELETE FROM $qbInvTable");
        $wpdb->query("DELETE FROM $qbPayTable");
        $periodStart = new \DateTimeImmutable('first day of last month');
        $periodEnd = new \DateTimeImmutable('last day of last month');
        $exportId = $createExport->handle(new \Pet\Application\Finance\Command\CreateBillingExportCommand(
            $customerId,
            $periodStart,
            $periodEnd,
            1
        ));
        $addItem->handle(new \Pet\Application\Finance\Command\AddBillingExportItemCommand(
            $exportId, 'time_entry', 1, 10.0, 120.0, 'Consulting Hours', 'SERV-001'
        ));
        $addItem->handle(new \Pet\Application\Finance\Command\AddBillingExportItemCommand(
            $exportId, 'time_entry', 2, 8.0, 120.0, 'Support Hours', 'SERV-002'
        ));
        $baselineCompId = (int)$wpdb->get_var("SELECT bc.id FROM {$wpdb->prefix}pet_baseline_components bc INNER JOIN {$wpdb->prefix}pet_baselines b ON b.id = bc.baseline_id ORDER BY bc.id ASC LIMIT 1");
        if ($baselineCompId) {
            $addItem->handle(new \Pet\Application\Finance\Command\AddBillingExportItemCommand(
                $exportId, 'baseline_component', $baselineCompId, 1.0, 200.0, 'Baseline Component', 'SERV-001'
            ));
        }
        $queueExport->handle(new \Pet\Application\Finance\Command\QueueBillingExportForQuickBooksCommand($exportId));
        $wpdb->update($wpdb->prefix . 'pet_billing_exports', ['status' => 'queued', 'updated_at' => $seededAt], ['id' => $exportId]);
        $qbInvoices->recordInvoiceSnapshot($customerId, [
            'doc_number' => 'INV-1001',
            'currency' => 'USD',
            'total_amount' => 2160.00
        ]);
        $qbInvoices->recordInvoiceSnapshot($customerId, [
            'doc_number' => 'INV-1002',
            'currency' => 'USD',
            'total_amount' => 840.00
        ]);
        $qbInvoices->recordInvoiceSnapshot($customerId, [
            'doc_number' => 'INV-1003',
            'currency' => 'USD',
            'total_amount' => 1250.00
        ]);
        $qbPayments->upsertPayment($customerId, 'PAY-5001', (new \DateTimeImmutable())->format('Y-m-d'), 1500.00, 'USD', [
            ['doc_number' => 'INV-1001', 'amount' => 1500.00]
        ]);
        $qbPayments->upsertPayment($customerId, 'PAY-5002', (new \DateTimeImmutable())->format('Y-m-d'), 500.00, 'USD', [
            ['doc_number' => 'INV-1002', 'amount' => 500.00]
        ]);
        $mappings->upsert('quickbooks', 'invoice', 1001, 'QB-INV-1001', 'v1');
        $wpdb->delete($wpdb->prefix . 'pet_external_mappings', ['system' => 'quickbooks', 'entity_type' => 'billing_export']);
        $mappings->upsert('quickbooks', 'billing_export', $exportId, 'QB-INV-1001', 'v1');
        $mappingExportCount = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}pet_external_mappings WHERE `system` = %s AND entity_type = %s AND pet_entity_id = %d",
            'quickbooks',
            'billing_export',
            $exportId
        ));
        if ($mappingExportCount <= 0) {
            $wpdb->insert($wpdb->prefix . 'pet_external_mappings', [
                'system' => 'quickbooks',
                'entity_type' => 'billing_export',
                'pet_entity_id' => $exportId,
                'external_id' => 'QB-INV-1001',
                'external_version' => 'v1',
                'created_at' => $seededAt,
                'updated_at' => $seededAt,
            ]);
        }
        $runsTable = $wpdb->prefix . 'pet_integration_runs';
        if ($wpdb->get_var("SHOW TABLES LIKE '$runsTable'") !== $runsTable) {
            $charsetCollate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $runsTable (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                `system` varchar(32) NOT NULL,
                direction varchar(16) NOT NULL,
                status varchar(16) NOT NULL,
                started_at datetime NOT NULL,
                finished_at datetime DEFAULT NULL,
                summary_json longtext,
                last_error text,
                PRIMARY KEY (id)
            ) $charsetCollate;";
            $wpdb->query($sql);
        }
        $wpdb->insert($runsTable, [
            'uuid' => $this->uuid(),
            'system' => 'quickbooks',
            'direction' => 'outbound',
            'status' => 'failed',
            'started_at' => $seededAt,
            'finished_at' => $seededAt,
            'summary_json' => json_encode(['entity_type' => 'billing_export', 'entity_id' => $exportId])
        ]);
        $exports = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pet_billing_exports");
        $invoices = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pet_qb_invoices");
        $payments = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pet_qb_payments");
        return ['exports' => $exports, 'invoices' => $invoices, 'payments' => $payments];
    }

    private function seedPulseway(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        $wpdb = $this->wpdb;
        // Table names match migration: CreatePulsewayIntegrationTables
        $intTable = $wpdb->prefix . 'pet_pulseway_integrations';
        $notifTable = $wpdb->prefix . 'pet_external_notifications';
        $devTable = $wpdb->prefix . 'pet_external_assets';
        $orgTable = $wpdb->prefix . 'pet_pulseway_org_mappings';
        $rulesTable = $wpdb->prefix . 'pet_pulseway_ticket_rules';
        $settingsTable = $wpdb->prefix . 'pet_settings';

        // Skip if Pulseway tables don't exist yet
        if ($wpdb->get_var("SHOW TABLES LIKE '$intTable'") !== $intTable) {
            return ['skipped' => true, 'reason' => 'pulseway tables not yet migrated'];
        }

        // Enable Pulseway feature flags
        foreach (['pet_pulseway_enabled', 'pet_pulseway_ticket_creation_enabled'] as $flag) {
            $existing = $wpdb->get_var($wpdb->prepare("SELECT setting_key FROM $settingsTable WHERE setting_key = %s", $flag));
            if (!$existing) {
                $wpdb->insert($settingsTable, [
                    'setting_key' => $flag,
                    'setting_value' => '1',
                    'setting_type' => 'boolean',
                    'description' => 'Pulseway RMM integration flag (demo seed)',
                    'updated_at' => $seededAt,
                ]);
            } else {
                $wpdb->update($settingsTable, ['setting_value' => '1', 'updated_at' => $seededAt], ['setting_key' => $flag]);
            }
        }

        // --- Integration record (schema: uuid, label, api_base_url, token_id_encrypted, token_secret_encrypted) ---
        $wpdb->insert($intTable, [
            'uuid' => $this->uuid(),
            'label' => 'Demo Pulseway Instance',
            'api_base_url' => 'https://api.pulseway.com/v3/',
            'token_id_encrypted' => base64_encode('demo_token_id'),
            'token_secret_encrypted' => base64_encode('demo_token_secret'),
            'is_active' => 1,
            'poll_interval_seconds' => 300,
            'last_poll_at' => $seededAt,
            'last_success_at' => $seededAt,
            'consecutive_failures' => 0,
            'created_at' => $seededAt,
            'updated_at' => $seededAt,
        ]);
        $integrationId = (int)$wpdb->insert_id;
        $this->registryAdd($seedRunId, $intTable, (string)$integrationId);

        // --- Devices (schema: pet_external_assets — external_asset_id, display_name, platform, status, external_org/site/group_id) ---
        $devices = [
            ['hostname' => 'DC-SVR-01',   'os' => 'Windows Server 2022', 'group' => 'Servers',      'site' => 'RPM Cape Town',     'status' => 'online',  'ip' => '10.0.1.10'],
            ['hostname' => 'DC-SVR-02',   'os' => 'Windows Server 2019', 'group' => 'Servers',      'site' => 'RPM Johannesburg',  'status' => 'online',  'ip' => '10.0.2.10'],
            ['hostname' => 'FW-EDGE-01',  'os' => 'pfSense 2.7',        'group' => 'Network',      'site' => 'RPM Cape Town',     'status' => 'online',  'ip' => '10.0.1.1'],
            ['hostname' => 'WS-MKT-PC03', 'os' => 'Windows 11 Pro',     'group' => 'Workstations', 'site' => 'Acme Stellenbosch', 'status' => 'offline', 'ip' => '192.168.5.103'],
            ['hostname' => 'APP-WEB-01',  'os' => 'Ubuntu 22.04 LTS',   'group' => 'Servers',      'site' => 'Nexus Cape Town',   'status' => 'online',  'ip' => '10.0.3.20'],
        ];
        $deviceIds = [];
        foreach ($devices as $d) {
            $wpdb->insert($devTable, [
                'integration_id' => $integrationId,
                'external_system' => 'pulseway',
                'external_asset_id' => 'pw_' . strtolower(str_replace('-', '_', $d['hostname'])),
                'external_org_id' => null,
                'external_site_id' => $d['site'],
                'external_group_id' => $d['group'],
                'display_name' => $d['hostname'],
                'platform' => $d['os'],
                'status' => $d['status'],
                'last_seen_at' => $seededAt,
                'raw_snapshot_json' => json_encode(array_merge($d, ['ip_address' => $d['ip']])),
                'snapshot_updated_at' => $seededAt,
                'created_at' => $seededAt,
                'updated_at' => $seededAt,
            ]);
            $deviceIds[] = (int)$wpdb->insert_id;
            $this->registryAdd($seedRunId, $devTable, (string)$wpdb->insert_id);
        }

        // --- Org Mappings (Pulseway org → PET customer) ---
        $rpmCustId = (int)$wpdb->get_var("SELECT id FROM {$wpdb->prefix}pet_customers WHERE name LIKE '%RPM%' LIMIT 1");
        $acmeCustId = (int)$wpdb->get_var("SELECT id FROM {$wpdb->prefix}pet_customers WHERE name LIKE '%Acme%' LIMIT 1");
        $nexusCustId = (int)$wpdb->get_var("SELECT id FROM {$wpdb->prefix}pet_customers WHERE name LIKE '%Nexus%' LIMIT 1");

        $orgMaps = [
            ['pulseway_org' => 'RPM Resources',    'customer_id' => $rpmCustId],
            ['pulseway_org' => 'Acme Manufacturing', 'customer_id' => $acmeCustId],
            ['pulseway_org' => 'Nexus Labs',        'customer_id' => $nexusCustId],
        ];
        foreach ($orgMaps as $om) {
            if ($om['customer_id'] > 0) {
                $wpdb->insert($orgTable, [
                    'integration_id' => $integrationId,
                    'pulseway_org_id' => strtolower(str_replace(' ', '_', $om['pulseway_org'])),
                    'pet_customer_id' => $om['customer_id'],
                    'is_active' => 1,
                    'created_at' => $seededAt,
                    'updated_at' => $seededAt,
                ]);
                $this->registryAdd($seedRunId, $orgTable, (string)$wpdb->insert_id);
            }
        }

        // --- Ticket Rules (schema: rule_name, not name) ---
        $wpdb->insert($rulesTable, [
            'integration_id' => $integrationId,
            'rule_name' => 'Critical Server Alerts',
            'match_severity' => 'critical',
            'match_category' => null,
            'output_ticket_kind' => 'incident',
            'output_priority' => 'critical',
            'output_queue_id' => 'support',
            'sort_order' => 1,
            'is_active' => 1,
            'created_at' => $seededAt,
            'updated_at' => $seededAt,
        ]);
        $this->registryAdd($seedRunId, $rulesTable, (string)$wpdb->insert_id);
        $wpdb->insert($rulesTable, [
            'integration_id' => $integrationId,
            'rule_name' => 'Network Warnings',
            'match_severity' => 'elevated',
            'match_category' => 'network',
            'output_ticket_kind' => 'alert',
            'output_priority' => 'high',
            'output_queue_id' => 'support',
            'sort_order' => 2,
            'is_active' => 1,
            'created_at' => $seededAt,
            'updated_at' => $seededAt,
        ]);
        $this->registryAdd($seedRunId, $rulesTable, (string)$wpdb->insert_id);

        // --- Notifications (schema: pet_external_notifications — external_notification_id, message, device_external_id) ---
        $notifications = [
            ['title' => 'CPU usage above 95% on DC-SVR-01',         'severity' => 'critical', 'category' => 'performance', 'device_idx' => 0, 'routing' => 'routed'],
            ['title' => 'Disk space below 5% on DC-SVR-02',         'severity' => 'critical', 'category' => 'storage',     'device_idx' => 1, 'routing' => 'routed'],
            ['title' => 'Firewall policy update failed on FW-EDGE-01', 'severity' => 'elevated', 'category' => 'network',  'device_idx' => 2, 'routing' => 'routed'],
            ['title' => 'Offline: WS-MKT-PC03 not responding',      'severity' => 'elevated', 'category' => 'availability', 'device_idx' => 3, 'routing' => 'routed'],
            ['title' => 'SSL certificate expiring in 7 days on APP-WEB-01', 'severity' => 'low', 'category' => 'security', 'device_idx' => 4, 'routing' => 'pending'],
            ['title' => 'Windows Update available on DC-SVR-01',     'severity' => 'informational', 'category' => 'patch',  'device_idx' => 0, 'routing' => 'skipped'],
            ['title' => 'Backup job completed with warnings',       'severity' => 'low',     'category' => 'backup',       'device_idx' => 1, 'routing' => 'pending'],
            ['title' => 'High memory usage on APP-WEB-01',          'severity' => 'elevated', 'category' => 'performance', 'device_idx' => 4, 'routing' => 'pending'],
        ];
        $notifIds = [];
        foreach ($notifications as $i => $n) {
            $ts = (new \DateTimeImmutable())->modify('-' . (count($notifications) - $i) . ' hours')->format('Y-m-d H:i:s');
            $deviceExternalId = 'pw_' . strtolower(str_replace('-', '_', $devices[$n['device_idx']]['hostname']));
            $wpdb->insert($notifTable, [
                'integration_id' => $integrationId,
                'external_system' => 'pulseway',
                'external_notification_id' => 'pw_notif_' . ($i + 1001),
                'title' => $n['title'],
                'message' => 'Alert details for: ' . $n['title'],
                'severity' => $n['severity'],
                'category' => $n['category'],
                'device_external_id' => $deviceExternalId,
                'routing_status' => $n['routing'],
                'dedupe_key' => 'demo_dedupe_' . ($i + 1001),
                'occurred_at' => $ts,
                'received_at' => $ts,
                'created_at' => $ts,
            ]);
            $notifIds[] = (int)$wpdb->insert_id;
            $this->registryAdd($seedRunId, $notifTable, (string)$wpdb->insert_id);
        }

        // --- Pulseway-sourced tickets (via CreateTicketHandler so all projectors fire) ---
        $c = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
        $createTicket = $c->get(\Pet\Application\Support\Command\CreateTicketHandler::class);

        $pulsewayTickets = [
            ['cust' => $rpmCustId,   'subject' => 'CRITICAL: CPU usage above 95% on DC-SVR-01',         'pri' => 'critical', 'notif_idx' => 0],
            ['cust' => $rpmCustId,   'subject' => 'CRITICAL: Disk space below 5% on DC-SVR-02',         'pri' => 'critical', 'notif_idx' => 1],
            ['cust' => $rpmCustId,   'subject' => 'ALERT: Firewall policy update failed on FW-EDGE-01', 'pri' => 'high',     'notif_idx' => 2],
            ['cust' => $acmeCustId,  'subject' => 'ALERT: Offline — WS-MKT-PC03 not responding',        'pri' => 'high',     'notif_idx' => 3],
        ];
        $ticketCount = 0;
        foreach ($pulsewayTickets as $pt) {
            if ($pt['cust'] <= 0) continue;
            $createTicket->handle(new \Pet\Application\Support\Command\CreateTicketCommand(
                $pt['cust'],
                null,
                null,
                $pt['subject'],
                'Auto-created from Pulseway RMM notification. Device alert detected by monitoring agent.',
                $pt['pri'],
                [
                    'intake_source' => 'pulseway',
                    'queue_id' => 'support',
                    'category' => 'monitoring',
                ]
            ));
            $ticketCount++;

            // Link notification → ticket via ticket_links for dedupe fidelity
            $newTicketId = (int)$wpdb->get_var("SELECT id FROM {$wpdb->prefix}pet_tickets ORDER BY id DESC LIMIT 1");
            if ($newTicketId && isset($notifIds[$pt['notif_idx']])) {
                $linksTable = $wpdb->prefix . 'pet_ticket_links';
                if ($wpdb->get_var("SHOW TABLES LIKE '$linksTable'") === $linksTable) {
                    $wpdb->insert($linksTable, [
                        'ticket_id' => $newTicketId,
                        'link_type' => 'external',
                        'linked_id' => 'demo_dedupe_' . ($pt['notif_idx'] + 1001),
                        'created_at' => $seededAt,
                    ]);
                    $this->registryAdd($seedRunId, $linksTable, (string)$wpdb->insert_id);
                }
            }
        }

        return [
            'integration' => 1,
            'devices' => count($deviceIds),
            'notifications' => count($notifIds),
            'org_mappings' => count(array_filter($orgMaps, fn($m) => $m['customer_id'] > 0)),
            'ticket_rules' => 2,
            'pulseway_tickets' => $ticketCount,
        ];
    }

    private function seedEventBackboneExpectations(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        return ['events' => 'ok'];
    }

    /**
     * Seed feed events with real entity IDs so the health-history endpoint
     * can detect "was_red" / "was_amber" for recovery dots on completed items.
     */
    private function seedHealthHistory(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        $wpdb = $this->wpdb;
        $feedTable = $wpdb->prefix . 'pet_feed_events';
        $ticketsTable = $wpdb->prefix . 'pet_tickets';
        $projectsTable = $wpdb->prefix . 'pet_projects';

        // Resolved / closed tickets that should show recovery dots
        $closedTickets = $wpdb->get_col("SELECT id FROM $ticketsTable WHERE status IN ('resolved','closed') ORDER BY id ASC LIMIT 3");
        // Active tickets that had SLA issues (for amber history on open items)
        $openTickets = $wpdb->get_col("SELECT id FROM $ticketsTable WHERE status NOT IN ('resolved','closed') ORDER BY id ASC LIMIT 5");
        // Projects
        $projIds = $wpdb->get_col("SELECT id FROM $projectsTable WHERE source_quote_id IS NOT NULL ORDER BY id ASC LIMIT 3");

        $count = 0;

        // Closed tickets that were previously breached or warned
        foreach ($closedTickets as $i => $tid) {
            // First closed ticket: was both red and amber
            if ($i === 0) {
                $wpdb->insert($feedTable, [
                    'id' => $this->uuid(),
                    'event_type' => 'sla_breached',
                    'source_engine' => 'support',
                    'source_entity_id' => (string)$tid,
                    'classification' => 'critical',
                    'title' => 'SLA Breached (historical)',
                    'summary' => 'Ticket was breached before resolution',
                    'metadata_json' => json_encode(['seed' => true, 'uhb_history' => true]),
                    'audience_scope' => 'global',
                    'audience_reference_id' => null,
                    'pinned_flag' => 0,
                    'expires_at' => null,
                    'created_at' => $seededAt,
                ]);
                $this->registryAdd($seedRunId, $feedTable, (string)$this->wpdb->insert_id);
                $count++;
            }
            // All closed tickets: were amber at some point
            $wpdb->insert($feedTable, [
                'id' => $this->uuid(),
                'event_type' => 'sla_warning',
                'source_engine' => 'support',
                'source_entity_id' => (string)$tid,
                'classification' => 'critical',
                'title' => 'SLA Warning (historical)',
                'summary' => 'Ticket had SLA warning before resolution',
                'metadata_json' => json_encode(['seed' => true, 'uhb_history' => true]),
                'audience_scope' => 'global',
                'audience_reference_id' => null,
                'pinned_flag' => 0,
                'expires_at' => null,
                'created_at' => $seededAt,
            ]);
            $this->registryAdd($seedRunId, $feedTable, (string)$this->wpdb->insert_id);
            $count++;
        }

        // Some open tickets with breach/warning history
        if (isset($openTickets[3])) {
            $wpdb->insert($feedTable, [
                'id' => $this->uuid(),
                'event_type' => 'sla_breached',
                'source_engine' => 'support',
                'source_entity_id' => (string)$openTickets[3],
                'classification' => 'critical',
                'title' => 'SLA Breached',
                'summary' => 'Resolution SLA breached',
                'metadata_json' => json_encode(['seed' => true, 'uhb_history' => true]),
                'audience_scope' => 'global',
                'audience_reference_id' => null,
                'pinned_flag' => 0,
                'expires_at' => null,
                'created_at' => $seededAt,
            ]);
            $this->registryAdd($seedRunId, $feedTable, (string)$this->wpdb->insert_id);
            $count++;
        }
        if (isset($openTickets[2])) {
            $wpdb->insert($feedTable, [
                'id' => $this->uuid(),
                'event_type' => 'sla_warning',
                'source_engine' => 'support',
                'source_entity_id' => (string)$openTickets[2],
                'classification' => 'critical',
                'title' => 'SLA Warning',
                'summary' => 'Approaching SLA breach',
                'metadata_json' => json_encode(['seed' => true, 'uhb_history' => true]),
                'audience_scope' => 'global',
                'audience_reference_id' => null,
                'pinned_flag' => 0,
                'expires_at' => null,
                'created_at' => $seededAt,
            ]);
            $this->registryAdd($seedRunId, $feedTable, (string)$this->wpdb->insert_id);
            $count++;
        }

        // Projects: Acme project was amber (at risk)
        if (isset($projIds[1])) {
            $wpdb->insert($feedTable, [
                'id' => $this->uuid(),
                'event_type' => 'project.health_amber',
                'source_engine' => 'delivery',
                'source_entity_id' => (string)$projIds[1],
                'classification' => 'critical',
                'title' => 'Project At Risk',
                'summary' => 'Acme project flagged as at risk due to burn rate',
                'metadata_json' => json_encode(['seed' => true, 'uhb_history' => true]),
                'audience_scope' => 'global',
                'audience_reference_id' => null,
                'pinned_flag' => 0,
                'expires_at' => null,
                'created_at' => $seededAt,
            ]);
            $this->registryAdd($seedRunId, $feedTable, (string)$this->wpdb->insert_id);
            $count++;

            $wpdb->insert($feedTable, [
                'id' => $this->uuid(),
                'event_type' => 'project.health_red',
                'source_engine' => 'delivery',
                'source_entity_id' => (string)$projIds[1],
                'classification' => 'critical',
                'title' => 'Project Critical',
                'summary' => 'Acme project went over budget',
                'metadata_json' => json_encode(['seed' => true, 'uhb_history' => true]),
                'audience_scope' => 'global',
                'audience_reference_id' => null,
                'pinned_flag' => 0,
                'expires_at' => null,
                'created_at' => $seededAt,
            ]);
            $this->registryAdd($seedRunId, $feedTable, (string)$this->wpdb->insert_id);
            $count++;
        }

        return ['health_events' => $count];
    }

    private function uuid(): string
    {
        if (function_exists('wp_generate_uuid4')) return wp_generate_uuid4();
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
