<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateTeamTables implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $charsetCollate = $this->wpdb->get_charset_collate();

        // Teams Table
        $teamsTable = $this->wpdb->prefix . 'pet_teams';
        $sqlTeams = "CREATE TABLE IF NOT EXISTS $teamsTable (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            parent_team_id bigint(20) UNSIGNED DEFAULT NULL,
            manager_id bigint(20) UNSIGNED DEFAULT NULL,
            escalation_manager_id bigint(20) UNSIGNED DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            visual_type varchar(20) DEFAULT NULL,
            visual_ref varchar(255) DEFAULT NULL,
            visual_version int(11) DEFAULT 1,
            visual_updated_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            archived_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY parent_team_id (parent_team_id),
            KEY manager_id (manager_id),
            KEY escalation_manager_id (escalation_manager_id)
        ) $charsetCollate;";

        // Team Members Table
        $membersTable = $this->wpdb->prefix . 'pet_team_members';
        $sqlMembers = "CREATE TABLE IF NOT EXISTS $membersTable (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            team_id bigint(20) UNSIGNED NOT NULL,
            employee_id bigint(20) UNSIGNED NOT NULL,
            role varchar(20) NOT NULL DEFAULT 'member',
            assigned_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            removed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY team_id (team_id),
            KEY employee_id (employee_id),
            KEY team_member_unique (team_id, employee_id, removed_at)
        ) $charsetCollate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sqlTeams);
        dbDelta($sqlMembers);
    }

    public function getDescription(): string
    {
        return 'Create tables for teams and team membership.';
    }
}
