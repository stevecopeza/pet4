<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateDeliveryTables implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $charsetCollate = $this->wpdb->get_charset_collate();

        // Projects Table
        $projectsTable = $this->wpdb->prefix . 'pet_projects';
        $sqlProjects = "CREATE TABLE IF NOT EXISTS $projectsTable (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) UNSIGNED NOT NULL,
            source_quote_id bigint(20) UNSIGNED NULL,
            name varchar(255) NOT NULL,
            state varchar(20) NOT NULL,
            sold_hours decimal(10, 2) NOT NULL DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT NULL,
            archived_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY source_quote_id (source_quote_id),
            KEY state (state)
        ) $charsetCollate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sqlProjects);
    }

    public function getDescription(): string
    {
        return 'Create delivery tables: projects.';
    }
}
