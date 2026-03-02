<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class DropTasksTable implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $table = $this->wpdb->prefix . 'pet_tasks';
        $this->wpdb->query("DROP TABLE IF EXISTS $table");
    }

    public function getDescription(): string
    {
        return 'Drop legacy tasks table.';
    }
}

