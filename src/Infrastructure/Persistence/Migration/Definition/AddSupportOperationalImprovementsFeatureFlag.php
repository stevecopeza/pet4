<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddSupportOperationalImprovementsFeatureFlag implements Migration
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

        $key = 'pet_support_operational_improvements_enabled';
        $exists = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT setting_key FROM $table WHERE setting_key = %s", $key)
        );
        if ($exists) {
            return;
        }

        $this->wpdb->insert($table, [
            'setting_key' => $key,
            'setting_value' => 'false',
            'setting_type' => 'boolean',
            'description' => 'Enable support/helpdesk operational UX improvements',
            'updated_at' => $now,
        ]);
    }

    public function getDescription(): string
    {
        return 'Add support operational improvements feature flag';
    }
}

