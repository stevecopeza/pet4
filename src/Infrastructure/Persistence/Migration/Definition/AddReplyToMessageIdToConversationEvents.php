<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddReplyToMessageIdToConversationEvents implements Migration
{
    public function up(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pet_conversation_events';

        // Check if column exists
        $row = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'reply_to_message_id'");
        if (empty($row)) {
            $sql = "ALTER TABLE $table ADD COLUMN reply_to_message_id bigint(20) unsigned DEFAULT NULL AFTER conversation_id";
            $wpdb->query($sql);
            
            // Add index for performance (finding replies to a message)
            $indexSql = "ALTER TABLE $table ADD INDEX reply_idx (reply_to_message_id)";
            $wpdb->query($indexSql);
        }
    }

    public function getDescription(): string
    {
        return 'Add reply_to_message_id to conversation events table.';
    }
}
