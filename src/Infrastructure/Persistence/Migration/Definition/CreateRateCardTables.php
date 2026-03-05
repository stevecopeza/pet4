<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateRateCardTables implements Migration
{
    public function up(): void
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'pet_rate_cards';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            $sql = "CREATE TABLE $table (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                role_id bigint(20) unsigned NOT NULL,
                service_type_id bigint(20) unsigned NOT NULL,
                sell_rate decimal(12,2) NOT NULL,
                contract_id bigint(20) unsigned DEFAULT NULL,
                valid_from date DEFAULT NULL,
                valid_to date DEFAULT NULL,
                status varchar(20) NOT NULL DEFAULT 'active',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY idx_resolution (role_id, service_type_id, contract_id, valid_from),
                KEY idx_role (role_id),
                KEY idx_service_type (service_type_id),
                KEY idx_contract (contract_id)
            ) $charsetCollate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    public function down(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pet_rate_cards';
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }

    public function getDescription(): string
    {
        return 'Create rate cards table with nullable date ranges (NULL = open-ended)';
    }
}
