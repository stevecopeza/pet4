<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\System;

use Pet\Application\System\Service\DemoPurgeService;
use Pet\Application\System\Service\DemoSeedService;
use Pet\Tests\Integration\Support\WpdbStub;
use PHPUnit\Framework\TestCase;

final class DemoSeedPurgeDeterminismTest extends TestCase
{
    private WpdbStub $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb = new WpdbStub();
    }

    public function testCustomersSitesContactsSeedIsIdempotentAcrossRepeatedRuns(): void
    {
        $this->wpdb->query(
            "CREATE TABLE {$this->wpdb->prefix}pet_customers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                contact_email TEXT,
                malleable_data TEXT,
                created_at TEXT
            )"
        );
        $this->wpdb->query(
            "CREATE TABLE {$this->wpdb->prefix}pet_sites (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_id INTEGER,
                name TEXT,
                created_at TEXT
            )"
        );
        $this->wpdb->query(
            "CREATE TABLE {$this->wpdb->prefix}pet_contacts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_id INTEGER,
                site_id INTEGER,
                first_name TEXT,
                last_name TEXT,
                email TEXT,
                created_at TEXT
            )"
        );
        $this->wpdb->query(
            "CREATE TABLE {$this->wpdb->prefix}pet_contact_affiliations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                contact_id INTEGER,
                customer_id INTEGER,
                site_id INTEGER,
                role TEXT,
                is_primary INTEGER,
                created_at TEXT
            )"
        );
        $this->wpdb->query(
            "CREATE TABLE {$this->wpdb->prefix}pet_demo_seed_registry (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                seed_run_id TEXT,
                table_name TEXT,
                row_id TEXT,
                created_at TEXT,
                purge_status TEXT DEFAULT 'ACTIVE',
                purged_at TEXT
            )"
        );

        $seedService = new DemoSeedService($this->wpdb);
        $method = new \ReflectionMethod($seedService, 'seedCustomersSitesContacts');
        $method->setAccessible(true);

        $seedRunId = 'seed-run-a';
        $seededAt = '2026-03-23 19:00:00';
        $method->invoke($seedService, $seedRunId, 'demo_full', $seededAt);
        $firstCustomers = (int)$this->wpdb->get_var("SELECT COUNT(*) FROM {$this->wpdb->prefix}pet_customers");
        $firstSites = (int)$this->wpdb->get_var("SELECT COUNT(*) FROM {$this->wpdb->prefix}pet_sites");
        $firstContacts = (int)$this->wpdb->get_var("SELECT COUNT(*) FROM {$this->wpdb->prefix}pet_contacts");
        $firstAffiliations = (int)$this->wpdb->get_var("SELECT COUNT(*) FROM {$this->wpdb->prefix}pet_contact_affiliations");

        $method->invoke($seedService, 'seed-run-b', 'demo_full', $seededAt);
        $secondCustomers = (int)$this->wpdb->get_var("SELECT COUNT(*) FROM {$this->wpdb->prefix}pet_customers");
        $secondSites = (int)$this->wpdb->get_var("SELECT COUNT(*) FROM {$this->wpdb->prefix}pet_sites");
        $secondContacts = (int)$this->wpdb->get_var("SELECT COUNT(*) FROM {$this->wpdb->prefix}pet_contacts");
        $secondAffiliations = (int)$this->wpdb->get_var("SELECT COUNT(*) FROM {$this->wpdb->prefix}pet_contact_affiliations");

        $this->assertSame($firstCustomers, $secondCustomers);
        $this->assertSame($firstSites, $secondSites);
        $this->assertSame($firstContacts, $secondContacts);
        $this->assertSame($firstAffiliations, $secondAffiliations);

        $duplicateCustomers = (int)$this->wpdb->get_var(
            "SELECT COUNT(*) FROM (
                SELECT name, COUNT(*) AS c
                FROM {$this->wpdb->prefix}pet_customers
                GROUP BY name
                HAVING COUNT(*) > 1
            ) x"
        );
        $this->assertSame(0, $duplicateCustomers);
    }

    public function testStaffSkillAndCertificationUpsertsPreventDuplicatePairsAndCollapseExistingDuplicates(): void
    {
        $this->wpdb->query(
            "CREATE TABLE {$this->wpdb->prefix}pet_person_skills (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER,
                skill_id INTEGER,
                review_cycle_id INTEGER,
                self_rating INTEGER,
                manager_rating INTEGER,
                effective_date TEXT,
                created_at TEXT
            )"
        );
        $this->wpdb->query(
            "CREATE TABLE {$this->wpdb->prefix}pet_person_certifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER,
                certification_id INTEGER,
                obtained_date TEXT,
                expiry_date TEXT,
                evidence_url TEXT,
                status TEXT,
                created_at TEXT
            )"
        );
        $this->wpdb->query(
            "CREATE TABLE {$this->wpdb->prefix}pet_demo_seed_registry (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                seed_run_id TEXT,
                table_name TEXT,
                row_id TEXT,
                created_at TEXT,
                purge_status TEXT DEFAULT 'ACTIVE',
                purged_at TEXT
            )"
        );

        // Seed intentional duplicates first to prove cleanup.
        $this->wpdb->insert($this->wpdb->prefix . 'pet_person_skills', [
            'employee_id' => 11,
            'skill_id' => 4,
            'review_cycle_id' => null,
            'self_rating' => 2,
            'manager_rating' => 2,
            'effective_date' => '2026-03-01',
            'created_at' => '2026-03-01 08:00:00',
        ]);
        $this->wpdb->insert($this->wpdb->prefix . 'pet_person_skills', [
            'employee_id' => 11,
            'skill_id' => 4,
            'review_cycle_id' => null,
            'self_rating' => 3,
            'manager_rating' => 3,
            'effective_date' => '2026-03-02',
            'created_at' => '2026-03-02 08:00:00',
        ]);
        $this->wpdb->insert($this->wpdb->prefix . 'pet_person_certifications', [
            'employee_id' => 12,
            'certification_id' => 1,
            'obtained_date' => '2026-03-01',
            'expiry_date' => null,
            'evidence_url' => null,
            'status' => 'valid',
            'created_at' => '2026-03-01 09:00:00',
        ]);
        $this->wpdb->insert($this->wpdb->prefix . 'pet_person_certifications', [
            'employee_id' => 12,
            'certification_id' => 1,
            'obtained_date' => '2026-03-02',
            'expiry_date' => null,
            'evidence_url' => null,
            'status' => 'valid',
            'created_at' => '2026-03-02 09:00:00',
        ]);

        $seedService = new DemoSeedService($this->wpdb);
        $skillMethod = new \ReflectionMethod($seedService, 'upsertSeededPersonSkill');
        $skillMethod->setAccessible(true);
        $certMethod = new \ReflectionMethod($seedService, 'upsertSeededPersonCertification');
        $certMethod->setAccessible(true);

        $skillMethod->invoke($seedService, 'seed-run-skills', 11, 4, 4, 5, '2026-03-23 19:00:00');
        $skillMethod->invoke($seedService, 'seed-run-skills', 11, 4, 4, 5, '2026-03-23 19:00:00');
        $certMethod->invoke($seedService, 'seed-run-certs', 12, 1, '2026-03-23', null, null, 'valid', '2026-03-23 19:00:00');
        $certMethod->invoke($seedService, 'seed-run-certs', 12, 1, '2026-03-23', null, null, 'valid', '2026-03-23 19:00:00');

        $skillPairCount = (int)$this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}pet_person_skills WHERE employee_id = 11 AND skill_id = 4"
        );
        $certPairCount = (int)$this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}pet_person_certifications WHERE employee_id = 12 AND certification_id = 1"
        );
        $this->assertSame(1, $skillPairCount);
        $this->assertSame(1, $certPairCount);

        $duplicateSkillPairs = (int)$this->wpdb->get_var(
            "SELECT COUNT(*) FROM (
                SELECT employee_id, skill_id, COUNT(*) AS c
                FROM {$this->wpdb->prefix}pet_person_skills
                GROUP BY employee_id, skill_id
                HAVING COUNT(*) > 1
            ) x"
        );
        $duplicateCertPairs = (int)$this->wpdb->get_var(
            "SELECT COUNT(*) FROM (
                SELECT employee_id, certification_id, COUNT(*) AS c
                FROM {$this->wpdb->prefix}pet_person_certifications
                GROUP BY employee_id, certification_id
                HAVING COUNT(*) > 1
            ) x"
        );
        $this->assertSame(0, $duplicateSkillPairs);
        $this->assertSame(0, $duplicateCertPairs);
    }

    public function testSeedEmployeesCollapsesDuplicateEmailRowsAndRemapsEmployeeReferences(): void
    {
        $employeesTable = $this->wpdb->prefix . 'pet_employees';
        $skillsTable = $this->wpdb->prefix . 'pet_person_skills';
        $registryTable = $this->wpdb->prefix . 'pet_demo_seed_registry';

        $this->wpdb->query(
            "CREATE TABLE $employeesTable (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                wp_user_id INTEGER,
                first_name TEXT,
                last_name TEXT,
                email TEXT,
                status TEXT,
                hire_date TEXT,
                manager_id INTEGER,
                created_at TEXT,
                archived_at TEXT
            )"
        );
        $this->wpdb->query(
            "CREATE TABLE $skillsTable (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER,
                skill_id INTEGER,
                review_cycle_id INTEGER,
                self_rating INTEGER,
                manager_rating INTEGER,
                effective_date TEXT,
                created_at TEXT
            )"
        );
        $this->wpdb->query(
            "CREATE TABLE {$this->wpdb->prefix}pet_person_certifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER,
                certification_id INTEGER,
                obtained_date TEXT,
                expiry_date TEXT,
                evidence_url TEXT,
                status TEXT,
                created_at TEXT
            )"
        );
        $this->wpdb->query(
            "CREATE TABLE $registryTable (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                seed_run_id TEXT,
                table_name TEXT,
                row_id TEXT,
                created_at TEXT,
                purge_status TEXT DEFAULT 'ACTIVE',
                purged_at TEXT
            )"
        );

        // Intentional duplicate email pair where second row is low-fidelity.
        $this->wpdb->insert($employeesTable, [
            'wp_user_id' => 1,
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@cope.zone',
            'status' => 'active',
            'hire_date' => null,
            'manager_id' => null,
            'created_at' => '2026-03-23 21:43:24',
            'archived_at' => null,
        ]);
        $keepId = (int)$this->wpdb->insert_id;
        $this->wpdb->insert($employeesTable, [
            'wp_user_id' => 0,
            'first_name' => '',
            'last_name' => '',
            'email' => 'admin@cope.zone',
            'status' => 'active',
            'hire_date' => null,
            'manager_id' => null,
            'created_at' => '2026-03-23 22:52:37',
            'archived_at' => null,
        ]);
        $duplicateId = (int)$this->wpdb->insert_id;

        // Reference the duplicate row to verify remapping during normalization.
        $this->wpdb->insert($skillsTable, [
            'employee_id' => $duplicateId,
            'skill_id' => 99,
            'review_cycle_id' => null,
            'self_rating' => 1,
            'manager_rating' => 1,
            'effective_date' => '2026-03-23',
            'created_at' => '2026-03-23 22:52:37',
        ]);

        $seedService = new DemoSeedService($this->wpdb);
        $seedEmployees = new \ReflectionMethod($seedService, 'seedEmployees');
        $seedEmployees->setAccessible(true);

        $seedEmployees->invoke($seedService, 'seed-run-a', 'demo_full', '2026-03-24 10:00:00');
        $seedEmployees->invoke($seedService, 'seed-run-b', 'demo_full', '2026-03-24 10:00:00');

        $duplicateEmailCount = (int)$this->wpdb->get_var(
            "SELECT COUNT(*) FROM (
                SELECT email, COUNT(*) AS c
                FROM $employeesTable
                WHERE email IS NOT NULL AND LENGTH(email) > 0
                GROUP BY email
                HAVING COUNT(*) > 1
            ) x"
        );
        $this->assertSame(0, $duplicateEmailCount);

        $this->assertSame(0, (int)$this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM $employeesTable WHERE id = %d",
            $duplicateId
        )));
        $this->assertSame(1, (int)$this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM $employeesTable WHERE id = %d",
            $keepId
        )));
        $this->assertSame(0, (int)$this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM $skillsTable WHERE employee_id = %d",
            $duplicateId
        )));
        $this->assertSame(1, (int)$this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM $skillsTable WHERE employee_id = %d",
            $keepId
        )));
    }

    public function testCapabilityLevelDuplicateNormalizationCollapsesOutOfBandStaffPairs(): void
    {
        $skillsTable = $this->wpdb->prefix . 'pet_person_skills';
        $certsTable = $this->wpdb->prefix . 'pet_person_certifications';

        $this->wpdb->query(
            "CREATE TABLE $skillsTable (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER,
                skill_id INTEGER,
                review_cycle_id INTEGER,
                self_rating INTEGER,
                manager_rating INTEGER,
                effective_date TEXT,
                created_at TEXT
            )"
        );
        $this->wpdb->query(
            "CREATE TABLE $certsTable (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER,
                certification_id INTEGER,
                obtained_date TEXT,
                expiry_date TEXT,
                evidence_url TEXT,
                status TEXT,
                created_at TEXT
            )"
        );

        $this->wpdb->insert($skillsTable, [
            'employee_id' => 3,
            'skill_id' => 5,
            'review_cycle_id' => null,
            'self_rating' => 3,
            'manager_rating' => 4,
            'effective_date' => '2026-03-23',
            'created_at' => '2026-03-23 19:21:10',
        ]);
        $this->wpdb->insert($skillsTable, [
            'employee_id' => 3,
            'skill_id' => 5,
            'review_cycle_id' => null,
            'self_rating' => 0,
            'manager_rating' => 0,
            'effective_date' => '0000-00-00',
            'created_at' => '2026-03-23 22:52:37',
        ]);
        $this->wpdb->insert($certsTable, [
            'employee_id' => 3,
            'certification_id' => 1,
            'obtained_date' => '2026-03-23',
            'expiry_date' => null,
            'evidence_url' => null,
            'status' => 'valid',
            'created_at' => '2026-03-23 19:21:10',
        ]);
        $this->wpdb->insert($certsTable, [
            'employee_id' => 3,
            'certification_id' => 1,
            'obtained_date' => '0000-00-00',
            'expiry_date' => null,
            'evidence_url' => null,
            'status' => 'valid',
            'created_at' => '2026-03-23 22:52:37',
        ]);

        $seedService = new DemoSeedService($this->wpdb);
        $skillsNormalize = new \ReflectionMethod($seedService, 'normalizeDuplicatePersonSkillPairs');
        $skillsNormalize->setAccessible(true);
        $certNormalize = new \ReflectionMethod($seedService, 'normalizeDuplicatePersonCertificationPairs');
        $certNormalize->setAccessible(true);

        $skillsNormalize->invoke($seedService);
        $certNormalize->invoke($seedService);

        $duplicateSkillPairs = (int)$this->wpdb->get_var(
            "SELECT COUNT(*) FROM (
                SELECT employee_id, skill_id, COUNT(*) AS c
                FROM $skillsTable
                GROUP BY employee_id, skill_id
                HAVING COUNT(*) > 1
            ) x"
        );
        $duplicateCertPairs = (int)$this->wpdb->get_var(
            "SELECT COUNT(*) FROM (
                SELECT employee_id, certification_id, COUNT(*) AS c
                FROM $certsTable
                GROUP BY employee_id, certification_id
                HAVING COUNT(*) > 1
            ) x"
        );
        $this->assertSame(0, $duplicateSkillPairs);
        $this->assertSame(0, $duplicateCertPairs);
        $this->assertSame(1, (int)$this->wpdb->get_var("SELECT COUNT(*) FROM $skillsTable WHERE employee_id = 3 AND skill_id = 5"));
        $this->assertSame(1, (int)$this->wpdb->get_var("SELECT COUNT(*) FROM $certsTable WHERE employee_id = 3 AND certification_id = 1"));
    }

    public function testPurgeRemovesStaffJoinRowsAndCleansRegistryForRun(): void
    {
        $skillsTable = $this->wpdb->prefix . 'pet_person_skills';
        $certsTable = $this->wpdb->prefix . 'pet_person_certifications';
        $membersTable = $this->wpdb->prefix . 'pet_team_members';
        $registryTable = $this->wpdb->prefix . 'pet_demo_seed_registry';

        $this->wpdb->query("CREATE TABLE $skillsTable (id INTEGER PRIMARY KEY AUTOINCREMENT, employee_id INTEGER, skill_id INTEGER)");
        $this->wpdb->query("CREATE TABLE $certsTable (id INTEGER PRIMARY KEY AUTOINCREMENT, employee_id INTEGER, certification_id INTEGER)");
        $this->wpdb->query("CREATE TABLE $membersTable (id INTEGER PRIMARY KEY AUTOINCREMENT, team_id INTEGER, employee_id INTEGER, role TEXT, removed_at TEXT)");
        $this->wpdb->query(
            "CREATE TABLE $registryTable (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                seed_run_id TEXT,
                table_name TEXT,
                row_id TEXT,
                created_at TEXT,
                purge_status TEXT DEFAULT 'ACTIVE',
                purged_at TEXT
            )"
        );

        $this->wpdb->insert($skillsTable, ['employee_id' => 11, 'skill_id' => 4]);
        $skillRowId = (int)$this->wpdb->insert_id;
        $this->wpdb->insert($certsTable, ['employee_id' => 12, 'certification_id' => 1]);
        $certRowId = (int)$this->wpdb->insert_id;
        $this->wpdb->insert($membersTable, ['team_id' => 2, 'employee_id' => 11, 'role' => 'lead', 'removed_at' => null]);
        $memberRowId = (int)$this->wpdb->insert_id;

        $seedRunId = 'seed-run-purge';
        foreach ([[$skillsTable, $skillRowId], [$certsTable, $certRowId], [$membersTable, $memberRowId]] as [$tableName, $rowId]) {
            $this->wpdb->insert($registryTable, [
                'seed_run_id' => $seedRunId,
                'table_name' => $tableName,
                'row_id' => (string)$rowId,
                'created_at' => '2026-03-23 19:00:00',
                'purge_status' => 'ACTIVE',
                'purged_at' => null,
            ]);
        }

        $purgeService = new DemoPurgeService($this->wpdb);
        $summary = $purgeService->purgeBySeedRunId($seedRunId);

        $this->assertSame(0, (int)$this->wpdb->get_var("SELECT COUNT(*) FROM $skillsTable"));
        $this->assertSame(0, (int)$this->wpdb->get_var("SELECT COUNT(*) FROM $certsTable"));
        $this->assertSame(0, (int)$this->wpdb->get_var("SELECT COUNT(*) FROM $membersTable"));
        $this->assertSame(0, (int)$this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM $registryTable WHERE seed_run_id = %s",
            $seedRunId
        )));
        $this->assertArrayHasKey('registry_deleted', $summary);
        $this->assertGreaterThan(0, (int)$summary['registry_deleted']);
    }
}

