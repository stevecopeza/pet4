<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class RepairDualAssignedWorkItems implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table = $this->wpdb->prefix . 'pet_work_items';
        $tableExists = $this->wpdb->get_var($this->wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if ($tableExists !== $table) {
            return;
        }
    }

    public function getDescription(): string
    {
        return 'Legacy no-op: dual-assigned work items are valid and should not be rewritten.';
    }
}

