<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\System;

use Pet\Application\System\Service\FeatureFlagService;
use Pet\Infrastructure\Persistence\Migration\Definition\AddDashboardsFeatureFlag;
use Pet\Infrastructure\Persistence\Migration\Definition\AddStaffTimeCaptureFeatureFlag;
use Pet\Infrastructure\Persistence\Migration\Definition\AddSupportOperationalImprovementsFeatureFlag;
use Pet\Infrastructure\Persistence\Migration\Definition\AddMissingReferencedFeatureFlags;
use Pet\Infrastructure\Persistence\Repository\SqlSettingRepository;
use Pet\Tests\Integration\Support\WpdbStub;
use PHPUnit\Framework\TestCase;

final class FeatureFlagSeedingTest extends TestCase
{
    private WpdbStub $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb = new WpdbStub();
        $this->wpdb->query(
            "CREATE TABLE {$this->wpdb->prefix}pet_settings (
                setting_key TEXT PRIMARY KEY,
                setting_value TEXT,
                setting_type TEXT,
                description TEXT,
                updated_at TEXT
            )"
        );
    }

    public function testMigrationSeedsMissingReferencedFlagsWithoutOverwritingExistingValues(): void
    {
        $this->wpdb->insert($this->wpdb->prefix . 'pet_settings', [
            'setting_key' => 'pet_helpdesk_enabled',
            'setting_value' => 'true',
            'setting_type' => 'boolean',
            'description' => 'existing',
            'updated_at' => '2026-01-01 00:00:00',
        ]);

        (new AddMissingReferencedFeatureFlags($this->wpdb))->up();

        $helpdesk = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT setting_value FROM {$this->wpdb->prefix}pet_settings WHERE setting_key = %s",
                'pet_helpdesk_enabled'
            ),
            ARRAY_A
        );
        $this->assertSame('true', $helpdesk['setting_value']);

        $advisory = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT setting_value FROM {$this->wpdb->prefix}pet_settings WHERE setting_key = %s",
                'pet_advisory_enabled'
            ),
            ARRAY_A
        );
        $this->assertNotNull($advisory);
        $this->assertSame('false', $advisory['setting_value']);
    }

    public function testFeatureFlagServiceDefaultsToFalseUntilExplicitlyEnabled(): void
    {
        $settingsRepo = new SqlSettingRepository($this->wpdb);
        $flags = new FeatureFlagService($settingsRepo);

        $this->assertFalse($flags->isHelpdeskEnabled());
        $this->assertFalse($flags->isStaffTimeCaptureEnabled());

        $this->wpdb->insert($this->wpdb->prefix . 'pet_settings', [
            'setting_key' => 'pet_helpdesk_enabled',
            'setting_value' => 'false',
            'setting_type' => 'boolean',
            'description' => 'helpdesk',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        $this->assertFalse($flags->isHelpdeskEnabled());

        $this->wpdb->update($this->wpdb->prefix . 'pet_settings', [
            'setting_value' => 'true',
        ], [
            'setting_key' => 'pet_helpdesk_enabled',
        ]);
        $this->assertTrue($flags->isHelpdeskEnabled());

        $this->wpdb->insert($this->wpdb->prefix . 'pet_settings', [
            'setting_key' => 'pet_staff_time_capture_enabled',
            'setting_value' => 'false',
            'setting_type' => 'boolean',
            'description' => 'staff time capture',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        $this->assertFalse($flags->isStaffTimeCaptureEnabled());

        $this->wpdb->update($this->wpdb->prefix . 'pet_settings', [
            'setting_value' => 'true',
        ], [
            'setting_key' => 'pet_staff_time_capture_enabled',
        ]);
        $this->assertTrue($flags->isStaffTimeCaptureEnabled());
    }

    public function testDashboardsFeatureFlagMigrationSeedsDefaultFalse(): void
    {
        (new AddDashboardsFeatureFlag($this->wpdb))->up();

        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT setting_value FROM {$this->wpdb->prefix}pet_settings WHERE setting_key = %s",
                'pet_dashboards_enabled'
            ),
            ARRAY_A
        );

        $this->assertNotNull($row);
        $this->assertSame('false', $row['setting_value']);

        $settingsRepo = new SqlSettingRepository($this->wpdb);
        $flags = new FeatureFlagService($settingsRepo);
        $this->assertFalse($flags->isDashboardsEnabled());
    }

    public function testSupportOperationalImprovementsFlagMigrationSeedsDefaultFalse(): void
    {
        (new AddSupportOperationalImprovementsFeatureFlag($this->wpdb))->up();

        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT setting_value FROM {$this->wpdb->prefix}pet_settings WHERE setting_key = %s",
                'pet_support_operational_improvements_enabled'
            ),
            ARRAY_A
        );

        $this->assertNotNull($row);
        $this->assertSame('false', $row['setting_value']);

        $settingsRepo = new SqlSettingRepository($this->wpdb);
        $flags = new FeatureFlagService($settingsRepo);
        $this->assertFalse($flags->isSupportOperationalImprovementsEnabled());
    }

    public function testStaffTimeCaptureFlagMigrationSeedsDefaultFalse(): void
    {
        (new AddStaffTimeCaptureFeatureFlag($this->wpdb))->up();

        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT setting_value FROM {$this->wpdb->prefix}pet_settings WHERE setting_key = %s",
                'pet_staff_time_capture_enabled'
            ),
            ARRAY_A
        );

        $this->assertNotNull($row);
        $this->assertSame('false', $row['setting_value']);

        $settingsRepo = new SqlSettingRepository($this->wpdb);
        $flags = new FeatureFlagService($settingsRepo);
        $this->assertFalse($flags->isStaffTimeCaptureEnabled());
    }
}
