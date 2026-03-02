<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateCommercialTables implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $charsetCollate = $this->wpdb->get_charset_collate();
        
        // Leads Table
        $leadsTable = $this->wpdb->prefix . 'pet_leads';
        $leadsSql = "CREATE TABLE $leadsTable (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            customer_id mediumint(9) NOT NULL,
            subject varchar(255) NOT NULL,
            description text NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'new',
            source varchar(50) DEFAULT NULL,
            estimated_value decimal(10,2) DEFAULT NULL,
            malleable_schema_version mediumint(9) DEFAULT NULL,
            malleable_data json DEFAULT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            updated_at datetime DEFAULT NULL,
            converted_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY customer_id (customer_id),
            KEY status (status)
        ) $charsetCollate;";

        // Quotes Table
        $quotesTable = $this->wpdb->prefix . 'pet_quotes';
        $quotesSql = "CREATE TABLE $quotesTable (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            customer_id mediumint(9) NOT NULL,
            state varchar(20) NOT NULL,
            version mediumint(9) NOT NULL DEFAULT 1,
            total_value decimal(10, 2) NOT NULL DEFAULT 0.00,
            currency varchar(3) NOT NULL DEFAULT 'USD',
            accepted_at datetime DEFAULT NULL,
            malleable_data longtext DEFAULT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            updated_at datetime DEFAULT NULL,
            archived_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY customer_id (customer_id)
        ) $charsetCollate;";

        // Quote Lines Table
        $quoteLinesTable = $this->wpdb->prefix . 'pet_quote_lines';
        $quoteLinesSql = "CREATE TABLE $quoteLinesTable (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            quote_id mediumint(9) NOT NULL,
            description text NOT NULL,
            quantity decimal(10,2) NOT NULL,
            unit_price decimal(10,2) NOT NULL,
            line_group_type varchar(50) NOT NULL,
            PRIMARY KEY  (id),
            KEY quote_id (quote_id)
        ) $charsetCollate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($leadsSql);
        dbDelta($quotesSql);
        dbDelta($quoteLinesSql);
    }

    public function getDescription(): string
    {
        return 'Create commercial tables (leads, quotes, quote lines).';
    }
}
