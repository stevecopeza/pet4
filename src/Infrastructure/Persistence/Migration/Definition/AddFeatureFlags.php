<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddFeatureFlags implements Migration
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
            'pet_sla_scheduler_enabled' => [
                'value' => 'false',
                'description' => 'Cron-based SLA evaluation',
            ],
            'pet_work_projection_enabled' => [
                'value' => 'false',
                'description' => 'Ticket -> WorkItem listener',
            ],
            'pet_queue_visibility_enabled' => [
                'value' => 'false',
                'description' => 'Queue endpoints & UI',
            ],
            'pet_priority_engine_enabled' => [
                'value' => 'false',
                'description' => 'PriorityScoringService activation',
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
        return 'Add feature flags for SLA and Work Orchestration.';
    }
}
