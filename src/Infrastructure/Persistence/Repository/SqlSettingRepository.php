<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Configuration\Entity\Setting;
use Pet\Domain\Configuration\Repository\SettingRepository;

class SqlSettingRepository implements SettingRepository
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function save(Setting $setting): void
    {
        $table = $this->wpdb->prefix . 'pet_settings';
        
        $data = [
            'setting_value' => $setting->value(),
            'setting_type' => $setting->type(),
            'description' => $setting->description(),
            'updated_at' => date('Y-m-d H:i:s'), // Always update timestamp on save
        ];

        $formats = ['%s', '%s', '%s', '%s'];
        
        // Use replace to handle insert or update based on primary key (setting_key)
        $this->wpdb->replace(
            $table,
            array_merge(['setting_key' => $setting->key()], $data),
            array_merge(['%s'], $formats)
        );
    }

    public function findByKey(string $key): ?Setting
    {
        $table = $this->wpdb->prefix . 'pet_settings';
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $table WHERE setting_key = %s", $key));

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findAll(): array
    {
        $table = $this->wpdb->prefix . 'pet_settings';
        $rows = $this->wpdb->get_results("SELECT * FROM $table ORDER BY setting_key ASC");

        return array_map([$this, 'hydrate'], $rows);
    }

    private function hydrate($row): Setting
    {
        return new Setting(
            $row->setting_key,
            $row->setting_value,
            $row->setting_type,
            $row->description,
            $row->updated_at ? new \DateTimeImmutable($row->updated_at) : null
        );
    }
}
