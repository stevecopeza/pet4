<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreateOpportunitiesTable implements Migration
{
    public function up(): void
    {
        global $wpdb;
        $table   = $wpdb->prefix . 'pet_opportunities';
        $charset = $wpdb->get_charset_collate();

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            return; // Already exists
        }

        $wpdb->query("
            CREATE TABLE $table (
                id                  VARCHAR(36)      NOT NULL,
                customer_id         BIGINT UNSIGNED  NOT NULL,
                lead_id             BIGINT UNSIGNED  NULL,
                name                VARCHAR(255)     NOT NULL,
                stage               VARCHAR(50)      NOT NULL DEFAULT 'discovery',
                estimated_value     DECIMAL(12,2)    NOT NULL DEFAULT 0.00,
                currency            VARCHAR(3)       NULL DEFAULT 'ZAR',
                expected_close_date DATE             NULL,
                owner_id            BIGINT UNSIGNED  NOT NULL DEFAULT 0,
                qualification_json  LONGTEXT         NULL,
                notes               TEXT             NULL,
                created_at          DATETIME         NOT NULL,
                updated_at          DATETIME         NULL,
                closed_at           DATETIME         NULL,
                quote_id            BIGINT UNSIGNED  NULL,
                PRIMARY KEY (id),
                INDEX idx_opp_customer (customer_id),
                INDEX idx_opp_lead (lead_id),
                INDEX idx_opp_stage (stage),
                INDEX idx_opp_owner (owner_id)
            ) $charset
        ");

        // Add opportunity_id FK column to wp_pet_leads
        $leadsTable = $wpdb->prefix . 'pet_leads';
        $leadsCols  = $wpdb->get_col("DESCRIBE $leadsTable", 0);
        if ($leadsTable === $wpdb->get_var("SHOW TABLES LIKE '$leadsTable'") && !in_array('opportunity_id', $leadsCols, true)) {
            $wpdb->query("ALTER TABLE $leadsTable ADD COLUMN opportunity_id VARCHAR(36) NULL AFTER id");
        }

        // Add opportunity_id FK column to wp_pet_quotes
        $quotesTable = $wpdb->prefix . 'pet_quotes';
        $quotesCols  = $wpdb->get_col("DESCRIBE $quotesTable", 0);
        if ($quotesTable === $wpdb->get_var("SHOW TABLES LIKE '$quotesTable'") && !in_array('opportunity_id', $quotesCols, true)) {
            $wpdb->query("ALTER TABLE $quotesTable ADD COLUMN opportunity_id VARCHAR(36) NULL AFTER lead_id");
        }
    }

    public function down(): void {}

    public function getDescription(): string
    {
        return 'Create wp_pet_opportunities table and add opportunity_id to leads and quotes';
    }
}
