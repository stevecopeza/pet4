<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateCostAdjustmentTable implements Migration
{
    private \wpdb $wpdb;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $charsetCollate = $this->wpdb->get_charset_collate();
        $table = $this->wpdb->prefix . 'pet_cost_adjustments';
        $sql = "CREATE TABLE $table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            quote_id bigint(20) UNSIGNED NOT NULL,
            description varchar(255) NOT NULL,
            amount decimal(10,2) NOT NULL,
            reason text NOT NULL,
            approved_by varchar(100) NOT NULL,
            applied_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            KEY quote_id (quote_id)
        ) $charsetCollate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function getDescription(): string
    {
        return 'Create cost adjustments table for quotes.';
    }
}
