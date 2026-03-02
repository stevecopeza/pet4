<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateQuoteSectionsTables implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $charsetCollate = $this->wpdb->get_charset_collate();

        $sectionsTable = $this->wpdb->prefix . 'pet_quote_sections';
        $sectionsSql = "CREATE TABLE $sectionsTable (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            quote_id mediumint(9) NOT NULL,
            name varchar(255) NOT NULL DEFAULT 'New Section',
            order_index int NOT NULL,
            show_total_value tinyint(1) NOT NULL DEFAULT 1,
            show_item_count tinyint(1) NOT NULL DEFAULT 0,
            show_total_hours tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY quote_id (quote_id),
            KEY order_index (order_index)
        ) $charsetCollate;";

        $blocksTable = $this->wpdb->prefix . 'pet_quote_blocks';
        $blocksSql = "CREATE TABLE $blocksTable (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            quote_id mediumint(9) NOT NULL,
            component_id mediumint(9) DEFAULT NULL,
            section_id mediumint(9) DEFAULT NULL,
            type varchar(50) NOT NULL,
            order_index int NOT NULL,
            priced tinyint(1) NOT NULL DEFAULT 1,
            PRIMARY KEY  (id),
            KEY quote_id (quote_id),
            KEY section_id (section_id),
            KEY component_id (component_id),
            KEY order_index (order_index)
        ) $charsetCollate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sectionsSql);
        dbDelta($blocksSql);
    }

    public function getDescription(): string
    {
        return 'Create quote sections and blocks ordering tables.';
    }
}

