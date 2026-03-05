<?php

declare(strict_types=1);

namespace Pet\Application\Delivery\Service;

use Pet\Domain\Delivery\Entity\Project;

/**
 * Evaluates a project's health state and emits a transition event
 * to pet_feed_events when the state changes.
 *
 * Must only be called from state-changing command handlers (never on read).
 * Fire-and-forget: failures are logged but never propagate to the caller.
 */
final class ProjectHealthTransitionEmitter
{
    private $wpdb;

    private const EVENT_MAP = [
        'green' => 'project.health_green',
        'amber' => 'project.health_amber',
        'red'   => 'project.health_red',
    ];

    private const TITLE_MAP = [
        'green' => 'Project Healthy',
        'amber' => 'Project At Risk',
        'red'   => 'Project Critical',
    ];

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Evaluate the project's current health and emit a transition event if changed.
     *
     * @param Project $project The project entity (must have tasks loaded)
     * @param float   $hoursUsed Current hours used (from malleable data or time entries)
     */
    public function evaluate(Project $project, float $hoursUsed = 0.0): void
    {
        try {
            $this->doEvaluate($project, $hoursUsed);
        } catch (\Throwable $e) {
            // Fire-and-forget: log but never propagate
            if (function_exists('error_log')) {
                error_log('[PET] ProjectHealthTransitionEmitter failed for project '
                    . $project->id() . ': ' . $e->getMessage());
            }
        }
    }

    private function doEvaluate(Project $project, float $hoursUsed): void
    {
        $projectId = $project->id();
        if ($projectId === null) {
            return;
        }

        // Compute current health state (server-side equivalent of computeProjectHealth)
        $health = $this->computeHealth($project, $hoursUsed);
        $newState = $health['state'];

        // Only green/amber/red are tracked; grey/blue are not journey states
        if (!isset(self::EVENT_MAP[$newState])) {
            return;
        }

        // Fetch the most recent transition event for this project
        $lastState = $this->getLastState($projectId);

        // Idempotency guard: only write if state changed
        // null last_state means no events yet → implicit green
        $effectiveLastState = $lastState ?? 'green';
        if ($newState === $effectiveLastState) {
            return;
        }

        // Write the transition event
        $this->writeEvent($projectId, $newState, $health['summary'], $health['metadata']);
    }

    /**
     * Server-side equivalent of the frontend computeProjectHealth().
     * Returns state, summary, and structured metadata.
     */
    private function computeHealth(Project $project, float $hoursUsed): array
    {
        $state = $project->state()->toString();

        // Completed → blue (not tracked)
        if ($state === 'completed') {
            return ['state' => 'blue', 'summary' => '', 'metadata' => []];
        }

        // Planned with no dates/budget → grey (not tracked)
        if ($state === 'planned' && $project->endDate() === null && $project->soldHours() <= 0) {
            return ['state' => 'grey', 'summary' => '', 'metadata' => []];
        }

        $soldH = $project->soldHours();
        $tasks = $project->tasks();
        $taskCount = count($tasks);
        $completedCount = 0;
        foreach ($tasks as $task) {
            if ($task->isCompleted()) {
                $completedCount++;
            }
        }
        $progressPct = $taskCount > 0 ? (int) round(($completedCount / $taskCount) * 100) : 0;
        $burnPct = $soldH > 0 ? (int) round(($hoursUsed / $soldH) * 100) : 0;

        $reasons = [];
        $reasonCodes = [];
        $worstState = 'green';

        // Red triggers
        $now = new \DateTimeImmutable();
        if ($project->endDate() !== null && $project->endDate() < $now) {
            $reasons[] = 'OVERDUE';
            $reasonCodes[] = 'OVERDUE';
            $worstState = 'red';
        }
        if ($soldH > 0 && $hoursUsed > $soldH) {
            $reasons[] = 'OVER BUDGET';
            $reasonCodes[] = 'OVER_BUDGET';
            $worstState = 'red';
        }

        // Amber triggers (only if not already red)
        if ($worstState !== 'red') {
            if ($burnPct > 80 && $progressPct < 80) {
                $reasons[] = 'AT RISK';
                $reasonCodes[] = 'AT_RISK';
                $worstState = 'amber';
            }
        }

        $summary = !empty($reasons)
            ? implode(' · ', $reasons) . " — burn {$burnPct}%, progress {$progressPct}%"
            : '';

        if ($worstState === 'green' && count($reasonCodes) === 0) {
            $summary = 'On track';
        }

        return [
            'state' => $worstState,
            'summary' => $summary,
            'metadata' => [
                'reason_codes' => $reasonCodes,
                'burn_pct' => $burnPct,
                'progress_pct' => $progressPct,
                'hours_used' => round($hoursUsed, 2),
                'sold_hours' => round($soldH, 2),
            ],
        ];
    }

    /**
     * Fetch the last recorded health state for a project from pet_feed_events.
     */
    private function getLastState(int $projectId): ?string
    {
        $table = $this->wpdb->prefix . 'pet_feed_events';
        $eventTypes = implode("','", array_values(self::EVENT_MAP));

        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT event_type FROM $table
                 WHERE source_entity_id = %s
                 AND event_type IN ('$eventTypes')
                 ORDER BY created_at DESC
                 LIMIT 1",
                (string) $projectId
            )
        );

        if (!$row) {
            return null;
        }

        // Reverse lookup: event_type → state
        $stateMap = array_flip(self::EVENT_MAP);
        return $stateMap[$row->event_type] ?? null;
    }

    /**
     * Write a health transition event to pet_feed_events.
     */
    private function writeEvent(int $projectId, string $state, string $summary, array $metadata): void
    {
        $table = $this->wpdb->prefix . 'pet_feed_events';

        $uuid = function_exists('wp_generate_uuid4')
            ? wp_generate_uuid4()
            : sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );

        $this->wpdb->insert($table, [
            'id'                    => $uuid,
            'event_type'            => self::EVENT_MAP[$state],
            'source_engine'         => 'delivery',
            'source_entity_id'      => (string) $projectId,
            'classification'        => 'critical',
            'title'                 => self::TITLE_MAP[$state],
            'summary'               => $summary,
            'metadata_json'         => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'audience_scope'        => 'global',
            'audience_reference_id' => null,
            'pinned_flag'           => 0,
            'expires_at'            => null,
            'created_at'            => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
