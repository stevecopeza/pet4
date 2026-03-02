<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateSupportTables implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $charsetCollate = $this->wpdb->get_charset_collate();
        $table = $this->wpdb->prefix . 'pet_tickets';

        $sql = "CREATE TABLE $table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            customer_id mediumint(9) NOT NULL,
            subject varchar(255) NOT NULL,
            description text NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'new',
            priority varchar(50) NOT NULL DEFAULT 'medium',
            site_id mediumint(9) DEFAULT NULL,
            sla_id mediumint(9) DEFAULT NULL,
            opened_at datetime DEFAULT NULL,
            closed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            resolved_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY customer_id (customer_id),
            KEY site_id (site_id)
        ) $charsetCollate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function getDescription(): string
    {
        return 'Create support tickets table.';
    }
}
