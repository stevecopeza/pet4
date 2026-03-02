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

        $summary['employees'] = $this->seedEmployees($seedRunId, $seedProfile, $recentDate);
        $summary['customers_sites_contacts'] = $this->seedCustomersSitesContacts($seedRunId, $seedProfile, $recentDate);
        $summary['teams'] = $this->seedTeams($seedRunId, $seedProfile, $recentDate);
        $summary['calendar'] = $this->seedCalendar($seedRunId, $seedProfile, $recentDate);
        $summary['capability'] = $this->seedCapability($seedRunId, $seedProfile, $recentDate);
        $summary['leave'] = $this->seedLeave($seedRunId, $seedProfile, $recentDate);
        $summary['catalog'] = $this->seedCatalog($seedRunId, $seedProfile, $recentDate);
        $summary['commercial'] = $this->seedCommercial($seedRunId, $seedProfile, $recentDate);
        $summary['delivery'] = $this->seedDelivery($seedRunId, $seedProfile, $recentDate);
        $summary['support'] = $this->seedSupport($seedRunId, $seedProfile, $recentDate);
        $summary['work'] = $this->seedWorkOrchestration($seedRunId, $seedProfile, $recentDate);
        $summary['time'] = $this->seedTimeEntries($seedRunId, $seedProfile, $recentDate);
        $summary['knowledge'] = $this->seedKnowledge($seedRunId, $seedProfile, $recentDate);
        $summary['feed'] = $this->seedFeed($seedRunId, $seedProfile, $recentDate);
        $summary['billing'] = $this->seedBilling($seedRunId, $seedProfile, $recentDate);
        $summary['event_backbone'] = $this->seedEventBackboneExpectations($seedRunId, $seedProfile, $recentDate);

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
        $quoteIds = $this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $quotes WHERE title IN (%s,%s,%s,%s)", [
            'Q1 Website Implementation & Advisory',
            'Q2 Website Implementation',
            'Q3 Advisory Retainer',
            'Q4 Catalog Items'
        ]));
        foreach ($quoteIds as $qid) {
            $this->registryAdd($seedRunId, $quotes, (string)$qid);
            foreach ($this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $qc WHERE quote_id = %d", [(int)$qid])) as $id) $this->registryAdd($seedRunId, $qc, (string)$id);
            foreach ($this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $qm WHERE quote_id = %d", [(int)$qid])) as $id) $this->registryAdd($seedRunId, $qm, (string)$id);
            foreach ($this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $qci WHERE quote_id = %d", [(int)$qid])) as $id) $this->registryAdd($seedRunId, $qci, (string)$id);
            foreach ($this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $qrs WHERE quote_id = %d", [(int)$qid])) as $id) $this->registryAdd($seedRunId, $qrs, (string)$id);
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
        $cust = $this->wpdb->prefix . 'pet_customers';
        $rpmId = (int)$this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $cust WHERE name=%s", 'RPM Resources (Pty) Ltd'));
        if ($rpmId) {
            $tickets = $this->wpdb->prefix . 'pet_tickets';
            $clocks = $this->wpdb->prefix . 'pet_sla_clock_state';
            $tIds = $this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $tickets WHERE customer_id = %d AND opened_at = %s", [$rpmId, $seededAt]));
            foreach ($tIds as $tid) {
                $this->registryAdd($seedRunId, $tickets, (string)$tid);
                foreach ($this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $clocks WHERE ticket_id = %d", [(int)$tid])) as $id) $this->registryAdd($seedRunId, $clocks, (string)$id);
            }
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

    private function seedEmployees(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        $t = $this->wpdb->prefix . 'pet_employees';
        $existing = (int)$this->wpdb->get_var("SELECT COUNT(*) FROM $t");
        if ($existing >= 6) return ['count' => $existing, 'skipped' => true];
        $rows = [
            ['first_name' => 'Steve', 'last_name' => 'Admin', 'email' => 'steve@example.com'],
            ['first_name' => 'Mia', 'last_name' => 'Manager', 'email' => 'mia@example.com'],
            ['first_name' => 'Liam', 'last_name' => 'Lead Tech', 'email' => 'liam@example.com'],
            ['first_name' => 'Ava', 'last_name' => 'Consultant', 'email' => 'ava@example.com'],
            ['first_name' => 'Noah', 'last_name' => 'Support', 'email' => 'noah@example.com'],
            ['first_name' => 'Zoe', 'last_name' => 'Finance', 'email' => 'zoe@example.com'],
        ];
        foreach ($rows as $r) {
            $this->wpdb->insert($t, [
                'wp_user_id' => 0,
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
        $this->wpdb->insert($c, ['name' => 'RPM Resources (Pty) Ltd', 'contact_email' => 'info@rpm.example', 'created_at' => $seededAt]);
        $rpmId = (int)$this->wpdb->insert_id;
        $this->registryAdd($seedRunId, $c, (string)$rpmId);
        $this->wpdb->insert($c, ['name' => 'Acme Manufacturing SA (Pty) Ltd', 'contact_email' => 'info@acme.example', 'created_at' => $seededAt]);
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
        return ['customers' => 2, 'sites' => 3, 'contacts' => 4];
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
                'Project for Quote #' . $q1Id,
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

        return ['quotes' => 4];
    }

    private function seedDelivery(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        global $wpdb;
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
        global $wpdb;
        $projectsTable = $wpdb->prefix . 'pet_projects';
        $ticketsTable = $wpdb->prefix . 'pet_tickets';
        $clockTable = $wpdb->prefix . 'pet_sla_clock_state';
        $projectRow = $wpdb->get_row("SELECT * FROM $projectsTable WHERE source_quote_id IS NOT NULL ORDER BY id ASC LIMIT 1");
        if (!$projectRow) {
            return ['tickets' => 0, 'sla' => 'missing_project'];
        }
        $customerId = (int)$projectRow->customer_id;
        $calendar = $calRepo->findDefault();
        if (!$calendar) {
            $calendar = new \Pet\Domain\Calendar\Entity\Calendar('Default', 'UTC', [], [], true);
        }
        $sla = new \Pet\Domain\Sla\Entity\SlaDefinition('Standard', $calendar, 240, 1440, [
            new \Pet\Domain\Sla\Entity\EscalationRule(75, 'warn'),
            new \Pet\Domain\Sla\Entity\EscalationRule(100, 'breach'),
        ], 'published', 1);
        $slaRepo->save($sla);
        $snapshot = $sla->createSnapshot((int)$projectRow->id);
        $snapshotId = $slaRepo->saveSnapshot($snapshot);
        $subjects = [
            'Login issue',
            'Email not syncing',
            'Server alert',
            'VPN access',
            'Printer offline',
            'New user setup',
            'Policy question'
        ];
        foreach ($subjects as $s) {
            $createTicket->handle(new \Pet\Application\Support\Command\CreateTicketCommand(
                $customerId,
                null,
                null,
                $s,
                'Auto-generated demo ticket',
                'medium',
                []
            ));
        }
        $now = new \DateTimeImmutable();
        $responseDue = $now->modify('+' . $snapshot->responseTargetMinutes() . ' minutes')->format('Y-m-d H:i:s');
        $resolutionDue = $now->modify('+' . $snapshot->resolutionTargetMinutes() . ' minutes')->format('Y-m-d H:i:s');
        $recentTickets = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM $ticketsTable WHERE customer_id = %d ORDER BY id DESC LIMIT 7",
            $customerId
        ));
        foreach ($recentTickets as $row) {
            $wpdb->update($ticketsTable, [
                'sla_snapshot_id' => $snapshotId,
                'response_due_at' => $responseDue,
                'resolution_due_at' => $resolutionDue,
                'status' => 'open',
                'opened_at' => $seededAt
            ], ['id' => (int)$row->id]);
            $wpdb->insert($clockTable, [
                'ticket_id' => (int)$row->id,
                'last_event_dispatched' => 'none',
                'last_evaluated_at' => null,
                'sla_version_id' => $snapshot->slaVersionAtBinding(),
                'paused_flag' => 0,
                'escalation_stage' => 0
            ]);
            $this->registryAdd($seedRunId, $ticketsTable, (string)$row->id);
            $this->registryAdd($seedRunId, $clockTable, (string)$this->wpdb->insert_id);
        }
        $ticketIds = array_map(fn($r) => (int)$r->id, $recentTickets);
        if (!empty($ticketIds)) {
            $criticalId = $ticketIds[0];
            $closedId = $ticketIds[1] ?? null;
            $pendingId = $ticketIds[2] ?? null;
            $resolvedId = $ticketIds[3] ?? null;
            $pausedId = $ticketIds[2] ?? null;
            $nearBreachId = $ticketIds[3] ?? null;
            $breachedId = $ticketIds[4] ?? null;

            // Critical open ticket
            $wpdb->update($ticketsTable, ['priority' => 'critical', 'status' => 'open'], ['id' => $criticalId]);

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

            if ($pausedId) {
                $wpdb->update($clockTable, ['paused_flag' => 1], ['ticket_id' => $pausedId]);
            }
            if ($nearBreachId) {
                $wpdb->update($clockTable, ['escalation_stage' => 1], ['ticket_id' => $nearBreachId]);
            }
            if ($breachedId) {
                $wpdb->update($clockTable, ['breach_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')], ['ticket_id' => $breachedId]);
            }
        }
        $ticketsCount = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $ticketsTable WHERE customer_id = %d", $customerId));
        return ['tickets' => $ticketsCount, 'sla' => 'ok'];
    }

    private function seedWorkOrchestration(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        $c = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
        /** @var \Pet\Infrastructure\Persistence\Repository\SqlWorkItemRepository $workRepo */
        $workRepo = $c->get(\Pet\Infrastructure\Persistence\Repository\SqlWorkItemRepository::class);
        /** @var \Pet\Infrastructure\Persistence\Repository\SqlDepartmentQueueRepository $queueRepo */
        $queueRepo = $c->get(\Pet\Infrastructure\Persistence\Repository\SqlDepartmentQueueRepository::class);
        global $wpdb;
        $tickets = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}pet_tickets ORDER BY id DESC LIMIT 3");
        $now = new \DateTimeImmutable();
        foreach ($tickets as $t) {
            $id = $this->uuid();
            $item = \Pet\Domain\Work\Entity\WorkItem::create($id, 'ticket', (string)$t->id, 'support', 80.0, 'active', $now);
            $workRepo->save($item);
            $queueRepo->save(\Pet\Domain\Work\Entity\DepartmentQueue::enter($this->uuid(), 'support', $id));
            $this->registryAdd($seedRunId, $wpdb->prefix . 'pet_work_items', $id);
            $this->registryAdd($seedRunId, $wpdb->prefix . 'pet_department_queues', $id);
        }
        $wiTable = $wpdb->prefix . 'pet_work_items';
        $dqTable = $wpdb->prefix . 'pet_department_queues';
        $oneItem = $wpdb->get_row("SELECT id FROM $wiTable ORDER BY created_at DESC LIMIT 1");
        if ($oneItem) {
            $wpdb->update($wiTable, ['escalation_level' => 1], ['id' => $oneItem->id]);
        }
        $oneQueue = $wpdb->get_row("SELECT id, entered_queue_at FROM $dqTable ORDER BY entered_queue_at DESC LIMIT 1");
        if ($oneQueue) {
            $picked = (new \DateTimeImmutable($oneQueue->entered_queue_at))->modify('+30 minutes')->format('Y-m-d H:i:s');
            $wpdb->update($dqTable, ['picked_up_at' => $picked], ['id' => $oneQueue->id]);
        }
        $items = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pet_work_items");
        $queues = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pet_department_queues");
        return ['work_items' => $items, 'queues' => $queues];
    }

    private function seedTimeEntries(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        $c = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
        /** @var \Pet\Application\Time\Command\LogTimeHandler $log */
        $log = $c->get(\Pet\Application\Time\Command\LogTimeHandler::class);
        /** @var \Pet\Application\Time\Command\SubmitTimeEntryHandler $submit */
        $submit = $c->get(\Pet\Application\Time\Command\SubmitTimeEntryHandler::class);
        global $wpdb;
        $employeesTable = $wpdb->prefix . 'pet_employees';
        $ticketsTable = $wpdb->prefix . 'pet_tickets';
        $entriesTable = $wpdb->prefix . 'pet_time_entries';
        $empId = (int)$wpdb->get_var("SELECT id FROM $employeesTable ORDER BY id ASC LIMIT 1");
        $ticketId = (int)$wpdb->get_var("SELECT id FROM $ticketsTable ORDER BY id ASC LIMIT 1");
        $startBase = new \DateTimeImmutable('today 09:00');
        for ($i = 0; $i < 20; $i++) {
            $start = $startBase->modify('+' . ($i % 5) . ' days');
            $end = $start->modify('+90 minutes');
            if ($ticketId) {
                $log->handle(new \Pet\Application\Time\Command\LogTimeCommand(
                    $empId,
                    $ticketId,
                    $start,
                    $end,
                    $i % 2 === 0,
                    'Demo work ' . ($i + 1),
                    []
                ));
            }
        }
        $rows = $wpdb->get_results("SELECT id FROM $entriesTable ORDER BY id DESC LIMIT 20");
        $ids = array_map(fn($r) => (int)$r->id, $rows);
        foreach ($ids as $id) {
            $cols = $wpdb->get_col("DESCRIBE $entriesTable", 0);
            if (in_array('malleable_data', $cols, true)) {
                $row = $wpdb->get_row($wpdb->prepare("SELECT ticket_id FROM $entriesTable WHERE id = %d", $id));
                $payload = ['seed_run_id' => $seedRunId];
                if ($row && (int)$row->ticket_id) {
                    $payload['ticket_id'] = (int)$row->ticket_id;
                }
                $wpdb->update(
                    $entriesTable,
                    ['malleable_data' => json_encode($payload)],
                    ['id' => $id],
                    ['%s'],
                    ['%d']
                );
            }
            $this->registryAdd($seedRunId, $entriesTable, (string)$id);
        }
        $toSubmit = array_slice($ids, 0, 8);
        foreach ($toSubmit as $id) {
            $submit->handle(new \Pet\Application\Time\Command\SubmitTimeEntryCommand($id));
        }
        $toLock = array_slice($ids, 8, 2);
        foreach ($toLock as $id) {
            $wpdb->update(
                $entriesTable,
                ['status' => 'locked'],
                ['id' => $id],
                ['%s'],
                ['%d']
            );
        }
        $oneTicket = $wpdb->get_row("SELECT id FROM {$wpdb->prefix}pet_tickets ORDER BY id DESC LIMIT 1");
        $oneEntry = $wpdb->get_row("SELECT id FROM $entriesTable ORDER BY id ASC LIMIT 1");
        $count = (int)$wpdb->get_var("SELECT COUNT(*) FROM $entriesTable");
        return ['time_entries' => $count];
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
        // Feed events
        $events = [
            ['commercial', 'quote', 'accepted', 'operational', 'Quote Accepted', 'Q1 accepted and contract created', 'global', null],
            ['delivery', 'project', 'created', 'operational', 'Project Created', 'Project initialized from accepted quote', 'department', 'delivery'],
            ['support', 'ticket', 'opened', 'operational', 'Ticket Opened', 'New ticket logged', 'department', 'support'],
            ['work', 'work_item', 'queued', 'informational', 'Item Queued', 'Work item entered department queue', 'department', 'support'],
            ['finance', 'billing_export', 'queued', 'operational', 'Export Queued', 'Billing export queued for QB', 'global', null],
            ['support', 'ticket', 'breached', 'critical', 'SLA Breach', 'Ticket breached SLA threshold', 'department', 'support'],
            ['work', 'work_item', 'completed', 'informational', 'Item Completed', 'Work item completed by delivery', 'department', 'delivery'],
            ['identity', 'employee', 'onboarded', 'informational', 'Employee Onboarded', 'New team member joined', 'global', null],
            ['advisory', 'report', 'published', 'strategic', 'Advisory Published', 'Governance advisory report released', 'global', null],
            ['time', 'time_entry', 'approved', 'operational', 'Time Approved', 'Manager approved submitted time', 'department', 'delivery'],
        ];
        foreach ($events as [$engine, $entity, $etype, $class, $title, $summary, $aud, $audRef]) {
            $eid = $this->uuid();
            $feedRepo->save(\Pet\Domain\Feed\Entity\FeedEvent::create(
                $eid,
                $etype,
                $engine,
                $entity,
                $class,
                $title,
                $summary,
                [
                    'seed_run_id' => $seedRunId,
                    'seed_profile' => $seedProfile,
                    'seeded_at' => $seededAt,
                ],
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

    private function seedBilling(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        global $wpdb;
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
            "SELECT COUNT(*) FROM {$wpdb->prefix}pet_external_mappings WHERE system = %s AND entity_type = %s AND pet_entity_id = %d",
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

    private function seedEventBackboneExpectations(string $seedRunId, string $seedProfile, string $seededAt): array
    {
        return ['events' => 'ok'];
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
