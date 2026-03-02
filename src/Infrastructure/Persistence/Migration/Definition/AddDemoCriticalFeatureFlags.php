<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddDemoCriticalFeatureFlags implements Migration
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
            'pet_escalation_engine_enabled' => [
                'value' => 'false',
                'description' => 'Escalation & Risk Engine (v1)',
            ],
            'pet_helpdesk_shortcode_enabled' => [
                'value' => 'false',
                'description' => 'Helpdesk Overview Shortcode',
            ],
            'pet_advisory_reports_enabled' => [
                'value' => 'false',
                'description' => 'Advisory Layer Reports',
            ],
            'pet_resilience_indicators_enabled' => [
                'value' => 'false',
                'description' => 'People Resilience Indicators',
            ],
        ];

        foreach ($flags as $key => $data) {
            $exists = $this->wpdb->get_var($this->wpdb->prepare("SELECT setting_key FROM $table WHERE setting_key = %s", $key));
            
            if (!$exists) {
                $this->wpdb->insert(
                    $table,
                    [
                        'setting_key' => $key,
                        'setting_value' => $data['value'],
                        'setting_type' => 'boolean',
                        'description' => $data['description'],
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }

    public function getDescription(): string
    {
        return 'Add feature flags for demo-critical areas (Escalation, Helpdesk, Advisory, Resilience).';
    }
}
