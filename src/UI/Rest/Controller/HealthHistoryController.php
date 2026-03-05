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

    /** Event types that signal "was Green" (recovery) */
    private const GREEN_EVENTS = [
        'project' => ['project.health_green'],
    ];

    /** All project health transition event types */
    private const PROJECT_HEALTH_EVENTS = [
        'project.health_green',
        'project.health_amber',
        'project.health_red',
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

        register_rest_route(self::NAMESPACE, '/health-history/journey', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getJourney'],
                'permission_callback' => fn() => current_user_can('read'),
                'args' => [
                    'project_ids' => ['required' => true, 'type' => 'string'],
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

    /**
     * GET /health-history/journey?project_ids=1,2,3
     * Returns precomputed journey segments and KPI totals per project.
     */
    public function getJourney(WP_REST_Request $request): WP_REST_Response
    {
        $projectIdsRaw = $request->get_param('project_ids');
        if (!$projectIdsRaw) {
            return new WP_REST_Response(['error' => 'project_ids required'], 400);
        }

        $projectIds = array_filter(array_map('intval', explode(',', $projectIdsRaw)));
        if (empty($projectIds)) {
            return new WP_REST_Response([], 200);
        }

        // Fetch project metadata (start_date, end_date, state)
        $projectsTable = $this->wpdb->prefix . 'pet_projects';
        $idPlaceholders = implode(',', array_fill(0, count($projectIds), '%d'));
        $projectRows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, start_date, end_date, state, created_at FROM $projectsTable WHERE id IN ($idPlaceholders)",
                ...$projectIds
            )
        );

        $projectMeta = [];
        foreach ($projectRows as $row) {
            $projectMeta[(int)$row->id] = [
                'start_date' => $row->start_date,
                'end_date' => $row->end_date,
                'state' => $row->state,
                'created_at' => $row->created_at,
            ];
        }

        // Fetch all health transition events for these projects
        $feedTable = $this->wpdb->prefix . 'pet_feed_events';
        $strIds = array_map('strval', $projectIds);
        $idPlaceholdersStr = implode(',', array_fill(0, count($strIds), '%s'));
        $typePlaceholders = implode(',', array_fill(0, count(self::PROJECT_HEALTH_EVENTS), '%s'));
        $params = array_merge($strIds, self::PROJECT_HEALTH_EVENTS);

        $events = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT source_entity_id, event_type, summary, metadata_json, created_at
                 FROM $feedTable
                 WHERE source_entity_id IN ($idPlaceholdersStr)
                 AND event_type IN ($typePlaceholders)
                 ORDER BY created_at ASC",
                ...$params
            )
        );

        // Group events by project
        $eventsByProject = [];
        foreach ($events as $event) {
            $pid = (int)$event->source_entity_id;
            $eventsByProject[$pid][] = $event;
        }

        // Build journey response for each requested project
        $now = (new \DateTimeImmutable())->format('Y-m-d\TH:i:s\Z');
        $result = [];

        foreach ($projectIds as $pid) {
            if (!isset($projectMeta[$pid])) {
                continue; // project doesn't exist — omit
            }

            $meta = $projectMeta[$pid];
            $projEvents = $eventsByProject[$pid] ?? [];

            $segments = $this->buildSegments($projEvents, $meta, $now);
            $totals = $this->computeTotals($segments);

            $result[$pid] = [
                'segments' => $segments,
                'totals' => $totals,
            ];
        }

        return new WP_REST_Response($result, 200);
    }

    /**
     * Build journey segments from raw transition events.
     */
    private function buildSegments(array $events, array $projectMeta, string $now): array
    {
        // Determine timeline start
        $startAt = $projectMeta['start_date'] ?? $projectMeta['created_at'] ?? null;
        if ($startAt && !empty($events)) {
            // Use whichever is earlier: project start or first event
            $firstEventTime = $events[0]->created_at;
            if ($firstEventTime < $startAt) {
                $startAt = $firstEventTime;
            }
        } elseif (!$startAt && !empty($events)) {
            $startAt = $events[0]->created_at;
        }

        if (!$startAt) {
            // No start date, no events — return empty
            return [];
        }

        // Determine timeline end
        $isCompleted = ($projectMeta['state'] === 'completed');
        $endAt = $isCompleted
            ? ($projectMeta['end_date'] ?? $now)
            : $now;

        // Map event_type to state
        $eventStateMap = [
            'project.health_green' => 'green',
            'project.health_amber' => 'amber',
            'project.health_red'   => 'red',
        ];

        // If no events → single green segment
        if (empty($events)) {
            $durationDays = $this->daysBetween($startAt, $endAt);
            return [[
                'state' => 'green',
                'start_at' => $this->toIso($startAt),
                'end_at' => $isCompleted ? $this->toIso($endAt) : null,
                'duration_days' => round($durationDays, 1),
                'reason' => null,
            ]];
        }

        $segments = [];
        $currentStart = $startAt;
        $currentState = 'green'; // implicit green from start
        $currentReason = null;

        foreach ($events as $event) {
            $eventState = $eventStateMap[$event->event_type] ?? 'green';
            $eventTime = $event->created_at;

            // Close the current segment at this event's timestamp
            if ($eventTime > $currentStart) {
                $segments[] = [
                    'state' => $currentState,
                    'start_at' => $this->toIso($currentStart),
                    'end_at' => $this->toIso($eventTime),
                    'duration_days' => round($this->daysBetween($currentStart, $eventTime), 1),
                    'reason' => $currentReason,
                ];
            }

            // Start new segment
            $currentStart = $eventTime;
            $currentState = $eventState;
            $currentReason = $event->summary ?: null;
        }

        // Final segment extends to end
        $durationDays = $this->daysBetween($currentStart, $endAt);
        $segments[] = [
            'state' => $currentState,
            'start_at' => $this->toIso($currentStart),
            'end_at' => $isCompleted ? $this->toIso($endAt) : null,
            'duration_days' => round($durationDays, 1),
            'reason' => $currentReason,
        ];

        return $segments;
    }

    /**
     * Compute KPI totals from segments.
     */
    private function computeTotals(array $segments): array
    {
        $days = ['green' => 0.0, 'amber' => 0.0, 'red' => 0.0];

        foreach ($segments as $seg) {
            $state = $seg['state'];
            if (isset($days[$state])) {
                $days[$state] += $seg['duration_days'];
            }
        }

        $totalDays = $days['green'] + $days['amber'] + $days['red'];

        return [
            'days_green' => round($days['green'], 1),
            'days_amber' => round($days['amber'], 1),
            'days_red' => round($days['red'], 1),
            'pct_green' => $totalDays > 0 ? (int) round(($days['green'] / $totalDays) * 100) : 100,
            'pct_amber' => $totalDays > 0 ? (int) round(($days['amber'] / $totalDays) * 100) : 0,
            'pct_red' => $totalDays > 0 ? (int) round(($days['red'] / $totalDays) * 100) : 0,
        ];
    }

    private function daysBetween(string $start, string $end): float
    {
        $s = strtotime($start);
        $e = strtotime($end);
        if ($s === false || $e === false || $e <= $s) {
            return 0.0;
        }
        return ($e - $s) / 86400.0;
    }

    private function toIso(string $datetime): string
    {
        $ts = strtotime($datetime);
        return $ts !== false ? gmdate('Y-m-d\TH:i:s\Z', $ts) : $datetime;
    }
}
