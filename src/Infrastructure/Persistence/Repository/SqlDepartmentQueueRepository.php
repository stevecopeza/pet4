<?php

namespace Pet\Infrastructure\Persistence\Repository;

use Pet\Domain\Work\Entity\DepartmentQueue;
use Pet\Domain\Work\Repository\DepartmentQueueRepository;
use DateTimeImmutable;

class SqlDepartmentQueueRepository implements DepartmentQueueRepository
{
    private $wpdb;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function save(DepartmentQueue $queueItem): void
    {
        $table = $this->wpdb->prefix . 'pet_department_queues';
        $data = [
            'id' => $queueItem->getId(),
            'department_id' => $queueItem->getDepartmentId(),
            'work_item_id' => $queueItem->getWorkItemId(),
            'assigned_user_id' => $queueItem->getAssignedUserId(),
            'entered_queue_at' => $queueItem->getEnteredQueueAt()->format('Y-m-d H:i:s'),
            'picked_up_at' => $queueItem->getPickedUpAt()?->format('Y-m-d H:i:s'),
        ];

        $formats = ['%s', '%s', '%s', '%s', '%s', '%s'];

        $exists = $this->findById($queueItem->getId());

        if ($exists) {
            $this->wpdb->update($table, $data, ['id' => $queueItem->getId()], $formats, ['%s']);
        } else {
            $this->wpdb->insert($table, $data, $formats);
        }
    }

    public function findById(string $id): ?DepartmentQueue
    {
        $table = $this->wpdb->prefix . 'pet_department_queues';
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $table WHERE id = %s", $id));

        return $row ? $this->mapRowToEntity($row) : null;
    }

    public function findByWorkItemId(string $workItemId): ?DepartmentQueue
    {
        $table = $this->wpdb->prefix . 'pet_department_queues';
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $table WHERE work_item_id = %s", $workItemId));

        return $row ? $this->mapRowToEntity($row) : null;
    }

    public function findUnassignedByDepartment(string $departmentId): array
    {
        $table = $this->wpdb->prefix . 'pet_department_queues';
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table WHERE department_id = %s AND assigned_user_id IS NULL",
            $departmentId
        ));

        return array_map([$this, 'mapRowToEntity'], $rows);
    }

    private function mapRowToEntity($row): DepartmentQueue
    {
        return new DepartmentQueue(
            $row->id,
            $row->department_id,
            $row->work_item_id,
            $row->assigned_user_id,
            new DateTimeImmutable($row->entered_queue_at),
            $row->picked_up_at ? new DateTimeImmutable($row->picked_up_at) : null
        );
    }
}
