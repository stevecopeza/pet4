<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddStaffTimeCaptureFeatureFlag implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table = $this->wpdb->prefix . 'pet_settings';
        $exists = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT setting_key FROM $table WHERE setting_key = %s", 'pet_staff_time_capture_enabled')
        );

        if ($exists) {
            return;
        }

        $this->wpdb->insert($table, [
            'setting_key' => 'pet_staff_time_capture_enabled',
            'setting_value' => 'false',
            'setting_type' => 'boolean',
            'description' => 'Enable staff-facing standalone time capture surface and self-scoped APIs',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getDescription(): string
    {
        return 'Add feature flag for staff time capture rollout.';
    }
}
