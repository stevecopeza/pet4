<?php

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddParticipantTypesToConversationParticipants implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $participants = $this->wpdb->prefix . 'pet_conversation_participants';
        
        // Check if user_id is already nullable (idempotency check)
        $columns = $this->wpdb->get_results("SHOW COLUMNS FROM $participants WHERE Field = 'user_id'");
        if (!empty($columns) && $columns[0]->Null === 'YES') {
            return; // Already migrated
        }

        // Add contact_id if not exists
        if (!$this->columnExists($participants, 'contact_id')) {
            $this->wpdb->query("ALTER TABLE $participants ADD COLUMN contact_id bigint(20) unsigned DEFAULT NULL");
            $this->wpdb->query("ALTER TABLE $participants ADD INDEX contact_idx (contact_id)");
        }

        // Add team_id if not exists
        if (!$this->columnExists($participants, 'team_id')) {
            $this->wpdb->query("ALTER TABLE $participants ADD COLUMN team_id bigint(20) unsigned DEFAULT NULL");
            $this->wpdb->query("ALTER TABLE $participants ADD INDEX team_idx (team_id)");
        }
        
        // Modify user_id to be nullable
        $this->wpdb->query("ALTER TABLE $participants MODIFY user_id bigint(20) unsigned DEFAULT NULL");

        // Drop old PK and Add Unique Indexes
        // We wrap this in a try-catch equivalent or check keys
        // Since WPDB suppresses errors by default unless show_errors is on, we just run it.
        // But we need to be careful not to break if PK is already dropped.
        
        // Check if PK exists
        $pk = $this->wpdb->get_results("SHOW KEYS FROM $participants WHERE Key_name = 'PRIMARY'");
        if (!empty($pk)) {
            $this->wpdb->query("ALTER TABLE $participants DROP PRIMARY KEY");
        }
        
        // Add unique indexes if they don't exist
        if (!$this->indexExists($participants, 'unique_user_participant')) {
            $this->wpdb->query("CREATE UNIQUE INDEX unique_user_participant ON $participants (conversation_id, user_id)");
        }
        if (!$this->indexExists($participants, 'unique_contact_participant')) {
            $this->wpdb->query("CREATE UNIQUE INDEX unique_contact_participant ON $participants (conversation_id, contact_id)");
        }
        if (!$this->indexExists($participants, 'unique_team_participant')) {
            $this->wpdb->query("CREATE UNIQUE INDEX unique_team_participant ON $participants (conversation_id, team_id)");
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $result = $this->wpdb->get_results("SHOW COLUMNS FROM $table WHERE Field = '$column'");
        return !empty($result);
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = $this->wpdb->get_results("SHOW KEYS FROM $table WHERE Key_name = '$indexName'");
        return !empty($result);
    }

    public function getDescription(): string
    {
        return 'Adds contact_id and team_id to conversation participants and updates constraints.';
    }
}
