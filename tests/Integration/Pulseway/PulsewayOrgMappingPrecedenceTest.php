<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Pulseway;

use Pet\Infrastructure\Persistence\Repository\Pulseway\SqlPulsewayIntegrationRepository;
use Pet\Tests\Integration\Support\WpdbStub;
use PHPUnit\Framework\TestCase;

final class PulsewayOrgMappingPrecedenceTest extends TestCase
{
    private WpdbStub $wpdb;
    private SqlPulsewayIntegrationRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb = new WpdbStub();
        $this->repo = new SqlPulsewayIntegrationRepository($this->wpdb);

        $this->wpdb->query(
            "CREATE TABLE {$this->wpdb->prefix}pet_pulseway_org_mappings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                integration_id INTEGER NOT NULL,
                pulseway_org_id TEXT NULL,
                pulseway_site_id TEXT NULL,
                pulseway_group_id TEXT NULL,
                pet_customer_id INTEGER NULL,
                pet_site_id INTEGER NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                archived_at TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )"
        );
    }

    public function testFindOrgMappingEnforcesApprovedPrecedenceShapes(): void
    {
        $table = $this->wpdb->prefix . 'pet_pulseway_org_mappings';
        $integrationId = 1;

        $this->wpdb->insert($table, [
            'integration_id' => $integrationId,
            'pulseway_org_id' => null,
            'pulseway_site_id' => null,
            'pulseway_group_id' => null,
            'pet_customer_id' => 100,
            'pet_site_id' => null,
            'is_active' => 1,
            'archived_at' => null,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);

        $this->wpdb->insert($table, [
            'integration_id' => $integrationId,
            'pulseway_org_id' => 'org1',
            'pulseway_site_id' => null,
            'pulseway_group_id' => null,
            'pet_customer_id' => 200,
            'pet_site_id' => null,
            'is_active' => 1,
            'archived_at' => null,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);

        $this->wpdb->insert($table, [
            'integration_id' => $integrationId,
            'pulseway_org_id' => 'org1',
            'pulseway_site_id' => 'site1',
            'pulseway_group_id' => null,
            'pet_customer_id' => 300,
            'pet_site_id' => 10,
            'is_active' => 1,
            'archived_at' => null,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);

        $this->wpdb->insert($table, [
            'integration_id' => $integrationId,
            'pulseway_org_id' => 'org1',
            'pulseway_site_id' => 'site1',
            'pulseway_group_id' => 'group1',
            'pet_customer_id' => 400,
            'pet_site_id' => 20,
            'is_active' => 1,
            'archived_at' => null,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);

        $exact = $this->repo->findOrgMapping($integrationId, 'org1', 'site1', 'group1');
        $this->assertSame(400, (int) $exact['pet_customer_id']);

        $siteLevel = $this->repo->findOrgMapping($integrationId, 'org1', 'site1', 'missing');
        $this->assertSame(300, (int) $siteLevel['pet_customer_id']);

        $orgLevel = $this->repo->findOrgMapping($integrationId, 'org1', 'missing', 'missing');
        $this->assertSame(200, (int) $orgLevel['pet_customer_id']);

        $global = $this->repo->findOrgMapping($integrationId, 'missing', 'missing', 'missing');
        $this->assertSame(100, (int) $global['pet_customer_id']);
    }

    public function testFindOrgMappingUsesStableTieBreakForGlobalCatchAll(): void
    {
        $table = $this->wpdb->prefix . 'pet_pulseway_org_mappings';
        $integrationId = 1;

        $this->wpdb->insert($table, [
            'integration_id' => $integrationId,
            'pulseway_org_id' => null,
            'pulseway_site_id' => null,
            'pulseway_group_id' => null,
            'pet_customer_id' => 111,
            'pet_site_id' => null,
            'is_active' => 1,
            'archived_at' => null,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);

        $this->wpdb->insert($table, [
            'integration_id' => $integrationId,
            'pulseway_org_id' => null,
            'pulseway_site_id' => null,
            'pulseway_group_id' => null,
            'pet_customer_id' => 222,
            'pet_site_id' => null,
            'is_active' => 1,
            'archived_at' => null,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);

        $global = $this->repo->findOrgMapping($integrationId, null, null, null);
        $this->assertSame(111, (int) $global['pet_customer_id']);
    }
}

