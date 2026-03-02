<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddSystemSettings implements Migration
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
        
        $settings = [
            'pet_currency' => [
                'value' => 'USD',
                'description' => 'System base currency (ISO 4217)',
                'type' => 'string',
            ],
            'pet_currency_symbol' => [
                'value' => '$',
                'description' => 'Currency symbol for display',
                'type' => 'string',
            ],
            'pet_date_format' => [
                'value' => 'Y-m-d',
                'description' => 'Default date format (PHP format)',
                'type' => 'string',
            ],
            'pet_time_format' => [
                'value' => 'H:i',
                'description' => 'Default time format (PHP format)',
                'type' => 'string',
            ],
            'pet_company_name' => [
                'value' => 'My Company',
                'description' => 'Company name for documents',
                'type' => 'string',
            ],
        ];

        foreach ($settings as $key => $data) {
            $exists = $this->wpdb->get_var($this->wpdb->prepare("SELECT setting_key FROM $table WHERE setting_key = %s", $key));
            
            if (!$exists) {
                $this->wpdb->insert(
                    $table,
                    [
                        'setting_key' => $key,
                        'setting_value' => $data['value'],
                        'setting_type' => $data['type'],
                        'description' => $data['description'],
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }

    public function getDescription(): string
    {
        return 'Add core system settings (currency, formats).';
    }
}
