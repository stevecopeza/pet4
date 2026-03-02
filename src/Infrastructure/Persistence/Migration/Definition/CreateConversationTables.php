<?php

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateConversationTables implements Migration
{
    public function up(): void
    {
        global $wpdb;
        $charsetCollate = $wpdb->get_charset_collate();

        $conversations = $wpdb->prefix . 'pet_conversations';
        $participants = $wpdb->prefix . 'pet_conversation_participants';
        $events = $wpdb->prefix . 'pet_conversation_events';
        $readState = $wpdb->prefix . 'pet_conversation_read_state';
        $decisions = $wpdb->prefix . 'pet_decisions';
        $decisionEvents = $wpdb->prefix . 'pet_decision_events';

        $sql = "
            CREATE TABLE $conversations (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                context_type varchar(50) NOT NULL,
                context_id char(36) NOT NULL,
                subject text NOT NULL,
                subject_key varchar(50) NOT NULL,
                state varchar(20) NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uuid (uuid),
                KEY context_idx (context_type, context_id),
                KEY subject_key_idx (subject_key)
            ) $charsetCollate;

            CREATE TABLE $participants (
                conversation_id bigint(20) unsigned NOT NULL,
                user_id bigint(20) unsigned NOT NULL,
                added_at datetime NOT NULL,
                PRIMARY KEY (conversation_id, user_id),
                KEY user_idx (user_id)
            ) $charsetCollate;

            CREATE TABLE $events (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                conversation_id bigint(20) unsigned NOT NULL,
                event_type varchar(100) NOT NULL,
                payload json NOT NULL,
                occurred_at datetime NOT NULL,
                actor_id bigint(20) unsigned NOT NULL,
                PRIMARY KEY (id),
                KEY conversation_time_idx (conversation_id, occurred_at)
            ) $charsetCollate;

            CREATE TABLE $readState (
                conversation_id bigint(20) unsigned NOT NULL,
                user_id bigint(20) unsigned NOT NULL,
                last_seen_event_id bigint(20) unsigned NOT NULL,
                PRIMARY KEY (conversation_id, user_id)
            ) $charsetCollate;

            CREATE TABLE $decisions (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                uuid char(36) NOT NULL,
                conversation_id bigint(20) unsigned NOT NULL,
                decision_type varchar(50) NOT NULL,
                state varchar(20) NOT NULL,
                payload json NOT NULL,
                policy_snapshot json NOT NULL,
                requested_at datetime NOT NULL,
                requester_id bigint(20) unsigned NOT NULL,
                finalized_at datetime DEFAULT NULL,
                finalizer_id bigint(20) unsigned DEFAULT NULL,
                outcome varchar(20) DEFAULT NULL,
                outcome_comment text DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uuid (uuid),
                KEY conversation_idx (conversation_id),
                KEY state_idx (state)
            ) $charsetCollate;

            CREATE TABLE $decisionEvents (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                decision_id bigint(20) unsigned NOT NULL,
                event_type varchar(100) NOT NULL,
                payload json NOT NULL,
                occurred_at datetime NOT NULL,
                actor_id bigint(20) unsigned NOT NULL,
                PRIMARY KEY (id),
                KEY decision_time_idx (decision_id, occurred_at)
            ) $charsetCollate;
        ";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function getDescription(): string
    {
        return 'Create conversation and decision tables';
    }
}
