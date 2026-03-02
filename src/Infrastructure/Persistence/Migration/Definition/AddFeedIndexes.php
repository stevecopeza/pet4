<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddFeedIndexes implements Migration
{
    public function up(): void
    {
        global $wpdb;

        // pet_feed_events
        $eventsTable = $wpdb->prefix . 'pet_feed_events';
        $this->ensureIndex($eventsTable, 'audience_scope_ref', 'ADD INDEX audience_scope_ref (audience_scope, audience_reference_id)');
        $this->ensureIndex($eventsTable, 'pinned_created', 'ADD INDEX pinned_created (pinned_flag, created_at)');

        // pet_announcements
        $annTable = $wpdb->prefix . 'pet_announcements';
        $this->ensureIndex($annTable, 'audience_scope_ref', 'ADD INDEX audience_scope_ref (audience_scope, audience_reference_id)');
        $this->ensureIndex($annTable, 'pinned_created', 'ADD INDEX pinned_created (pinned_flag, created_at)');
    }

    public function getDescription(): string
    {
        return 'Add composite indexes for Feed tables to improve audience and ordering queries';
    }

    private function ensureIndex(string $table, string $indexName, string $alterClause): void
    {
        $existing = $this->getIndexes($table);
        if (!in_array($indexName, $existing, true)) {
            $sql = "ALTER TABLE $table $alterClause";
            $this->runQuery($sql);
        }
    }

    private function getIndexes(string $table): array
    {
        global $wpdb;
        $rows = $wpdb->get_results("SHOW INDEX FROM $table");
        $names = [];
        foreach ($rows as $row) {
            if (isset($row->Key_name)) {
                $names[] = $row->Key_name;
            }
        }
        return array_unique($names);
    }

    private function runQuery(string $sql): void
    {
        global $wpdb;
        $wpdb->query($sql);
    }
}
