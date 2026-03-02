<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class CreatePerformanceTables implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $charsetCollate = $this->wpdb->get_charset_collate();

        // 1. Performance Reviews
        $reviewsTable = $this->wpdb->prefix . 'pet_performance_reviews';
        $sqlReviews = "CREATE TABLE IF NOT EXISTS $reviewsTable (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            employee_id bigint(20) UNSIGNED NOT NULL,
            reviewer_id bigint(20) UNSIGNED NOT NULL,
            period_start date NOT NULL,
            period_end date NOT NULL,
            status varchar(50) DEFAULT 'draft' NOT NULL,
            content longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            KEY employee_id (employee_id),
            KEY reviewer_id (reviewer_id),
            KEY status (status)
        ) $charsetCollate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sqlReviews);
    }

    public function getDescription(): string
    {
        return 'Create performance review tables';
    }
}
