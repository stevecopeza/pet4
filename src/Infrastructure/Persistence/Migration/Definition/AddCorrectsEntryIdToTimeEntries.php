<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

/**
 * B2: Add corrects_entry_id to wp_pet_time_entries for compensating/correction entries.
 * A correction entry references the original entry it corrects.
 */
class AddCorrectsEntryIdToTimeEntries implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table = $this->wpdb->prefix . 'pet_time_entries';

        $row = $this->wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'corrects_entry_id'");
        if (empty($row)) {
            $this->wpdb->query("ALTER TABLE $table ADD corrects_entry_id BIGINT UNSIGNED NULL");
            $this->wpdb->query("ALTER TABLE $table ADD INDEX idx_corrects_entry_id (corrects_entry_id)");
        }
    }

    public function getDescription(): string
    {
        return 'Add corrects_entry_id column to time_entries for compensating/correction entries.';
    }
}
