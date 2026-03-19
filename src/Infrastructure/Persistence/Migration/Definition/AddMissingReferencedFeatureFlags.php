<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddMissingReferencedFeatureFlags implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table = $this->wpdb->prefix . 'pet_settings';
        $now = date('Y-m-d H:i:s');

        $flags = [
            'pet_helpdesk_enabled' => [
                'value' => 'false',
                'description' => 'Enable the helpdesk / support ticket system',
            ],
            'pet_advisory_enabled' => [
                'value' => 'false',
                'description' => 'Enable advisory signals on work items',
            ],
        ];

        foreach ($flags as $key => $data) {
            $exists = $this->wpdb->get_var(
                $this->wpdb->prepare("SELECT setting_key FROM $table WHERE setting_key = %s", $key)
            );
            if ($exists) {
                continue;
            }
            $this->wpdb->insert($table, [
                'setting_key' => $key,
                'setting_value' => $data['value'],
                'setting_type' => 'boolean',
                'description' => $data['description'],
                'updated_at' => $now,
            ]);
        }
    }

    public function getDescription(): string
    {
        return 'Seed missing feature flags referenced by FeatureFlagService.';
    }
}

