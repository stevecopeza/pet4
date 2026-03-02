<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddVersionToConversations implements Migration
{
    public function up(): void
    {
        global $wpdb;
        $conversations = $wpdb->prefix . 'pet_conversations';
        
        // Add context_version column if it doesn't exist
        $row = $wpdb->get_results("SHOW COLUMNS FROM $conversations LIKE 'context_version'");
        if (empty($row)) {
            $wpdb->query("ALTER TABLE $conversations ADD COLUMN context_version varchar(50) DEFAULT NULL AFTER context_id");
            $wpdb->query("ALTER TABLE $conversations ADD INDEX context_version_idx (context_type, context_id, context_version)");
            
            // Backfill existing quote conversations to version 1 to maintain access
            $wpdb->query("UPDATE $conversations SET context_version = '1' WHERE context_type = 'quote' AND context_version IS NULL");
        }
    }

    public function getDescription(): string
    {
        return 'Add context_version column to conversations table for version isolation';
    }
}
