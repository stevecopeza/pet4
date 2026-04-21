<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddQuoteApprovalSettings implements Migration
{
    public function up(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'pet_settings';
        $now   = date('Y-m-d H:i:s');

        $settings = [
            'pet_quote_approval_value_threshold' => [
                'value'       => '0',
                'type'        => 'number',
                'description' => 'Quote total value at or above which manager approval is required before sending (0 = disabled)',
            ],
            'pet_quote_approval_discount_threshold_pct' => [
                'value'       => '0',
                'type'        => 'number',
                'description' => 'Maximum line-item discount % before manager approval is required (0 = disabled)',
            ],
        ];

        foreach ($settings as $key => $setting) {
            $existing = $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM $table WHERE setting_key = %s", $key)
            );

            if (!$existing) {
                $wpdb->insert($table, [
                    'setting_key'   => $key,
                    'setting_value' => $setting['value'],
                    'setting_type'  => $setting['type'],
                    'description'   => $setting['description'],
                    'updated_at'    => $now,
                ], ['%s', '%s', '%s', '%s', '%s']);
            }
        }
    }

    public function down(): void {}

    public function getDescription(): string
    {
        return 'Add quote approval threshold settings (value and discount %)';
    }
}
