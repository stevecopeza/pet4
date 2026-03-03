<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class HealthHistoryController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private $wpdb;

    /** Event types that signal "was Red" per entity type */
    private const RED_EVENTS = [
        'ticket' => ['sla_breached', 'escalation_triggered'],
        'project' => ['project.health_red'],
    ];

    /** Event types that signal "was Amber" */
    private const AMBER_EVENTS = [
        'ticket' => ['sla_warning'],
        'project' => ['project.health_amber'],
    ];

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/health-history', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getHealthHistory'],
                'permission_callback' => fn() => current_user_can('read'),
                'args' => [
                    'entity_type' => ['required' => true, 'type' => 'string'],
                    'entity_ids' => ['required' => true, 'type' => 'string'],
                ],
            ],
        ]);
    }

    public function getHealthHistory(WP_REST_Request $request): WP_REST_Response
    {
        $entityType = $request->get_param('entity_type');
        $entityIdsRaw = $request->get_param('entity_ids');

        if (!$entityType || !$entityIdsRaw) {
            return new WP_REST_Response(['error' => 'entity_type and entity_ids required'], 400);
        }

        $entityIds = array_filter(array_map('trim', explode(',', $entityIdsRaw)));
        if (empty($entityIds)) {
            return new WP_REST_Response([], 200);
        }

        $redEventTypes = self::RED_EVENTS[$entityType] ?? [];
        $amberEventTypes = self::AMBER_EVENTS[$entityType] ?? [];
        $allEventTypes = array_merge($redEventTypes, $amberEventTypes);

        if (empty($allEventTypes)) {
            // No history tracking for this entity type — return empty
            $result = [];
            foreach ($entityIds as $id) {
                $result[$id] = ['was_red' => false, 'was_amber' => false];
            }
            return new WP_REST_Response($result, 200);
        }

        $table = $this->wpdb->prefix . 'pet_feed_events';

        // Build placeholders
        $idPlaceholders = implode(',', array_fill(0, count($entityIds), '%s'));
        $typePlaceholders = implode(',', array_fill(0, count($allEventTypes), '%s'));

        $params = array_merge($entityIds, $allEventTypes);

        $sql = "SELECT source_entity_id, event_type 
                FROM $table 
                WHERE source_entity_id IN ($idPlaceholders) 
                AND event_type IN ($typePlaceholders)";

        $rows = $this->wpdb->get_results($this->wpdb->prepare($sql, ...$params));

        // Build result map
        $result = [];
        foreach ($entityIds as $id) {
            $result[$id] = ['was_red' => false, 'was_amber' => false];
        }

        foreach ($rows as $row) {
            $id = $row->source_entity_id;
            if (!isset($result[$id])) continue;

            if (in_array($row->event_type, $redEventTypes, true)) {
                $result[$id]['was_red'] = true;
            }
            if (in_array($row->event_type, $amberEventTypes, true)) {
                $result[$id]['was_amber'] = true;
            }
        }

        return new WP_REST_Response($result, 200);
    }
}
