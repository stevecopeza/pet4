<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddEscalationSummaryAndResolutionNote implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $escalationsTable = $this->wpdb->prefix . 'pet_escalations';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$escalationsTable'") !== $escalationsTable) {
            return;
        }

        $columns = $this->wpdb->get_col("DESCRIBE $escalationsTable", 0);

        if (!in_array('summary', $columns, true)) {
            $this->wpdb->query("ALTER TABLE $escalationsTable ADD COLUMN summary TEXT NULL AFTER reason");
        }

        if (!in_array('resolution_note', $columns, true)) {
            $this->wpdb->query("ALTER TABLE $escalationsTable ADD COLUMN resolution_note TEXT NULL AFTER resolved_at");
        }
    }

    public function getDescription(): string
    {
        return 'Add summary and resolution_note columns to escalations.';
    }
}

