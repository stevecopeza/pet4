<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddSubjectKeyAndIndexes implements Migration
{
    public function up(): void
    {
        global $wpdb;
        $conversations = $wpdb->prefix . 'pet_conversations';
        
        // 1. Ensure subject_key exists
        $row = $wpdb->get_results("SHOW COLUMNS FROM $conversations LIKE 'subject_key'");
        if (empty($row)) {
            // Add nullable subject_key if missing
            $wpdb->query("ALTER TABLE $conversations ADD COLUMN subject_key varchar(50) DEFAULT NULL AFTER subject");
            // Backfill existing with NULL (implicit by default)
        } else {
            // If exists, we don't modify nullability to avoid breaking existing constraints
            // unless we specifically want to relax it. 
            // Given the requirement "Add nullable column", if it's missing we add it as nullable.
        }

        // 2. Add composite index for lookup
        // Index: (context_type, context_id, context_version, subject_key)
        // Check if index exists first to avoid errors
        $indices = $wpdb->get_results("SHOW INDEX FROM $conversations WHERE Key_name = 'context_lookup_idx'");
        if (empty($indices)) {
            // Note: subject_key and context_version might be null, but standard BTREE indexes handle NULLs in MySQL.
            // However, we need to be careful with key length.
            // context_type(50) + context_id(36) + context_version(50) + subject_key(50) = 186 chars. 
            // utf8mb4 max is 191 chars for 767 byte limit, so this is safe.
            $wpdb->query("ALTER TABLE $conversations ADD INDEX context_lookup_idx (context_type, context_id, context_version, subject_key)");
        }
    }

    public function getDescription(): string
    {
        return 'Add subject_key column if missing and composite index for context lookups';
    }
}
