<?php
require_once dirname(__FILE__) . '/../../../wp-load.php';
global $wpdb;
$table = $wpdb->prefix . 'pet_domain_event_stream';
$results = $wpdb->get_results("DESCRIBE $table");
foreach ($results as $row) {
    echo $row->Field . ': ' . $row->Type . "\n";
}
