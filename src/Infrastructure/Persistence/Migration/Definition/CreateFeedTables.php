<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateFeedTables implements Migration
{
    public function up(): void
    {
        global $wpdb;
        $charsetCollate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // pet_feed_events
        $tableEvents = $wpdb->prefix . 'pet_feed_events';
        if ($wpdb->get_var("SHOW TABLES LIKE '$tableEvents'") !== $tableEvents) {
            $sqlEvents = "CREATE TABLE $tableEvents (
                id char(36) NOT NULL,
                event_type varchar(50) NOT NULL,
                source_engine varchar(50) NOT NULL,
                source_entity_id varchar(36) NOT NULL,
                classification varchar(20) NOT NULL,
                title varchar(255) NOT NULL,
                summary text NOT NULL,
                metadata_json text NOT NULL,
                audience_scope varchar(20) NOT NULL,
                audience_reference_id varchar(36) DEFAULT NULL,
                pinned_flag tinyint(1) NOT NULL DEFAULT 0,
                expires_at datetime DEFAULT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY classification (classification),
                KEY audience_scope (audience_scope),
                KEY created_at (created_at),
                KEY source_entity_id (source_entity_id)
            ) $charsetCollate;";
            dbDelta($sqlEvents);
        }

        // pet_announcements
        $tableAnnouncements = $wpdb->prefix . 'pet_announcements';
        if ($wpdb->get_var("SHOW TABLES LIKE '$tableAnnouncements'") !== $tableAnnouncements) {
            $sqlAnnouncements = "CREATE TABLE $tableAnnouncements (
                id char(36) NOT NULL,
                title varchar(255) NOT NULL,
                body text NOT NULL,
                priority_level varchar(20) NOT NULL,
                pinned_flag tinyint(1) NOT NULL DEFAULT 0,
                acknowledgement_required tinyint(1) NOT NULL DEFAULT 0,
                gps_required tinyint(1) NOT NULL DEFAULT 0,
                acknowledgement_deadline datetime DEFAULT NULL,
                audience_scope varchar(20) NOT NULL,
                audience_reference_id varchar(36) DEFAULT NULL,
                author_user_id varchar(36) NOT NULL,
                expires_at datetime DEFAULT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY created_at (created_at),
                KEY priority_level (priority_level)
            ) $charsetCollate;";
            dbDelta($sqlAnnouncements);
        }

        // pet_announcement_acknowledgements
        $tableAcks = $wpdb->prefix . 'pet_announcement_acknowledgements';
        if ($wpdb->get_var("SHOW TABLES LIKE '$tableAcks'") !== $tableAcks) {
            $sqlAcks = "CREATE TABLE $tableAcks (
                id char(36) NOT NULL,
                announcement_id char(36) NOT NULL,
                user_id varchar(36) NOT NULL,
                acknowledged_at datetime NOT NULL,
                device_info varchar(255) DEFAULT NULL,
                gps_lat decimal(10,8) DEFAULT NULL,
                gps_lng decimal(11,8) DEFAULT NULL,
                PRIMARY KEY  (id),
                KEY announcement_id (announcement_id),
                KEY user_id (user_id)
            ) $charsetCollate;";
            dbDelta($sqlAcks);
        }

        // pet_feed_reactions
        $tableReactions = $wpdb->prefix . 'pet_feed_reactions';
        if ($wpdb->get_var("SHOW TABLES LIKE '$tableReactions'") !== $tableReactions) {
            $sqlReactions = "CREATE TABLE $tableReactions (
                id char(36) NOT NULL,
                feed_event_id char(36) NOT NULL,
                user_id varchar(36) NOT NULL,
                reaction_type varchar(20) NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY feed_event_id (feed_event_id),
                KEY user_id (user_id),
                UNIQUE KEY unique_reaction (feed_event_id, user_id)
            ) $charsetCollate;";
            dbDelta($sqlReactions);
        }
    }

    public function getDescription(): string
    {
        return 'Create tables for Command Surface (Feed, Announcements, Reactions)';
    }

    public function down(): void
    {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}pet_feed_reactions");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}pet_announcement_acknowledgements");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}pet_announcements");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}pet_feed_events");
    }
}
