<?php

declare(strict_types=1);

namespace Pet\Infrastructure\Persistence\Migration\Definition;

use Pet\Infrastructure\Persistence\Migration\Migration;

class AddContactAffiliations implements Migration
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function up(): void
    {
        $charset_collate = $this->wpdb->get_charset_collate();
        $table_name = $this->wpdb->prefix . 'pet_contact_affiliations';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            contact_id bigint(20) UNSIGNED NOT NULL,
            customer_id bigint(20) UNSIGNED NOT NULL,
            site_id bigint(20) UNSIGNED DEFAULT NULL,
            role varchar(100) DEFAULT NULL,
            is_primary tinyint(1) DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY contact_id (contact_id),
            KEY customer_id (customer_id),
            KEY site_id (site_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Migrate existing data from wp_pet_contacts if it exists
        $contacts_table = $this->wpdb->prefix . 'pet_contacts';
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '$contacts_table'");
        
        if ($table_exists) {
            $existing_contacts = $this->wpdb->get_results("SELECT id, customer_id, site_id, created_at FROM $contacts_table");

            foreach ($existing_contacts as $contact) {
                // Check if affiliation already exists to avoid duplicates if migration is rerun
                $exists = $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT id FROM $table_name WHERE contact_id = %d AND customer_id = %d AND (site_id = %d OR (site_id IS NULL AND %d IS NULL))",
                    $contact->id,
                    $contact->customer_id,
                    $contact->site_id,
                    $contact->site_id
                ));

                if (!$exists) {
                    $this->wpdb->insert($table_name, [
                        'contact_id' => $contact->id,
                        'customer_id' => $contact->customer_id,
                        'site_id' => $contact->site_id,
                        'is_primary' => 1,
                        'created_at' => $contact->created_at,
                    ]);
                }
            }
        }
    }

    public function getDescription(): string
    {
        return 'Adds contact affiliations table for many-to-many relationships between contacts, customers, and sites.';
    }
}
