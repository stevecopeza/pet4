<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddAdvisorySignalLifecycleFields implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table = $this->wpdb->prefix . 'pet_advisory_signals';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return;
        }

        $columns = $this->wpdb->get_col("DESCRIBE $table", 0);

        if (!in_array('status', $columns, true)) {
            $this->wpdb->query("ALTER TABLE $table ADD COLUMN status varchar(20) NOT NULL DEFAULT 'ACTIVE' AFTER severity");
        }
        if (!in_array('resolved_at', $columns, true)) {
            $this->wpdb->query("ALTER TABLE $table ADD COLUMN resolved_at datetime NULL AFTER status");
        }
        if (!in_array('generation_run_id', $columns, true)) {
            $this->wpdb->query("ALTER TABLE $table ADD COLUMN generation_run_id char(36) NULL AFTER resolved_at");
        }

        if (!in_array('title', $columns, true)) {
            $this->wpdb->query("ALTER TABLE $table ADD COLUMN title varchar(255) NULL AFTER generation_run_id");
        }
        if (!in_array('summary', $columns, true)) {
            $this->wpdb->query("ALTER TABLE $table ADD COLUMN summary text NULL AFTER title");
        }
        if (!in_array('metadata_json', $columns, true)) {
            $this->wpdb->query("ALTER TABLE $table ADD COLUMN metadata_json longtext NULL AFTER summary");
        }
        if (!in_array('source_entity_type', $columns, true)) {
            $this->wpdb->query("ALTER TABLE $table ADD COLUMN source_entity_type varchar(50) NULL AFTER metadata_json");
        }
        if (!in_array('source_entity_id', $columns, true)) {
            $this->wpdb->query("ALTER TABLE $table ADD COLUMN source_entity_id varchar(64) NULL AFTER source_entity_type");
        }
        if (!in_array('customer_id', $columns, true)) {
            $this->wpdb->query("ALTER TABLE $table ADD COLUMN customer_id bigint(20) unsigned NULL AFTER source_entity_id");
        }
        if (!in_array('site_id', $columns, true)) {
            $this->wpdb->query("ALTER TABLE $table ADD COLUMN site_id bigint(20) unsigned NULL AFTER customer_id");
        }
    }

    public function getDescription(): string
    {
        return 'Add lifecycle and metadata fields to advisory signals (additive history).';
    }
}

