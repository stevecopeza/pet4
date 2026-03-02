<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class UpdateQuoteBlocksAddPayloadAndCreatedAt implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table = $this->wpdb->prefix . 'pet_quote_blocks';

        $columns = $this->wpdb->get_results("SHOW COLUMNS FROM $table", ARRAY_A);
        $columnNames = array_column($columns, 'Field');

        if (!in_array('payload_json', $columnNames, true)) {
            $this->wpdb->query("ALTER TABLE $table ADD COLUMN payload_json longtext NULL");
        }

        if (!in_array('created_at', $columnNames, true)) {
            $this->wpdb->query("ALTER TABLE $table ADD COLUMN created_at datetime NULL DEFAULT CURRENT_TIMESTAMP");
        }

        $indexes = $this->wpdb->get_results("SHOW INDEX FROM $table", ARRAY_A);
        $indexNames = array_column($indexes, 'Key_name');

        if (!in_array('quote_section_order', $indexNames, true)) {
            $this->wpdb->query("ALTER TABLE $table ADD INDEX quote_section_order (quote_id, section_id, order_index)");
        }
    }

    public function getDescription(): string
    {
        return 'Update quote blocks table with payload_json, created_at and composite index.';
    }
}

