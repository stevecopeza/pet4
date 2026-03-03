<?php
require_once dirname(__FILE__) . '/../../../wp-load.php';
if (!current_user_can('manage_options')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}
global $wpdb;
$table = $wpdb->prefix . 'pet_domain_event_stream';
$results = $wpdb->get_results("DESCRIBE $table");
foreach ($results as $row) {
    echo $row->Field . ': ' . $row->Type . "\n";
}
