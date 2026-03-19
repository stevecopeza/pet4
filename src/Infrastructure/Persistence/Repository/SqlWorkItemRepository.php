<?php

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Work\Entity\WorkItem;
use Pet\Domain\Work\Repository\WorkItemRepository;
use DateTimeImmutable;

class SqlWorkItemRepository implements WorkItemRepository
{
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function save(WorkItem $workItem): void
    {
        $table = $this->wpdb->prefix . 'pet_work_items';
        $data = [
            'id' => $workItem->getId(),
            'source_type' => $workItem->getSourceType(),
            'source_id' => $workItem->getSourceId(),
            'assigned_user_id' => $workItem->getAssignedUserId(),
            'department_id' => $workItem->getDepartmentId(),
            'assigned_team_id' => $workItem->getAssignedTeamId(),
            'assignment_mode' => $workItem->getAssignmentMode(),
            'queue_key' => $workItem->getQueueKey(),
            'routing_reason' => $workItem->getRoutingReason(),
            'sla_snapshot_id' => $workItem->getSlaSnapshotId(),
            'sla_time_remaining_minutes' => $workItem->getSlaTimeRemainingMinutes(),
            'priority_score' => $workItem->getPriorityScore(),
            'scheduled_start_utc' => $workItem->getScheduledStartUtc()?->format('Y-m-d H:i:s'),
            'scheduled_due_utc' => $workItem->getScheduledDueUtc()?->format('Y-m-d H:i:s'),
            'capacity_allocation_percent' => $workItem->getCapacityAllocationPercent(),
            'status' => $workItem->getStatus(),
            'escalation_level' => $workItem->getEscalationLevel(),
            'created_at' => $workItem->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $workItem->getUpdatedAt()->format('Y-m-d H:i:s'),
            'revenue' => $workItem->getRevenue(),
            'client_tier' => $workItem->getClientTier(),
            'manager_priority_override' => $workItem->getManagerPriorityOverride(),
            'required_role_id' => $workItem->getRequiredRoleId(),
        ];

        $formats = [
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%f', '%s', '%d', '%s', '%s', '%f', '%d', '%f', '%d'
        ];

        $exists = $this->findById($workItem->getId());

        if ($exists) {
            $this->wpdb->update($table, $data, ['id' => $workItem->getId()], $formats, ['%s']);
        } else {
            $this->wpdb->insert($table, $data, $formats);
        }
    }

    public function findById(string $id): ?WorkItem
    {
        $table = $this->wpdb->prefix . 'pet_work_items';
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $table WHERE id = %s", $id));

        return $row ? $this->mapRowToEntity($row) : null;
    }

    public function findBySource(string $sourceType, string $sourceId): ?WorkItem
    {
        $table = $this->wpdb->prefix . 'pet_work_items';
        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table WHERE source_type = %s AND source_id = %s",
            $sourceType,
            $sourceId
        ));

        return $row ? $this->mapRowToEntity($row) : null;
    }

    public function findByAssignedUser(string $userId): array
    {
        $table = $this->wpdb->prefix . 'pet_work_items';
        // Sort by Priority Score DESC
        $query = $this->wpdb->prepare(
            "SELECT * FROM $table WHERE assigned_user_id = %s ORDER BY priority_score DESC",
            $userId
        );
        $rows = $this->wpdb->get_results($query);

        return array_map([$this, 'mapRowToEntity'], $rows);
    }

    public function findByDepartmentUnassigned(string $departmentId): array
    {
        $table = $this->wpdb->prefix . 'pet_work_items';
        // Unassigned means assigned_user_id IS NULL or empty
        // Sort by Priority Score DESC
        $query = $this->wpdb->prepare(
            "SELECT * FROM $table WHERE department_id = %s AND (assigned_user_id IS NULL OR assigned_user_id = '') ORDER BY priority_score DESC",
            $departmentId
        );
        $rows = $this->wpdb->get_results($query);

        return array_map([$this, 'mapRowToEntity'], $rows);
    }

    public function findActive(): array
    {
        $table = $this->wpdb->prefix . 'pet_work_items';
        // Active or Waiting
        $query = "SELECT * FROM $table WHERE status IN ('active', 'waiting')";
        $rows = $this->wpdb->get_results($query);

        return array_map([$this, 'mapRowToEntity'], $rows);
    }

    public function findAll(): array
    {
        $table = $this->wpdb->prefix . 'pet_work_items';
        $rows = $this->wpdb->get_results("SELECT * FROM $table");

        return array_map([$this, 'mapRowToEntity'], $rows);
    }

    private function mapRowToEntity($row): WorkItem
    {
        // Handle backward compatibility for new columns if they are missing in old rows (though DB should have defaults)
        $revenue = isset($row->revenue) ? (float)$row->revenue : 0.0;
        $clientTier = isset($row->client_tier) ? (int)$row->client_tier : 1;
        $managerPriorityOverride = isset($row->manager_priority_override) ? (float)$row->manager_priority_override : 0.0;
        $requiredRoleId = isset($row->required_role_id) ? (int)$row->required_role_id : null;
        $assignedTeamId = isset($row->assigned_team_id) ? ($row->assigned_team_id !== null ? (string)$row->assigned_team_id : null) : null;
        $assignmentMode = isset($row->assignment_mode) ? ($row->assignment_mode !== null ? (string)$row->assignment_mode : null) : null;
        $queueKey = isset($row->queue_key) ? ($row->queue_key !== null ? (string)$row->queue_key : null) : null;
        $routingReason = isset($row->routing_reason) ? ($row->routing_reason !== null ? (string)$row->routing_reason : null) : null;

        return new WorkItem(
            $row->id,
            $row->source_type,
            $row->source_id,
            $row->assigned_user_id,
            $row->department_id,
            $assignedTeamId,
            $assignmentMode,
            $queueKey,
            $routingReason,
            $requiredRoleId,
            $row->sla_snapshot_id,
            $row->sla_time_remaining_minutes !== null ? (int)$row->sla_time_remaining_minutes : null,
            (float)$row->priority_score,
            $row->scheduled_start_utc ? new DateTimeImmutable($row->scheduled_start_utc) : null,
            $row->scheduled_due_utc ? new DateTimeImmutable($row->scheduled_due_utc) : null,
            (float)$row->capacity_allocation_percent,
            $row->status,
            (int)$row->escalation_level,
            new DateTimeImmutable($row->created_at),
            new DateTimeImmutable($row->updated_at),
            $revenue,
            $clientTier,
            $managerPriorityOverride
        );
    }
}
